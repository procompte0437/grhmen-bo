<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\CessationService;
use App\Models\Etablissement;
use App\Models\Presence;
use App\Models\Province;
use App\Support\Perimetre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RapportController extends Controller
{
    public function synthese(Request $request): JsonResponse
    {
        $user = $request->user();
        $debut = $request->query('date_debut', now()->startOfMonth()->toDateString());
        $fin = $request->query('date_fin', now()->toDateString());

        $agents = Perimetre::filtrer(Agent::query(), $user, 'etablissement_id', 'id')->where('statut', 'actif')
            ->with(['etablissement:id,province_id,type', 'discipline:id,libelle', 'grade:id,code'])->get();

        $provinces = Province::orderBy('nom')->get();
        $parProvince = $provinces->map(function ($p) use ($agents) {
            $l = $agents->filter(fn ($a) => $a->etablissement?->province_id === $p->id);

            return [
                'province' => $p->nom,
                'total' => $l->count(),
                'enseignants' => $l->filter->estEnseignant()->count(),
                'administratifs' => $l->where('categorie', 'administratif')->count(),
                'femmes' => $l->where('sexe', 'F')->count(),
            ];
        })->filter(fn ($l) => $l['total'] > 0)->values();

        $parDiscipline = $agents->filter->estEnseignant()->groupBy(fn ($a) => $a->discipline?->libelle ?? 'Non renseignée')
            ->map(fn ($g, $k) => ['discipline' => $k, 'total' => $g->count()])->sortByDesc('total')->values();

        $parGrade = $agents->groupBy(fn ($a) => $a->grade?->code ?? 'N/R')->map(fn ($g, $k) => ['grade' => $k, 'total' => $g->count()])
            ->sortBy('grade')->values();

        $tranches = ['< 30 ans' => [0, 29], '30 - 39 ans' => [30, 39], '40 - 49 ans' => [40, 49], '50 - 54 ans' => [50, 54], '55 ans et +' => [55, 200]];
        $pyramide = collect($tranches)->map(function ($b, $lib) use ($agents) {
            $l = $agents->filter(fn ($a) => $a->date_naissance && $a->date_naissance->age >= $b[0] && $a->date_naissance->age <= $b[1]);

            return ['tranche' => $lib, 'hommes' => $l->where('sexe', 'M')->count(), 'femmes' => $l->where('sexe', 'F')->count()];
        })->values();

        $departsRetraite = Perimetre::filtrer(Agent::query(), $user, 'etablissement_id', 'id')->where('statut', 'actif')
            ->whereNotNull('date_naissance')->where('date_naissance', '<=', now()->subYears(59)->toDateString())
            ->with('etablissement:id,nom')->orderBy('date_naissance')->limit(50)->get(['id', 'matricule', 'nom', 'prenom', 'date_naissance', 'etablissement_id']);

        $presences = Perimetre::filtrer(Presence::query(), $user)->whereBetween('date', [$debut, $fin])
            ->selectRaw('statut, COUNT(*) as total')->groupBy('statut')->pluck('total', 'statut')->map(fn ($v) => (int) $v);
        $totalP = $presences->sum();

        $nonPrise = Perimetre::filtrer(Affectation::query(), $user)->with(['agent:id,matricule,nom,prenom', 'etablissement:id,nom'])
            ->where('statut', 'en_attente_prise_service')->orderBy('date_effet')->limit(100)->get();

        $cessations = Perimetre::filtrer(CessationService::query(), $user)->whereBetween('date_cessation', [now()->startOfYear()->toDateString(), now()->toDateString()])
            ->selectRaw('type, statut, COUNT(*) as total')->groupBy('type', 'statut')->get()
            ->map(fn ($c) => ['type' => CessationService::TYPES[$c->type] ?? $c->type, 'statut' => $c->statut, 'total' => (int) $c->total]);

        $etabs = Perimetre::filtrer(Etablissement::query(), $user, 'id', null)->withCount(['agents as effectif' => fn ($a) => $a->where('statut', 'actif')])
            ->get(['id', 'nom', 'type', 'effectif_eleves'])
            ->map(fn ($e) => [
                'etablissement' => $e->nom,
                'type' => $e->type_libelle,
                'effectif' => $e->effectif,
                'eleves' => $e->effectif_eleves,
                'ratio' => $e->effectif && $e->effectif_eleves ? round($e->effectif_eleves / $e->effectif, 1) : null,
            ])->sortByDesc('effectif')->values();

        return response()->json([
            'periode' => ['debut' => $debut, 'fin' => $fin],
            'effectif_actif' => $agents->count(),
            'par_province' => $parProvince,
            'par_discipline' => $parDiscipline,
            'par_grade' => $parGrade,
            'pyramide_ages' => $pyramide,
            'departs_retraite' => $departsRetraite,
            'presences' => [
                'totaux' => $presences,
                'taux_presence' => $totalP ? round((($presences['present'] ?? 0) + ($presences['retard'] ?? 0) + ($presences['mission'] ?? 0)) * 100 / $totalP, 1) : 0,
                'taux_absence' => $totalP ? round(($presences['absent'] ?? 0) * 100 / $totalP, 1) : 0,
            ],
            'non_prise_service' => $nonPrise,
            'cessations_annee' => $cessations,
            'etablissements' => $etabs,
        ]);
    }
}
