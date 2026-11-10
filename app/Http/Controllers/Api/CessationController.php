<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\CessationService;
use App\Models\JournalActivite;
use App\Support\Perimetre;
use App\Support\Recherche;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CessationController extends Controller
{
    protected array $relations = [
        'agent:id,matricule,nom,prenom,sexe,categorie,statut,grade_id,fonction_id', 'agent.grade:id,code,libelle', 'agent.fonction:id,libelle',
        'etablissement:id,nom,ville,province_id', 'etablissement.province:id,nom', 'affectation:id,nature,numero_decision,date_effet',
        'signaleur:id,name', 'valideur:id,name',
    ];

    public function index(Request $request): JsonResponse
    {
        $q = CessationService::query()->with($this->relations);
        Perimetre::filtrer($q, $request->user());
        Recherche::appliquer($q, ['numero', 'agent.nom', 'agent.prenom', 'agent.matricule'], $request->query('q'));
        foreach (['statut', 'type', 'etablissement_id', 'agent_id'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }

        return response()->json($q->orderByDesc('created_at')->paginate((int) $request->query('par_page', 15)));
    }

    public function show(Request $request, CessationService $cessation): JsonResponse
    {
        Perimetre::exiger($request->user(), $cessation->etablissement_id, $cessation->agent_id);

        return response()->json(['data' => $cessation->load($this->relations)]);
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'agent_id' => ['required', 'exists:agents,id'],
            'type' => ['required', Rule::in(array_keys(CessationService::TYPES))],
            'date_cessation' => ['required', 'date'],
            'motif' => ['required', 'string', 'max:2000'],
        ]);
        $agent = Agent::findOrFail($donnees['agent_id']);
        Perimetre::exiger($request->user(), $agent->etablissement_id);

        if (CessationService::where('agent_id', $agent->id)->where('statut', 'signalee')->exists()) {
            return response()->json(['message' => 'Une cessation est déjà en cours de traitement pour cet agent.'], 422);
        }

        $cessation = CessationService::create(array_merge($donnees, [
            'etablissement_id' => $agent->etablissement_id,
            'affectation_id' => $agent->affectationCourante?->id,
            'numero' => PriseServiceController::numero('CS'),
            'statut' => 'signalee',
            'signalee_par' => $request->user()->id,
        ]));
        JournalActivite::consigner('signalement', 'cessations', "Cessation signalée ({$cessation->type_libelle}) pour {$agent->nom_complet}", $cessation);

        return response()->json(['data' => $cessation->load($this->relations)], 201);
    }

    /** Génère une cessation pour chaque agent qui n'a pas pris service après le délai réglementaire. */
    public function genererNonPriseService(Request $request): JsonResponse
    {
        $request->validate(['delai_jours' => ['nullable', 'integer', 'min:1', 'max:365']]);
        $delai = (int) ($request->input('delai_jours') ?: config('grhmen.delai_non_prise_service', 30));
        $q = Affectation::with('agent', 'etablissement:id,nom')->where('statut', 'en_attente_prise_service')
            ->where('date_effet', '<=', now()->subDays($delai)->toDateString())
            ->whereDoesntHave('prisesService', fn ($p) => $p->whereIn('statut', ['declaree', 'confirmee']));
        Perimetre::filtrer($q, $request->user());

        $generees = collect();
        DB::transaction(function () use ($q, $request, $delai, &$generees) {
            foreach ($q->get() as $affectation) {
                $deja = CessationService::where('affectation_id', $affectation->id)->whereIn('statut', ['signalee', 'validee'])->exists();
                if ($deja) {
                    continue;
                }
                $generees->push(CessationService::create([
                    'agent_id' => $affectation->agent_id,
                    'etablissement_id' => $affectation->etablissement_id,
                    'affectation_id' => $affectation->id,
                    'numero' => PriseServiceController::numero('CS'),
                    'type' => 'non_prise_service',
                    'date_cessation' => now()->toDateString(),
                    'motif' => "Non-prise de service à {$affectation->etablissement->nom} plus de $delai jours après la date d'effet du ".$affectation->date_effet->format('d/m/Y').'.',
                    'statut' => 'signalee',
                    'generee_automatiquement' => true,
                    'signalee_par' => $request->user()->id,
                ]));
            }
        });
        JournalActivite::consigner('generation', 'cessations', $generees->count()." cessation(s) générée(s) pour non-prise de service (délai $delai j)");

        return response()->json([
            'message' => $generees->count().' cessation(s) de service générée(s).',
            'nombre' => $generees->count(),
            'data' => CessationService::with($this->relations)->whereIn('id', $generees->pluck('id'))->get(),
        ]);
    }

    public function valider(Request $request, CessationService $cessation): JsonResponse
    {
        Perimetre::exiger($request->user(), $cessation->etablissement_id);
        if ($cessation->statut !== 'signalee') {
            return response()->json(['message' => 'Cette cessation a déjà été traitée.'], 422);
        }

        DB::transaction(function () use ($cessation, $request) {
            $cessation->update(['statut' => 'validee', 'validee_par' => $request->user()->id, 'date_validation' => now()]);
            if ($cessation->affectation && in_array($cessation->affectation->statut, ['en_attente_prise_service', 'en_service'], true)) {
                $cessation->affectation->update(['statut' => 'cessation']);
            }
            if ($nouveauStatut = CessationService::STATUT_AGENT[$cessation->type] ?? null) {
                $cessation->agent->update(['statut' => $nouveauStatut]);
                if (in_array($nouveauStatut, ['retraite', 'decede', 'demissionnaire'], true)) {
                    $cessation->agent->utilisateur?->update(['etat' => 'inactif']);
                }
            }
        });
        JournalActivite::consigner('validation', 'cessations', "Cessation {$cessation->numero} validée", $cessation);

        return response()->json(['data' => $cessation->fresh($this->relations)]);
    }

    public function annuler(Request $request, CessationService $cessation): JsonResponse
    {
        Perimetre::exiger($request->user(), $cessation->etablissement_id);
        if ($cessation->statut !== 'signalee') {
            return response()->json(['message' => 'Seule une cessation signalée peut être annulée.'], 422);
        }
        $motif = $request->validate(['motif' => ['required', 'string', 'max:500']])['motif'];
        $cessation->update(['statut' => 'annulee', 'motif' => trim($cessation->motif."\nAnnulation : ".$motif), 'validee_par' => $request->user()->id, 'date_validation' => now()]);
        JournalActivite::consigner('annulation', 'cessations', "Cessation {$cessation->numero} annulée : $motif", $cessation);

        return response()->json(['data' => $cessation->fresh($this->relations)]);
    }
}
