<?php

namespace App\Filament\Resources\ChatResource\Pages;

use App\Filament\Resources\ChatResource;
use App\Mail\OpenAIException;
use App\Models\AIModel;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Mail;

class CreateChat extends CreateRecord
{
    protected static string $resource = ChatResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        ray('Creando chat');

        try {
            $client = app('openai');

            $name = config('app.env') . '-' . $this->data['code'];

            // Create an Vector Store
            $response_vectorstore = $client->vectorStores()->create([
                'name' => $name,
            ]);

            ray($response_vectorstore);

            // Create an assistant
            $response_assistant = $client->assistants()->create([
                'name' => $name,
                'tools' => [
                    [
                        'type' => 'file_search',
                    ],
                ],
                'tool_resources' => [
                    'file_search' => [
                        'vector_store_ids' => [
                            $response_vectorstore->id
                        ]
                    ],
                ],

                'model' => AIModel::find($data['model_id'])->name,
            ]);

            ray($response_assistant);

            $data['user_id']      = auth()->id();
            $data['assistant_id'] = $response_assistant->id;
            $data['vectorstore_id'] = $response_vectorstore->id;

        } catch (\Exception $e) {
            ray($e->getMessage());

            Mail::to( config('mail.to.address') )
                ->queue(new OpenAIException($e->getMessage()));

            Notification::make()
                ->title('Se produjo una Excepción')
                ->danger()
                ->send();

            $this->halt();
        }

        ray('Chat: Creado');

        return $data;

    }



}
