<?php

namespace App\Support;

use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Périmètre des données visibles selon la portée du profil :
 * national (tout), provincial (établissements de la province), établissement, personnel (ses propres données).
 */
class Perimetre
{
    /** Identifiants des établissements visibles, ou null si tout est visible. */
    public static function etablissementIds(User $user): ?array
    {
        return match ($user->portee()) {
            'national' => null,
            'provincial' => Etablissement::where('province_id', $user->province_id ?? 0)->pluck('id')->all(),
            'etablissement' => $user->etablissement_id ? [$user->etablissement_id] : [],
            default => [],
        };
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
