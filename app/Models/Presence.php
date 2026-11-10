<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presence extends Model
{
    public const STATUTS = [
        'present' => 'Présent',
        'retard' => 'En retard',
        'absent' => 'Absent',
        'absence_justifiee' => 'Absence justifiée',
        'mission' => 'En mission',
    ];

    protected $fillable = [
        'agent_id', 'etablissement_id', 'date', 'heure_arrivee', 'heure_depart', 'statut', 'mode',
        'latitude', 'longitude', 'validation', 'saisie_par', 'validee_par', 'date_validation', 'observations',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'date_validation' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected $appends = ['statut_libelle'];

    public function getStatutLibelleAttribute(): string
    {
        return self::STATUTS[$this->statut] ?? (string) $this->statut;
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class)->withTrashed();
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function valideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par');
    }
}
