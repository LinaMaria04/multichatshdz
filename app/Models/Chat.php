<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',    
        'name',
        'prompt',
        'user_id',
        'assistant_id',
        'vectorstore_id',
        'agent_id',
        'model_id',
        'header',
        'provider',
        'providername',
    ];

    protected $casts = [
        'last_fetch_execution' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function files()
    {
        return $this->hasMany(Files::class);
    }

    public function imagen()
    {
        return $this->hasMany(imagen::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function model()
    {
        return $this->belongsTo(AIModel::class);
    }

    public function shouldExecuteFetch(): bool
    {
        // Verificar que existan los campos necesarios
        if (!$this->fetch_url || !$this->fetch_periodicity || !$this->assistant_id) {
            return false;
        }

        // Si nunca se ha ejecutado, debe ejecutarse
        if (!$this->last_fetch_execution) {
            return true;
        }

        // Obtener las horas como un entero
        $hours = (int) $this->fetch_periodicity;

        // Calcular cuándo debe ejecutarse la próxima actualización
        $nextExecution = $this->last_fetch_execution->addHours($hours);

        // Determinar si ya pasó el tiempo para la siguiente ejecución
        return now()->greaterThanOrEqualTo($nextExecution);
    }
}
