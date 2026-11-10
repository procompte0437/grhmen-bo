<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Vérifie que le profil de l'utilisateur possède au moins une des options demandées. Usage : option:CODE1,CODE2 */
class VerifierOption
{
    public function handle(Request $request, Closure $next, string ...$codes): Response
    {
        $user = $request->user();

        if (! $user || $user->etat !== 'actif') {
            return response()->json(['message' => 'Compte inactif ou non authentifié.'], 401);
        }

        if (! $user->aUneOption($codes)) {
            return response()->json([
                'message' => 'Vous n\'avez pas l\'habilitation requise pour cette action.',
                'options_requises' => $codes,
            ], 403);
        }

        return $next($request);
    }
}
