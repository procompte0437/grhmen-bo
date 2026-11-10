<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fonction extends Model
{
    protected $table = 'fonctions';

    protected $fillable = ['code', 'libelle', 'enseignante'];

    protected $casts = ['enseignante' => 'boolean'];
}
