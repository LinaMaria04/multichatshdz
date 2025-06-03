<?php

namespace App\Livewire;

use App\Models\Log;
use Illuminate\Http\Request;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Smalot\PdfParser\Parser;

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

    public function mount(string $code = null, Request $request)
    {
        $chat = \App\Models\Chat::where('code', $code)->firstOrFail();

        \Log::info('Chat data', $chat->toArray());

        $this->code = $code;

        $this->header = $chat->header;

        $this->assistant_id = $chat->assistant_id;

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

        //$this->messages[] = ['role' => 'user', 'content' => $this->body];
        //$this->messages[] = ['role' => 'assistant', 'content' => ''];
        //$this->is_typing = true;

        //$this->body = '';

         $currentUserMessage = $this->body;
        $this->body = '';

        try {
            $assistantReply = '';

             // Si hay un PDF para enviar y usar
            if ($this->rutaArchivo && empty($this->messages)) {
                $textoPDF = $this->extraerTextoPDF(storage_path('app/public/' . $this->rutaArchivo  ));
                // Agregamos el texto del PDF como contexto
                $this->messages = [
                    ['role' => 'system', 
                    'content' => "Comportamiento general:\n{$this->comportamiento}\n\nContenido del PDF:\n{$textoPDF}",]
                ];

            }

            $this->messages[] = ['role' => 'user', 'content' => $currentUserMessage];

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
                    \Log::info('Anthropic response:', $json);

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
                    \Log::info('Respuesta Anthropic completa:', $json);

                    $assistantReply = $json['completion'] ?? ($json['choices'][0]['message']['content'] ?? '');
                }
            }
            array_pop($this->messages);


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
            \Log::error('Error en chat send(): ' . $e->getMessage());

            array_pop($this->messages);
            $this->messages[] = ['role' => 'assistant', 'content' => 'Se ha producido un error al procesar tu consulta. Inténtalo más tarde.'];
        }

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
            \Log::error('Error al calcular la fórmula con IA: ' . $e->getMessage());
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
