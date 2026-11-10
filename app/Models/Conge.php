<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conge extends Model
{
    public const TYPES = [
        'annuel' => 'Congé annuel',
        'maladie' => 'Congé de maladie',
        'maternite' => 'Congé de maternité',
        'paternite' => 'Congé de paternité',
        'exceptionnel' => 'Autorisation d\'absence exceptionnelle',
        'formation' => 'Congé de formation',
    ];

    public const STATUTS = [
        'soumise' => 'Soumise',
        'avis_favorable' => 'Avis favorable du chef',
        'avis_defavorable' => 'Avis défavorable du chef',
        'approuvee' => 'Approuvée',
        'rejetee' => 'Rejetée',
    ];

    protected $fillable = [
        'agent_id', 'etablissement_id', 'type', 'date_debut', 'date_fin', 'nombre_jours', 'motif', 'statut',
        'avis_chef', 'avis_par', 'decision_par', 'commentaire_decision', 'date_decision',
    ];

    protected $casts = [
        'date_debut' => 'date:Y-m-d',
        'date_fin' => 'date:Y-m-d',
        'date_decision' => 'datetime',
        'nombre_jours' => 'integer',
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
}
