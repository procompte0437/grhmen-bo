<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\JournalActivite;
use App\Models\Presence;
use App\Models\Profil;
use App\Models\User;
use App\Support\Perimetre;
use App\Support\Recherche;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AgentController extends Controller
{
    protected array $relationsListe = ['fonction:id,libelle', 'discipline:id,libelle', 'grade:id,code,libelle', 'corps:id,libelle', 'etablissement:id,nom,province_id', 'etablissement.province:id,nom'];

    public function index(Request $request): JsonResponse
    {
        $q = Agent::query()->with($this->relationsListe)->withExists('utilisateur as a_un_compte');
        Perimetre::selonEtablissement($q, $request->user(), 'etablissement_id', 'id');
        Recherche::appliquer($q, ['nom', 'prenom', 'matricule', 'telephone', 'email'], $request->query('q'));

        foreach (['categorie', 'statut', 'etablissement_id', 'discipline_id', 'fonction_id', 'sexe', 'grade_id'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        if ($p = $request->query('province_id')) {
            $q->whereHas('etablissement', fn ($e) => $e->where('province_id', $p));
        }

        $q->orderBy('nom')->orderBy('prenom');

        if ($request->boolean('tout')) {
            return response()->json(['data' => $q->limit(5000)->get()]);
        }

        return response()->json($q->paginate((int) $request->query('par_page', 15)));
    }

    public function show(Request $request, Agent $agent): JsonResponse
    {
        Perimetre::exiger($request->user(), $agent->etablissement_id, $agent->id);
        Perimetre::exigerConsulte($request->user(), $agent->etablissement_id);

        return response()->json(['data' => $this->dossier($agent)]);
    }

    /** Dossier individuel complet : identité, carrière, présences, cessations, congés. */
    public static function dossier(Agent $agent): array
    {
        $agent->load([
            'fonction', 'discipline', 'grade', 'corps', 'etablissement.province', 'utilisateur.profil:id,libelle',
            'affectations.etablissement:id,nom', 'affectations.etablissementOrigine:id,nom', 'affectations.fonction:id,libelle',
            'prisesService.etablissement:id,nom', 'debutsCours.etablissement:id,nom', 'debutsCours.discipline:id,libelle',
            'cessations.etablissement:id,nom', 'conges',
        ]);

        $debutMois = now()->startOfMonth()->toDateString();
        $presences = Presence::where('agent_id', $agent->id)->where('date', '>=', now()->subDays(60)->toDateString())->orderByDesc('date')->get();
        $mois = $presences->filter(fn ($p) => $p->date->toDateString() >= $debutMois);

        return [
            'agent' => $agent,
            'presences_recentes' => $presences->take(30)->values(),
            'synthese_presence_mois' => [
                'present' => $mois->where('statut', 'present')->count(),
                'retard' => $mois->where('statut', 'retard')->count(),
                'absent' => $mois->where('statut', 'absent')->count(),
                'absence_justifiee' => $mois->whereIn('statut', ['absence_justifiee', 'mission'])->count(),
            ],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $this->valider($request);
        if (! empty($donnees['etablissement_id'])) {
            Perimetre::exiger($request->user(), (int) $donnees['etablissement_id']);
        }
        $agent = Agent::create($donnees);
        JournalActivite::consigner('creation', 'personnel', "Agent créé : {$agent->nom_complet} ({$agent->matricule})", $agent);

        return response()->json(['data' => $agent->load($this->relationsListe)], 201);
    }

    public function update(Request $request, Agent $agent): JsonResponse
    {
        Perimetre::exiger($request->user(), $agent->etablissement_id, $agent->id);
        $agent->update($this->valider($request, $agent->id));
        JournalActivite::consigner('modification', 'personnel', "Agent modifié : {$agent->nom_complet} ({$agent->matricule})", $agent);

        return response()->json(['data' => $agent->load($this->relationsListe)]);
    }

    public function destroy(Request $request, Agent $agent): JsonResponse
    {
        Perimetre::exiger($request->user(), $agent->etablissement_id, $agent->id);
        $agent->utilisateur?->update(['etat' => 'inactif']);
        $agent->delete();
        JournalActivite::consigner('suppression', 'personnel', "Agent supprimé : {$agent->nom_complet} ({$agent->matricule})", $agent);

        return response()->json(['message' => 'Agent supprimé (archivé).']);
    }

    /** Crée le compte de connexion de l'agent, avec le profil adapté à sa fonction. */
    public function creerCompte(Request $request, Agent $agent): JsonResponse
    {
        Perimetre::exiger($request->user(), $agent->etablissement_id, $agent->id);
        if ($agent->utilisateur) {
            return response()->json(['message' => 'Cet agent a déjà un compte.'], 422);
        }

        $donnees = $request->validate([
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')],
            'profil_id' => ['required', 'exists:profils,id'],
        ]);

        $profil = Profil::findOrFail($donnees['profil_id']);
        if ($profil->systeme && $request->user()->profil?->code !== 'ADMIN') {
            abort(403, 'Seul l\'administrateur peut attribuer ce profil.');
        }

        $motDePasse = 'Grh@'.Str::upper(Str::random(6));
        $user = User::create([
            'name' => $agent->prenom.' '.mb_strtoupper($agent->nom),
            'email' => mb_strtolower($donnees['email']),
            'matricule' => $agent->matricule,
            'telephone' => $agent->telephone,
            'password' => $motDePasse,
            'profil_id' => $profil->id,
            'agent_id' => $agent->id,
            'etablissement_id' => $agent->etablissement_id,
            'province_id' => $agent->etablissement?->province_id,
            'etat' => 'actif',
            'doit_changer_mot_de_passe' => true,
        ]);

        JournalActivite::consigner('creation', 'utilisateurs', "Compte créé pour l'agent {$agent->nom_complet}", $user);

        return response()->json(['data' => $user->load('profil:id,libelle'), 'mot_de_passe_temporaire' => $motDePasse], 201);
    }

    protected function valider(Request $request, ?int $id = null): array
    {
        $donnees = $this->regles($request, $id);
        $donnees['statut'] = $donnees['statut'] ?? 'actif';
        $donnees['nationalite'] = $donnees['nationalite'] ?? 'Gabonaise';
        $donnees['nombre_enfants'] = $donnees['nombre_enfants'] ?? 0;
        $donnees['nom'] = mb_strtoupper($donnees['nom']);

        return $donnees;
    }

    protected function regles(Request $request, ?int $id): array
    {
        return $request->validate([
            'matricule' => ['required', 'string', 'max:30', Rule::unique('agents', 'matricule')->ignore($id)],
            'nom' => ['required', 'string', 'max:120'],
            'prenom' => ['required', 'string', 'max:120'],
            'sexe' => ['required', Rule::in(['M', 'F'])],
            'date_naissance' => ['nullable', 'date', 'before:-18 years'],
            'lieu_naissance' => ['nullable', 'string', 'max:120'],
            'nationalite' => ['nullable', 'string', 'max:60'],
            'situation_matrimoniale' => ['nullable', Rule::in(['celibataire', 'marie', 'divorce', 'veuf', 'union_libre'])],
            'nombre_enfants' => ['nullable', 'integer', 'min:0', 'max:30'],
            'numero_piece' => ['nullable', 'string', 'max:40'],
            'telephone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'adresse' => ['nullable', 'string', 'max:200'],
            'categorie' => ['required', Rule::in(array_keys(Agent::CATEGORIES))],
            'corps_id' => ['nullable', 'exists:corps,id'],
            'grade_id' => ['nullable', 'exists:grades,id'],
            'echelon' => ['nullable', 'integer', 'min:1', 'max:15'],
            'discipline_id' => ['nullable', 'exists:disciplines,id'],
            'fonction_id' => ['nullable', 'exists:fonctions,id'],
            'etablissement_id' => ['nullable', 'exists:etablissements,id'],
            'diplome' => ['nullable', 'string', 'max:160'],
            'date_recrutement' => ['nullable', 'date', 'before_or_equal:today'],
            'date_depart_retraite' => ['nullable', 'date'],
            'statut' => ['nullable', Rule::in(array_keys(Agent::STATUTS))],
            'observations' => ['nullable', 'string', 'max:2000'],
        ], [
            'date_naissance.before' => 'L\'agent doit avoir au moins 18 ans.',
        ]);
    }
}
