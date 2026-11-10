<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\AnneeScolaire;
use App\Models\JournalActivite;
use App\Support\Perimetre;
use App\Support\Recherche;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AffectationController extends Controller
{
    protected array $relations = [
        'agent:id,matricule,nom,prenom,sexe,categorie,statut', 'etablissement:id,nom,province_id', 'etablissement.province:id,nom',
        'etablissementOrigine:id,nom', 'fonction:id,libelle', 'discipline:id,libelle', 'anneeScolaire:id,libelle', 'priseServiceActive',
    ];

    public function index(Request $request): JsonResponse
    {
        $q = Affectation::query()->with($this->relations);
        Perimetre::selonEtablissement($q, $request->user());
        if ($fin = \App\Support\AnneeConsultee::fin()) {
            $debutAnnee = \App\Support\AnneeConsultee::debut();
            $q->where('date_effet', '<=', $fin)->where(fn ($w) => $w->where('date_effet', '>=', $debutAnnee)->orWhereIn('statut', ['en_attente_prise_service', 'en_service']));
        }
        Recherche::appliquer($q, ['numero_decision', 'agent.nom', 'agent.prenom', 'agent.matricule'], $request->query('q'));

        foreach (['statut', 'nature', 'etablissement_id', 'agent_id', 'annee_scolaire_id'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        if ($p = $request->query('province_id')) {
            $q->whereHas('etablissement', fn ($e) => $e->where('province_id', $p));
        }
        // Affectations en retard de prise de service : date d'effet dépassée de plus de N jours.
        if ($request->filled('retard_jours')) {
            $q->where('statut', 'en_attente_prise_service')
                ->where('date_effet', '<=', now()->subDays((int) $request->query('retard_jours'))->toDateString());
        }

        return response()->json($q->orderByDesc('date_effet')->orderByDesc('id')->paginate((int) $request->query('par_page', 15)));
    }

    public function show(Request $request, Affectation $affectation): JsonResponse
    {
        Perimetre::exiger($request->user(), $affectation->etablissement_id, $affectation->agent_id);
        Perimetre::exigerConsulte($request->user(), $affectation->etablissement_id);

        return response()->json(['data' => $affectation->load(array_merge($this->relations, ['prisesService', 'auteur:id,name']))]);
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $this->valider($request);
        $agent = Agent::findOrFail($donnees['agent_id']);
        Perimetre::exiger($request->user(), (int) $donnees['etablissement_id']);
        if ($agent->etablissement_id) {
            Perimetre::exiger($request->user(), (int) $agent->etablissement_id, $agent->id);
            Perimetre::exigerConsulte($request->user(), (int) $agent->etablissement_id);
        }

        if (! in_array($agent->statut, ['actif', 'disponibilite', 'detache'], true)) {
            return response()->json(['message' => "Impossible d'affecter un agent au statut « {$agent->statut_libelle} »."], 422);
        }
        if ($agent->etablissement_id && (int) $agent->etablissement_id === (int) $donnees['etablissement_id']
            && $agent->affectationCourante && $agent->affectationCourante->statut === 'en_service') {
            return response()->json(['message' => 'L\'agent est déjà en service dans cet établissement.'], 422);
        }

        $affectation = DB::transaction(function () use ($donnees, $agent, $request) {
            // L'affectation précédente se termine à la nouvelle date d'effet.
            Affectation::where('agent_id', $agent->id)->whereIn('statut', ['en_attente_prise_service', 'en_service'])
                ->update(['statut' => 'terminee']);

            $affectation = Affectation::create(array_merge($donnees, [
                'etablissement_origine_id' => $agent->etablissement_id,
                'annee_scolaire_id' => $donnees['annee_scolaire_id'] ?? AnneeScolaire::active()?->id,
                'statut' => 'en_attente_prise_service',
                'cree_par' => $request->user()->id,
            ]));

            $agent->update(array_filter([
                'etablissement_id' => $affectation->etablissement_id,
                'fonction_id' => $affectation->fonction_id ?? $agent->fonction_id,
                'discipline_id' => $affectation->discipline_id ?? $agent->discipline_id,
                'statut' => 'actif',
            ]));
            $agent->utilisateur?->update([
                'etablissement_id' => $affectation->etablissement_id,
                'province_id' => $affectation->etablissement->province_id,
            ]);

            return $affectation;
        });

        JournalActivite::consigner('creation', 'affectations', "Affectation de {$agent->nom_complet} à {$affectation->etablissement->nom}", $affectation);

        return response()->json(['data' => $affectation->load($this->relations)], 201);
    }

    public function update(Request $request, Affectation $affectation): JsonResponse
    {
        if (! in_array($affectation->statut, ['en_attente_prise_service'], true)) {
            return response()->json(['message' => 'Seule une affectation en attente de prise de service peut être modifiée.'], 422);
        }
        $donnees = $request->validate([
            'nature' => ['required', Rule::in(array_keys(Affectation::NATURES))],
            'numero_decision' => ['nullable', 'string', 'max:60'],
            'date_decision' => ['nullable', 'date'],
            'date_effet' => ['required', 'date'],
            'fonction_id' => ['nullable', 'exists:fonctions,id'],
            'discipline_id' => ['nullable', 'exists:disciplines,id'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);
        $affectation->update($donnees);
        JournalActivite::consigner('modification', 'affectations', "Affectation n°{$affectation->id} modifiée", $affectation);

        return response()->json(['data' => $affectation->load($this->relations)]);
    }

    public function annuler(Request $request, Affectation $affectation): JsonResponse
    {
        if ($affectation->statut !== 'en_attente_prise_service') {
            return response()->json(['message' => 'Seule une affectation en attente de prise de service peut être annulée.'], 422);
        }
        $motif = $request->validate(['motif' => ['required', 'string', 'max:500']])['motif'];

        DB::transaction(function () use ($affectation, $motif) {
            $affectation->update(['statut' => 'annulee', 'observations' => trim(($affectation->observations ?? '')."\nAnnulation : ".$motif)]);
            $affectation->prisesService()->where('statut', 'declaree')->update(['statut' => 'rejetee', 'motif_rejet' => 'Affectation annulée']);

            // L'agent retrouve son établissement d'origine.
            $agent = $affectation->agent;
            $precedente = Affectation::where('agent_id', $agent->id)->where('id', '!=', $affectation->id)
                ->where('statut', 'terminee')->orderByDesc('date_effet')->first();
            if ($precedente) {
                $precedente->update(['statut' => 'en_service']);
            }
            $agent->update(['etablissement_id' => $affectation->etablissement_origine_id]);
            $affectation->loadMissing('etablissementOrigine');
            $agent->utilisateur?->update([
                'etablissement_id' => $affectation->etablissement_origine_id,
                'province_id' => $affectation->etablissementOrigine?->province_id,
            ]);
        });

        JournalActivite::consigner('annulation', 'affectations', "Affectation n°{$affectation->id} annulée : $motif", $affectation);

        return response()->json(['data' => $affectation->fresh($this->relations)]);
    }

    protected function valider(Request $request): array
    {
        return $request->validate([
            'agent_id' => ['required', 'exists:agents,id'],
            'etablissement_id' => ['required', 'exists:etablissements,id'],
            'nature' => ['required', Rule::in(array_keys(Affectation::NATURES))],
            'numero_decision' => ['nullable', 'string', 'max:60'],
            'date_decision' => ['nullable', 'date'],
            'date_effet' => ['required', 'date'],
            'fonction_id' => ['nullable', 'exists:fonctions,id'],
            'discipline_id' => ['nullable', 'exists:disciplines,id'],
            'annee_scolaire_id' => ['nullable', 'exists:annees_scolaires,id'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
