<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employe extends Authenticatable implements FilamentUser
{
    use HasFactory, HasApiTokens, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'prenom',
        'biometric_id',
        'salaire',
        'salaire_mensuel',
        'poste',
        'departement_id',
        'shift_id',
        'email',
        'adresse',
        'Numero_telephone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function departement()
    {
        return $this->belongsTo(Departement::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function shiftChanges()
    {
        return $this->morphMany(ShiftChange::class, 'user');
    }

    public function shiftLe($date)
    {
        return $this->shiftChanges()
            ->where('date_debut', '<=', $date)
            ->orderByDesc('date_debut')
            ->first()?->shift ?? $this->shift;
    }

    public function demandeConges()
    {
        return $this->hasMany(demande_conge::class);
    }

    public function reclamations()
    {
        return $this->hasMany(reclamations::class);
    }

    public function presences()
    {
        return $this->hasMany(Presence::class);
    }

    public function ficheDePaies()
    {
        return $this->hasMany(fiche_de_paie::class);
    }

    public function primes()
    {
        return $this->morphMany(Prime::class, 'user');
    }

    public function avances()
    {
        return $this->morphMany(Avance::class, 'user');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
