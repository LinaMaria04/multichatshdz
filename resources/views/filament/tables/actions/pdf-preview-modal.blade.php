@if($isPdf)
    <div class="px-4 py-2">
        <div class="bg-gray-100 rounded-lg p-4">
            <iframe src="{{ $fileUrl }}" class="w-full h-[70vh] border-0 rounded"></iframe>
        </div>
        <div class="mt-4 flex justify-end">
            <a href="{{ $fileUrl }}" target="_blank" class="text-primary-600 hover:underline">
                Abrir PDF en nueva pestaña →
            </a>
        </div>
    </div>
@else
    <div class="px-4 py-2 text-center text-gray-500">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <p class="mt-2">La vista previa solo está disponible para archivos PDF</p>
    </div>
@endif