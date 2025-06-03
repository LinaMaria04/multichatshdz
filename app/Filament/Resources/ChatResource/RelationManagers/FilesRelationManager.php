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
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class FilesRelationManager extends RelationManager
{
    // Constante para el número máximo de archivos por chat
    const MAX_FILES_PER_CHAT = 20;

    protected static string $relationship = 'files';

    protected static ?string $title = 'Archivos';

    public function getResourceTable(): string
    {
        $currentCount = $this->getOwnerRecord()->files()->count();
        $maxFiles = self::MAX_FILES_PER_CHAT;
        $remainingSlots = $maxFiles - $currentCount;

        // Continúa con la renderización normal de la tabla
        return parent::getResourceTable();
    }

    public function getTableHeaderViewData(): array
    {
        $currentCount = $this->getOwnerRecord()->files()->count();
        $maxFiles = self::MAX_FILES_PER_CHAT;
        $remainingSlots = $maxFiles - $currentCount;
        return [
            'remainingFiles' => $remainingSlots,
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('filename')
                    ->label('Archivo(s)')
                    ->multiple() // Permitir cargar múltiples archivos
                    ->maxFiles(10) // Limitar a máximo 10 archivos
                    ->maxSize(100 * 1024) // 100MB en kilobytes
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/msword',
                        'text/plain',
                        'application/zip',
                        'application/json'
                    ])
                    ->directory(function(RelationManager $livewire) {
                        $assistant_id = $livewire->getOwnerRecord()->assistant_id;
                        return 'assistants/'. $assistant_id;
                    })
                    ->helperText(function() {
                        $currentCount = $this->getOwnerRecord()->files()->count();
                        $maxFiles = self::MAX_FILES_PER_CHAT;
                        $remainingSlots = $maxFiles - $currentCount;

                        if ($remainingSlots <= 0) {
                            return 'Límite máximo de ' . $maxFiles . ' archivos alcanzado. No puedes subir más.';
                        }

                        return 'Puedes subir hasta ' . min(10, $remainingSlots) . ' archivos a la vez. Límite total: ' . $currentCount . '/' . $maxFiles . ' archivos.';
                    })
                    ->previewable() // Permitir previsualizar los PDFs
                    ->required(),
                Forms\Components\TextInput::make('filename_description')
                    ->label('Descripción común para los archivos'),
                Textarea::make('description')
                    ->label('Descripción detallada')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        $currentCount = $this->getOwnerRecord()->files()->count();
        $maxFiles = self::MAX_FILES_PER_CHAT;
        $remainingFiles = $maxFiles - $currentCount;

        return $table
            ->recordTitleAttribute('filename')
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('filename_description')
                    ->label('Descripción del archivo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('filename')
                    ->label('Archivo')
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        $shortName = strlen($state) > 20
                            ? '...' . substr($state, -20)
                            : $state;
                        return $shortName;
                    }),
            ])
            ->heading(
                new HtmlString(
                    view('filament.tables.header.files-header-with-actions', [
                        'remainingFiles' => $remainingFiles,
                        'max' => $maxFiles,
                        'count' => $currentCount,
                    ])->render()
                )
            )
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->createAnother(false)
                    ->model(Files::class)
                    ->successNotificationTitle('Archivos subidos correctamente')
                    ->before(function (Tables\Actions\CreateAction $action, RelationManager $livewire) {
                        $currentCount = $livewire->getOwnerRecord()->files()->count();
                        $remainingSlots = static::MAX_FILES_PER_CHAT - $currentCount;
                        if ($remainingSlots <= 0) {
                            $action->cancel();
                        }
                        
                        // Validar que se hayan seleccionado archivos
                        $data = $action->getFormData();
                        if (empty($data['filename']) || !is_array($data['filename'])) {
                            Notification::make()
                                ->title('Debes seleccionar al menos un archivo')
                                ->danger()
                                ->send();
                            
                            $action->cancel();
                        }
                    })
                    ->using(function (array $data, RelationManager $livewire): Files {
                        // Asegurarse de que filename es un array antes de continuar
                        if (!isset($data['filename']) || !is_array($data['filename'])) {
                            throw new \Exception('No se han seleccionado archivos');
                        }
                        
                        // Crear registros para cada archivo subido
                        $firstFile = null;
                        
                        foreach ($data['filename'] as $index => $filename) {
                            $file = $livewire->getOwnerRecord()->files()->create([
                                'filename' => $filename,
                                'filename_description' => $data['filename_description'] ?? null,
                                'description' => $data['description'] ?? null,
                                'file_id' => null, // Este campo se actualizará después si es necesario
                            ]);
                            
                            // Subir el archivo a OpenAI y asociarlo al VectorStore
                            try {
                                $client = app('openai');
                                $chat = $livewire->getOwnerRecord();
                                
                                // 1. Subir el archivo a OpenAI
                                $filePath = Storage::disk('public')->path($filename);
                                $uploadedFile = $client->files()->upload([
                                    'file' => fopen($filePath, 'r'),
                                    'purpose' => 'assistants',
                                ]);
                                
                                // 2. Asociar el archivo al VectorStore
                                if ($chat->vectorstore_id) {
                                    $client->vectorStores()->files()->create(
                                        vectorStoreId: $chat->vectorstore_id,
                                        parameters: [
                                            'file_id' => $uploadedFile->id,
                                        ]
                                    );
                                }
                                
                                // 3. Actualizar el registro con el ID del archivo
                                $file->update([
                                    'file_id' => $uploadedFile->id
                                ]);
                                
                            } catch (\Exception $e) {
                                // Registrar el error pero continuar con el proceso
                                \Log::error('Error al subir archivo a OpenAI: ' . $e->getMessage());
                            }
                            
                            // Guardar referencia al primer archivo para devolverlo
                            if ($index === 0) {
                                $firstFile = $file;
                            }
                        }
                        
                        // Devolver el primer archivo creado (requerido por Filament)
                        return $firstFile;
                    })
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalContent(fn (Files $record) => view('filament.tables.actions.pdf-preview-modal', [
                        'fileUrl' => \Storage::disk('public')->url($record->filename),
                        'isPdf' => str_ends_with(strtolower($record->filename), '.pdf'),
                    ])),
                Tables\Actions\DeleteAction::make()
                    ->before(function (DeleteAction $action, Files $file) {
                        ray('Vamos a eliminar del servidorel archivo: ' . $file->filename);
                        \Storage::disk('public')->delete($file->filename);
                        $client = app('openai');
                        // Solo intentar eliminar el archivo de OpenAI si file_id no es null
                        if (!empty($file->file_id)) {
                            ray('Vamos a eliminar de OpenAI el archivo: ' . $file->file_id);
                            $client->files()->delete($file->file_id);
                        }
                    })
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Tables\Actions\DeleteBulkAction $action, \Illuminate\Database\Eloquent\Collection $records) {
                            foreach ($records as $record) {
                                Storage::disk('public')->delete($record->filename);

                                $client = app('openai');
                                // Solo intentar eliminar el archivo de OpenAI si file_id no es null
                                if (!empty($record->file_id)) {
                                    $client->files()->delete($record->file_id);
                                }
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('No hay archivos añadidos')
            ->emptyStateDescription(function() {
                $currentCount = $this->getOwnerRecord()->files()->count();
                $maxFiles = self::MAX_FILES_PER_CHAT;
                $remainingSlots = $maxFiles - $currentCount;

                if ($remainingSlots <= 0) {
                    return "Este chat ha alcanzado el límite máximo de " . self::MAX_FILES_PER_CHAT . " archivos. Elimina alguno para poder añadir más.";
                }

                return "Puedes añadir hasta " . $remainingSlots . " archivo(s) a este chat (límite: " . self::MAX_FILES_PER_CHAT . ").";
            });
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return parent::render();
    }
}