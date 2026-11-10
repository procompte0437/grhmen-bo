<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriseService extends Model
{
    public const STATUTS = [
        'declaree' => 'Déclarée, à confirmer',
        'confirmee' => 'Confirmée',
        'rejetee' => 'Rejetée',
    ];

    protected $table = 'prises_service';

    protected $fillable = [
        'affectation_id', 'agent_id', 'etablissement_id', 'numero', 'date_prise_service', 'statut',
        'observations', 'declaree_par', 'traitee_par', 'date_traitement', 'motif_rejet',
    ];

    protected $casts = [
        'date_prise_service' => 'date:Y-m-d',
        'date_traitement' => 'datetime',
    ];

    protected $appends = ['statut_libelle'];

    public function getStatutLibelleAttribute(): string
    {
        return self::STATUTS[$this->statut] ?? (string) $this->statut;
    }

    public function affectation(): BelongsTo
    {
        return $this->belongsTo(Affectation::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class)->withTrashed();
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function declarant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declaree_par');
    }

    public function valideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traitee_par');
    }
}
