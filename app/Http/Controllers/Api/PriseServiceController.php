<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\JournalActivite;
use App\Models\PriseService;
use App\Support\Perimetre;
use App\Support\Recherche;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PriseServiceController extends Controller
{
    protected array $relations = [
        'agent:id,matricule,nom,prenom,sexe,categorie', 'etablissement:id,nom,ville', 'affectation:id,nature,numero_decision,date_effet,statut,etablissement_origine_id',
        'affectation.etablissementOrigine:id,nom', 'declarant:id,name', 'valideur:id,name',
    ];

    public function index(Request $request): JsonResponse
    {
        $q = PriseService::query()->with($this->relations);
        Perimetre::selonEtablissement($q, $request->user());
        \App\Support\AnneeConsultee::dansAnnee($q, 'date_prise_service');
        Recherche::appliquer($q, ['numero', 'agent.nom', 'agent.prenom', 'agent.matricule'], $request->query('q'));
        foreach (['statut', 'etablissement_id', 'agent_id'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }

        return response()->json($q->orderByDesc('created_at')->paginate((int) $request->query('par_page', 15)));
    }

    public function show(Request $request, PriseService $priseService): JsonResponse
    {
        Perimetre::exiger($request->user(), $priseService->etablissement_id, $priseService->agent_id);
        Perimetre::exigerConsulte($request->user(), $priseService->etablissement_id);

        return response()->json(['data' => $priseService->load(array_merge($this->relations, ['agent.grade:id,code,libelle', 'agent.fonction:id,libelle', 'agent.discipline:id,libelle', 'etablissement.province:id,nom']))]);
    }

    /** L'agent déclare lui-même sa prise de service pour son affectation en cours. */
    public function declarer(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user->agent_id, 422, 'Votre compte n\'est rattaché à aucun dossier agent.');
        $donnees = $request->validate([
            'date_prise_service' => ['required', 'date', 'before_or_equal:today'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ]);

        $affectation = Affectation::where('agent_id', $user->agent_id)->where('statut', 'en_attente_prise_service')->latest('date_effet')->first();

        return $this->enregistrerPour($affectation, $donnees, $request);
    }

    /** L'administrateur, la DRH, le gestionnaire RH, le chef d'établissement ou le censeur enregistre la prise de service d'un agent. */
    public function enregistrer(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'affectation_id' => ['required', 'exists:affectations,id'],
            'date_prise_service' => ['required', 'date', 'before_or_equal:today'],
            'observations' => ['nullable', 'string', 'max:1000'],
            'confirmer' => ['boolean'],
        ]);
        $affectation = Affectation::findOrFail($donnees['affectation_id']);
        Perimetre::exiger($request->user(), $affectation->etablissement_id);

        $reponse = $this->enregistrerPour($affectation, $donnees, $request);
        if ($reponse->status() === 201 && ($donnees['confirmer'] ?? false) && $request->user()->aOption('PRISE_SERVICE_CONFIRMER')) {
            $prise = PriseService::find($reponse->getData()->data->id);

            return $this->appliquerConfirmation($prise, $request, null);
        }

        return $reponse;
    }

    protected function enregistrerPour(?Affectation $affectation, array $donnees, Request $request): JsonResponse
    {
        if (! $affectation || $affectation->statut !== 'en_attente_prise_service') {
            return response()->json(['message' => 'Aucune affectation en attente de prise de service.'], 422);
        }
        if ($affectation->prisesService()->whereIn('statut', ['declaree', 'confirmee'])->exists()) {
            return response()->json(['message' => 'Une prise de service est déjà enregistrée pour cette affectation.'], 422);
        }
        if ($donnees['date_prise_service'] < $affectation->date_effet->toDateString()) {
            return response()->json(['message' => 'La date de prise de service ne peut pas précéder la date d\'effet de l\'affectation ('.$affectation->date_effet->format('d/m/Y').').', 'errors' => ['date_prise_service' => ['Date antérieure à la date d\'effet.']]], 422);
        }

        $prise = PriseService::create([
            'affectation_id' => $affectation->id,
            'agent_id' => $affectation->agent_id,
            'etablissement_id' => $affectation->etablissement_id,
            'numero' => self::numero('PS'),
            'date_prise_service' => $donnees['date_prise_service'],
            'observations' => $donnees['observations'] ?? null,
            'statut' => 'declaree',
            'declaree_par' => $request->user()->id,
        ]);
        JournalActivite::consigner('declaration', 'prises_service', "Prise de service déclarée ({$prise->numero}) pour {$affectation->agent->nom_complet}", $prise);

        return response()->json(['data' => $prise->load($this->relations)], 201);
    }

    public function confirmer(Request $request, PriseService $priseService): JsonResponse
    {
        Perimetre::exiger($request->user(), $priseService->etablissement_id);
        if ($priseService->statut !== 'declaree') {
            return response()->json(['message' => 'Cette prise de service a déjà été traitée.'], 422);
        }
        $observations = $request->validate(['observations' => ['nullable', 'string', 'max:1000']])['observations'] ?? null;

        return $this->appliquerConfirmation($priseService, $request, $observations);
    }

    protected function appliquerConfirmation(PriseService $prise, Request $request, ?string $observations): JsonResponse
    {
        DB::transaction(function () use ($prise, $request, $observations) {
            $prise->update([
                'statut' => 'confirmee',
                'traitee_par' => $request->user()->id,
                'date_traitement' => now(),
                'observations' => $observations ? trim(($prise->observations ?? '')."\n".$observations) : $prise->observations,
            ]);
            $prise->affectation->update(['statut' => 'en_service']);
        });
        JournalActivite::consigner('confirmation', 'prises_service', "Prise de service confirmée ({$prise->numero})", $prise);

        return response()->json(['data' => $prise->fresh($this->relations)]);
    }

    public function rejeter(Request $request, PriseService $priseService): JsonResponse
    {
        Perimetre::exiger($request->user(), $priseService->etablissement_id);
        if ($priseService->statut !== 'declaree') {
            return response()->json(['message' => 'Cette prise de service a déjà été traitée.'], 422);
        }
        $motif = $request->validate(['motif' => ['required', 'string', 'max:500']])['motif'];
        $priseService->update(['statut' => 'rejetee', 'motif_rejet' => $motif, 'traitee_par' => $request->user()->id, 'date_traitement' => now()]);
        JournalActivite::consigner('rejet', 'prises_service', "Prise de service rejetée ({$priseService->numero}) : $motif", $priseService);

        return response()->json(['data' => $priseService->fresh($this->relations)]);
    }

    public static function numero(string $prefixe): string
    {
        return $prefixe.'-'.now()->format('Y').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }
}
