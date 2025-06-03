<?php

namespace App\Filament\Pages;

use App\Models\Log;
use Filament\Pages\Page;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;

class ChatAnalytics extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Análisis de Chats';

    protected static string $view = 'filament.pages.chat-analytics';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'desde' => now()->subDays(30)->toDateString(),
            'hasta' => now()->toDateString(),
        ]);

        $this->loadData();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filtros de Fecha')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('desde')
                                    ->label('Desde')
                                    ->required(),
                                DatePicker::make('hasta')
                                    ->label('Hasta')
                                    ->required(),
                            ]),
                    ])
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    public function loadData(): void
    {
        $startDate = $this->form->getState()['desde'];
        $endDate = $this->form->getState()['hasta'];

        // Datos agrupados por IP (usuario)
        $this->userStats = DB::table('logs')
            ->select(
                'ip',
                DB::raw('COUNT(*) as total_messages'),
                DB::raw('COUNT(CASE WHEN role = "user" THEN 1 END) as user_messages'),
                DB::raw('COUNT(CASE WHEN role = "agent" THEN 1 END) as agent_messages'),
                DB::raw('COUNT(DISTINCT agent_code) as unique_agents_used')
            )
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->groupBy('ip')
            ->orderBy('total_messages', 'desc')
            ->limit(10)
            ->get();

        // Datos agrupados por código de agente
        $this->agentStats = DB::table('logs')
            ->select(
                'agent_code',
                DB::raw('COUNT(*) as total_conversations'),
                DB::raw('COUNT(DISTINCT ip) as unique_users')
            )
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->groupBy('agent_code')
            ->orderBy('total_conversations', 'desc')
            ->get();

        // Estadísticas generales
        $this->generalStats = [
            'total_messages' => DB::table('logs')
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->count(),
            'unique_users' => DB::table('logs')
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->distinct('ip')
                ->count('ip'),
            'active_agents' => DB::table('logs')
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->distinct('agent_code')
                ->count('agent_code'),
        ];

        // Datos para gráfico de actividad diaria
        $this->dailyActivity = DB::table('logs')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total')
            )
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Análisis de usuarios únicos por día
        $this->uniqueUsersByDay = DB::table('logs')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(DISTINCT ip) as unique_users')
            )
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    public function submit(): void
    {
        $this->loadData();
    }
}