<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prime extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'user_type',
        'user_id',
        'mois',
        'montant',
        'description',
    ];

    public function user()
    {
        return $this->morphTo();
    }

}
