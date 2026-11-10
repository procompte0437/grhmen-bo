<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/** Recherche plein texte simple, insensible à la casse, compatible PostgreSQL et SQLite. */
class Recherche
{
    public static function appliquer(Builder $query, array $colonnes, ?string $terme): Builder
    {
        $terme = mb_strtolower(trim((string) $terme));
        if ($terme === '') {
            return $query;
        }

        return $query->where(function (Builder $w) use ($colonnes, $terme) {
            foreach ($colonnes as $colonne) {
                if (str_contains($colonne, '.')) {
                    [$relation, $champ] = explode('.', $colonne, 2);
                    $w->orWhereHas($relation, fn (Builder $r) => $r->whereRaw("LOWER($champ) LIKE ?", ["%$terme%"]));
                } else {
                    $w->orWhereRaw("LOWER($colonne) LIKE ?", ["%$terme%"]);
                }
            }
        });
    }
}
