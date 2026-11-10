<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\AnneeScolaire;
use App\Models\CessationService;
use App\Models\Conge;
use App\Models\Corps;
use App\Models\Discipline;
use App\Models\Etablissement;
use App\Models\Fonction;
use App\Models\Grade;
use App\Models\JournalActivite;
use App\Models\Presence;
use App\Models\Profil;
use App\Models\Province;
use App\Support\Perimetre;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReferentielController extends Controller
{
    /** Référentiels administrables : type d'URL => [modèle, règles, libellé]. */
    protected function definitions(): array
    {
        return [
            'provinces' => [Province::class, ['code' => 'required|string|max:10', 'nom' => 'required|string|max:120', 'chef_lieu' => 'nullable|string|max:120'], 'Province'],
            'corps' => [Corps::class, ['code' => 'required|string|max:20', 'libelle' => 'required|string|max:160', 'categorie' => ['required', Rule::in(array_keys(Agent::CATEGORIES))]], 'Corps'],
            'grades' => [Grade::class, ['code' => 'required|string|max:20', 'libelle' => 'required|string|max:160', 'hierarchie' => 'nullable|string|max:5', 'indice_base' => 'nullable|integer|min:0|max:9999'], 'Grade'],
            'disciplines' => [Discipline::class, ['code' => 'required|string|max:20', 'libelle' => 'required|string|max:160'], 'Discipline'],
            'fonctions' => [Fonction::class, ['code' => 'required|string|max:30', 'libelle' => 'required|string|max:160', 'enseignante' => 'boolean'], 'Fonction'],
            'annees-scolaires' => [AnneeScolaire::class, ['libelle' => 'required|string|max:20', 'date_debut' => 'required|date', 'date_fin' => 'required|date|after:date_debut', 'active' => 'boolean'], 'Année scolaire'],
        ];
    }

    protected function definition(string $type): array
    {
        $def = $this->definitions()[$type] ?? null;
        abort_if(! $def, 404, 'Référentiel inconnu.');

        return $def;
    }

    /** Toutes les listes utiles aux formulaires (accessibles à tout utilisateur connecté). */
    public function listes(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('agent:id,etablissement_id');
        if ($user->portee() === 'personnel') {
            $rattache = $user->etablissement_id ?: $user->agent?->etablissement_id;
            $ids = $rattache ? [$rattache] : [];
        } else {
            $ids = Perimetre::etablissementIds($user);
        }
        $etabs = Etablissement::query()->where('actif', true)->orderBy('nom');
        if ($ids !== null) {
            $etabs->whereIn('id', $ids ?: [0]);
        }
        $etablissements = $etabs->get(['id', 'code', 'nom', 'type', 'province_id', 'ville']);
        $provinces = Province::orderBy('nom')->get(['id', 'code', 'nom', 'chef_lieu']);
        if ($ids !== null) {
            $pids = $etablissements->pluck('province_id')->unique()->filter()->all();
            $provinces = $provinces->whereIn('id', $pids ?: [0])->values();
        }

        return response()->json([
            'provinces' => $provinces,
            'corps' => Corps::orderBy('libelle')->get(['id', 'code', 'libelle', 'categorie']),
            'grades' => Grade::orderBy('code')->get(['id', 'code', 'libelle', 'hierarchie']),
            'disciplines' => Discipline::orderBy('libelle')->get(['id', 'code', 'libelle']),
            'fonctions' => Fonction::orderBy('libelle')->get(['id', 'code', 'libelle', 'enseignante']),
            'annees_scolaires' => AnneeScolaire::orderByDesc('date_debut')->get(['id', 'libelle', 'active', 'date_debut', 'date_fin']),
            'etablissements' => $etablissements,
            'profils' => Profil::orderBy('libelle')->get(['id', 'code', 'libelle', 'portee']),
            'ministere' => config('grhmen.ministere'),
            'feries' => \App\Support\Calendrier::feriesEntre(now()->startOfYear(), now()->addYear()->endOfYear()),
            'enums' => [
                'categories' => Agent::CATEGORIES,
                'statuts_agent' => Agent::STATUTS,
                'types_etablissement' => Etablissement::TYPES,
                'natures_affectation' => Affectation::NATURES,
                'statuts_affectation' => Affectation::STATUTS,
                'statuts_presence' => Presence::STATUTS,
                'types_cessation' => CessationService::TYPES,
                'types_conge' => Conge::TYPES,
                'statuts_conge' => Conge::STATUTS,
                'portees' => [
                    'national' => 'Nationale (tout le ministère)',
                    'provincial' => 'Provinciale (académie)',
                    'etablissement' => 'Établissement',
                    'personnel' => 'Personnelle (ses propres données)',
                ],
            ],
        ]);
    }

    public function index(string $type): JsonResponse
    {
        [$modele] = $this->definition($type);

        return response()->json(['data' => $modele::query()->orderBy($type === 'annees-scolaires' ? 'date_debut' : 'id')->get()]);
    }

    public function store(Request $request, string $type): JsonResponse
    {
        [$modele, $regles, $libelle] = $this->definition($type);
        $donnees = $request->validate($this->reglesUniques($regles, $modele));
        $element = $modele::create($donnees);
        $this->activerAnneeUnique($element);
        JournalActivite::consigner('creation', 'referentiels', "$libelle créé(e) : ".($element->libelle ?? $element->nom), $element);

        return response()->json(['data' => $element], 201);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        [$modele, $regles, $libelle] = $this->definition($type);
        $element = $modele::findOrFail($id);
        $donnees = $request->validate($this->reglesUniques($regles, $modele, $id));
        $element->update($donnees);
        $this->activerAnneeUnique($element);
        JournalActivite::consigner('modification', 'referentiels', "$libelle modifié(e) : ".($element->libelle ?? $element->nom), $element);

        return response()->json(['data' => $element]);
    }

    public function destroy(string $type, int $id): JsonResponse
    {
        [$modele, , $libelle] = $this->definition($type);
        $element = $modele::findOrFail($id);

        try {
            $element->delete();
        } catch (QueryException) {
            return response()->json(['message' => "Ce(tte) $libelle est utilisé(e) et ne peut pas être supprimé(e)."], 422);
        }

        JournalActivite::consigner('suppression', 'referentiels', "$libelle supprimé(e) : ".($element->libelle ?? $element->nom), $element);

        return response()->json(['message' => 'Supprimé.']);
    }

    protected function reglesUniques(array $regles, string $modele, ?int $id = null): array
    {
        $table = (new $modele)->getTable();
        $champ = isset($regles['code']) ? 'code' : 'libelle';
        $regles[$champ] = array_merge((array) (is_string($regles[$champ]) ? explode('|', $regles[$champ]) : $regles[$champ]), [Rule::unique($table, $champ)->ignore($id)]);

        return $regles;
    }

    protected function activerAnneeUnique($element): void
    {
        if ($element instanceof AnneeScolaire && $element->active) {
            AnneeScolaire::where('id', '!=', $element->id)->update(['active' => false]);
        }
    }
}
