<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\DebutCours;
use App\Models\PriseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonEspaceController extends Controller
{
    public function dossier(Request $request): JsonResponse
    {
        $agent = Agent::find($request->user()->agent_id);
        abort_if(! $agent, 422, 'Votre compte n\'est rattaché à aucun dossier agent.');

        return response()->json(['data' => AgentController::dossier($agent)]);
    }

    /** Situation de l'agent : affectation en cours, prise de service et début des cours. */
    public function services(Request $request): JsonResponse
    {
        $agent = Agent::with(['etablissement:id,nom', 'discipline:id,libelle'])->find($request->user()->agent_id);
        abort_if(! $agent, 422, 'Votre compte n\'est rattaché à aucun dossier agent.');

        $affectation = Affectation::with(['etablissement:id,nom,ville', 'etablissementOrigine:id,nom', 'fonction:id,libelle', 'discipline:id,libelle'])
            ->where('agent_id', $agent->id)->whereIn('statut', ['en_attente_prise_service', 'en_service'])
            ->orderByDesc('date_effet')->first();

        return response()->json([
            'agent' => $agent,
            'est_enseignant' => $agent->estEnseignant(),
            'affectation' => $affectation,
            'prise_service' => $affectation ? PriseService::with('valideur:id,name')->where('affectation_id', $affectation->id)->latest()->first() : null,
            'debut_cours' => $affectation ? DebutCours::with(['valideur:id,name', 'discipline:id,libelle'])->where('affectation_id', $affectation->id)->latest()->first() : null,
            'historique_prises' => PriseService::with('etablissement:id,nom')->where('agent_id', $agent->id)->latest()->get(),
        ]);
    }
}
