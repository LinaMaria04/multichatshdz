<?php

namespace App\Livewire;

use App\Models\Log;
use Illuminate\Http\Request;
use Livewire\Attributes\Validate;
use Livewire\Component;

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

    public function mount(string $code = null, Request $request)
    {
        $chat = \App\Models\Chat::where('code', $code)->firstOrFail();

        $this->code = $code;

        $this->header = $chat->header;

        $this->assistant_id = $chat->assistant_id;

        $this->agent = $chat->agent;

        $this->log_ip = $request->ip();

        $this->url = $request->url();

        $this->user_agent = $request->userAgent();
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

        $this->messages[] = ['role' => 'user', 'content' => $this->body];
        $this->messages[] = ['role' => 'assistant', 'content' => ''];
        $this->is_typing = true;

        $this->body = '';
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
