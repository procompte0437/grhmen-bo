<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    use SoftDeletes;

    /** Limite d'âge du départ à la retraite, en années. */
    public const AGE_LIMITE_RETRAITE = 62;

    public const CATEGORIES = [
        'enseignant_primaire' => 'Enseignant du primaire',
        'enseignant_secondaire' => 'Enseignant du secondaire',
        'administratif' => 'Personnel administratif',
    ];

    public const STATUTS = [
        'actif' => 'En activité',
        'suspendu' => 'Suspendu (solde suspendue)',
        'disponibilite' => 'En disponibilité',
        'detache' => 'Détaché',
        'retraite' => 'Retraité',
        'decede' => 'Décédé',
        'demissionnaire' => 'Démissionnaire',
    ];

    protected $fillable = [
        'matricule', 'nom', 'prenom', 'sexe', 'date_naissance', 'lieu_naissance', 'nationalite',
        'situation_matrimoniale', 'nombre_enfants', 'numero_piece', 'telephone', 'email', 'adresse',
        'categorie', 'corps_id', 'grade_id', 'echelon', 'discipline_id', 'fonction_id', 'etablissement_id',
        'diplome', 'date_recrutement', 'date_depart_retraite', 'statut', 'observations',
    ];

    protected $casts = [
        'date_naissance' => 'date:Y-m-d',
        'date_recrutement' => 'date:Y-m-d',
        'date_depart_retraite' => 'date:Y-m-d',
        'nombre_enfants' => 'integer',
        'echelon' => 'integer',
    ];

    protected $appends = ['nom_complet', 'categorie_libelle', 'statut_libelle'];

    public function getNomCompletAttribute(): string
    {
        return trim(mb_strtoupper($this->nom).' '.$this->prenom);
    }

    public function getCategorieLibelleAttribute(): string
    {
        return self::CATEGORIES[$this->categorie] ?? (string) $this->categorie;
    }

    public function getStatutLibelleAttribute(): string
    {
        return self::STATUTS[$this->statut] ?? (string) $this->statut;
    }

    public function estEnseignant(): bool
    {
        return in_array($this->categorie, ['enseignant_primaire', 'enseignant_secondaire'], true);
    }

    public function corps(): BelongsTo
    {
        return $this->belongsTo(Corps::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function fonction(): BelongsTo
    {
        return $this->belongsTo(Fonction::class);
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function utilisateur(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class)->orderByDesc('date_effet')->orderByDesc('id');
    }

    /** Affectation en cours (en attente de prise de service ou en service). */
    public function affectationCourante(): HasOne
    {
        return $this->hasOne(Affectation::class)
            ->whereIn('statut', ['en_attente_prise_service', 'en_service'])
            ->latestOfMany('date_effet');
    }

    public function prisesService(): HasMany
    {
        return $this->hasMany(PriseService::class)->orderByDesc('date_prise_service');
    }

    public function debutsCours(): HasMany
    {
        return $this->hasMany(DebutCours::class)->orderByDesc('date_debut');
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    public function cessations(): HasMany
    {
        return $this->hasMany(CessationService::class)->orderByDesc('date_cessation');
    }

    public function conges(): HasMany
    {
        return $this->hasMany(Conge::class)->orderByDesc('date_debut');
    }
}
