<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profil extends Model
{
    public const PORTEES = ['national', 'provincial', 'etablissement', 'personnel'];

    protected $fillable = ['code', 'libelle', 'description', 'portee', 'systeme'];

    protected $casts = ['systeme' => 'boolean'];

    public function options(): BelongsToMany
    {
        return $this->belongsToMany(Option::class, 'option_profil')->withTimestamps();
    }

    public function utilisateurs(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
