<?php

namespace App\Filament\Widgets;

use App\Models\Log;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ChatStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected static ?string $panel = 'admin';

    protected function getStats(): array
    {
        // Definir período (últimos 30 días por defecto)
        $startDate = now()->subDays(30)->toDateString();
        $endDate = now()->toDateString();

        // Obtener estadísticas básicas
        $totalMessages = DB::table('logs')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->count();

        $uniqueUsers = DB::table('logs')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->distinct('ip')
            ->count('ip');

        $uniqueAgents = DB::table('logs')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->distinct('agent_code')
            ->count('agent_code');

        // Obtener promedio de mensajes por usuario
        $avgMessages = 0;
        if ($uniqueUsers > 0) {
            $avgMessages = round($totalMessages / $uniqueUsers, 1);
        }

        return [
            Stat::make('Total de Mensajes', number_format($totalMessages))
                ->description('En los últimos 30 días')
                ->descriptionIcon('heroicon-o-chat-bubble-bottom-center-text')
                ->color('primary'),

            Stat::make('Usuarios Únicos', number_format($uniqueUsers))
                ->description('En los últimos 30 días')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('success'),

            Stat::make('Promedio de Mensajes', number_format($avgMessages))
                ->description('Por usuario')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('warning'),

            Stat::make('Agentes Activos', number_format($uniqueAgents))
                ->description('En los últimos 30 días')
                ->descriptionIcon('heroicon-o-computer-desktop')
                ->color('danger'),
        ];
    }
}