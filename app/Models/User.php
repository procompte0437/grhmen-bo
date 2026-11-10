<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'matricule', 'telephone', 'profil_id', 'agent_id',
        'etablissement_id', 'province_id', 'etat', 'doit_changer_mot_de_passe', 'derniere_connexion',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** Cache mémoire des codes d'options du profil, pour la durée de la requête. */
    protected ?Collection $codesOptionsCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'derniere_connexion' => 'datetime',
            'doit_changer_mot_de_passe' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function profil(): BelongsTo
    {
        return $this->belongsTo(Profil::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** Codes de toutes les options (accès et gestion) attribuées au profil de l'utilisateur. */
    public function codesOptions(): Collection
    {
        if ($this->codesOptionsCache === null) {
            $this->codesOptionsCache = $this->profil
                ? $this->profil->options()->pluck('code')
                : collect();
        }

        return $this->codesOptionsCache;
    }

    public function viderCacheOptions(): void
    {
        $this->codesOptionsCache = null;
        $this->unsetRelation('profil');
    }

    public function aOption(string $code): bool
    {
        return $this->codesOptions()->contains($code);
    }

    public function aUneOption(array $codes): bool
    {
        return $this->codesOptions()->intersect($codes)->isNotEmpty();
    }

    public function portee(): string
    {
        return $this->profil?->portee ?? 'personnel';
    }
}
