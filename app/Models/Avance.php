<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Avance extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'user_type',
        'user_id',
        'mois',
        'montant',
        'motif',
    ];

    
    public function user()
{
    return $this->morphTo();
}

}
