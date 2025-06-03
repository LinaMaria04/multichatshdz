<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite('resources/css/app.css')
    </head>
    <body class="bg-gray-100">
        {{ $slot }}
        @vite('resources/js/app.js') {{-- Assuming you want the main app JS --}}
        @livewireScripts {{-- Make sure Livewire scripts are included --}}
        @stack('scripts') {{-- Add stack for scripts --}}
    </body>
</html>
