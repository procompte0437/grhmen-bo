<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Corps extends Model
{
    protected $table = 'corps';

    protected $fillable = ['code', 'libelle', 'categorie'];
}
