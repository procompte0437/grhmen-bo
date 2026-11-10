<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CessationService extends Model
{
    public const TYPES = [
        'non_prise_service' => 'Non-prise de service',
        'abandon_poste' => 'Abandon de poste',
        'mutation' => 'Mutation',
        'retraite' => 'Admission à la retraite',
        'deces' => 'Décès',
        'demission' => 'Démission',
        'disponibilite' => 'Mise en disponibilité',
        'detachement' => 'Détachement',
        'revocation' => 'Révocation',
    ];

    /** Statut de l'agent après validation de la cessation, selon son type. */
    public const STATUT_AGENT = [
        'non_prise_service' => 'suspendu',
        'abandon_poste' => 'suspendu',
        'retraite' => 'retraite',
        'deces' => 'decede',
        'demission' => 'demissionnaire',
        'disponibilite' => 'disponibilite',
        'detachement' => 'detache',
        'revocation' => 'suspendu',
    ];

    public const STATUTS = [
        'signalee' => 'Signalée, à valider',
        'validee' => 'Validée',
        'annulee' => 'Annulée',
    ];

    protected $table = 'cessations_service';

    protected $fillable = [
        'agent_id', 'etablissement_id', 'affectation_id', 'numero', 'type', 'date_cessation', 'motif', 'statut',
        'generee_automatiquement', 'signalee_par', 'validee_par', 'date_validation',
    ];

    protected $casts = [
        'date_cessation' => 'date:Y-m-d',
        'date_validation' => 'datetime',
        'generee_automatiquement' => 'boolean',
    ];

    protected $appends = ['type_libelle', 'statut_libelle'];

    public function getTypeLibelleAttribute(): string
    {
        return self::TYPES[$this->type] ?? (string) $this->type;
    }

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

    public function affectation(): BelongsTo
    {
        return $this->belongsTo(Affectation::class);
    }

    public function signaleur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signalee_par');
    }

    public function valideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par');
    }
}
