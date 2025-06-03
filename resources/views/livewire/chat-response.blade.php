<div class="w-3/4 space-y-0.5">
    <div class="text-xs font-semibold"><img src="/images/girl.png" class="h-7"></div>
    <div class="bg-indigo-100 rounded-xl rounded-tl-none px-3 py-1.5 text-sm relative group" @if(isset($audio_url) && $audio_url) data-audio-url="{{ $audio_url }}" @endif>
        {{-- Spinner: shown until stream-content sibling appears --}}
        <div class="flex flex-row items-center space-x-2 py-2 typing-indicator group-has-[span.stream-content]:hidden">
            <span class="w-7 h-7 flex items-center justify-center bg-slate-200 rounded-full">
                <img src="/images/spinner.svg" alt="" class="h-6">
            </span>
            <span class="text-xs text-slate-400">El asistente está escribiendo...</span>
        </div>

        {{-- Stream container --}}
        <div wire:stream="stream-{{ $this->getId() }}" class="stream">
            {{-- Add a wrapper with stream-content class only if $response is not empty --}}
            @if(!empty($response))
            <span class="stream-content">
                <x-markdown>{!! $response !!}</x-markdown>
            </span>
            @endif
        </div>

        @if($tts_error)
            <div class="mt-2 text-red-500 text-xs">
                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                {{ $tts_error }}
            </div>
        @endif
    </div>
</div>