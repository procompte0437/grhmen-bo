<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\AnneeScolaire;
use App\Models\DebutCours;
use App\Models\JournalActivite;
use App\Support\Perimetre;
use App\Support\Recherche;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DebutCoursController extends Controller
{
    protected array $relations = ['agent:id,matricule,nom,prenom,sexe,categorie', 'etablissement:id,nom', 'discipline:id,libelle', 'valideur:id,name'];

    public function index(Request $request): JsonResponse
    {
        $q = DebutCours::query()->with($this->relations);
        Perimetre::filtrer($q, $request->user());
        Recherche::appliquer($q, ['classes', 'agent.nom', 'agent.prenom', 'agent.matricule'], $request->query('q'));
        foreach (['statut', 'etablissement_id', 'agent_id'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }

        return response()->json($q->orderByDesc('date_debut')->orderByDesc('id')->paginate((int) $request->query('par_page', 15)));
    }

    /** Enseignants en service n'ayant pas encore commencé les cours sur l'année active. */
    public function sansDebut(Request $request): JsonResponse
    {
        $annee = AnneeScolaire::active();
        $q = Agent::query()->with(['etablissement:id,nom', 'discipline:id,libelle'])
            ->whereIn('categorie', ['enseignant_primaire', 'enseignant_secondaire'])
            ->where('statut', 'actif')
            ->whereHas('affectations', fn ($a) => $a->where('statut', 'en_service'))
            ->whereDoesntHave('debutsCours', fn ($d) => $d->whereIn('statut', ['declare', 'confirme'])
                ->when($annee, fn ($w) => $w->where('annee_scolaire_id', $annee->id)));
        Perimetre::filtrer($q, $request->user(), 'etablissement_id', 'id');

        return response()->json(['data' => $q->orderBy('nom')->limit(500)->get()]);
    }

    public function declarer(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user->agent_id, 422, 'Votre compte n\'est rattaché à aucun dossier agent.');

        return $this->creer($request, Agent::findOrFail($user->agent_id));
    }

    public function enregistrer(Request $request): JsonResponse
    {
        $agent = Agent::findOrFail($request->validate(['agent_id' => ['required', 'exists:agents,id']])['agent_id']);
        Perimetre::exiger($user = $request->user(), $agent->etablissement_id);
        $reponse = $this->creer($request, $agent);

        if ($reponse->status() === 201 && $request->boolean('confirmer') && $user->aOption('DEBUT_COURS_CONFIRMER')) {
            $debut = DebutCours::find($reponse->getData()->data->id);
            $debut->update(['statut' => 'confirme', 'traite_par' => $user->id, 'date_traitement' => now()]);

            return response()->json(['data' => $debut->fresh($this->relations)], 201);
        }

        return $reponse;
    }

    protected function creer(Request $request, Agent $agent): JsonResponse
    {
        $donnees = $request->validate([
            'date_debut' => ['required', 'date', 'before_or_equal:today'],
            'classes' => ['required', 'string', 'max:200'],
            'volume_horaire' => ['required', 'integer', 'min:1', 'max:40'],
            'discipline_id' => ['nullable', 'exists:disciplines,id'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ], [], ['classes' => 'classes tenues', 'volume_horaire' => 'volume horaire hebdomadaire']);

        if (! $agent->estEnseignant()) {
            return response()->json(['message' => 'Le début des cours ne concerne que le personnel enseignant.'], 422);
        }
        $affectation = Affectation::where('agent_id', $agent->id)->where('statut', 'en_service')->latest('date_effet')->first();
        if (! $affectation) {
            return response()->json(['message' => 'La prise de service doit être confirmée avant de déclarer le début des cours.'], 422);
        }
        $annee = AnneeScolaire::active();
        $existe = DebutCours::where('agent_id', $agent->id)->where('affectation_id', $affectation->id)
            ->whereIn('statut', ['declare', 'confirme'])->exists();
        if ($existe) {
            return response()->json(['message' => 'Le début des cours est déjà déclaré pour cette affectation.'], 422);
        }

        $debut = DebutCours::create(array_merge($donnees, [
            'agent_id' => $agent->id,
            'etablissement_id' => $affectation->etablissement_id,
            'affectation_id' => $affectation->id,
            'discipline_id' => $donnees['discipline_id'] ?? $agent->discipline_id,
            'annee_scolaire_id' => $annee?->id,
            'statut' => 'declare',
            'declare_par' => $request->user()->id,
        ]));
        JournalActivite::consigner('declaration', 'debuts_cours', "Début des cours déclaré pour {$agent->nom_complet}", $debut);

        return response()->json(['data' => $debut->load($this->relations)], 201);
    }

    public function confirmer(Request $request, DebutCours $debutCours): JsonResponse
    {
        Perimetre::exiger($request->user(), $debutCours->etablissement_id);
        if ($debutCours->statut !== 'declare') {
            return response()->json(['message' => 'Ce début des cours a déjà été traité.'], 422);
        }
        $debutCours->update(['statut' => 'confirme', 'traite_par' => $request->user()->id, 'date_traitement' => now()]);
        JournalActivite::consigner('confirmation', 'debuts_cours', "Début des cours confirmé pour {$debutCours->agent->nom_complet}", $debutCours);

        return response()->json(['data' => $debutCours->fresh($this->relations)]);
    }

    public function rejeter(Request $request, DebutCours $debutCours): JsonResponse
    {
        Perimetre::exiger($request->user(), $debutCours->etablissement_id);
        if ($debutCours->statut !== 'declare') {
            return response()->json(['message' => 'Ce début des cours a déjà été traité.'], 422);
        }
        $motif = $request->validate(['motif' => ['required', 'string', 'max:500']])['motif'];
        $debutCours->update(['statut' => 'rejete', 'motif_rejet' => $motif, 'traite_par' => $request->user()->id, 'date_traitement' => now()]);
        JournalActivite::consigner('rejet', 'debuts_cours', "Début des cours rejeté : $motif", $debutCours);

        return response()->json(['data' => $debutCours->fresh($this->relations)]);
    }
}
