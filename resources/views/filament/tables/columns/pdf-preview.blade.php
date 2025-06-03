@php
    $isPdf = str_ends_with(strtolower($getRecord()->filename), '.pdf');
    $filePath = Storage::disk('public')->url($getRecord()->filename);
@endphp

@if ($isPdf)
    <div class="flex items-center">
        <a href="{{ $filePath }}" class="text-primary-600 hover:underline flex items-center gap-2" target="_blank">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
            </svg>
            <span class="text-xs">Ver PDF</span>
        </a>
    </div>
@else
    <div class="text-xs text-gray-500">
        No disponible
    </div>
@endif