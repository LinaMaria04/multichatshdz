<?php

namespace App\Filament\Resources\ChatResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Crypt;


class BBDDRelationManager extends RelationManager{

    protected static string $relationship = 'databaseConnection';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('tipo_conector') 
                    ->label('Tipo de Conector (BBDD)') 
                    ->options([
                        'mysql' => 'MySQL',
                        'postgresql' => 'PostgreSQL',
                        'sqlite' => 'SQLite',
                        'sqlserver' => 'SQL Server',
                    ])
                    ->required()
                    ->reactive() // Formulario reaccione a los cambios
                    ->afterStateUpdated(fn ($state, callable $set) => $set('database', null)) // Limpia la base de datos seleccionada si se cambia el tipo de conector
                    ->default('mysql'), 

                TextInput::make('ip_host') 
                    ->label('IP/Host') 
                    ->required()
                    ->maxLength(255), 

                TextInput::make('port')
                    ->label('Puerto')
                    ->numeric()
                    ->default(3306) //Puerto por defecto para mysql
                    ->required(),    

                TextInput::make('usuario') 
                    ->label('Usuario') 
                    ->required()
                    ->maxLength(255),

                TextInput::make('password') 
                    ->password()
                    ->label('Contraseña') 
                    ->required()
                    // También podriamos usar Crypt::encryptString(), permite desencriptar la contraseña
                    ->dehydrateStateUsing(fn (string $state): string => Crypt::encryptString($state))
                    ->dehydrated(fn (?string $state): bool => filled($state)), 
                    

                // Acción para probar la conexión y listar las tablas de la bases de datos
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('prueba_conexion')
                        ->label('Probar Conexión y Listar Bases de Datos')
                        ->color('primary')
                        ->icon('heroicon-o-server')
                        ->action(function ($state, callable $set) { // Recibe `$state` (todos los datos del formulario) y `$set` (función para actualizar el estado de otros campos).
                            $host = $state['ip_host'] ?? null;
                            $user = $state['usuario'] ?? null;
                            $password = $state['password'] ?? null; // Contraseña en texto plano tal como la escribió el usuario
                            $port = $state['port'] ?? 3306; //Puerto por defecto para mysql
                            $connectorType = $state['tipo_conector'] ?? null;

                            // Validaciones de campos con datos
                            if (!$host || !$user || !$password || !$connectorType) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Información Requerida')
                                    ->body('Por favor, ingresa el IP/Host, Usuario y Contraseña para continuar.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            //Validación para la conexión con MYSQL, debo de agregar las validaciones para los otros tipos de conexion
                            if ($connectorType !== 'mysql') {
                                \Filament\Notifications\Notification::make()
                                    ->title('Conector No Soportado')
                                    ->body('Actualmente, solo se soporta MySQL para la prueba de conexión.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            try {
                                // Establecer la conexión PDO para MySQL, el DSN para PDO es "mysql:host=..."
                                $pdo = new \PDO("mysql:host=$host;port=$port", $user, $password);
                                // Consulta para mostrar las tablas de la base de datos
                                $stmt = $pdo->query('SHOW DATABASES'); 
                                $databases = $stmt->fetchAll(\PDO::FETCH_COLUMN); //Obtiene los reultados como un arreglo de columnas

                                // Filtrar bases de datos del sistema
                                $databases = array_filter($databases, function ($db) {
                                    return !in_array($db, ['information_schema', 'mysql', 'performance_schema', 'sys']);
                                });

                                // Actualizar el estado del formulario con las BBDD disponibles
                                $set('available_databases', $databases);
                                $set('connection_status', 'Conexión exitosa. Selecciona una base de datos de la lista.');
                                \Filament\Notifications\Notification::make()
                                    ->title('Conexión Exitosa')
                                    ->body('Se han listado las bases de datos disponibles.')
                                    ->success()
                                    ->send();

                            } catch (\PDOException $e) {
                                // Manejo de errores de conexión
                                $set('available_databases', []); // Limpia las BBDD si hay error
                                $set('connection_status', 'Error de conexión: ' . $e->getMessage());
                                \Filament\Notifications\Notification::make()
                                    ->title('Error de Conexión')
                                    ->body('No se pudo establecer la conexión con la base de datos: ' . $e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                ]),

                // Campo para mostrar el estado de la conexión
                Textarea::make('connection_status')
                    ->label('Estado de la Conexión')
                    ->rows(2)
                    ->disabled()
                    ->hidden(fn (callable $get) => empty($get('connection_status'))), //Permanece oculto hasta que se establezca la conexión

                // Campo para seleccionar la Base de Datos
                Select::make('database') 
                    ->label('Base de Datos') 
                    // Las opciones provienen del estado 'available_databases' llenado por la acción del botón
                    ->options(fn (callable $get) => array_combine($get('available_databases') ?? [], $get('available_databases') ?? []))
                    ->searchable()
                    ->required()
                    ->reactive() 
                    ->afterStateUpdated(fn ($state, callable $set) => $set('select_tables', [])) 
                    ->hidden(fn (callable $get) => empty($get('available_databases'))), //Permanece oculto hasta que se establezca la conexión

                // Campo para seleccionar las Tablas de la base de datos
                Select::make('select_tables') 
                    ->label('Tablas a seleccionar') 
                    ->multiple() //Permite seleccionar múltiples tablas
                    ->options(function (callable $get) {
                        // Obtener los valores de los campos necesarios para mostrar las tablas
                        $host = $get('ip_host');
                        $user = $get('usuario');
                        $password = $get('password');
                        $port = $get('port'); //Obtiene el puerto
                        $database = $get('database'); // La base de datos seleccionada
                        $connectorType = $get('tipo_conector');

                        // Validaciones antes de intentar mostrar tablas
                        if (empty($host) || empty($user) || empty($password) || empty($database) || $connectorType !== 'mysql') {
                            return [];
                        }

                        try {
                            // Conectar a la base de datos y mostrar tablas
                            $pdo = new \PDO("mysql:host=$host;port=$port;dbname=$database", $user, $password);
                            $stmt = $pdo->query('SHOW TABLES');
                            $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);//Obtiene los reultados como un arreglo de columnas
                            return array_combine($tables, $tables);
                        } catch (\PDOException $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error al listar las tablas')
                                ->body('No se pudieron obtener las tablas de la base de datos: ' . $e->getMessage())
                                ->danger()
                                ->send();
                            return [];
                        }
                    })
                    ->searchable()
                    // Se oculta hasta que se haya seleccionado una base de datos
                    ->hidden(fn (callable $get) => empty($get('database'))),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tipo_conector')
            ->columns([
                Tables\Columns\TextColumn::make('tipo_conector') 
                    ->label('Tipo de Conector (BBDD)') 
                    ->searchable(),
                Tables\Columns\TextColumn::make('ip_host') 
                    ->label('IP/Host') 
                    ->searchable(),
                Tables\Columns\TextColumn::make('port')
                    ->label('Puerto')
                    ->searchable(),    
                Tables\Columns\TextColumn::make('usuario') 
                    ->label('Usuario') 
                    ->searchable(),
                // No se muestra la contraseña por seguridad

                Tables\Columns\TextColumn::make('database') 
                    ->label('Base de Datos') 
                    ->searchable(),
                Tables\Columns\TextColumn::make('select_tables') 
                    ->label('Tablas Seleccionadas') 
                    ->listWithLineBreaks() // Muestra cada tabla en una línea separada
                    ->badge(), // Muestra cada tabla como un "badge" 
            ])
            ->filters([
                // ¿Vamos a añadir filtors? 
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
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
}