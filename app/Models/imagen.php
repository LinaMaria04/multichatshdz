<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class imagen extends Model
{
    protected $table = 'imagen';
    
    protected $fillable = [
        'chat_id',
        'path',
        'nameimage',
        'description'
    ];
    
    /**
     * Obtener el chat al que pertenece esta imagen.
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }
}