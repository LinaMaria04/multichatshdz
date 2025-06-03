<?php

namespace App\Filament\Resources\ChatResource\Pages;

use App\Filament\Resources\ChatResource;
use App\Models\Chat;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditChat extends EditRecord
{
    protected static string $resource = ChatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action, Chat $chat) {

                    $chat->files()->each(function ($file) use ($chat) {
                        ray($chat);
                        Storage::disk('public')->delete($file->filename);
                        Storage::disk('public')->deleteDirectory('assistants/'.$chat->assistant_id);
                    });

                    $chat->files()->delete();

                    $client = app('openai');

                    // Get all the files of this Chat
                    $response = $client->vectorStores()->files()->list(
                        vectorStoreId: $chat->vectorstore_id,
                    );

                    // Delete all the files
                    foreach ($response->data as $file) {
                        $client->files()->delete( $file->id );
                    }

                    // Delete the VectorStore
                    $client->vectorStores()->delete(
                        vectorStoreId: $chat->vectorstore_id
                    );

                    // Delete the Assistant
                    $client->assistants()->delete( $chat->assistant_id );

                }),
        ];
    }

    // Eliminado: getHeaderWidgets() para evitar referencia a FilesProgressBar

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
