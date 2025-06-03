<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationLabel = 'Usuarios';

    // Usar la policy
//    protected static string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        $isCreating = $form->getOperation() === 'create';
        $user = auth()->user();

        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->label('Nombre'),

                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->label('Email'),

                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->required($isCreating)
                    ->label('Contraseña'),

                Forms\Components\Select::make('role')
                    ->options(function () use ($user) {
                        if ($user->isSuperAdmin()) {
                            return [
                                'superadmin' => 'Super Administrador',
                                'admin' => 'Administrador',
                                'user' => 'Usuario',
                            ];
                        }

                        return [
                            'admin' => 'Administrador',
                            'user' => 'Usuario',
                        ];
                    })
                    ->default('user')
                    ->required()
                    ->label('Rol')
                    ->visible(fn () => $user->can('changeRole', $form->getRecord() ?? new User())),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label('Nombre'),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->label('Email'),

                Tables\Columns\BadgeColumn::make('role')
                    ->colors([
                        'primary' => 'user',
                        'success' => 'admin',
                        'danger' => 'superadmin',
                    ])
                    ->label('Rol'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Creado el'),
            ])
        ->filters([
            Tables\Filters\SelectFilter::make('role')
                ->options([
                    'superadmin' => 'Super Administrador',
                    'admin' => 'Administrador',
                    'user' => 'Usuario',
                ])
                ->label('Filtrar por rol'),
        ], layout: Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Si el usuario no es superadmin, no mostrar superadmins
        if (!auth()->user()->isSuperAdmin()) {
            $query->where('role', '!=', 'superadmin');
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

   // Este método controla si el recurso es visible en la navegación
    public static function canAccess(): bool
    {
        $user = auth()->user();

        // Solo mostrar para superadmin y admin
        return $user->role === 'superadmin' || $user->role === 'admin';
    }

    // También podemos controlar más específicamente si puede verse en la navegación
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}