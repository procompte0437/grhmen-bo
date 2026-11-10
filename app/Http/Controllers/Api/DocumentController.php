<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\CessationService;
use App\Models\Conge;
use App\Models\DebutCours;
use App\Models\Etablissement;
use App\Models\JournalActivite;
use App\Models\Presence;
use App\Models\PriseService;
use App\Models\User;
use App\Support\Calendrier;
use App\Support\Perimetre;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Données des documents administratifs officiels (fiches et certificats à en-tête du ministère).
 * Chaque type vérifie l'habilitation et le périmètre avant de renvoyer les données à imprimer.
 */
class DocumentController extends Controller
{
    /** Types de documents : options d'accès qui autorisent leur édition. */
    public const TYPES = [
        'prise-service' => ['PRISES_SERVICE', 'MES_SERVICES'],
        'debut-cours' => ['DEBUTS_COURS', 'MES_SERVICES'],
        'affectation' => ['AFFECTATIONS', 'MES_SERVICES', 'PERSONNEL'],
        'conge' => ['CONGES', 'MES_CONGES'],
        'cessation' => ['CESSATIONS', 'PERSONNEL'],
        'retraite' => ['PERSONNEL', 'CESSATIONS', 'MON_DOSSIER'],
        'attestation-travail' => ['PERSONNEL', 'MON_DOSSIER'],
        'fiche-agent' => ['PERSONNEL', 'MON_DOSSIER'],
        'etat-absences' => ['PRESENCES', 'RAPPORTS'],
    ];

    protected array $agentComplet = ['agent.grade:id,code,libelle', 'agent.corps:id,libelle', 'agent.fonction:id,libelle', 'agent.discipline:id,libelle'];

    public function show(Request $request, string $type, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless(isset(self::TYPES[$type]), 404, 'Document inconnu.');
        abort_unless($user->aUneOption(self::TYPES[$type]), 403, 'Vous n\'avez pas l\'habilitation requise pour éditer ce document.');

        $donnees = match ($type) {
            'prise-service' => $this->priseService($user, $id),
            'debut-cours' => $this->debutCours($user, $id),
            'affectation' => $this->affectation($user, $id),
            'conge' => $this->conge($user, $id),
            'cessation' => $this->cessation($user, $id),
            'retraite' => $this->retraite($user, $id),
            'attestation-travail', 'fiche-agent' => $this->agent($user, $id),
            'etat-absences' => $this->etatAbsences($request, $user, $id),
        };

        JournalActivite::consigner('edition', 'documents', 'Édition du document « '.$type.' » n°'.$id);

        return response()->json(['type' => $type, 'ministere' => config('grhmen.ministere'), 'data' => $donnees]);
    }

    protected function priseService(User $user, int $id): PriseService
    {
        $p = PriseService::with(array_merge(['etablissement', 'etablissement.province:id,nom', 'affectation.etablissementOrigine:id,nom', 'valideur:id,name', 'declarant:id,name'], $this->agentComplet, ['agent']))->findOrFail($id);
        Perimetre::exiger($user, $p->etablissement_id, $p->agent_id);

        return $p;
    }

    protected function debutCours(User $user, int $id): DebutCours
    {
        $d = DebutCours::with(array_merge(['agent', 'etablissement', 'discipline:id,libelle', 'valideur:id,name'], $this->agentComplet))->findOrFail($id);
        Perimetre::exiger($user, $d->etablissement_id, $d->agent_id);
        $d->setAttribute('annee', $d->annee_scolaire_id ? \App\Models\AnneeScolaire::find($d->annee_scolaire_id)?->libelle : null);

        return $d;
    }

    protected function affectation(User $user, int $id): Affectation
    {
        $a = Affectation::with(array_merge(['agent', 'etablissement', 'etablissement.province:id,nom', 'etablissementOrigine:id,nom', 'fonction:id,libelle', 'discipline:id,libelle', 'anneeScolaire:id,libelle', 'auteur:id,name'], $this->agentComplet))->findOrFail($id);
        Perimetre::exiger($user, $a->etablissement_id, $a->agent_id);

        return $a;
    }

    protected function conge(User $user, int $id): Conge
    {
        $c = Conge::with(array_merge(['agent', 'etablissement', 'etablissement.province:id,nom'], $this->agentComplet))->findOrFail($id);
        Perimetre::exiger($user, $c->etablissement_id, $c->agent_id);
        $c->setAttribute('avis_nom', $c->avis_par ? User::find($c->avis_par)?->name : null);
        $c->setAttribute('decision_nom', $c->decision_par ? User::find($c->decision_par)?->name : null);
        $c->setAttribute('date_reprise', $this->prochainJourOuvre(Carbon::parse($c->date_fin)));

        return $c;
    }

    protected function cessation(User $user, int $id): CessationService
    {
        $c = CessationService::with(array_merge(['agent', 'etablissement', 'etablissement.province:id,nom', 'affectation:id,nature,numero_decision,date_effet', 'signaleur:id,name', 'valideur:id,name'], $this->agentComplet))->findOrFail($id);
        Perimetre::exiger($user, $c->etablissement_id, $c->agent_id);

        return $c;
    }

    /** Notification de départ à la retraite : date calculée selon la limite d'âge, ou cessation « retraite » si elle existe. */
    protected function retraite(User $user, int $id): array
    {
        $agent = Agent::withTrashed()->with(['grade:id,code,libelle', 'corps:id,libelle', 'fonction:id,libelle', 'discipline:id,libelle', 'etablissement', 'etablissement.province:id,nom'])->findOrFail($id);
        $this->exigerAgent($user, $agent);
        abort_if(! $agent->date_naissance && ! $agent->date_depart_retraite, 422, 'La date de naissance de l\'agent est nécessaire pour établir la notification de retraite.');

        $cessation = CessationService::where('agent_id', $agent->id)->where('type', 'retraite')->whereIn('statut', ['signalee', 'validee'])->latest('date_cessation')->first();
        $limite = $agent->date_naissance?->copy()->addYears(Agent::AGE_LIMITE_RETRAITE);
        $date = $agent->date_depart_retraite ?? $cessation?->date_cessation ?? $limite;

        return [
            'agent' => $agent,
            'age_limite' => Agent::AGE_LIMITE_RETRAITE,
            'date_limite_age' => $limite?->toDateString(),
            'date_depart' => $date?->toDateString(),
            'cessation' => $cessation,
            'anciennete' => $agent->date_recrutement ? $agent->date_recrutement->diff($date ?? now())->y : null,
        ];
    }

    protected function agent(User $user, int $id): array
    {
        $agent = Agent::withTrashed()->with(['grade:id,code,libelle', 'corps:id,libelle', 'fonction:id,libelle', 'discipline:id,libelle', 'etablissement', 'etablissement.province:id,nom',
            'affectations.etablissement:id,nom', 'affectations.fonction:id,libelle'])->findOrFail($id);
        $this->exigerAgent($user, $agent);
        $priseInitiale = PriseService::where('agent_id', $agent->id)->where('statut', 'confirmee')->orderBy('date_prise_service')->first();

        return [
            'agent' => $agent,
            'affectation_courante' => $agent->affectationCourante()->with('etablissement:id,nom')->first(),
            'premiere_prise_service' => $priseInitiale?->date_prise_service?->toDateString(),
            'anciennete' => $agent->date_recrutement ? $agent->date_recrutement->diff(now())->y : null,
        ];
    }

    /** État mensuel des absences au poste d'un établissement (note circulaire : transmission avant le 30 de chaque mois). */
    protected function etatAbsences(Request $request, User $user, int $etablissementId): array
    {
        $etab = Etablissement::with('province:id,nom')->findOrFail($etablissementId);
        Perimetre::exiger($user, $etab->id);
        $mois = $request->query('mois', now()->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $mois), 422, 'Mois invalide (format AAAA-MM).');
        $debut = Carbon::parse($mois.'-01')->startOfMonth();
        $fin = $debut->copy()->endOfMonth();

        $agents = Agent::with(['fonction:id,libelle', 'grade:id,code', 'discipline:id,libelle'])->where('etablissement_id', $etab->id)
            ->where('statut', 'actif')->orderBy('nom')->orderBy('prenom')->get();
        $presences = Presence::where('etablissement_id', $etab->id)->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->get()->groupBy('agent_id');

        $lignes = $agents->map(function (Agent $a) use ($presences) {
            $p = $presences->get($a->id, collect());
            $absences = $p->where('statut', 'absent');

            return [
                'agent' => $a,
                'present' => $p->where('statut', 'present')->count(),
                'retard' => $p->where('statut', 'retard')->count(),
                'absent' => $absences->count(),
                'absence_justifiee' => $p->whereIn('statut', ['absence_justifiee', 'mission'])->count(),
                'dates_absence' => $absences->map(fn ($x) => $x->date->format('d/m'))->sort()->values(),
            ];
        })->values();

        return [
            'etablissement' => $etab,
            'mois' => $mois,
            'libelle_mois' => ucfirst($debut->locale('fr')->translatedFormat('F Y')),
            'jours_ouvres' => Calendrier::joursOuvres($debut, $fin),
            'feries' => Calendrier::feriesEntre($debut, $fin),
            'lignes' => $lignes,
            'totaux' => [
                'effectif' => $agents->count(),
                'absences' => $lignes->sum('absent'),
                'retards' => $lignes->sum('retard'),
                'agents_absents' => $lignes->where('absent', '>', 0)->count(),
            ],
            'date_limite_transmission' => $debut->copy()->day(min(30, $debut->daysInMonth))->toDateString(),
        ];
    }

    protected function exigerAgent(User $user, Agent $agent): void
    {
        if ($user->portee() === 'personnel' || ! $user->aUneOption(['PERSONNEL', 'CESSATIONS'])) {
            abort_unless($agent->id === $user->agent_id, 403, 'Vous ne pouvez éditer que vos propres documents.');

            return;
        }
        Perimetre::exiger($user, $agent->etablissement_id, $agent->id);
    }

    protected function prochainJourOuvre(Carbon $jour): string
    {
        $d = $jour->copy()->addDay();
        while (! Calendrier::estOuvre($d)) {
            $d->addDay();
        }

        return $d->toDateString();
    }
}
