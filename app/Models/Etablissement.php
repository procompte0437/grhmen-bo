<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Etablissement extends Model
{
    public const TYPES = [
        'ecole_primaire' => 'École primaire',
        'college' => 'Collège (CES)',
        'lycee' => 'Lycée',
        'lycee_technique' => 'Lycée technique',
        'direction_centrale' => 'Direction centrale',
        'academie' => 'Académie provinciale',
    ];

    protected $fillable = [
        'code', 'nom', 'type', 'province_id', 'ville', 'adresse', 'telephone', 'email',
        'effectif_eleves', 'latitude', 'longitude', 'heure_debut', 'heure_fin', 'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'effectif_eleves' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected $appends = ['type_libelle'];

    public function getTypeLibelleAttribute(): ?string
    {
        $type = $this->attributes['type'] ?? null;

        return $type ? (self::TYPES[$type] ?? $type) : null;
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }
}
