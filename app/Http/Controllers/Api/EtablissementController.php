<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\Etablissement;
use App\Models\JournalActivite;
use App\Models\Presence;
use App\Support\Perimetre;
use App\Support\Recherche;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EtablissementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Etablissement::query()->with('province:id,nom')->withCount(['agents as effectif_personnel' => fn ($a) => $a->where('statut', 'actif')]);
        Perimetre::filtrer($q, $request->user(), 'id', null);

        Recherche::appliquer($q, ['nom', 'code', 'ville'], $request->query('q'));
        foreach (['type', 'province_id'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }

        return response()->json($q->orderBy('nom')->paginate((int) $request->query('par_page', 15)));
    }

    public function show(Request $request, Etablissement $etablissement): JsonResponse
    {
        Perimetre::exiger($request->user(), $etablissement->id);
        $etablissement->load('province');

        $agents = $etablissement->agents()->with(['fonction:id,libelle', 'discipline:id,libelle', 'grade:id,code'])
            ->orderBy('nom')->get();

        $aujourdhui = now()->toDateString();
        $presencesJour = Presence::where('etablissement_id', $etablissement->id)->where('date', $aujourdhui)->get();

        return response()->json([
            'data' => $etablissement,
            'agents' => $agents,
            'indicateurs' => [
                'effectif' => $agents->where('statut', 'actif')->count(),
                'enseignants' => $agents->filter(fn ($a) => $a->estEnseignant() && $a->statut === 'actif')->count(),
                'femmes' => $agents->where('sexe', 'F')->where('statut', 'actif')->count(),
                'en_attente_prise_service' => Affectation::where('etablissement_id', $etablissement->id)->where('statut', 'en_attente_prise_service')->count(),
                'presents_aujourdhui' => $presencesJour->whereIn('statut', ['present', 'retard'])->count(),
                'absents_aujourdhui' => $presencesJour->where('statut', 'absent')->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $etab = Etablissement::create($this->valider($request));
        JournalActivite::consigner('creation', 'etablissements', 'Établissement créé : '.$etab->nom, $etab);

        return response()->json(['data' => $etab], 201);
    }

    public function update(Request $request, Etablissement $etablissement): JsonResponse
    {
        $etablissement->update($this->valider($request, $etablissement->id));
        JournalActivite::consigner('modification', 'etablissements', 'Établissement modifié : '.$etablissement->nom, $etablissement);

        return response()->json(['data' => $etablissement]);
    }

    public function destroy(Etablissement $etablissement): JsonResponse
    {
        if ($etablissement->agents()->exists() || Affectation::where('etablissement_id', $etablissement->id)->exists()) {
            return response()->json(['message' => 'Cet établissement a du personnel ou des affectations : désactivez-le plutôt.'], 422);
        }
        $etablissement->delete();
        JournalActivite::consigner('suppression', 'etablissements', 'Établissement supprimé : '.$etablissement->nom);

        return response()->json(['message' => 'Établissement supprimé.']);
    }

    protected function valider(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('etablissements', 'code')->ignore($id)],
            'nom' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(array_keys(Etablissement::TYPES))],
            'province_id' => ['required', 'exists:provinces,id'],
            'ville' => ['nullable', 'string', 'max:120'],
            'adresse' => ['nullable', 'string', 'max:200'],
            'telephone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'effectif_eleves' => ['nullable', 'integer', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'heure_debut' => ['nullable', 'date_format:H:i,H:i:s'],
            'heure_fin' => ['nullable', 'date_format:H:i,H:i:s'],
            'actif' => ['boolean'],
        ]);
    }
}
