<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Conge;
use App\Models\JournalActivite;
use App\Models\Presence;
use App\Support\Perimetre;
use App\Support\Recherche;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CongeController extends Controller
{
    protected array $relations = ['agent:id,matricule,nom,prenom,sexe,categorie', 'etablissement:id,nom'];

    public function index(Request $request): JsonResponse
    {
        $q = Conge::query()->with($this->relations);
        Perimetre::filtrer($q, $request->user());
        Recherche::appliquer($q, ['agent.nom', 'agent.prenom', 'agent.matricule'], $request->query('q'));
        foreach (['statut', 'type', 'etablissement_id', 'agent_id'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }

        return response()->json($q->orderByDesc('created_at')->paginate((int) $request->query('par_page', 15)));
    }

    public function mesConges(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent_id;
        abort_if(! $agentId, 422, 'Votre compte n\'est rattaché à aucun dossier agent.');
        $conges = Conge::where('agent_id', $agentId)->orderByDesc('date_debut')->get();
        $annee = now()->year;
        $pris = $conges->where('type', 'annuel')->where('statut', 'approuvee')
            ->filter(fn ($c) => $c->date_debut->year === $annee)->sum('nombre_jours');

        return response()->json(['data' => $conges, 'solde_annuel' => ['droit' => 30, 'pris' => $pris, 'restant' => max(0, 30 - $pris)]]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user->agent_id, 422, 'Votre compte n\'est rattaché à aucun dossier agent.');
        $donnees = $request->validate([
            'type' => ['required', Rule::in(array_keys(Conge::TYPES))],
            'date_debut' => ['required', 'date', 'after_or_equal:today'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'motif' => ['required', 'string', 'max:1000'],
        ], ['date_debut.after_or_equal' => 'La date de début ne peut pas être passée.']);

        $agent = Agent::findOrFail($user->agent_id);
        $chevauche = Conge::where('agent_id', $agent->id)->whereNotIn('statut', ['rejetee', 'avis_defavorable'])
            ->where('date_debut', '<=', $donnees['date_fin'])->where('date_fin', '>=', $donnees['date_debut'])->exists();
        if ($chevauche) {
            return response()->json(['message' => 'Une demande couvre déjà tout ou partie de cette période.'], 422);
        }

        $jours = PresenceController::joursOuvres(Carbon::parse($donnees['date_debut']), Carbon::parse($donnees['date_fin']));
        $conge = Conge::create(array_merge($donnees, [
            'agent_id' => $agent->id,
            'etablissement_id' => $agent->etablissement_id,
            'nombre_jours' => max(1, $jours),
            'statut' => 'soumise',
        ]));
        JournalActivite::consigner('demande', 'conges', "Demande de congé ({$conge->type_libelle}) de {$agent->nom_complet}", $conge);

        return response()->json(['data' => $conge->load($this->relations)], 201);
    }

    public function annulerMaDemande(Request $request, Conge $conge): JsonResponse
    {
        abort_if($conge->agent_id !== $request->user()->agent_id, 403, 'Cette demande ne vous appartient pas.');
        if ($conge->statut !== 'soumise') {
            return response()->json(['message' => 'Une demande déjà traitée ne peut plus être retirée.'], 422);
        }
        $conge->delete();

        return response()->json(['message' => 'Demande retirée.']);
    }

    public function avis(Request $request, Conge $conge): JsonResponse
    {
        Perimetre::exiger($request->user(), $conge->etablissement_id);
        if ($conge->statut !== 'soumise') {
            return response()->json(['message' => 'L\'avis a déjà été donné.'], 422);
        }
        $donnees = $request->validate(['favorable' => ['required', 'boolean'], 'commentaire' => ['nullable', 'string', 'max:500']]);
        $conge->update([
            'statut' => $donnees['favorable'] ? 'avis_favorable' : 'avis_defavorable',
            'avis_chef' => $donnees['commentaire'] ?? null,
            'avis_par' => $request->user()->id,
        ]);
        JournalActivite::consigner('avis', 'conges', 'Avis '.($donnees['favorable'] ? 'favorable' : 'défavorable')." sur le congé n°{$conge->id}", $conge);

        return response()->json(['data' => $conge->fresh($this->relations)]);
    }

    public function decider(Request $request, Conge $conge): JsonResponse
    {
        Perimetre::exiger($request->user(), $conge->etablissement_id);
        if (! in_array($conge->statut, ['soumise', 'avis_favorable', 'avis_defavorable'], true)) {
            return response()->json(['message' => 'Cette demande a déjà été tranchée.'], 422);
        }
        $donnees = $request->validate(['approuver' => ['required', 'boolean'], 'commentaire' => ['nullable', 'string', 'max:500']]);

        DB::transaction(function () use ($conge, $donnees, $request) {
            $conge->update([
                'statut' => $donnees['approuver'] ? 'approuvee' : 'rejetee',
                'commentaire_decision' => $donnees['commentaire'] ?? null,
                'decision_par' => $request->user()->id,
                'date_decision' => now(),
            ]);

            // Les jours de congé approuvés sont portés en « absence justifiée » sur la feuille de présence.
            if ($donnees['approuver'] && $conge->etablissement_id) {
                for ($d = $conge->date_debut->copy(); $d->lte($conge->date_fin); $d->addDay()) {
                    if (! \App\Support\Calendrier::estOuvre($d)) {
                        continue;
                    }
                    Presence::updateOrCreate(['agent_id' => $conge->agent_id, 'date' => $d->toDateString()], [
                        'etablissement_id' => $conge->etablissement_id,
                        'statut' => 'absence_justifiee',
                        'mode' => 'saisie',
                        'validation' => 'validee',
                        'validee_par' => $request->user()->id,
                        'date_validation' => now(),
                        'observations' => $conge->type_libelle,
                    ]);
                }
            }
        });
        JournalActivite::consigner('decision', 'conges', 'Congé n°'.$conge->id.' '.($donnees['approuver'] ? 'approuvé' : 'rejeté'), $conge);

        return response()->json(['data' => $conge->fresh($this->relations)]);
    }
}
