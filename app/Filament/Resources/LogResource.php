<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogResource\Pages;
use App\Filament\Resources\LogResource\RelationManagers;
use App\Models\Chat;
use App\Models\Log;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;

class LogResource extends Resource
{
    protected static ?string $model = Log::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('ip')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('url')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('user_agent')
                    ->maxLength(255),
                Forms\Components\TextInput::make('agent_code')
                    ->maxLength(255),
                Forms\Components\TextInput::make('role')
                    ->required(),
                Forms\Components\Textarea::make('content')
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('timestamp')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('timestamp')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip')
                    ->searchable()
                    ->summarize(
                        Tables\Columns\Summarizers\Count::make()
                            ->label('Usuarios Únicos')
                            ->query(fn ($query) => $query->distinct('ip'))
                    ),

                Tables\Columns\TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'user' => 'info',
                        'agent' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('agent_code')
                    ->searchable()
                    ->summarize(
                        Tables\Columns\Summarizers\Count::make()
                            ->label('Agentes Únicos')
                            ->query(fn ($query) => $query->distinct('agent_code'))
                    ),
                Tables\Columns\TextColumn::make('content')
                     ->limitWithTooltip(40),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // Columna para contar mensajes (aparecerá en el resumen de grupo)
                Tables\Columns\TextColumn::make('message_count')
                    ->label('Total Mensajes')
                    ->state(fn () => 1)
                    ->summarize(Tables\Columns\Summarizers\Sum::make())
                    ->visible(false),
            ])

            ->defaultSort('created_at', 'desc')

            ->filters([
                SelectFilter::make('Role')
                    ->options([
                        'user' => 'User',
                        'agent' => 'Agent'
                    ]),

                SelectFilter::make('agent_code')
                    ->label('Agent Name')
                    ->options(
                        Chat::query()->distinct('code')->orderBy('name')->pluck('name', 'code')),
                DateRangeFilter::make('timestamp')->useRangeLabels(),
            ],  layout: FiltersLayout::AboveContent)

            ->groups([
                Group::make('ip')
                    ->label('Usuario (IP)')
                    ->collapsible(),
                Group::make('agent_code')
                    ->label('Código de Agente')
                    ->collapsible(),
                Group::make('created_at')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->collapsible(),
                // Agrupar por período (usamos un grupo estándar)
                Group::make('periodo')
                    ->label('Período')
                    ->date('d/m/Y')
                    ->collapsible(),
            ])

            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLogs::route('/'),
            'create' => Pages\CreateLog::route('/create'),
            'edit' => Pages\EditLog::route('/{record}/edit'),
            'list' => Pages\ListLogs::route('/list'),
        ];
    }

    // Método adicional para crear informes detallados
    public static function getGroupedReportData($startDate, $endDate, $groupBy = 'ip')
    {
        // Base de la consulta
        $query = DB::table('logs')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

        // Agrupar por IP (usuarios)
        if ($groupBy === 'ip') {
            return $query->select(
                'ip',
                DB::raw('COUNT(*) as total_messages'),
                DB::raw('COUNT(DISTINCT agent_code) as unique_agents'),
                DB::raw('MIN(created_at) as first_interaction'),
                DB::raw('MAX(created_at) as last_interaction')
            )
            ->groupBy('ip')
            ->orderBy('total_messages', 'desc')
            ->get();
        }

        // Agrupar por agente
        if ($groupBy === 'agent_code') {
            return $query->select(
                'agent_code',
                DB::raw('COUNT(*) as total_messages'),
                DB::raw('COUNT(DISTINCT ip) as unique_users'),
                DB::raw('MIN(created_at) as first_interaction'),
                DB::raw('MAX(created_at) as last_interaction')
            )
            ->groupBy('agent_code')
            ->orderBy('total_messages', 'desc')
            ->get();
        }

        // Resumen general (sin agrupar)
        return [
            'total_messages' => $query->count(),
            'unique_users' => $query->distinct('ip')->count('ip'),
            'unique_agents' => $query->distinct('agent_code')->count('agent_code'),
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ];
    }
}
