<?php

namespace App\Livewire;

//use App\Mail\OpenAIException;
use App\Mail\OpenAIException;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;

class ChatResponse extends Component
{
    public bool $tts_voice = false;
    public array $prompt;

    public array $messages;

    public ?string $response = null;

    public string $assistant_id;

    public string $log_ip = '';

    public string $url = '';

    public string $code = '';

    public string $user_agent = '';

    public ?string $tts_error = null;

    public function mount(string $assistant_id, string $code, Request $request, bool $tts_voice = false)
    {
        $this->tts_voice = $tts_voice;
        ray('ChatResponse::mount', $assistant_id)->green();
        $this->assistant_id = $assistant_id;

        $this->log_ip = $request->ip();

        $this->url = $request->url();

        $this->user_agent = $request->userAgent();

        $this->code = $code;

        $this->js('$wire.getResponse()');
    }

    public function getResponse()
    {
        $instructions = \App\Models\Chat::where('code', $this->code)->first()?->prompt;

        if(!$instructions)
        {
            $instructions = "
              #ROL
              Eres un amable asistente que atiende a personas.              
        
              #INSTRUCCIONES          
              - En tus respuestas, no debe incluir referencias de fuentes o anotaciones.
              - Responde sólo de lo que encuentres en los documentos adjuntos.
              - Se proactiva y cálida en tu trato.                       
            ";
        }

        $filteredArray = array_filter($this->messages, function ($item) {
            return $item['role'] === 'user';
        });

        // Reindexar el array para que las claves sean consecutivas
        $filteredArray = array_values($filteredArray);

        try {
            $stream = app('openai')->threads()->createAndRunStreamed(
                [
                    'assistant_id' => $this->assistant_id,
                    'instructions' => $instructions,
                    'thread' => [
                        'messages' => $filteredArray
                    ],
                    'max_completion_tokens' => 600,
                ],
            );


            $content = '';
            foreach ($stream as $response) {

                ray($response->event);

                if ($response->event === 'thread.run.failed') {
                    $content = 'Tenemos problemas técnicos, vuelve a hacer tu pregunta en unos segundos';

                    $this->response = $content;
                }

                if ($response->event === 'thread.message.delta') {

                    $content = Arr::get($response->response->delta->content[0]->toArray(), 'text.value');

                    $this->response .= $content;
                }

                $this->stream(
                    to: 'stream-' . $this->getId(),
                    content: $content,
                    replace: false
                );

            }

            // Dispatch event to signal text generation is complete
            $this->dispatch('typingFinished');

            // Emitir evento para hacer scroll al input
            $this->dispatch('scrollToInput');

            // Let's save Assistant's response
            \App\Models\Log::create([
                'ip' => $this->log_ip,
                'url' => $this->url,
                'user_agent' => $this->user_agent,
                'agent_code' => $this->code,
                'role' => 'agent',
                'content' => $this->response,
                'timestamp' => now()
            ]);

            // If TTS is enabled, generate audio file and dispatch event
            if ($this->tts_voice && !empty($this->response)) {
                try {
                    $this->tts_error = null; // Clear previous error
                    $audioContent = app('openai')->audio()->speech([
                        'model' => 'tts-1',
                        'input' => $this->response,
                        'voice' => 'alloy', // Or another voice like nova, shimmer, etc.
                    ]);

                    $filename = 'tts/' . Str::uuid() . '.mp3';
                    Storage::disk('public')->put($filename, $audioContent);
                    $audioUrl = Storage::disk('public')->url($filename);

                    $this->dispatch('play-audio-file', url: $audioUrl);

                } catch (\Exception $e) {
                    Log::error('Error generating TTS audio', ['error' => $e->getMessage()]);
                    $this->tts_error = 'Error al generar el audio.';
                    // Optionally dispatch an error event to the frontend
                    // $this->dispatch('tts-error', message: 'Error al generar el audio.');
                }
            }

        } catch (\Exception $e) {
            Log::error('Error OpenAI API call', ['error' => $e->getMessage()]);
            $this->response = 'Se ha producido un error al procesar tu consulta. Inténtalo más tarde.';
            ray($e->getMessage());
//            Mail::to( config('mail.to.address') )
//                ->queue(new OpenAIException($e->getMessage()));

        }
    }


    public function render()
    {
        return view('livewire.chat-response');
    }
}
