<?php

namespace App\Services;

use App\Models\DatabaseConnection;
use Illuminate\Container\Attributes\Database;
use PDO;
use PDOException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class DatabaseConnector{
    /**
     * Establece una conexión PDO con la base de datos.
     *
     * @param DatabaseConnection $dbConnection El modelo DatabaseConnection.
     * @param string|null $dbName El nombre de la base de datos específica si no es la principal.
     * @return PDO|null Retorna una instancia de PDO o null si falla la conexión.
     */

     public function connect(DatabaseConnection $dbConnection, string $dbName = null): ?PDO{

        $host = $dbConnection->ip_host;
        $port = $dbConnection->port;
        $user = $dbConnection->usuario;
        $password = Crypt::decryptString($dbConnection->password); // Desencripta la contraseña almacenada

        $dsn = "mysql:host={$host}; port={$port}";
        if ($dbName){
            $dsn .= ";dbname={$dbName}";
        }

        try{
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, //Muestra las excepciones en caso de error
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //FETCH_ASSOC por defecto
            ]);
            return $pdo;
        } catch (PDOException $e){
            Log::error("Error en la conexión a la base de datos {$host}:{$port}/{$dbName} -". $e->getMessage());
            return null;
        }
     }

     /**
     * Obtiene el esquema (columnas y tipos) de las tablas seleccionadas.
     *
     * @param DatabaseConnection $dbConnection El modelo DatabaseConnection.
     * @return array Un array con el esquema de las tablas.
     */
    public function getSchema(DatabaseConnection $dbConnection): array{
        if(!in_array($dbConnection->tipo_conector, ['mysql'])){ // Se deben de agregar también las otras conexiones
            Log::warning("Introspección de esquemo no soportada por el conector: " . $dbConnection->tipo_conector);
            return[];
        }

        $dbName = $dbConnection->database; //Nombre de la base de datos seleccionada
        $selectedTables = $dbConnection->select_tables; //Arreglo con el nombre de las tablas

        $pdo = $this->connect($dbConnection, $dbName); //Conectar a la base de datoss especiifca
        if(!$pdo){
            return [];
        }

        $schema = [];
        foreach($selectedTables as $tableName){
            try {
                // Consulta para obtener la estructura de la tabla
                $stmt = $pdo->query("SHOW COLUMNS FROM `{$tableName}`");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $tableSchema = [
                    'name' => $tableName,
                    'columns' => [],
                ];

                foreach ($columns as $column){
                    $tableSchema['columns'][] = [
                        'name' => $column['Field'],
                        'type' => $column['Type'],
                        'nullable' => $column['Null'] === 'YES'
                    ];
                }
                $schema[] = $tableSchema;

            } catch (PDOException $e){
                Log::error("Error al obtener el esquema de la tabla '{$tableName}' en la base de datos '{$dbName}': " . $e->getMessage());
            }
        }
        return $schema;
    }

     /**
     * Obtiene el esquema detallado de la base de datos utilizando INFORMATION_SCHEMA.COLUMNS.
     *
     * @param DatabaseConnection $dbConnection El modelo DatabaseConnection.
     * @return array Un array con el esquema detallado.
     */
    public function getDetailedSchema(DatabaseConnection $dbConnection): array{

        if(!in_array($dbConnection->tipo_conector, ['mysql'])){
            Log::warning("La ejecución del esquema detallado no soportada por el conector: " . $dbConnection->tipo_conector);
            return [];
        }

        $dbName = $dbConnection->database; //Nombre de la base de datos seleccionada
        $pdo = $this->connect($dbConnection, $dbName); //Conectar a la base de datos especifica

        if(!$pdo){
            return []; 
        }

        $sql = " 
            SELECT
                TABLE_NAME as 'Tabla',
                COLUMN_NAME as 'Campo',
                ORDINAL_POSITION as 'Posicion',
                COLUMN_DEFAULT as 'ValorPorDefecto',
                DATA_TYPE as 'TipoDeDato',
                IS_NULLABLE as 'PermiteNULL',
                CHARACTER_MAXIMUM_LENGTH as 'LongitudMaxima',
                CHARACTER_OCTET_LENGTH as 'LongitudEnBytes',
                NUMERIC_PRECISION as 'PrecisionNumerica',
                NUMERIC_SCALE as 'EscalaNumerica',
                DATETIME_PRECISION as 'PrecisionDateTime',
                CHARACTER_SET_NAME as 'ConjuntoCaracteres',
                COLLATION_NAME as 'Intercalacion',
                COLUMN_TYPE as 'TipoDeColumna',
                EXTRA as 'InformacionExtra',
                COLUMN_COMMENT as 'Comentario',
                COLUMN_KEY as 'TipoDeClave'
                
            FROM
                INFORMATION_SCHEMA.COLUMNS
            WHERE
                TABLE_SCHEMA = :db_name
            ORDER BY
                TABLE_NAME, ORDINAL_POSITION;
        ";

        try {
            //Preparación de consulta para recibir parámetros con nombre
            $stmt = $pdo->prepare($sql);
            //Asignamos el nombre de la base de datos a la variable _db_name
            $stmt->bindParam(':db_name', $dbName, PDO::PARAM_STR);
            $stmt->execute();

            $rawColumnsData = $stmt->fetchAll(PDO::FETCH_ASSOC); // Obtener todos los datos devueltos por la consulta

            //Construcción del esquema detallado
            $detailedSchema = [];

            foreach ($rawColumnsData as $columnInfo) {
                $tableName = $columnInfo['Tabla'];
                $columnName = $columnInfo['Campo'];
                $dataType = $columnInfo['TipoDeDato'];
                $isNullable = $columnInfo['PermiteNULL'];
                $columnKey = $columnInfo['TipoDeClave']; // 'PRI', 'UNI', 'MUL' o vacío

                // Puedes decidir qué nivel de detalle incluir aquí.
                // Para el LLM, 'TipoDeDato' y 'TipoDeClave' (PRI/MUL para FK) suelen ser suficientes.
                $detailedSchema[$tableName][$columnName] = [
                    'type' => $dataType,
                    'nullable' => $isNullable,
                    'key' => $columnKey, // Añade información sobre la clave
                    // Puedes añadir más si tu LLM lo necesita, por ejemplo:
                    // 'default' => $columnInfo['ValorPorDefecto'],
                    // 'extra' => $columnInfo['InformacionExtra'],
                    // 'comment' => $columnInfo['Comentario'],
                ];
            }

            Log::debug('DEBUG DB_CONNECTOR: Esquema detallado obtenido para DB ID ' . $dbConnection->id . ' y nombre ' . $dbName, $detailedSchema);

            return $detailedSchema;


        } catch (PDOException $e){
            Log::error("Error al obtener el esquema detallado de la base de datos '{$dbName}': " . $e->getMessage());
            return [];
        }
    }

     /**
     * Ejecuta una consulta SQL en la base de datos.
     *
     * @param DatabaseConnection $dbConnection El modelo DatabaseConnection.
     * @param string $sql La consulta SQL a ejecutar.
     * @return array|null Los resultados de la consulta o null en caso de error.
     */

    public function executeQuery(DatabaseConnection $dbConnection, string $sql): ?array {

        $dbName = $dbConnection->database;
        $pdo = $this->connect($dbConnection, $dbName);
        if (!$pdo){
            return null;
        }

        try {
            $stmt = $pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e){
            Log::error("Error al ejecutar la consulta SQL en la base de datos '{$dbName}': {$sql} - " . $e->getMessage());
            return null;
        }
    } 
}
