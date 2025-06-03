<?php

namespace App\Filament\Resources\ChatResource\RelationManagers;

use App\Models\Files;

use Filament\Forms;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class ImageRelationManager extends RelationManager{ 

    protected static string $relationship = 'imagen';

    public function form(Form $form): Form{
        return $form
            ->schema([
                Forms\Components\FileUpload::make('path')
                ->image()
                ->imageEditor()
                ->directory(function(RelationManager $livewire){
                    $chat_id = $livewire->getOwnerRecord()->id;
                    return 'chats/'. $chat_id . 'images';
                })
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                ->maxSize(5120)
                ->required(),
                Forms\Components\TextInput::make('nameimage')
                ->label('Título de la imagen')
                ->required(),
                Forms\Components\Textarea::make('description')
                ->label('Descripción')
                ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\ImageColumn::make('path')
                    ->label('Imagen')
                    ->square() // o circular(), width(), height()
                    ->defaultImageUrl('/images/placeholder.jpg'),
                Tables\Columns\TextColumn::make('nameimage')
                    ->label('Título'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(50),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->createAnother(false)
                    ->mutateFormDataUsing(function (array $data): array {
                        // Aquí puedes agregar código para procesar con modelos de IA
                        // Por ejemplo, enviar a OpenAI o Anthropic para obtener descripción

                        // Ejemplo conceptual:
                        if ($this->getOwnerRecord()->model->has_vision_capability) {
                            // Obtener descripción automática
                            $data['auto_description'] = $this->getImageDescription(
                                Storage::disk('public')->path($data['path']),
                                $data['title']
                            );
                        }

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        Storage::disk('public')->delete($record->path);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            $records->each(function ($record) {
                                Storage::disk('public')->delete($record->path);
                            });
                        }),
                ]),
            ]);
    }
    
    protected function getImageDescription(string $imagePath, string $title)
    {
        $imageData = base64_encode(file_get_contents($imagePath));
        $mimeType = mime_content_type($imagePath);

        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un asistente que describe imágenes.'],
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => "Título: {$title}"],
                            ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$imageData}"]],
                        ],
                    ],
                ],
                'temperature' => 0.7,
            ]);

        return $response->json('choices.0.message.content') ?? 'Descripción no disponible';
    }

}
