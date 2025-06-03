<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanOldTtsAudios extends Command
{
    protected $signature = 'tts:clean-old {--days=1 : Días de antigüedad}';
    protected $description = 'Elimina archivos de audio TTS antiguos';

    public function handle()
    {
        $days = (int) $this->option('days');
        $files = Storage::disk('public')->files('tts');
        $now = now();
        $deleted = 0;
        foreach ($files as $file) {
            $fullPath = storage_path('app/public/' . $file);
            if (file_exists($fullPath)) {
                $lastModified = \Carbon\Carbon::createFromTimestamp(filemtime($fullPath));
                if ($lastModified->diffInDays($now) >= $days) {
                    Storage::disk('public')->delete($file);
                    $deleted++;
                }
            }
        }
        $this->info("Eliminados $deleted archivos de audio TTS con más de $days días.");
        return Command::SUCCESS;
    }
}
