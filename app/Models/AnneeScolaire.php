<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnneeScolaire extends Model
{
    protected $table = 'annees_scolaires';

    protected $fillable = ['libelle', 'date_debut', 'date_fin', 'active'];

    protected $casts = ['active' => 'boolean', 'date_debut' => 'date:Y-m-d', 'date_fin' => 'date:Y-m-d'];

    public static function active(): ?self
    {
        return static::where('active', true)->first();
    }
}
