<?php

namespace App\Filament\Resources\ChatResource\RelationManagers\Components;

use Filament\Forms\Components\Component;
use Illuminate\View\Component as ViewComponent;
use Illuminate\Contracts\View\View;

class FilesUsageIndicator extends ViewComponent
{
    public $currentCount;
    public $maxFiles;

    /**
     * Create the component instance.
     *
     * @param  int  $currentCount
     * @param  int  $maxFiles
     * @return void
     */
    public function __construct($currentCount, $maxFiles)
    {
        $this->currentCount = $currentCount;
        $this->maxFiles = $maxFiles;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function render(): View
    {
        $percentage = ($this->currentCount / $this->maxFiles) * 100;

        // Determinar el color de la barra de progreso según el porcentaje
        $progressColor = 'bg-green-500';
        if ($percentage >= 80) {
            $progressColor = 'bg-red-500';
        } elseif ($percentage >= 50) {
            $progressColor = 'bg-yellow-500';
        }

        return view('components.files-usage-indicator', [
            'currentCount' => $this->currentCount,
            'maxFiles' => $this->maxFiles,
            'percentage' => $percentage,
            'progressColor' => $progressColor,
            'remainingFiles' => $this->maxFiles - $this->currentCount
        ]);
    }
}