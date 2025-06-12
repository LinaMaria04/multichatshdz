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
use Illuminate\Support\Facades\Http; 
use Illuminate\Support\Facades\Log as LogFacade; 

class FilesRelationManager extends RelationManager
{
    // Constante para el número máximo de archivos por chat
    const MAX_FILES_PER_CHAT = 20;

    protected static string $relationship = 'files';

    protected static ?string $title = 'Archivos';

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
                        // 'application/msword', // Se enviarán como binarios, Python los manejará
                        'text/plain',
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
                        
                        $firstFile = null;
                        $chat = $livewire->getOwnerRecord(); // Obtener la instancia del Chat
                        $pythonApiUrl = env('PYTHON_API_URL'); // Obtener la URL de la API de Python

                        // Obtener el vectorstore_id del chat
                        $vectorstoreId = $chat->vectorstore_id;

                        $vectorstoreId  = str_replace('_', '-', $vectorstoreId);

                        //CREAR EL ÍNDICE EN PINECONE SI NO EXISTE
                        if (empty($vectorstoreId)) {
                            Notification::make()
                                ->title('Error: El chat no tiene un ID de vector store asociado.')
                                ->danger()
                                ->send();
                            LogFacade::error('Error: vectorstore_id no está disponible para el chat ' . $chat->id);
                            throw new \Exception('ID de vector store no disponible.');
                        }

                        try {
                             $createIndexResponse = Http::withHeaders([
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json',
                            ])->post("{$pythonApiUrl}/create_pinecone_index", [
                                'index_name' => $vectorstoreId ,
                                'dimension' => 1024,
                                'metric' => 'cosine'
                            ]);

                            if (!$createIndexResponse->successful()) {
                                $errorDetail = $createIndexResponse->json('detail') ?? $createIndexResponse->body();
                                Notification::make()
                                    ->title('Error al crear el índice de Pinecone.')
                                    ->body("Detalles: " . (is_array($errorDetail) ? json_encode($errorDetail) : $errorDetail))
                                    ->danger()
                                    ->send();
                                LogFacade::error("Error al crear índice Pinecone para '{$vectorstoreId}': " . $createIndexResponse->body());
                                throw new \Exception('Fallo al crear el índice de Pinecone.');
                            } else {
                                LogFacade::info("Índice Pinecone para '{$vectorstoreId}' (re)confirmado: " . $createIndexResponse->body());
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Excepción al preparar el índice de Pinecone.')
                                ->body("Error: " . $e->getMessage())
                                ->danger()
                                ->send();
                            LogFacade::error("Excepción al crear/verificar índice Pinecone para '{$vectorstoreId}': " . $e->getMessage());
                            throw $e; // Re-lanza para que Filament sepa que la acción falló
                        }
                        
                        //Procesar cada archivo para ingesta en Pinecone 
                        foreach ($data['filename'] as $index => $filename) {
                            $file = $chat->files()->create([
                                'filename' => $filename,
                                'filename_description' => $data['filename_description'] ?? null,
                                'description' => $data['description'] ?? null,
                                'file_id' => null, // Este campo ya no se usará para OpenAI Files, sino para tu propio ID si lo necesitas
                            ]);
                            
                            $filePath = Storage::disk('public')->path($filename);
                            $fileMimeType = mime_content_type($filePath);

                            // Definir los tipos de archivos soportados por la API de Python para procesamiento
                            $supportedMimeTypes = [
                                'application/pdf',
                                'text/plain',
                                'application/json',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
                                'application/msword', // .doc
                                'application/zip', // .zip
                            ];

                            if (!in_array($fileMimeType, $supportedMimeTypes)) {
                                Notification::make()
                                    ->title('Tipo de archivo no soportado para ingesta.')
                                    ->body("El tipo '{$fileMimeType}' para '{$filename}' no es soportado. Contacta a soporte si necesitas este tipo de archivo.")
                                    ->danger()
                                    ->send();
                                LogFacade::warning("Tipo de archivo '{$fileMimeType}' no soportado para ingesta directa en Pinecone: {$filename}");
                                continue; 
                            }

                            try {
                                $fileContent = file_get_contents($filePath);

                                $ingestResponse = Http::attach(
                                    'file', // Nombre del campo del formulario en Python
                                    $fileContent,
                                    basename($filePath), // Nombre original del archivo para la API
                                    ['Content-Type' => $fileMimeType] // Tipo de archivo
                                )->post("{$pythonApiUrl}/ingest_file", [ 
                                    'document_id' => 'file-' . $file->id, 
                                    'metadata_json' => json_encode([ 
                                        'source' => basename($filename),
                                        'chat_id' => $chat->id,
                                        'chat_code' => $chat->code,
                                        'type' => $fileMimeType,
                                        'filename_description' => $data['filename_description'] ?? null,
                                        'description' => $data['description'] ?? null,
                                    ]),
                                    'vectorstore_id' => $vectorstoreId, // El ID del vectorstore
                                ]);

                                if ($ingestResponse->successful()) {
                                    LogFacade::info("Archivo '{$filename}' (ID: {$file->id}) enviado a Python para ingesta exitosamente (índice: {$vectorstoreId}). Respuesta: " . $ingestResponse->body());
                                } else {
                                    $errorDetail = $ingestResponse->json() ?? $ingestResponse->body();
                                    Notification::make()
                                        ->title("Error al ingestar '{$filename}' en Pinecone (vía Python).")
                                        ->body("Detalles: " . (is_array($errorDetail) ? json_encode($errorDetail) : $errorDetail))
                                        ->danger()
                                        ->send();
                                    LogFacade::error("Error al ingestar archivo '{$filename}' en Pinecone (índice: {$vectorstoreId}): " . $ingestResponse->body());
                                }
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title("Excepción al enviar '{$filename}' a la API de Python.")
                                    ->body("Error: " . $e->getMessage())
                                    ->danger()
                                    ->send();
                                LogFacade::error("Excepción al enviar archivo '{$filename}' a API de Python: " . $e->getMessage());
                            }
                            
                            // Guardar referencia al primer archivo para devolverlo
                            if ($index === 0) {
                                $firstFile = $file;
                            }
                        }
                        
                        return $firstFile;
                    })
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->before(function (DeleteAction $action, Files $file) {
                        ray('Vamos a eliminar del servidorel archivo: ' . $file->filename);
                        Storage::disk('public')->delete($file->filename);
                    })
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Tables\Actions\DeleteBulkAction $action, \Illuminate\Database\Eloquent\Collection $records) {
                            foreach ($records as $record) {
                                Storage::disk('public')->delete($record->filename);
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