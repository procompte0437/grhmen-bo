<?php

namespace App\Support;

use App\Models\AnneeScolaire;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Année scolaire consultée par l'utilisateur, choisie dans la barre du haut.
 * Transmise par l'en-tête HTTP « X-Annee-Scolaire » (identifiant) ; à défaut, l'année active.
 */
class AnneeConsultee
{
    protected static ?AnneeScolaire $cache = null;

    protected static ?string $cleCache = null;

    public static function get(): ?AnneeScolaire
    {
        $id = request()?->header('X-Annee-Scolaire');
        // Une requête HTTP = une valeur (évite de garder l'année d'une requête précédente).
        $cle = spl_object_id(request()).'|'.$id;
        if (static::$cleCache !== $cle || static::$cache === null) {
            static::$cleCache = $cle;
            static::$cache = ($id ? AnneeScolaire::find((int) $id) : null)
                ?? AnneeScolaire::active()
                ?? AnneeScolaire::orderByDesc('date_debut')->first();
        }

        return static::$cache;
    }

    public static function oublier(): void
    {
        static::$cache = null;
        static::$cleCache = null;
    }

    public static function debut(): ?string
    {
        return static::get()?->date_debut?->toDateString();
    }

    public static function fin(): ?string
    {
        return static::get()?->date_fin?->toDateString();
    }

    /** L'année consultée est-elle l'année en cours ? */
    public static function estActive(): bool
    {
        return (bool) static::get()?->active;
    }

    /** Date de référence : aujourd'hui, ou la fin de l'année consultée si elle est terminée (début si elle n'a pas commencé). */
    public static function dateReference(): Carbon
    {
        $annee = static::get();
        if (! $annee) {
            return now();
        }
        if (now()->gt($annee->date_fin)) {
            return $annee->date_fin->copy();
        }
        if (now()->lt($annee->date_debut)) {
            return $annee->date_debut->copy();
        }

        return now();
    }

    /** Restreint une requête aux enregistrements dont la date tombe dans l'année consultée. */
    public static function dansAnnee(Builder $query, string $colonne): Builder
    {
        if (! static::get()) {
            return $query;
        }

        return $query->whereBetween($colonne, [static::debut(), static::fin()]);
    }

    /** Restreint une requête aux périodes [debut, fin] qui chevauchent l'année consultée. */
    public static function chevauche(Builder $query, string $colDebut, string $colFin): Builder
    {
        if (! static::get()) {
            return $query;
        }

        return $query->where($colDebut, '<=', static::fin())->where($colFin, '>=', static::debut());
    }
}
