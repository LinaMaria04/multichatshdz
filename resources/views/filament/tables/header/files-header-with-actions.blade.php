@php
    $usados = $count;
    $maximos = $max;
    $restantes = $max - $count;
    $porcentaje = $maximos > 0 ? intval(($usados / $maximos) * 100) : 0;
    $color = $porcentaje < 70 ? 'bg-success-600' : ($porcentaje < 90 ? 'bg-warning-500' : 'bg-danger-600');
@endphp

<div class="flex flex-col gap-1 w-full max-w-xs">
    <div class="flex justify-between text-xs text-gray-600 gap-3">
        <span>Archivos usados: <b>{{ $usados }}</b></span>
        <span>Máximo: <b>{{ $maximos }}</b></span>
        <span>Restantes: <b>{{ $restantes }}</b></span>
    </div>
    <div class="w-full h-3 bg-gray-200 rounded">
        <div class="h-3 rounded transition-all duration-300 {{ $color }}" style="width: {{ $porcentaje }}%"></div>
    </div>
    <div class="text-xs text-right text-gray-500 mt-1">
        {{ $porcentaje }}% del espacio usado
    </div>
</div>
