<?php

namespace App\Filament\Resources\ChatResource\Pages;

use App\Filament\Resources\ChatResource;
use App\Mail\OpenAIException;
use App\Models\AIModel;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class CreateChat extends CreateRecord
{
    protected static string $resource = ChatResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        ray('Creando chat');

        try {

            $model = AIModel::findOrFail($data['model_id']);
            $provider = $model->provider;
            $clientname = $model->name;

           
            $data['user_id'] = auth()->id();
            $data['provider'] = $provider;
            $data['providername'] = $clientname;

            if ($provider === 'openai') {

                $client = app('openai');

                $code = str_replace('_', '-', $this->data['code']);

                // Create an Vector Store
                $response_vectorstore = $client->vectorStores()->create([
                    'name' => 'eim-' . $this->data['code'],
                ]);

                $vectorstore_id = $response_vectorstore->id;
                //ray($response_vectorstore);
                // Create an assistant
                $response_assistant = $client->assistants()->create([
                    'name' => 'eim-' . $this->data['code'],
                    'tools' => [
                        [
                            'type' => 'file_search',
                        ],
                    ],
                    'tool_resources' => [
                        'file_search' => [
                            'vector_store_ids' => [
                                $vectorstore_id,
                            ]
                        ],
                    ],

                    'model' => $clientname,
                ]);

                //ray($response_assistant);

                //$data['user_id']      = auth()->id();
                $data['assistant_id'] = $response_assistant->id;
                $data['vectorstore_id'] = $vectorstore_id;
            } elseif ($provider === 'anthropic') {

                $response = Http::withHeaders([
                    'x-api-key' => config('anthropic.key'),
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])->post('https://api.anthropic.com/v1/messages', [
                    'model' => $clientname,
                    'max_tokens' => 1000,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => 'Inicializar conversación para ' . $this->data['code'],
                        ],
                    ],
                ]);

                if ($response->successful()) {
                    $data['assistant_id'] = null;
                } else {
                    throw new \Exception('Error en la llamada a la API de Anthropic: ' . $response->body());
                }

                // Anthropic no usa vectorstore
                $data['vectorstore_id'] = null;
            }

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
