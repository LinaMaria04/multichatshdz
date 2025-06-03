<?php

namespace App\Filament\Resources\ChatResource\RelationManagers\Components;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Concerns\EvaluatesClosures;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class PdfViewerModal extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    use EvaluatesClosures;

    public $filePath;
    public $isOpen = false;

    protected $listeners = [
        'openPdfViewer' => 'openViewer',
    ];

    public function openViewer($filePath)
    {
        $this->filePath = Storage::disk('public')->url($filePath);
        $this->isOpen = true;
    }

    public function closeViewer()
    {
        $this->isOpen = false;
    }

    public function render()
    {
        return view('filament.resources.chat-resource.relation-managers.components.pdf-viewer-modal');
    }
}