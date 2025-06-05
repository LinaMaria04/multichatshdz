<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatabaseConnection extends Model
{

    protected $table = '_database_connection';
    protected $fillable = [
        'chat_id',
        'tipo_conector',
        'ip_host',
        'usuario',
        'password',
        'database',
        'select_tables',
    ];

    protected $casts = [
        'select_tables' => 'array', // Asegura que select_tables se maneje como un array
    ];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }
}
