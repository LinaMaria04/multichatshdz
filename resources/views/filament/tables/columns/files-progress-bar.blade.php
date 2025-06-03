@php
    $currentCount = $currentCount ?? 0;
    $maxFiles = $maxFiles ?? 20;
    $percentage = min(100, $maxFiles > 0 ? round(($currentCount / $maxFiles) * 100) : 0);
@endphp
<div class="w-full flex flex-col items-center">
    <div class="w-3/4 bg-gray-200 rounded-full h-4 dark:bg-gray-700">
        <div class="bg-blue-600 h-4 rounded-full transition-all duration-300" style="width: {{ $percentage }}%"></div>
    </div>
    <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">
        Archivos: <span class="font-semibold">{{ $currentCount }}</span> / {{ $maxFiles }}
    </div>
</div>
