<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Etablissement;
use App\Models\JournalActivite;
use App\Models\Presence;
use App\Support\Perimetre;
use App\Support\Recherche;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PresenceController extends Controller
{
    /** Tolérance (minutes) avant qu'une arrivée soit comptée en retard. */
    public const TOLERANCE_RETARD = 15;

    protected array $relations = ['agent:id,matricule,nom,prenom,sexe,categorie,fonction_id', 'agent.fonction:id,libelle', 'etablissement:id,nom', 'valideur:id,name'];

    /** Pointage par l'agent lui-même : arrivée puis départ. */
    public function pointer(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user->agent_id, 422, 'Votre compte n\'est rattaché à aucun dossier agent.');
        $donnees = $request->validate([
            'type' => ['required', Rule::in(['arrivee', 'depart'])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'observations' => ['nullable', 'string', 'max:250'],
        ]);

        $agent = Agent::with('etablissement')->findOrFail($user->agent_id);
        if (! $agent->etablissement_id || $agent->statut !== 'actif') {
            return response()->json(['message' => 'Vous n\'avez pas de poste actif : le pointage est impossible.'], 422);
        }
        $enService = $agent->affectations()->where('statut', 'en_service')->where('etablissement_id', $agent->etablissement_id)->exists();
        if (! $enService) {
            return response()->json(['message' => 'Votre prise de service à '.$agent->etablissement->nom.' doit d\'abord être confirmée pour pouvoir pointer.'], 422);
        }

        $maintenant = now();
        $aujourdhui = $maintenant->toDateString();
        $presence = Presence::where('agent_id', $agent->id)->where('date', $aujourdhui)->first();

        if ($donnees['type'] === 'arrivee') {
            if ($presence && $presence->heure_arrivee) {
                return response()->json(['message' => 'Votre arrivée est déjà pointée aujourd\'hui à '.substr($presence->heure_arrivee, 0, 5).'.'], 422);
            }
            $limite = Carbon::parse($aujourdhui.' '.($agent->etablissement->heure_debut ?? '07:30:00'))->addMinutes((int) config("grhmen.tolerance_retard", self::TOLERANCE_RETARD));
            $presence = Presence::updateOrCreate(
                ['agent_id' => $agent->id, 'date' => $aujourdhui],
                [
                    'etablissement_id' => $agent->etablissement_id,
                    'heure_arrivee' => $maintenant->format('H:i:s'),
                    'statut' => $maintenant->greaterThan($limite) ? 'retard' : 'present',
                    'mode' => 'pointage',
                    'latitude' => $donnees['latitude'] ?? null,
                    'longitude' => $donnees['longitude'] ?? null,
                    'validation' => 'en_attente',
                    'saisie_par' => $user->id,
                    'observations' => $donnees['observations'] ?? null,
                ]
            );
            JournalActivite::consigner('pointage', 'presences', "Arrivée pointée par {$agent->nom_complet}", $presence);
        } else {
            if (! $presence || ! $presence->heure_arrivee) {
                return response()->json(['message' => 'Pointez d\'abord votre arrivée.'], 422);
            }
            if ($presence->heure_depart) {
                return response()->json(['message' => 'Votre départ est déjà pointé aujourd\'hui.'], 422);
            }
            $presence->update(['heure_depart' => $maintenant->format('H:i:s')]);
            JournalActivite::consigner('pointage', 'presences', "Départ pointé par {$agent->nom_complet}", $presence);
        }

        return response()->json(['data' => $presence->fresh($this->relations)]);
    }

    /** Historique de pointage de l'agent connecté, par mois. */
    public function moi(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user->agent_id, 422, 'Votre compte n\'est rattaché à aucun dossier agent.');
        $mois = $request->query('mois', now()->format('Y-m'));
        $debut = Carbon::parse($mois.'-01')->startOfMonth();
        $fin = $debut->copy()->endOfMonth();

        $presences = Presence::where('agent_id', $user->agent_id)->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->orderByDesc('date')->get();

        return response()->json([
            'aujourdhui' => Presence::where('agent_id', $user->agent_id)->where('date', now()->toDateString())->first(),
            'agent' => Agent::with('etablissement:id,nom,heure_debut,heure_fin')->find($user->agent_id),
            'data' => $presences,
            'synthese' => self::synthese($presences),
            'jours_ouvres' => self::joursOuvres($debut, $fin->min(now())),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $q = Presence::query()->with($this->relations);
        Perimetre::filtrer($q, $request->user());
        Recherche::appliquer($q, ['agent.nom', 'agent.prenom', 'agent.matricule'], $request->query('q'));
        foreach (['statut', 'validation', 'etablissement_id', 'agent_id', 'mode'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        if ($d = $request->query('date_debut')) {
            $q->where('date', '>=', $d);
        }
        if ($d = $request->query('date_fin')) {
            $q->where('date', '<=', $d);
        }

        return response()->json($q->orderByDesc('date')->orderBy('heure_arrivee')->paginate((int) $request->query('par_page', 20)));
    }

    /** Feuille de présence d'un établissement pour une date : tous les agents actifs et leur situation. */
    public function feuille(Request $request): JsonResponse
    {
        $user = $request->user();
        $date = $request->query('date', now()->toDateString());
        $etabId = (int) ($request->query('etablissement_id') ?: $user->etablissement_id);
        abort_if(! $etabId, 422, 'Choisissez un établissement.');
        Perimetre::exiger($user, $etabId);

        // Seuls les agents ayant effectivement pris service dans l'établissement figurent sur la feuille.
        $agents = Agent::with(['fonction:id,libelle', 'discipline:id,libelle'])->where('etablissement_id', $etabId)
            ->where('statut', 'actif')
            ->whereHas('affectations', fn ($a) => $a->where('statut', 'en_service')->where('etablissement_id', $etabId))
            ->orderBy('nom')->orderBy('prenom')->get();
        $presences = Presence::where('etablissement_id', $etabId)->where('date', $date)->get()->keyBy('agent_id');

        $lignes = $agents->map(fn (Agent $a) => ['agent' => $a, 'presence' => $presences->get($a->id)]);

        return response()->json([
            'date' => $date,
            'etablissement' => Etablissement::find($etabId, ['id', 'nom', 'heure_debut', 'heure_fin']),
            'lignes' => $lignes,
            'synthese' => self::synthese($presences->values()) + ['non_renseigne' => $agents->count() - $presences->count(), 'effectif' => $agents->count()],
        ]);
    }

    /** Saisie de la feuille de présence par le censeur ou le chef d'établissement (attestation). */
    public function saisir(Request $request): JsonResponse
    {
        $user = $request->user();
        $donnees = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'etablissement_id' => ['required', 'exists:etablissements,id'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.agent_id' => ['required', 'exists:agents,id'],
            'lignes.*.statut' => ['required', Rule::in(array_keys(Presence::STATUTS))],
            'lignes.*.heure_arrivee' => ['nullable', 'date_format:H:i,H:i:s'],
            'lignes.*.observations' => ['nullable', 'string', 'max:250'],
        ]);
        Perimetre::exiger($user, (int) $donnees['etablissement_id']);

        $nb = 0;
        DB::transaction(function () use ($donnees, $user, &$nb) {
            foreach ($donnees['lignes'] as $ligne) {
                $agent = Agent::find($ligne['agent_id']);
                if ((int) $agent->etablissement_id !== (int) $donnees['etablissement_id']) {
                    continue;
                }
                $existante = Presence::where('agent_id', $agent->id)->where('date', $donnees['date'])->first();
                $absent = in_array($ligne['statut'], ['absent', 'absence_justifiee', 'mission'], true);
                Presence::updateOrCreate(
                    ['agent_id' => $agent->id, 'date' => $donnees['date']],
                    [
                        'etablissement_id' => $agent->etablissement_id,
                        'statut' => $ligne['statut'],
                        'heure_arrivee' => $absent ? null : ($ligne['heure_arrivee'] ?? $existante?->heure_arrivee ?? '07:30:00'),
                        'mode' => $existante?->mode === 'pointage' ? 'pointage' : 'saisie',
                        'validation' => 'validee',
                        'saisie_par' => $existante?->saisie_par ?? $user->id,
                        'validee_par' => $user->id,
                        'date_validation' => now(),
                        'observations' => $ligne['observations'] ?? $existante?->observations,
                    ]
                );
                $nb++;
            }
        });

        $etab = Etablissement::find($donnees['etablissement_id']);
        JournalActivite::consigner('saisie', 'presences', "Feuille de présence du {$donnees['date']} saisie pour {$etab->nom} ($nb agents)", $etab);

        return response()->json(['message' => "$nb présence(s) enregistrée(s) et attestée(s).", 'nombre' => $nb]);
    }

    public function valider(Request $request): JsonResponse
    {
        $user = $request->user();
        $donnees = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'decision' => ['required', Rule::in(['validee', 'rejetee'])],
            'observations' => ['nullable', 'string', 'max:250'],
        ]);

        $q = Presence::whereIn('id', $donnees['ids'])->where('validation', 'en_attente');
        Perimetre::filtrer($q, $user);
        $presences = $q->get();
        foreach ($presences as $p) {
            $p->update([
                'validation' => $donnees['decision'],
                'validee_par' => $user->id,
                'date_validation' => now(),
                'statut' => $donnees['decision'] === 'rejetee' ? 'absent' : $p->statut,
                'observations' => $donnees['observations'] ?? $p->observations,
            ]);
        }
        JournalActivite::consigner('validation', 'presences', $presences->count().' présence(s) '.($donnees['decision'] === 'validee' ? 'validée(s)' : 'rejetée(s)'));

        return response()->json(['message' => $presences->count().' présence(s) traitée(s).', 'nombre' => $presences->count()]);
    }

    /** Taux de présence par établissement sur une période. */
    public function statistiques(Request $request): JsonResponse
    {
        $debut = $request->query('date_debut', now()->startOfMonth()->toDateString());
        $fin = $request->query('date_fin', now()->toDateString());

        $q = Presence::query()->whereBetween('date', [$debut, $fin]);
        Perimetre::filtrer($q, $request->user());
        $lignes = $q->select('etablissement_id', 'statut', DB::raw('COUNT(*) as total'))->groupBy('etablissement_id', 'statut')->get();

        $etabs = Etablissement::whereIn('id', $lignes->pluck('etablissement_id')->unique())->pluck('nom', 'id');
        $parEtab = $lignes->groupBy('etablissement_id')->map(function ($groupe, $id) use ($etabs) {
            $totaux = $groupe->pluck('total', 'statut')->map(fn ($v) => (int) $v);
            $total = $totaux->sum();
            $presents = ($totaux['present'] ?? 0) + ($totaux['retard'] ?? 0) + ($totaux['mission'] ?? 0);

            return [
                'etablissement_id' => (int) $id,
                'etablissement' => $etabs[$id] ?? '?',
                'total' => $total,
                'present' => $totaux['present'] ?? 0,
                'retard' => $totaux['retard'] ?? 0,
                'absent' => $totaux['absent'] ?? 0,
                'absence_justifiee' => $totaux['absence_justifiee'] ?? 0,
                'mission' => $totaux['mission'] ?? 0,
                'taux_presence' => $total ? round($presents * 100 / $total, 1) : 0,
            ];
        })->sortBy('taux_presence')->values();

        return response()->json(['date_debut' => $debut, 'date_fin' => $fin, 'data' => $parEtab]);
    }

    public static function synthese($presences): array
    {
        $total = $presences->count();
        $presents = $presences->whereIn('statut', ['present', 'retard', 'mission'])->count();

        return [
            'present' => $presences->where('statut', 'present')->count(),
            'retard' => $presences->where('statut', 'retard')->count(),
            'absent' => $presences->where('statut', 'absent')->count(),
            'absence_justifiee' => $presences->where('statut', 'absence_justifiee')->count(),
            'mission' => $presences->where('statut', 'mission')->count(),
            'en_attente' => $presences->where('validation', 'en_attente')->count(),
            'taux_presence' => $total ? round($presents * 100 / $total, 1) : 0,
        ];
    }

    public static function joursOuvres(Carbon $debut, Carbon $fin): int
    {
        return \App\Support\Calendrier::joursOuvres($debut, $fin);
    }
}
