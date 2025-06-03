<?php

namespace App\Filament\Resources\AIModelResource\Pages;

use App\Filament\Resources\AIModelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAIModel extends EditRecord
{
    protected static string $resource = AIModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
