<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Files extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'file_id',
        'filename',
        'filename_description',
        'description'
    ];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }
}
