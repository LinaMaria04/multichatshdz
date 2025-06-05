<?php

namespace App\Livewire;

use App\Models\Log;
use App\Models\Chat as ChatModel;
use App\Models\DatabaseConnection;
use App\Services\DatabaseConnector;
use Illuminate\Http\Request;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Log as LogFacade;
use OpenAI\Laravel\Facades\OpenAI;

class Chat extends Component
{
    protected $listeners = [
        'scrollToInput' => 'scrollToInput',
        'ttsAudioReady' => 'playTtsAudio',
        'typingFinished' => 'handleTypingFinished'
    ];

    public bool $tts_voice = false;
    #[Validate('required|max:1000')]
    public string $body = '';

    public array $messages = [];

    public bool $is_typing = false;

    public string $assistant_id = '';

    public $agent = null;

    public string $log_ip = '';

    public string $url = '';

    public string $code = '';

    public string $user_agent = '';

    public string $header = '';

    public $provider = null;

    public $providername = null;

    public $imagen;

    public $rutaArchivo;

    public $comportamiento;

    public ?string $formula = null;
    public bool $usarFormula = false;
    public ?string $formtemp = null;
    public array $variables = [];

    public $dbConnections = null;

    public function mount(string $code = null, Request $request)
    {
        $chat = \App\Models\Chat::where('code', $code)->firstOrFail();

        LogFacade::info('Chat data', $chat->toArray());

        $this->code = $code;

        $this->header = $chat->header;

        $this->assistant_id = $chat->assistant_id ?? '';

        $this->agent = $chat->agent;

        $this->log_ip = $request->ip();

        $this->url = $request->url();

        $this->user_agent = $request->userAgent();

        $this->provider = $chat->provider;
        $this->providername = $chat->providername;

        $firstImagen = $chat->imagen()->select('path')->first();
        $this->imagen = $firstImagen ? $firstImagen['path'] : null;

        $archivo = $chat->files()->select('filename')->first();
        $this->rutaArchivo = $archivo ? $archivo['filename'] : null;

        $this->comportamiento = $chat->prompt;

        //Cargar las conexiones de bases de datos asocidas al chat
        $this->dbConnections = $chat->databaseConnection;
        
        LogFacade::info('DB Connections loaded', $this->dbConnections->toArray());
    }

    public function extraerTextoPDF($rutaArchivo){
        $parser = new Parser();
        $pdf = $parser->parseFile($rutaArchivo);
        $texto = $pdf->getText();
        return $texto;
    }

    public function send()
    {
        // Asegúrate de que $tts_voice está actualizado con el valor del checkbox
        $this->tts_voice = (bool) $this->tts_voice;
        $this->validate();

        Log::create([
            'ip' => $this->log_ip,
            'url' => $this->url,
            'user_agent' => $this->user_agent,
            'agent_code' => $this->code,
            'role' => 'user',
            'content' => $this->body,
            'timestamp' => now()
        ]);

        $currentUserMessage = $this->body;
        $this->messages[] = ['role' => 'user', 'content' => $currentUserMessage]; //Revisar si funciona con todas las posibilidades de chats
        $this->body = '';
        $this->is_typing = true; //Activa el indicador de escribiendo

        try {
            $assistantReply = '';

            //Procesar preguntas al usuario con la base de datos
            $dbResponse = $this->handleDatabaseQuery($currentUserMessage);
            //Si hay una respuesta de la base de datos, utilizarla como respuesta del chat
            if($dbResponse !== null){
                $assistantReply = $dbResponse;
                $this->is_typing = true;
            } else {
             // Si hay un PDF para enviar y usar
            if ($this->rutaArchivo && empty($this->messages)) {
                $textoPDF = $this->extraerTextoPDF(storage_path('app/public/' . $this->rutaArchivo  ));
                // Agregamos el texto del PDF como contexto
                $this->messages = [
                    ['role' => 'system', 
                    'content' => "Comportamiento general:\n{$this->comportamiento}\n\nContenido del PDF:\n{$textoPDF}",]
                ];

            }

            //$this->messages[] = ['role' => 'user', 'content' => $currentUserMessage];

            if ($this->provider === 'openai') {

                 // Si hay un PDF para enviar y usar
                if ($this->rutaArchivo ?? false) {
                    $textoPDF = $this->extraerTextoPDF(storage_path('app/public/' . $this->rutaArchivo  ));
                    // Agregamos el texto del PDF como contexto
                    $this->messages[] = ['role' => 'system', 'content' => "Contenido del PDF: " . $textoPDF];
                }
                
                $response = app('openai')->chat()->create([
                    'model' => $this->providername,
                    'messages' => $this->messages,
                ]);
                $assistantReply = $response->choices[0]->message->content ?? '';

                 $this->messages[] = ['role' => 'assistant', 'content' => $assistantReply];

            } elseif ($this->provider === 'anthropic') {

                $apiMessages = [];

                if (count($this->messages) <= 2) {
                    // Primer mensaje, incluir imagen si existe
                    if ($this->imagen !== null) {
                        $imagePath = storage_path('app/public/' . $this->imagen);

                        if (!file_exists($imagePath)) {
                            throw new \Exception("La imagen no existe: $imagePath");
                        }

                        $imageData = base64_encode(file_get_contents($imagePath));
                        $mimeType = mime_content_type($imagePath);

                        $apiMessages[] = [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'Describe esta imagen basada en su título.',
                                ],
                                [
                                    'type' => 'image',
                                    'source' => [
                                        'type' => 'base64',
                                        'media_type' => $mimeType,
                                        'data' => $imageData,
                                    ],
                                ],
                            ],
                        ];
                    } else {
                        // Primer mensaje sin imagen
                         // Si hay un PDF para enviar y usar
                            if ($this->rutaArchivo ?? false) {
                                $textoPDF = $this->extraerTextoPDF(storage_path('app/public/' . $this->rutaArchivo  ));
                                // Agregamos el texto del PDF como contexto
                                $this->messages[] = ['role' => 'system', 'content' => "Contenido del PDF: " . $textoPDF];
                            }
                        $apiMessages[] = [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => $currentUserMessage,
                                ]
                            ],
                        ];
                    }

                    $response = Http::withHeaders([
                        'x-api-key' => config('anthropic.key'),
                        'anthropic-version' => '2023-06-01',
                        'Content-Type' => 'application/json',
                    ])->post('https://api.anthropic.com/v1/messages', [
                        'model' =>'claude-3-7-sonnet-20250219',
                        'max_tokens' => 1024,
                        'messages' => $apiMessages,
                        
                    ]);

                    $json = $response->json();
                    LogFacade::info('Anthropic response:', $json);

                    $assistantReply = $json['content'][0]['text'] ?? 'Sin respuesta.';
                    
                } else {
                    // Mensajes posteriores sin imagen
                    $response = Http::withHeaders([
                        'x-api-key' => config('anthropic.key'),
                        'anthropic-version' => '2023-06-01',
                        'Content-Type' => 'application/json',
                    ])->post('https://api.anthropic.com/v1/messages', [
                        'model' => $this->providername,
                        'messages' => [
                            ['role' => 'user', 'content' => $currentUserMessage],
                        ],
                        'max_tokens' => 1000,
                    ]);

                    $json = $response->json();
                    LogFacade::info('Respuesta Anthropic completa:', $json);

                    $assistantReply = $json['completion'] ?? ($json['choices'][0]['message']['content'] ?? '');
                }
            }
        }
            //array_pop($this->messages); Eliminar el último mensaje del usuario para no repetirlo


            $this->formula = $this->extraerFormulaLatex($assistantReply);

            if ($this->formula) {
                // Quitar fórmula del texto para no repetirla en el mensaje
                $cleanText = preg_replace('/(\$\$.*?\$\$|\\\(.*?\\\))/s', '', $assistantReply);
                $cleanText = trim($cleanText);

                // Agregar solo el texto limpio al chat
                $this->messages[] = ['role' => 'assistant', 'content' => $cleanText];
            } else {
                $this->formula = null;
                $this->messages[] = ['role' => 'assistant', 'content' => $assistantReply];
            }

            // Resetear uso de fórmula en caso que hubiera
            $this->usarFormula = false;

            $this->dispatch('formulaActualizada');

            $this->formtemp = $this->usarFormula($this->formula);

        //dd( $this->extraerFormulaLatex($assistantReply));


        } catch (\Exception $e) {
            LogFacade::error('Error en chat send(): ' . $e->getMessage());

            array_pop($this->messages);
            $this->messages[] = ['role' => 'assistant', 'content' => 'Se ha producido un error al procesar tu consulta. Inténtalo más tarde.'];
        }

    }

    //Metodo para responder las preguntas del usuario con la base de datos
    private function handleDatabaseQuery(string $userMessage): ?string
    {
        //Verificar si hay una bd asociada al chat
        if ($this->dbConnections->isEmpty()) {
            return null;
        }

        foreach ($this->dbConnections as $dbConnection) {
            if ($dbConnection->tipo_conector !== 'mysql') { // Si NO es 'mysql', salta a la siguiente
                LogFacade::info("Saltando conexión DB ID {$dbConnection->id} por tipo de conector no soportado: {$dbConnection->tipo_conector}");
                continue;
            }

            try {
                $connector = new DatabaseConnector(); 
                $schema = $connector->getSchema($dbConnection);

                //dd($schema); 
                if (empty($schema)) {
                    LogFacade::warning(("No se pudo obtener el esquema para la conexión de la base de datos: " . $dbConnection->id));
                    continue; // Intenta seguir con la siguiente conexión en dado caso de que hayan varias
                }

                $schemaString = $this->formatSchemaForLLM($schema);

                $promptForSQL = "Eres un asistente de IA experto en SQL. Tu tarea es generar la consulta SQL más adecuada para responder a la pregunta del usuario, basándote *únicamente* en el esquema de base de datos proporcionado. 
                Las tablas disponibles son: {$schemaString}.
                Genera una consulta SQL (SOLO la consulta, sin ningún texto, explicaciones, markdown, ni comentarios adicionales) para responder a la siguiente pregunta del usuario: '{$userMessage}'
                Ejemplo de salida esperada: SELECT * FROM users;";

                $sqlQuery = '';
                $modelToUse = $this->providername;

                if ($this->provider === 'openai') { 
                    $sqlLlmResponse = app('openai')->chat()->create([
                        'model' => $modelToUse,
                        'messages' => [
                            ['role' => 'system', 'content' => $promptForSQL],
                            ['role' => 'user', 'content' => $userMessage],
                        ],
                        'max_tokens' => 200,
                        'temperature' => 0.1,
                    ]);
                    $sqlQuery = $sqlLlmResponse->choices[0]->message->content ?? '';
                } elseif ($this->provider === 'anthropic') {
                    $response = Http::withHeaders([
                        'x-api-key' => config('anthropic.key'),
                        'anthropic-version' => '2023-06-01',
                        'Content-Type' => 'application/json',
                    ])->post('https://api.anthropic.com/v1/messages', [
                        'model' => $modelToUse,
                        'system' => "Eres un experto en SQL que genera consultas SELECT para MySQL basadas en un esquema de base de datos. Responde solo con la consulta SQL.",
                        'messages' => [
                            ['role' => 'user', 'content' => $promptForSQL . "\n\nPregunta del usuario: " . $userMessage],
                        ],
                        'max_tokens' => 200,
                        'temperature' => 0.1,
                    ]);

                    if ($response->successful()) {
                        $anthropicResponse = $response->json();
                        $sqlQuery = $anthropicResponse['content'][0]['text'] ?? '';
                    } else {
                        LogFacade::error("Error en la llamada a la API de Anthropic (SQL): " . $response->body());
                        return null; 
                    }
                } else {
                    LogFacade::warning("Proveedor LLM no soportado: {$this->provider}");
                    return null;
                }

                // Limpieza de la consulta SQL generada por el LLM
                if (str_starts_with(trim($sqlQuery), '```sql')) {
                    $sqlQuery = trim(str_replace(['```sql', '```'], '', $sqlQuery));
                }
                $sqlQuery = trim($sqlQuery);

                if (!empty($sqlQuery) && $this->isValidSql($sqlQuery)) {
                    LogFacade::info("SQL generado por LLM: {$sqlQuery}");
                    $dbResults = $connector->executeQuery($dbConnection, $sqlQuery);

                    LogFacade::info("Resultados de la DB (raw): " . json_encode($dbResults));

                    if (empty($dbResults)) {
                        LogFacade::warning("La consulta SQL no arrojó resultados para la pregunta '{$userMessage}'. SQL: '{$sqlQuery}'");
                        return null;
                    }

                    $resultsString = json_encode($dbResults);

                    $extractedValue = null;
                    if (is_array($dbResults) && !empty($dbResults)) {
                        foreach ($dbResults as $row) {
                            if (is_array($row)) {
                                foreach ($row as $key => $value) {
                                    // Busca un valor numérico, idealmente el primero encontrado
                                    if (is_numeric($value)) {
                                        $extractedValue = $value;
                                        break 2; // Salir de ambos bucles
                                    }
                                }
                            }
                        }
                    }

                    $openaiPromptForResponse = "";
                    $anthropicPromptForResponse = "";

                    if ($extractedValue !== null) {
                        $openaiPromptForResponse = "El usuario preguntó: '{$userMessage}'. La respuesta a esta pregunta, basada en la consulta SQL ('{$sqlQuery}') ejecutada en la base de datos, es el número: {$extractedValue}. Por favor, genera una respuesta amigable y concisa para el usuario, utilizando **únicamente** este número {$extractedValue} para contestar la pregunta original. No menciones la base de datos, las consultas SQL, ni que no tienes acceso a la BD. Simplemente da la respuesta al usuario.";

                        $anthropicPromptForResponse = "La pregunta del usuario es: '{$userMessage}'. El resultado numérico de la base de datos es: {$extractedValue}. Responde la pregunta del usuario de forma amigable y concisa utilizando este número. No menciones bases de datos ni consultas SQL.";
                    } else {
                        $openaiPromptForResponse = "El usuario preguntó: '{$userMessage}'. Los resultados de la base de datos obtenidos fueron: {$resultsString}. Genera una respuesta amigable y concisa para el usuario, basándote en estos resultados. Si no puedes extraer un número claro, explica lo que los resultados indican sin mencionar que no tienes acceso a la BD.";
                        $anthropicPromptForResponse = "La pregunta del usuario es: '{$userMessage}'. Los resultados de la base de datos obtenidos son: {$resultsString}. Responde la pregunta de forma amigable y concisa basándote en estos resultados.";
                    }

                    //Limpieza adicional del prompt para Anthropic (se aplica a la cadena final)
                    $anthropicPromptForResponse = mb_convert_encoding($anthropicPromptForResponse, 'UTF-8', 'UTF-8');
                    $anthropicPromptForResponse = preg_replace('/[[:cntrl:]]/', '', $anthropicPromptForResponse);
                    $anthropicPromptForResponse = trim($anthropicPromptForResponse);

                    if (empty($anthropicPromptForResponse)) {
                        LogFacade::error("El prompt para Anthropic quedó vacío después de la limpieza. Revisa los caracteres en la cadena.");
                        return null; 
                    }

                    $finalLlmResponseContent = '';
                    
                    if ($this->provider === 'openai') {
                        $finalLlmResponse = app('openai')->chat()->create([
                            'model' => $modelToUse,
                            'messages' => [
                                ['role' => 'system', 'content' => $openaiPromptForResponse],
                                ['role' => 'user', 'content' => $userMessage],
                            ],
                            'temperature' => 0.5,
                        ]);
                        $finalLlmResponseContent = $finalLlmResponse->choices[0]->message->content ?? '';

                    } elseif ($this->provider === 'anthropic') {

                        $systemPromptAnthropicFinal = "Eres un asistente que responde preguntas de forma concisa y directa, utilizando la información proporcionada. NO inventes información ni menciones que no tienes acceso a la base de datos. Enfócate en responder con el dato que te doy.";
                        $systemPromptAnthropicFinal = mb_convert_encoding($systemPromptAnthropicFinal, 'UTF-8', 'UTF-8');
                        $systemPromptAnthropicFinal = preg_replace('/[[:cntrl:]]/', '', $systemPromptAnthropicFinal);
                        $systemPromptAnthropicFinal = trim($systemPromptAnthropicFinal);

                        // Este es el PAYLOAD FINAL que se enviará a Anthropic
                        $finalPayloadAnthropic = [
                            'model' => $modelToUse,
                            'system' => $systemPromptAnthropicFinal, // Usa el system prompt limpio y específico
                            'messages' => [
                                ['role' => 'user', 'content' => $anthropicPromptForResponse], // Usa el prompt de usuario limpio
                            ],
                            'max_tokens' => 500,
                            'temperature' => 0.7,
                        ];

                        // dd($finalPayloadAnthropic);

                        LogFacade::info("Payload Final Anthropic: " . json_encode($finalPayloadAnthropic)); 

                        $response = Http::withHeaders([
                            'x-api-key' => config('anthropic.key'),
                            'anthropic-version' => '2023-06-01',
                            'Content-Type' => 'application/json',
                        ])->post('https://api.anthropic.com/v1/messages', $finalPayloadAnthropic); 

                        if ($response->successful()) {
                            $anthropicResponse = $response->json();
                            $finalLlmResponseContent = $anthropicResponse['content'][0]['text'] ?? '';
                        } else {
                            LogFacade::error("Error en la llamada a la API de Anthropic (Respuesta Final): " . $response->body());
                            return null;
                        }
                    }
                    

                    if (!empty($finalLlmResponseContent)) {
                        LogFacade::info("Respuesta Final de LLM: {$finalLlmResponseContent}");
                        return $finalLlmResponseContent;
                    }

                    return null;

                } else {
                    LogFacade::warning("LLM generó SQL inválido o no generó SQL para la pregunta: '{$userMessage}'. SQL generado: '{$sqlQuery}'");
                    return null;
                }
            } catch (\Exception $e) {
                LogFacade::error("Error en el procesamiento de DB para la pregunta '{$userMessage}': " . $e->getMessage());

                return null;
            }
        }
        return null; // Si no se pudo responder con ninguna conexión de BD
    }

    private function formatSchemaForLLM(array $schema): string{

        $formattedSchema = "";

        foreach ($schema as $item){
            //dd($item);
            if(is_array($item) && isset($item['name']) && isset($item['columns'])) {
                //dd($item);
                $tableName = $item['name'];
                $columnsData  = $item['columns'];
                //dd($tableName);
                $formattedSchema .= "Tabla: {$tableName}\n";
                $formattedSchema .= "Columnas:\n";

                if (is_array($columnsData)) { // Asegurarse de que $columnsData es un array
                    foreach ($columnsData as $column) {
                        // Asegúrate de que cada $column sea un array y contenga la clave 'name'
                        if (is_array($column) && isset($column['name'])) {
                            $columnType = $column['type'] ?? 'VARCHAR'; // Asume VARCHAR si no hay tipo
                            $formattedSchema .= "  - {$column['name']} ({$columnType})\n";
                        }
                    }
                }
                $formattedSchema .= "\n";
            }
        }
        return $formattedSchema;
    }

    private function isValidSql(string $sql): bool{
        // Convertir la consulta a minúsculas para una comparación insensible a mayúsculas/minúsculas
        $lowerSql = strtolower(trim($sql));

        if (!preg_match('/^select\b/', $lowerSql)) {
            LogFacade::warning("SQL inválido: no es una consulta SELECT. Consulta: {$sql}");
            return false;
        }

        // Palabras clave que no deben estar presentes (operaciones de escritura)
        $forbiddenKeywords = [
            'insert into', 'update', 'delete from', 'drop table', 'alter table',
            'create table', 'truncate table', 'union all', // Considerar 'union all' si no quieres que el LLM combine resultados de tablas no relacionadas
            'grant', 'revoke', 'flush', 'set password', 'load data', 'into outfile'
        ];

        foreach ($forbiddenKeywords as $keyword) {
            if (str_contains($lowerSql, $keyword)) {
                LogFacade::warning("SQL inválido: contiene palabra clave prohibida ('{$keyword}'). Consulta: {$sql}");
                return false;
            }
        }

        return true; // Si pasa todas las validaciones, es válido.
    }

    

    private function extraerFormulaLatex(string $texto): ?string {

        if (preg_match('/\\\\\[([\s\S]*?)\\\\\]/', $texto, $matches)) {
            return '[' . trim($matches[1]) . ']'; 
        }

        if (preg_match('/\$\$([\s\S]*?)\$\$/', $texto, $matches)) {
            return '$$' . trim($matches[1]) . '$$';
        }       

        if (preg_match('/\\\\\(([\s\S]*?)\\\\\)/', $texto, $matches)) {
            return '(' . trim($matches[1]) . ')'; 
        }

        return null;
    }

    public function usarFormula(){

        if($this->formula){

        $this->usarFormula = true;

        // Detectar variables para inputs
        $variables = $this->detectarVariables($this->formula);
        $this->variables = array_fill_keys($variables, null);
        }

    }

    private function detectarVariables(?string $formula): array
    {
        if (!$formula) return [];

        // Extraer lado derecho de la ecuación
        $partes = explode('=', $formula);
        $ladoDerecho = count($partes) >= 2 ? trim($partes[1]) : trim($formula);

        // Eliminar delimitadores LaTeX como $$, \[, \], \(, \)
        $clean = preg_replace('/(\$\$|\\\\\[|\\\\\]|\\\\\(|\\\\\))/', '', $ladoDerecho);

        // Eliminar comandos LaTeX (ej: \frac, \cdot, \sin, \cos, etc.)
        $clean = preg_replace('/\\\\[a-zA-Z]+/', '', $clean);

        // Eliminar caracteres no alfanuméricos (excepto guiones bajos y espacios)
        $clean = preg_replace('/[^a-zA-Z0-9_ ]/', ' ', $clean);

        // Detectar tokens alfanuméricos (pueden ser variables como x, v_f, t1, etc.)
        preg_match_all('/\b[a-zA-Z][a-zA-Z0-9_]*\b/', $clean, $matches);

        // Filtrar funciones matemáticas comunes por seguridad
        $funciones = [
            'sin', 'cos', 'tan', 'log', 'ln', 'sqrt', 'exp',
            'min', 'max', 'mod', 'floor', 'ceil', 'round',
            'pi', 'e'
        ];

        $variables = array_filter($matches[0], function ($v) use ($funciones) {
            return !in_array(strtolower($v), $funciones);
        });

        return array_unique($variables);
    }

    public function calcularFormula()
    {
        // Verificar si hay una fórmula
        if (!$this->formula) {
            $this->messages[] = ['role' => 'system', 'content' => 'No hay una fórmula definida para calcular.'];
            return;
        }

        // Verificar si todas las variables tienen valores numéricos
        foreach ($this->variables as $var => $value) {
            if (!is_numeric($value)) {
                $this->messages[] = ['role' => 'system', 'content' => "El valor de '$var' debe ser un número válido."];
                return;
            }
        }

        // Construir mensaje para la IA
        $mensaje = "Dado que la fórmula es '$this->formula' y los valores de las variables son: " . json_encode($this->variables) . 
                ", calcula el resultado y devuelve solo el número.";

        // Enviar solicitud a la IA
        try {

            if ($this->provider === 'openai') {
                $response = app('openai')->chat()->create([
                    'model' => 'gpt-4-turbo',
                    'messages' => [
                        ['role' => 'user', 'content' => $mensaje]
                    ]
                ]);

                $resultadoFinal = $response->choices[0]->message->content ?? 'Error en respuesta';
                $this->messages[] = ['role' => 'assistant', 'content' => "El resultado es: $resultadoFinal"];
            }elseif($this->provider === 'anthropic') {    

                $response = Http::withHeaders([
                'x-api-key' => config('anthropic.key'),
                'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-3-7-sonnet-20250219',
                'max_tokens' => 256,
                'messages' => [
                    ['role' => 'user', 'content' => $mensaje]
                ]
            ]);

            $resultadoFinal = $response->json()['completion'] ?? 'Error en respuesta';
            $this->messages[] = ['role' => 'assistant', 'content' => "El resultado es: $resultadoFinal"];
            }

                        $this->dispatch('formulaActualizada');

        } catch (\Exception $e) {
            LogFacade::error('Error al calcular la fórmula con IA: ' . $e->getMessage());
           $this->messages[] = ['role' => 'assistant', 'content' => 'Ocurrió un error al procesar la fórmula.'];
        }
    }


    public function playTtsAudio($url)
    {
        $this->dispatch('play-tts-audio', url: $url);
    }

    public function scrollToInput()
    {
        $this->dispatch('scroll-to-input');
    }

    public function handleTypingFinished()
    {
        $this->is_typing = false;
    }

    public function render()
    {
        return view('livewire.chat');
    }
}
