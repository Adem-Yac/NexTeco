<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftChange extends Model
{
    use SoftDeletes;
    
    protected $fillable = ['user_type', 'user_id', 'shift_id', 'date_debut'];

    public function user()
    {
        return $this->morphTo(); // Employe ou Grh
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
