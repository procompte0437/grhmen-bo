<?php

namespace App\Support;

use App\Models\Etablissement;
use App\Models\Option;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Périmètre des données visibles selon la portée du profil :
 * national (tout), provincial (établissements de la province), établissement, personnel (ses propres données).
 */
class Perimetre
{
    /**
     * Identifiants des établissements visibles, ou null si tout le ministère est visible.
     * Le droit « voir tous les établissements » est accordé par défaut aux profils nationaux
     * et peut être retiré dans les habilitations.
     */
    public static function etablissementIds(User $user): ?array
    {
        return match ($user->portee()) {
            'national' => self::voitTousLesEtablissements($user)
                ? null
                : ($user->etablissement_id ? [$user->etablissement_id] : []),
            'provincial' => Etablissement::where('province_id', $user->province_id ?? 0)->pluck('id')->all(),
            'etablissement' => $user->etablissement_id ? [$user->etablissement_id] : [],
            default => [],
        };
    }

    /** Profil national autorisé à ouvrir n'importe quel établissement (option retirable). */
    public static function voitTousLesEtablissements(User $user): bool
    {
        if ($user->portee() !== 'national') {
            return false;
        }
        // Avant l'enregistrement du catalogue, le droit reste celui de la portée nationale.
        if (! self::optionTousPresente()) {
            return true;
        }

        return $user->aOption('VOIR_TOUS_ETABLISSEMENTS');
    }

    protected static function optionTousPresente(): bool
    {
        $cle = request() ? (string) spl_object_id(request()) : 'cli';
        static $cleCache = null;
        static $presente = false;
        if ($cleCache !== $cle) {
            $cleCache = $cle;
            $presente = Option::where('code', 'VOIR_TOUS_ETABLISSEMENTS')->exists();
        }

        return $presente;
    }

    public static function filtrer(Builder $query, User $user, string $colonneEtab = 'etablissement_id', ?string $colonneAgent = 'agent_id'): Builder
    {
        if ($user->portee() === 'personnel') {
            if ($colonneAgent === null) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where($colonneAgent, $user->agent_id ?? 0);
        }

        $ids = self::etablissementIds($user);
        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($colonneEtab, $ids ?: [0]);
    }

    /** Périmètre du profil, puis établissement choisi dans la barre lorsqu'il y en a plusieurs. */
    public static function selonEtablissement(Builder $query, User $user, string $colonneEtab = 'etablissement_id', ?string $colonneAgent = 'agent_id'): Builder
    {
        return self::consulter(self::filtrer($query, $user, $colonneEtab, $colonneAgent), $user, $colonneEtab);
    }

    /** Restreint une requête à l'établissement consulté, sans élargir le périmètre. */
    public static function consulter(Builder $query, User $user, string $colonneEtab = 'etablissement_id'): Builder
    {
        $id = EtablissementConsulte::id($user);
        if ($id === null) {
            return $query;
        }

        return $query->where($colonneEtab, $id);
    }

    public static function exigerConsulte(User $user, ?int $etablissementId): void
    {
        $id = EtablissementConsulte::id($user);
        if ($id === null) {
            return;
        }
        if ((int) $etablissementId !== $id) {
            abort(403, 'Cet élément n\'appartient pas à l\'établissement consulté.');
        }
    }

    public static function couvre(User $user, ?int $etablissementId, ?int $agentId = null): bool
    {
        if ($user->portee() === 'personnel') {
            return $agentId !== null && $agentId === $user->agent_id;
        }

        $ids = self::etablissementIds($user);

        return $ids === null || ($etablissementId !== null && in_array($etablissementId, $ids, true));
    }

    public static function exiger(User $user, ?int $etablissementId, ?int $agentId = null): void
    {
        if (! self::couvre($user, $etablissementId, $agentId)) {
            abort(403, 'Cet élément est hors de votre périmètre.');
        }
    }
}
