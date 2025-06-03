<?php

namespace App\Console\Commands;

use App\Models\Chat;
#use App\Services\OpenAiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FetchChatData extends Command
{
    protected $signature = 'app:fetch-chat-data {chat_id?} {--force}';
    protected $description = 'Descarga datos desde la URL configurada para un chat (JSON, HTML o RSS) y actualiza su asistente';    

    public function handle()
    {
        ray('FetchChatData command executed');

        $query = Chat::query()->whereNotNull('fetch_url')->whereNotNull('assistant_id');

        // Si se proporciona un ID de chat, procesar solo ese chat
        if ($chatId = $this->argument('chat_id')) {
            $query->where('id', $chatId);
        }

        $chats = $query->get();

        if ($chats->isEmpty()) {
            $this->info("No hay chats configurados para actualización de datos.");
            return Command::SUCCESS;
        }

        foreach ($chats as $chat) {
            // Comprobar si es hora de ejecutar según periodicidad, a menos que se fuerce
            if (!$this->option('force') && !$chat->shouldExecuteFetch()) {
                $this->info("Saltando chat {$chat->name} - no es momento de ejecutar actualización.");
                continue;
            }

            $this->info("Procesando chat {$chat->name} (ID: {$chat->code})");
            $this->info("URL: {$chat->fetch_url}");

            try {

                $filePath = $this->downloadContent($chat->fetch_url, $chat->assistant_id, $chat->code);

//                ray($filePath)->die();


                if ($filePath) {
                    // Actualizar los archivos del asistente en OpenAI
//                    $result = $openAiService->updateAssistantFiles($chat->assistant_id, $filePath);
                    $result = $this->updateAssistantFiles($chat, $filePath);

                    if ($result) {
                        // Actualizar la última ejecución
                        $chat->update(['last_fetch_execution' => now()]);
                        $this->info("Archivos actualizados correctamente para chat {$chat->name}");
                    } else {
                        $this->error("Error al actualizar archivos del asistente para chat {$chat->name}");
                    }
                } else {
                    $this->error("No se pudo descargar el contenido desde la URL: {$chat->fetch_url}");
                }
            } catch (\Exception $e) {
                Log::error("Error al procesar chat {$chat->name} - URL {$chat->fetch_url}: " . $e->getMessage());
                $this->error("Error: " . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }

    private function downloadContent(string $url, string $assistantId, string $chatCode): ?string
    {
        try {
            $response = Http::timeout(30)->get($url);

            if ($response->successful()) {
                $content = $response->body();
                $contentType = $response->header('Content-Type');
                
                // Determinar el tipo de contenido basado en el Content-Type o analizando el contenido
                if (strpos($contentType, 'application/json') !== false || $this->isValidJson($content)) {
                    // Para JSON, mantener el formato bonito
                    $decoded = json_decode($content, false, 512, JSON_UNESCAPED_UNICODE);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::warning("El contenido de la URL no es un JSON válido: {$url}");
                        return null;
                    }
                    
                    $contentFormatted = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    $extension = 'json';
                } 
                elseif (strpos($contentType, 'text/html') !== false || $this->isHtml($content)) {
                    // Para HTML, extraer solo el texto y guardar como TXT
                    $contentFormatted = $this->extractTextFromHtml($content);
                    $extension = 'txt';
                }
                elseif (strpos($contentType, 'application/rss+xml') !== false || 
                       strpos($contentType, 'application/atom+xml') !== false || 
                       $this->isRssOrAtom($content)) {
                    // Para RSS o Atom, guardar tal cual
                    $contentFormatted = $content;
                    $extension = 'txt';
                }
                else {
                    // Tipo desconocido, guardar como texto plano
                    $contentFormatted = $content;
                    $extension = 'txt';
                }

                // Construir el nombre del archivo con la extensión adecuada
                $envPrefix = strtolower(config('app.env')) === 'local' ? strtolower(config('app.env')) . '-' : '';
                $fileName = sprintf('assistants/%s/%s%s.%s',
                    $assistantId,
                    $envPrefix,
                    $chatCode,
                    $extension
                );

                // Asegurarnos de que el directorio existe
                Storage::disk('public')->makeDirectory(dirname($fileName));

                // Guardar el archivo
                Storage::disk('public')->put($fileName, $contentFormatted);

                // Devolver solo el nombre del archivo, no la ruta completa
                return $fileName;
            }

            Log::error("Error al descargar el contenido. Código: {$response->status()}");
            return null;
        } catch (\Exception $e) {
            Log::error("Error en la descarga: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verifica si una cadena es un JSON válido
     */
    private function isValidJson(string $content): bool
    {
        json_decode($content);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Verifica si una cadena es HTML
     */
    private function isHtml(string $content): bool
    {
        return strpos(trim($content), '<') === 0 && 
               (strpos($content, '<html') !== false || strpos($content, '<!DOCTYPE html') !== false);
    }

    /**
     * Verifica si una cadena es un feed RSS o Atom
     */
    private function isRssOrAtom(string $content): bool
    {
        return strpos($content, '<rss') !== false || 
               strpos($content, '<feed') !== false || 
               strpos($content, '<channel') !== false ||
               strpos($content, '<item>') !== false;
    }

    /**
     * Extrae texto de contenido HTML
     */
    private function extractTextFromHtml(string $html): string
    {
        // Usar DOMDocument para extraer texto de HTML
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        
        // Extraer texto de los elementos relevantes
        $text = '';
        $xpath = new \DOMXPath($dom);
        $textNodes = $xpath->query('//text()');
        
        foreach ($textNodes as $node) {
            $parentNode = $node->parentNode;
            // Excluir scripts, estilos y comentarios
            if (!in_array(strtolower($parentNode->nodeName), ['script', 'style', 'noscript'])) {
                $nodeText = trim($node->nodeValue);
                if (!empty($nodeText)) {
                    $text .= $nodeText . "\n";
                }
            }
        }
        
        // Eliminar líneas en blanco múltiples
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);
        
        return $text;
    }

    private function updateAssistantFiles(Chat $chat, string $fileName): bool
    {
        try {
            $client = app('openai');

            // 1. Obtener la lista de archivos en el VectorStore
            $response = $client->vectorStores()->files()->list(
                vectorStoreId: $chat->vectorstore_id
            );

            // 2. Obtener el file_id guardado en la base de datos
            $existingFile = $chat->files()->where('filename', $fileName)->first();
            $existingFileId = $existingFile ? $existingFile->file_id : null;

            // 3. Si existe un archivo previo, eliminarlo
            if ($existingFileId) {
                $client->files()->delete($existingFileId);
            }

            // 4. Verificar que el archivo existe en almacenamiento local
            if (!Storage::disk('public')->exists($fileName)) {
                throw new \Exception("Archivo no encontrado: " . $fileName);
            }

            // 5. Subir el archivo actualizado a OpenAI
            $uploadedFile = $client->files()->upload([
                'file' => Storage::disk('public')->readStream($fileName),
                'purpose' => 'assistants',
            ]);

            // 6. Asociar el archivo nuevo al VectorStore
            $client->vectorStores()->files()->create(
                vectorStoreId: $chat->vectorstore_id,
                parameters: [
                    'file_id' => $uploadedFile->id,
                ]
            );

            // 7. Obtener la periodicidad de actualización en horas
            $updateFrequency = $chat->fetch_periodicity ?? 'Desconocido';

            // 8. Formatear la fecha y hora de la actualización en un formato corto
            $lastUpdatedAt = now()->format('d-m-Y H:i');

            // 9. Crear descripción corta con periodicidad y última actualización
            $description = "Actualiza cada {$updateFrequency}h - Última: {$lastUpdatedAt}";

            // 10. Guardar o actualizar la referencia del archivo en la base de datos
            $chat->files()->updateOrCreate(
                ['filename' => $fileName],
                [
                    'file_id' => $uploadedFile->id, // Guardamos el nuevo file_id
                    'description' => $description,
                    'filename_description' => $description,
                ]
            );

            return true;
        } catch (\Exception $e) {
            Log::error('Error actualizando archivos del asistente: ' . $e->getMessage());
            throw $e;
        }
    }

}