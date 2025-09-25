<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class dispositif_biometrique extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ip',
        'port',
        'version',
        'status',
    ];

    public function presences()
    {
        return $this->hasMany(Presence::class, 'dispositif_id');
    }
}

