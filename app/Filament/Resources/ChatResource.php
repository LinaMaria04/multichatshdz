<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChatResource\Pages;
use App\Filament\Resources\ChatResource\RelationManagers;
use App\Models\Chat;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class ChatResource extends Resource
{
    protected static ?string $model = Chat::class;

//    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Hidden::make('code')
                    ->required()
                    ->default(function () {
                        return Str::lower(Str::random(5));
                    }),

                Select::make('agent_id')
                    ->label('Agente Virtual')
                    ->required()
                    ->relationship('agent', 'name'),

                Select::make('model_id')
                    ->label('Modelo IA de OpenAI')
                    ->required()
                    ->relationship('model', 'name'),

                TextInput::make('name')
                    ->label('Nombre del Chat')
                    ->required()
                    ->maxLength(255),
                TextInput::make('header')
                    ->label('Texto cabecera del chat')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('prompt')
                    ->label('Prompt IA')
                    ->autosize()
                    ->rows(10)
                    ->columnSpanFull(),
                Fieldset::make('URL para indexar periódicamente')
                    ->schema([
                        TextInput::make('fetch_url')
                            ->label('URL para obtener datos')
                            ->url()
                            ->required(fn ($record) => !empty($record->fetch_periodicity))
                            ->placeholder('https://example.com/data.json')
                            ->helperText('Introduce la URL completa que proporciona datos (json, rss, html.)')
                            ->maxLength(255)->columnSpan(3),
                        Select::make('fetch_periodicity')
                            ->label('Periodicidad (horas)')
                            ->helperText('Introduce cada cuántas horas se debe actualizar la información')
                            ->options([
                                '8' => '8',
                                '12' => '12',
                                '24' => '24',
                                '48' => '48',
                                '72' => '72',
                            ]),
                        DateTimePicker::make('last_fetch_execution')
                            ->label('Última ejecución')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(4)
                    ->visible(fn ($record) => (bool) $record?->show_url_to_index === true) // Condición de visibilidad
            ])->columns(4);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('agent.name')->label('Agente')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('chat')
                    ->label('Enlace al chat')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->url(fn (Chat $chat): string => config('app.url') . '/chat/' . $chat->code )
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make()
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
            RelationManagers\FilesRelationManager::class,
            RelationManagers\ImageRelationManager::class,
            RelationManagers\BBDDRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChats::route('/'),
            'create' => Pages\CreateChat::route('/create'),
            'edit' => Pages\EditChat::route('/{record}/edit'),
        ];
    }

}
