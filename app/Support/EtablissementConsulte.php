<?php

namespace App\Support;

use App\Models\User;

/**
 * Établissement consulté, choisi dans la barre du haut par les profils qui en voient plusieurs.
 * Transmis par l'en-tête HTTP « X-Etablissement ». Sans en-tête, le périmètre du profil s'applique tel quel.
 */
class EtablissementConsulte
{
    /**
     * Identifiant à appliquer aux listes de personnel, ou null s'il n'y a pas de choix à honorer.
     * 0 lorsque l'en-tête désigne un établissement hors périmètre : aucune ligne ne doit sortir.
     */
    public static function id(User $user): ?int
    {
        if (in_array($user->portee(), ['personnel', 'etablissement'], true)) {
            return null;
        }

        $brut = request()?->header('X-Etablissement');
        if ($brut === null || $brut === '') {
            return null;
        }

        $id = (int) $brut;

        return Perimetre::couvre($user, $id) ? $id : 0;
    }
}
