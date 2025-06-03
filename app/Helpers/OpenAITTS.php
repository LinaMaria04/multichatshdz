<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
// use OpenAI\Laravel\Facades\OpenAI;

class OpenAITTS
{
    public static function getAudioFromText(string $text, string $voice = 'alloy'): ?string
    {
        Log::info('OpenAITTS: Generando audio para texto', ['text' => $text]);
        try {
            $client = app('openai');
            $result = $client->audio()->speech([
                'model' => 'tts-1',
                'input' => $text,
                'voice' => $voice,
                'response_format' => 'mp3',
            ]);
            $filename = 'tts_' . uniqid() . '_' . time() . '.mp3';
            $ttsDir = storage_path('app/public/tts');
            if (!is_dir($ttsDir)) {
                Log::info('OpenAITTS: Creando directorio tts', ['dir' => $ttsDir]);
                mkdir($ttsDir, 0775, true);
            }
            Storage::disk('public')->put('tts/' . $filename, $result); // $result es binario mp3
            Log::info('OpenAITTS: Audio guardado', ['file' => $filename]);
            return Storage::url('tts/' . $filename);
        } catch (\Exception $e) {
            Log::error('OpenAITTS: Error al llamar a la API de OpenAI', ['error' => $e->getMessage()]);
        }
        return null;
    }
}
