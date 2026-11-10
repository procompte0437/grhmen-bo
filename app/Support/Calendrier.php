<?php

namespace App\Support;

use Carbon\Carbon;

/** Calendrier administratif : week-ends et jours fériés (config/grhmen.php). */
class Calendrier
{
    /** Libellé du jour férié, ou null si le jour n'est pas férié. */
    public static function ferie(Carbon $jour): ?string
    {
        return config('grhmen.feries_dates')[$jour->toDateString()]
            ?? config('grhmen.feries_fixes')[$jour->format('m-d')]
            ?? null;
    }

    public static function estOuvre(Carbon $jour): bool
    {
        return ! $jour->isWeekend() && self::ferie($jour) === null;
    }

    public static function joursOuvres(Carbon $debut, Carbon $fin): int
    {
        $n = 0;
        for ($d = $debut->copy()->startOfDay(); $d->lte($fin); $d->addDay()) {
            if (self::estOuvre($d)) {
                $n++;
            }
        }

        return $n;
    }

    /** Jours fériés compris entre deux dates : [date => libellé]. */
    public static function feriesEntre(Carbon $debut, Carbon $fin): array
    {
        $liste = [];
        for ($d = $debut->copy()->startOfDay(); $d->lte($fin); $d->addDay()) {
            if ($libelle = self::ferie($d)) {
                $liste[$d->toDateString()] = $libelle;
            }
        }

        return $liste;
    }
}
