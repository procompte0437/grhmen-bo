<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Option d'accès (élément du menu latéral) ou option de gestion (action dans un écran). */
class Option extends Model
{
    public const ACCES = 'acces';

    public const GESTION = 'gestion';

    protected $fillable = ['code', 'libelle', 'type', 'parent_id', 'groupe', 'route', 'icone', 'ordre', 'description'];

    protected $casts = ['ordre' => 'integer'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'parent_id');
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(Option::class, 'parent_id')->orderBy('ordre');
    }

    public function profils(): BelongsToMany
    {
        return $this->belongsToMany(Profil::class, 'option_profil')->withTimestamps();
    }
}
