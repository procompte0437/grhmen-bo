<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebutCours extends Model
{
    public const STATUTS = [
        'declare' => 'Déclaré, à confirmer',
        'confirme' => 'Confirmé',
        'rejete' => 'Rejeté',
    ];

    protected $table = 'debuts_cours';

    protected $fillable = [
        'agent_id', 'etablissement_id', 'affectation_id', 'discipline_id', 'annee_scolaire_id', 'date_debut',
        'classes', 'volume_horaire', 'statut', 'observations', 'declare_par', 'traite_par', 'date_traitement',
        'motif_rejet',
    ];

    protected $casts = [
        'date_debut' => 'date:Y-m-d',
        'date_traitement' => 'datetime',
        'volume_horaire' => 'integer',
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

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function valideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }
}
