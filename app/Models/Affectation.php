<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Affectation extends Model
{
    public const NATURES = [
        'premiere_affectation' => 'Première affectation',
        'mutation' => 'Mutation',
        'nomination' => 'Nomination',
        'permutation' => 'Permutation',
        'redeploiement' => 'Redéploiement',
        'reintegration' => 'Réintégration',
    ];

    public const STATUTS = [
        'en_attente_prise_service' => 'En attente de prise de service',
        'en_service' => 'En service',
        'terminee' => 'Terminée',
        'cessation' => 'Cessation de service',
        'annulee' => 'Annulée',
    ];

    protected $fillable = [
        'agent_id', 'etablissement_id', 'etablissement_origine_id', 'fonction_id', 'discipline_id',
        'annee_scolaire_id', 'nature', 'numero_decision', 'date_decision', 'date_effet', 'statut',
        'observations', 'cree_par',
    ];

    protected $casts = [
        'date_decision' => 'date:Y-m-d',
        'date_effet' => 'date:Y-m-d',
    ];

    protected $appends = ['nature_libelle', 'statut_libelle', 'jours_depuis_effet'];

    public function getNatureLibelleAttribute(): string
    {
        return self::NATURES[$this->nature] ?? (string) $this->nature;
    }

    public function getStatutLibelleAttribute(): string
    {
        return self::STATUTS[$this->statut] ?? (string) $this->statut;
    }

    /** Nombre de jours écoulés depuis la date d'effet (utile pour repérer les non-prises de service). */
    public function getJoursDepuisEffetAttribute(): ?int
    {
        if (! $this->date_effet) {
            return null;
        }

        return (int) $this->date_effet->startOfDay()->diffInDays(now()->startOfDay(), false);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class)->withTrashed();
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function etablissementOrigine(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class, 'etablissement_origine_id');
    }

    public function fonction(): BelongsTo
    {
        return $this->belongsTo(Fonction::class);
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function prisesService(): HasMany
    {
        return $this->hasMany(PriseService::class);
    }

    public function priseServiceActive(): HasOne
    {
        return $this->hasOne(PriseService::class)->whereIn('statut', ['declaree', 'confirmee'])->latestOfMany();
    }
}
