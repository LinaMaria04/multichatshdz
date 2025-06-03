<?php

namespace App\Filament\Resources\LogResource\Pages;

use App\Filament\Resources\LogResource;
use App\Models\Log;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\ActionSize;
use Illuminate\Support\Facades\DB;

class ListLogs extends ListRecords
{
    protected static string $resource = LogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportar_informe')
                ->label('Exportar Informe')
                ->color('success')
                ->size(ActionSize::Large)
                ->icon('heroicon-o-document-arrow-down')
                ->action(function (array $data) {
                    // Obtener fechas del filtro actual o usar predeterminadas
                    $startDate = $data['desde'] ?? now()->subDays(30)->toDateString();
                    $endDate = $data['hasta'] ?? now()->toDateString();
                    $groupBy = $data['agrupar_por'] ?? 'ip';

                    return $this->exportReport($startDate, $endDate, $groupBy);
                })
                ->form([
                    \Filament\Forms\Components\DatePicker::make('desde')
                        ->label('Desde')
                        ->default(now()->subDays(30))
                        ->required(),
                    \Filament\Forms\Components\DatePicker::make('hasta')
                        ->label('Hasta')
                        ->default(now())
                        ->required(),
                    \Filament\Forms\Components\Select::make('agrupar_por')
                        ->label('Agrupar por')
                        ->options([
                            'ip' => 'Usuario (IP)',
                            'agent_code' => 'Código de Agente',
                            'summary' => 'Resumen General',
                        ])
                        ->default('ip')
                        ->required(),
                ]),

            // Acción adicional para informe de usuarios únicos
            Action::make('usuarios_unicos')
                ->label('Informe de Usuarios Únicos')
                ->color('primary')
                ->size(ActionSize::Large)
                ->icon('heroicon-o-user-group')
                ->action(function (array $data) {
                    $startDate = $data['desde'] ?? now()->subDays(30)->toDateString();
                    $endDate = $data['hasta'] ?? now()->toDateString();

                    return $this->exportUniqueUsersReport($startDate, $endDate);
                })
                ->form([
                    \Filament\Forms\Components\DatePicker::make('desde')
                        ->label('Desde')
                        ->default(now()->subDays(30))
                        ->required(),
                    \Filament\Forms\Components\DatePicker::make('hasta')
                        ->label('Hasta')
                        ->default(now())
                        ->required(),
                ]),

            // Nuevo botón para informe por IP
            Action::make('informe_ip')
                ->label('Informe de conversaciones')
                ->color(Color::Purple)
                ->size(ActionSize::Large)
                ->icon('heroicon-o-globe-alt')
                ->action(function (array $data) {
                    // Obtener fechas del filtro actual o usar predeterminadas
                    $startDate = $data['desde'] ?? now()->subDays(30)->toDateString();
                    $endDate = $data['hasta'] ?? now()->toDateString();
                    $detailLevel = $data['nivel_detalle'] ?? 'resumen';

                    return $this->exportIPReport($startDate, $endDate, $detailLevel);
                })
                ->form([
                    \Filament\Forms\Components\DatePicker::make('desde')
                        ->label('Desde')
                        ->default(now()->subDays(30))
                        ->required(),
                    \Filament\Forms\Components\DatePicker::make('hasta')
                        ->label('Hasta')
                        ->default(now())
                        ->required(),
                    \Filament\Forms\Components\Select::make('nivel_detalle')
                        ->label('Nivel de Detalle')
                        ->options([
                            'resumen' => 'Resumen por IP',
                            'completo' => 'Detalle completo',
                            'usuarios' => 'Solo usuarios',
                            'agentes' => 'Solo agentes',
                        ])
                        ->default('completo')
                        ->required(),
                ]),
        ];
    }

    protected function exportReport($startDate, $endDate, $groupBy = 'ip')
    {
        // Determinar nombre y columnas según tipo de agrupación
        if ($groupBy === 'ip') {
            $fileName = 'informe_por_usuarios_' . now()->format('Y-m-d') . '.csv';
            $columns = ['Usuario (IP)', 'Total Mensajes', 'Mensajes Usuario', 'Mensajes Agente', 'Agentes Únicos', 'Primera Interacción', 'Última Interacción'];

            // Consulta para obtener datos agrupados por usuario
            $groupedData = DB::table('logs')
                ->select(
                    'ip',
                    DB::raw('COUNT(*) as total_messages'),
                    DB::raw('COUNT(CASE WHEN role = "user" THEN 1 END) as user_messages'),
                    DB::raw('COUNT(CASE WHEN role = "agent" THEN 1 END) as agent_messages'),
                    DB::raw('COUNT(DISTINCT agent_code) as unique_agents'),
                    DB::raw('MIN(created_at) as first_interaction'),
                    DB::raw('MAX(created_at) as last_interaction')
                )
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->groupBy('ip')
                ->orderBy('total_messages', 'desc')
                ->get();

            $callback = function() use($groupedData, $columns) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $columns);

                foreach ($groupedData as $row) {
                    fputcsv($file, [
                        $row->ip,
                        $row->total_messages,
                        $row->user_messages,
                        $row->agent_messages,
                        $row->unique_agents,
                        $row->first_interaction,
                        $row->last_interaction,
                    ]);
                }

                fclose($file);
            };
        }
        elseif ($groupBy === 'agent_code') {
            $fileName = 'informe_por_agentes_' . now()->format('Y-m-d') . '.csv';
            $columns = ['Código de Agente', 'Total Mensajes', 'Usuarios Únicos', 'Primera Interacción', 'Última Interacción'];

            // Consulta para obtener datos agrupados por agente
            $groupedData = DB::table('logs')
                ->select(
                    'agent_code',
                    DB::raw('COUNT(*) as total_messages'),
                    DB::raw('COUNT(DISTINCT ip) as unique_users'),
                    DB::raw('MIN(created_at) as first_interaction'),
                    DB::raw('MAX(created_at) as last_interaction')
                )
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->groupBy('agent_code')
                ->orderBy('total_messages', 'desc')
                ->get();

            $callback = function() use($groupedData, $columns) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $columns);

                foreach ($groupedData as $row) {
                    fputcsv($file, [
                        $row->agent_code,
                        $row->total_messages,
                        $row->unique_users,
                        $row->first_interaction,
                        $row->last_interaction,
                    ]);
                }

                fclose($file);
            };
        }
        else {
            // Resumen general
            $fileName = 'resumen_general_' . now()->format('Y-m-d') . '.csv';
            $columns = ['Período', 'Total Mensajes', 'Usuarios Únicos', 'Agentes Únicos'];

            // Obtener estadísticas generales
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

            $periodo = $startDate . ' al ' . $endDate;

            $callback = function() use($columns, $periodo, $totalMessages, $uniqueUsers, $uniqueAgents) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $columns);
                fputcsv($file, [$periodo, $totalMessages, $uniqueUsers, $uniqueAgents]);
                fclose($file);
            };
        }

        // Crear archivo CSV
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        return response()->stream($callback, 200, $headers);
    }

    protected function exportUniqueUsersReport($startDate, $endDate)
    {
        // Informe detallado de usuarios únicos por día
        $fileName = 'usuarios_unicos_por_dia_' . now()->format('Y-m-d') . '.csv';
        $columns = ['Fecha', 'Usuarios Únicos', 'Total Mensajes', 'Agentes Activos'];

        // Consulta para obtener usuarios únicos por día
        $dailyData = DB::table('logs')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(DISTINCT ip) as unique_users'),
                DB::raw('COUNT(*) as total_messages'),
                DB::raw('COUNT(DISTINCT agent_code) as active_agents')
            )
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Crear archivo CSV
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $callback = function() use($dailyData, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($dailyData as $row) {
                fputcsv($file, [
                    $row->date,
                    $row->unique_users,
                    $row->total_messages,
                    $row->active_agents,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Nuevo método para el informe por IP
    protected function exportIPReport(string $startDate, string $endDate, string $detailLevel)
    {
        // Iniciar la consulta base
        $query = Log::query()
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

        // Filtrar por rol si es necesario
        if ($detailLevel === 'usuarios') {
            $query->where('role', 'user');
        } elseif ($detailLevel === 'agentes') {
            $query->where('role', 'agent');
        }

        // Si es un resumen, agrupar por IP y obtener métricas
        if ($detailLevel === 'resumen') {
            $data = $query->select([
                'ip',
                DB::raw('COUNT(DISTINCT agent_code) as conversation_count'),
                DB::raw('COUNT(*) as message_count'),
                DB::raw('MIN(created_at) as first_message'),
                DB::raw('MAX(created_at) as last_message')
            ])
            ->groupBy('ip')
            ->get();

            // Preparar nombre del archivo con fecha actual
            $filename = 'informe_ips_' . now()->format('Y-m-d') . '.csv';

            // Cabeceras para el CSV
            $headers = [
                'IP', 'Total Conversaciones', 'Total Mensajes', 'Primera Interacción', 'Última Interacción'
            ];

            // Convertir a formato para CSV
            $rows = $data->map(function ($item) {
                return [
                    $item->ip,
                    $item->conversation_count,
                    $item->message_count,
                    $item->first_message,
                    $item->last_message,
                ];
            });
        } else {
            // Para informes detallados, seleccionar todos los campos relevantes
            $data = $query->select([
                'ip', // IP primero
                'created_at', // Fecha/Hora segundo
                'agent_code',
                'role',
                'content',
                // Otros campos según necesites
            ])
            ->orderBy('ip') // Ordenar primero por IP
            ->orderBy('created_at') // Luego por fecha
            ->get();

            // Preparar nombre del archivo
            $filename = 'informe_ips_detallado_' . now()->format('Y-m-d') . '.csv';

            // Cabeceras para el CSV
            $headers = [
                'IP', 'Fecha/Hora', 'Código Agente', 'Rol', 'Contenido' // IP primero, Fecha/Hora segundo
            ];

            // Convertir a formato para CSV
            $rows = $data->map(function ($item) {
                return [
                    $item->ip, // IP primero
                    $item->created_at, // Fecha/Hora segundo
                    $item->agent_code,
                    $item->role,
                    $item->content,
                ];
            });
        }

        // Crear el CSV y devolverlo como descarga
        $callback = function() use ($headers, $rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach ($rows as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}