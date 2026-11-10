<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\AnneeScolaire;
use App\Models\CessationService;
use App\Models\Conge;
use App\Models\DebutCours;
use App\Models\Etablissement;
use App\Models\Presence;
use App\Models\PriseService;
use App\Models\User;
use App\Support\Perimetre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableauBordController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->portee() === 'personnel') {
            return response()->json(['type' => 'personnel'] + $this->personnel($user));
        }

        $agents = Perimetre::filtrer(Agent::query(), $user, 'etablissement_id', 'id');
        $actifs = (clone $agents)->where('statut', 'actif');
        $aujourdhui = now()->toDateString();
        $presencesJour = Perimetre::filtrer(Presence::query(), $user)->where('date', $aujourdhui);
        $effectifActif = (clone $actifs)->count();
        $presentsJour = (clone $presencesJour)->whereIn('statut', ['present', 'retard', 'mission'])->count();

        $parCategorie = (clone $actifs)->selectRaw('categorie, COUNT(*) as total')->groupBy('categorie')->pluck('total', 'categorie');
        $parSexe = (clone $actifs)->selectRaw('sexe, COUNT(*) as total')->groupBy('sexe')->pluck('total', 'sexe');
        $parStatut = (clone $agents)->selectRaw('statut, COUNT(*) as total')->groupBy('statut')->pluck('total', 'statut');

        // Évolution des présences sur les 10 derniers jours ouvrés.
        $jours = collect();
        for ($d = now()->copy(); $jours->count() < 10; $d->subDay()) {
            if (! $d->isWeekend()) {
                $jours->prepend($d->toDateString());
            }
        }
        $presences10 = Perimetre::filtrer(Presence::query(), $user)->whereIn('date', $jours)
            ->selectRaw('date, statut, COUNT(*) as total')->groupBy('date', 'statut')->get();
        $evolution = $jours->map(function ($jour) use ($presences10) {
            $l = $presences10->filter(fn ($p) => substr((string) $p->date, 0, 10) === $jour);
            $total = (int) $l->sum('total');
            $presents = (int) $l->whereIn('statut', ['present', 'retard', 'mission'])->sum('total');

            return [
                'date' => $jour,
                'present' => (int) $l->where('statut', 'present')->sum('total'),
                'retard' => (int) $l->where('statut', 'retard')->sum('total'),
                'absent' => (int) $l->whereIn('statut', ['absent'])->sum('total'),
                'taux' => $total ? round($presents * 100 / $total, 1) : null,
            ];
        });

        $enAttentePs = Perimetre::filtrer(Affectation::query(), $user)->where('statut', 'en_attente_prise_service');

        return response()->json([
            'type' => 'gestion',
            'annee_scolaire' => AnneeScolaire::active()?->libelle,
            'indicateurs' => [
                'effectif_total' => (clone $agents)->count(),
                'effectif_actif' => $effectifActif,
                'enseignants' => (clone $actifs)->whereIn('categorie', ['enseignant_primaire', 'enseignant_secondaire'])->count(),
                'administratifs' => (clone $actifs)->where('categorie', 'administratif')->count(),
                'etablissements' => Perimetre::filtrer(Etablissement::query(), $user, 'id', null)->count(),
                'presents_aujourdhui' => $presentsJour,
                'absents_aujourdhui' => (clone $presencesJour)->where('statut', 'absent')->count(),
                'retards_aujourdhui' => (clone $presencesJour)->where('statut', 'retard')->count(),
                'non_pointes_aujourdhui' => max(0, $effectifActif - (clone $presencesJour)->count()),
                'taux_presence_jour' => $effectifActif ? round($presentsJour * 100 / $effectifActif, 1) : 0,
                'affectations_en_attente' => (clone $enAttentePs)->count(),
                'non_prise_service_30j' => (clone $enAttentePs)->where('date_effet', '<=', now()->subDays(30)->toDateString())->count(),
                'cessations_a_valider' => Perimetre::filtrer(CessationService::query(), $user)->where('statut', 'signalee')->count(),
                'conges_en_cours' => Perimetre::filtrer(Conge::query(), $user)->where('statut', 'approuvee')
                    ->where('date_debut', '<=', $aujourdhui)->where('date_fin', '>=', $aujourdhui)->count(),
            ],
            'repartition' => [
                'categories' => collect(Agent::CATEGORIES)->map(fn ($lib, $code) => ['code' => $code, 'libelle' => $lib, 'total' => (int) ($parCategorie[$code] ?? 0)])->values(),
                'sexe' => ['hommes' => (int) ($parSexe['M'] ?? 0), 'femmes' => (int) ($parSexe['F'] ?? 0)],
                'statuts' => collect(Agent::STATUTS)->map(fn ($lib, $code) => ['code' => $code, 'libelle' => $lib, 'total' => (int) ($parStatut[$code] ?? 0)])->values(),
            ],
            'evolution_presences' => $evolution,
            'a_traiter' => $this->compteurs($user),
            'dernieres_affectations' => Perimetre::filtrer(Affectation::query(), $user)
                ->with(['agent:id,nom,prenom,matricule', 'etablissement:id,nom'])->latest()->limit(6)->get(),
        ]);
    }

    /** Nombre d'éléments en attente de traitement, selon les habilitations (badges du menu latéral). */
    public function compteurs(User $user): array
    {
        $c = [];
        if ($user->aOption('PRISE_SERVICE_CONFIRMER')) {
            $c['PRISES_SERVICE'] = Perimetre::filtrer(PriseService::query(), $user)->where('statut', 'declaree')->count();
        }
        if ($user->aOption('DEBUT_COURS_CONFIRMER')) {
            $c['DEBUTS_COURS'] = Perimetre::filtrer(DebutCours::query(), $user)->where('statut', 'declare')->count();
        }
        if ($user->aOption('PRESENCE_VALIDER')) {
            $c['PRESENCES'] = Perimetre::filtrer(Presence::query(), $user)->where('validation', 'en_attente')->count();
        }
        if ($user->aOption('CESSATION_VALIDER')) {
            $c['CESSATIONS'] = Perimetre::filtrer(CessationService::query(), $user)->where('statut', 'signalee')->count();
        }
        if ($user->aOption('CONGE_AVIS')) {
            $c['CONGES'] = Perimetre::filtrer(Conge::query(), $user)->where('statut', 'soumise')->count();
        } elseif ($user->aOption('CONGE_APPROUVER')) {
            $c['CONGES'] = Perimetre::filtrer(Conge::query(), $user)->whereIn('statut', ['soumise', 'avis_favorable', 'avis_defavorable'])->count();
        }
        if ($user->aOption('PRISE_SERVICE_DECLARER') && $user->agent_id) {
            $c['MES_SERVICES'] = Affectation::where('agent_id', $user->agent_id)->where('statut', 'en_attente_prise_service')
                ->whereDoesntHave('prisesService', fn ($p) => $p->whereIn('statut', ['declaree', 'confirmee']))->count();
        }

        return $c;
    }

    public function badges(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->compteurs($request->user())]);
    }

    protected function personnel(User $user): array
    {
        $agent = $user->agent_id ? Agent::with(['etablissement:id,nom,heure_debut,heure_fin', 'fonction:id,libelle', 'discipline:id,libelle', 'grade:id,code,libelle', 'affectationCourante.etablissement:id,nom'])->find($user->agent_id) : null;
        if (! $agent) {
            return ['agent' => null];
        }
        $mois = Presence::where('agent_id', $agent->id)->where('date', '>=', now()->startOfMonth()->toDateString())->get();

        return [
            'agent' => $agent,
            'aujourdhui' => Presence::where('agent_id', $agent->id)->where('date', now()->toDateString())->first(),
            'synthese_mois' => PresenceController::synthese($mois),
            'jours_ouvres_mois' => PresenceController::joursOuvres(now()->startOfMonth(), now()),
            'affectation' => $agent->affectationCourante,
            'prise_service' => PriseService::where('agent_id', $agent->id)->latest()->first(),
            'debut_cours' => DebutCours::where('agent_id', $agent->id)->latest()->first(),
            'conges' => Conge::where('agent_id', $agent->id)->latest()->limit(3)->get(),
            'a_traiter' => $this->compteurs($user),
        ];
    }
}
