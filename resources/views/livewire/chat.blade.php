<div class="max-w-lg mx-auto p-2 md:p-10 bg-white border border-slate-200 shadow-lg flex flex-col h-dvh">
    <!-- Cabecera fija -->
    <div class="flex-shrink-0 mb-4">
        <div class="hidden md:flex flex-row items-center justify-center mb-3 py-2 bg-indigo-100 rounded-lg ">
            <div class="flex flex-row items-center justify-center rounded-2xl text-indigo-700 bg-indigo-100 h-10 w-10">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                        </svg>
                    </div>
            <div class=" font-bold text-xl text-slate-700">{{ $header ?? 'Pregunta cualquier duda' }}</div>
        </div>
        <div class="flex flex-row justify-between">
            <img src="{{ url('storage/'.$agent->image)  }}" alt="profesora" class="rounded-lg h-16 md:h-40 mr-3">

            <div class="flex-1 text-slate-600 text-sm p-4 bg-slate-100 rounded-lg">
                    <div class="font-bold text-xl pb-1">{{ $agent->name }}</div>
                    <div class="text-sm hidden md:block">{{ $agent->description }}</div>
            </div>
        </div>
        <div class="flex my-3 bg-slate-100 h-1 rounded-lg"></div>
    </div>

    <!-- Cuerpo scrolleable -->
    <div id="chat-messages" class="flex-grow overflow-y-auto mb-4 flex flex-col space-y-3">
        <div class="flex flex-col w-full">
            @foreach($messages as $key => $message)
                @if ($message['role'] === 'user')
                    <div class="w-3/4 space-y-1 self-end">
                        <div class="text-xs text-right">Tú</div>
                        <div class="bg-slate-100 text-slate-600 rounded-xl rounded-tr-none px-3 py-1.5 text-sm">
                            {{ $message['content'] }}
                        </div>
                    </div>
                @endif
                @if ($message['role'] === 'assistant')
                    <livewire:chat-response
                     :key="$key"
                     :messages="$messages"
                     :prompt="$messages[$key > 0 ? $key - 1 : 0]" 
                     :assistant_id="$assistant_id" 
                     :code="$code" 
                     :provider="$provider"
                     :providername="$providername"
                     :assistantReply="$message['content']"
                     :tts_voice="$tts_voice"/>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Mostrar fórmula SI EXISTE (fuera del bucle de mensajes) --}}
    @if ($formula)
        <div id="formulaContainer" style="margin-bottom: 10px;">
            <div class="math">\({!! $formula !!}\)</div>
            <div>
                <button type="button" wire:click="usarFormula" 
                    style="display: block; background-color: #FFA500; color: #4b0082; padding: 10px 15px; border: none; border-radius: 5px; margin-top: 10px; cursor: pointer;">
                    Usar esta fórmula
                </button>
            </div>
        </div>
    @endif

    {{-- Formulario para ingresar variables --}}
    @if ($usarFormula)
        <form wire:submit.prevent="calcularFormula" class="my-4 p-3 bg-orange-50 border border-orange-300 rounded space-y-3">
            <div class="font-semibold mb-2">Ingresa los valores para las variables:</div>

            @foreach($variables as $var => $value)
                <div>
                    <label for="{{ $var }}" class="block font-medium">{{ $var }}:</label>
                    <input type="number" step="any" wire:model.defer="variables.{{ $var }}" id="{{ $var }}"
                        class="border rounded px-2 py-1 w-full" required>
                </div>
            @endforeach

            <button type = "submit" style="display: block; background-color: #FFA500; color: #4b0082; padding: 10px 15px; border: none; border-radius: 5px; margin-top: 10px; cursor: pointer;"> Calcular </button>
        </form>
    @endif

    <!-- Input fijo -->
    <div class="flex-shrink-0 pt-3 border-t border-slate-200">
        <form wire:submit="send" class="flex flex-col py-3 space-y-2">
            <div class="bg-white rounded-full grow relative flex items-center h-10 outline outline-1 outline-indigo-400 focus-within:ring-1 ring-inset ring-indigo-400">
                <input id="autoFocusInput" autofocus  autocomplete="off" class="bg-transparent rounded-full grow px-4 py-2 text-sm h-10 border-transparent focus:border-transparent focus:ring-0"  placeholder="¿En qué puedo ayudarte?" wire:model="body" />
                <button type="submit" class="bg-indigo-400 text-slate-100 rounded-3xl text-sm font-medium size-10 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                        <path d="M3.105 2.288a.75.75 0 0 0-.826.95l1.414 4.926A1.5 1.5 0 0 0 5.135 9.25h6.115a.75.75 0 0 1 0 1.5H5.135a1.5 1.5 0 0 0-1.442 1.086l-1.414 4.926a.75.75 0 0 0 .826.95 28.897 28.897 0 0 0 15.293-7.155.75.75 0 0 0 0-1.114A28.897 28.897 0 0 0 3.105 2.288Z" />
                    </svg>
                </button>
            </div>
            <div class="flex flex-row items-center justify-center pt-2 ">
                <label for="speak-response-checkbox" class="flex items-center cursor-pointer select-none">
                    <span class="text-xs text-gray-400 mr-3">Activar respuesta por voz</span>
                    <span class="relative">
                        <input type="checkbox" id="speak-response-checkbox" wire:model="tts_voice" class="sr-only peer">
                        <div class="w-8 h-4 bg-gray-300 rounded-full shadow-inner peer-checked:bg-indigo-400 transition-colors"></div>
                        <div class="absolute left-0.5 top-0.5 w-3 h-3 bg-white rounded-full shadow peer-checked:translate-x-4 transition-transform"></div>
                    </span>
                </label>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    let userManuallyScrolled = false;
    let chatContainer = null;
    let currentAudio = null;

    function scrollToBottomIfNecessary() {
        if (chatContainer && !userManuallyScrolled) {
            requestAnimationFrame(() => {
                 chatContainer.scrollTop = chatContainer.scrollHeight;
            });
        }
    }

    function initializeChatScroll() {
        chatContainer = document.getElementById('chat-messages');
        if (!chatContainer) return;

        userManuallyScrolled = false;
        scrollToBottomIfNecessary();

        chatContainer.addEventListener('scroll', () => {
            const isNearBottom = chatContainer.scrollHeight - chatContainer.scrollTop - chatContainer.clientHeight < 10;
            userManuallyScrolled = !isNearBottom;
        }, { passive: true });

        const observer = new MutationObserver((mutationsList) => {
            for(const mutation of mutationsList) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    scrollToBottomIfNecessary();
                    break;
                }
            }
        });
        observer.observe(chatContainer, { childList: true, subtree: true });

        const ttsCheckbox = document.getElementById('speak-response-checkbox');
        const inputField = document.getElementById('autoFocusInput');
        if (ttsCheckbox && inputField) {
            ttsCheckbox.addEventListener('change', () => {
                inputField.focus();
                if (!ttsCheckbox.checked && currentAudio) {
                    currentAudio.pause();
                    currentAudio.src = '';
                    currentAudio = null;
                }
            });
        }
    }

    document.addEventListener('livewire:initialized', () => {
        console.log('Livewire inicializado. Registrando listeners.');
        initializeChatScroll();
         const input = document.getElementById('autoFocusInput');
         if (input) {
             input.focus();
         }

        window.Livewire.on('typingFinished', () => {
            console.log('Evento typingFinished recibido');
            requestAnimationFrame(() => {
                scrollToBottomIfNecessary();
            });
        });
        window.Livewire.on('scrollToInput', () => {
            const inputField = document.getElementById('autoFocusInput');
            if(inputField) {
                 console.log('Haciendo scroll al input y focus.');
                 inputField.focus();
                 inputField.scrollIntoView({ behavior: 'smooth', block: 'center' });
             }
         });
         window.Livewire.on('play-audio-file', (event) => {
             const audioUrl = event?.url ?? event?.[0]?.url;
             if (audioUrl) {
                 if (currentAudio) {
                    currentAudio.pause();
                    currentAudio.src = '';
                    currentAudio = null;
                 }
                 console.log('Evento play-audio-file recibido con URL:', audioUrl);
                 const audioElement = new Audio(audioUrl);
                 currentAudio = audioElement;
                 audioElement.addEventListener('ended', () => {
                     currentAudio = null;
                 });
                 audioElement.play().catch(e => {
                     console.error('Error al reproducir el audio:', e);
                     alert('No se pudo reproducir el audio automáticamente. Es posible que necesites interactuar con la página primero.');
                     currentAudio = null;
                 });
             } else {
                 console.warn('Evento play-audio-file recibido sin URL válida.');
             }
         });
         document.addEventListener('keydown', (event) => {
             if (event.key === "Escape") {
                 if (currentAudio) {
                     console.log("Tecla ESC presionada, deteniendo audio.");
                     currentAudio.pause();
                     currentAudio.src = '';
                     currentAudio = null;
                 }
             }
         });
    });

    document.addEventListener('livewire:navigated', () => {
        console.log('Livewire navigated.');
        initializeChatScroll();
        const input = document.getElementById('autoFocusInput');
        if (input) {
            input.focus();
        }
    });
</script>
<script>
   document.addEventListener("DOMContentLoaded", function () {
        if (window.Livewire) {
            Livewire.on("formulaActualizada", () => {
                setTimeout(() => {
                    const formulaDiv = document.getElementById("formulaContainer");
                    if (window.MathJax && formulaDiv) {
                        //.typeset([formulaDiv]); // Procesar solo ese contenedor
                        MathJax.typesetPromise([formulaDiv]);
                    } else {
                        console.error("MathJax o el div de la fórmula no están disponibles.");
                    }
                }, 300);
            });
            Livewire.on("usarFormula", () => {
                    console.log("Evento usarFormula recibido correctamente.");
                });

        } else {
            console.error("Livewire no está definido en el frontend.");
        }
    });

</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js" async></script>
<script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
<script id="MathJax-script" async
        src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js">
</script>

@endpush
