<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalActivite;
use App\Models\Option;
use App\Models\Profil;
use App\Models\User;
use App\Support\Recherche;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Utilisateurs, profils, options (habilitations) et journal d'audit. */
class AdministrationController extends Controller
{
    // ---------------------------------------------------------------- Utilisateurs

    public function utilisateurs(Request $request): JsonResponse
    {
        $q = User::query()->with(['profil:id,code,libelle,portee', 'agent:id,matricule,nom,prenom', 'etablissement:id,nom', 'province:id,nom']);
        Recherche::appliquer($q, ['name', 'email', 'matricule'], $request->query('q'));
        foreach (['profil_id', 'etat', 'etablissement_id'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }

        return response()->json($q->orderBy('name')->paginate((int) $request->query('par_page', 15)));
    }

    public function creerUtilisateur(Request $request): JsonResponse
    {
        $donnees = $this->validerUtilisateur($request);
        $this->verifierProfilSysteme($request, $donnees['profil_id']);
        $motDePasse = $donnees['password'] ?? 'Grh@'.Str::upper(Str::random(6));
        $user = User::create(array_merge($donnees, [
            'email' => mb_strtolower($donnees['email']),
            'password' => $motDePasse,
            'doit_changer_mot_de_passe' => true,
            'etat' => $donnees['etat'] ?? 'actif',
        ]));
        JournalActivite::consigner('creation', 'utilisateurs', "Utilisateur créé : {$user->name}", $user);

        return response()->json(['data' => $user->load('profil:id,libelle'), 'mot_de_passe_temporaire' => $motDePasse], 201);
    }

    public function modifierUtilisateur(Request $request, User $user): JsonResponse
    {
        $donnees = $this->validerUtilisateur($request, $user->id);
        $this->verifierProfilSysteme($request, $donnees['profil_id']);
        if ($user->id === $request->user()->id && ($donnees['etat'] ?? 'actif') !== 'actif') {
            return response()->json(['message' => 'Vous ne pouvez pas désactiver votre propre compte.'], 422);
        }
        unset($donnees['password']);
        $donnees['email'] = mb_strtolower($donnees['email']);
        $user->update($donnees);
        JournalActivite::consigner('modification', 'utilisateurs', "Utilisateur modifié : {$user->name}", $user);

        return response()->json(['data' => $user->fresh(['profil:id,libelle'])]);
    }

    public function supprimerUtilisateur(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 422);
        }
        $user->tokens()->delete();
        $user->delete();
        JournalActivite::consigner('suppression', 'utilisateurs', "Utilisateur supprimé : {$user->name}");

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }

    public function reinitialiserMotDePasse(User $user): JsonResponse
    {
        $motDePasse = 'Grh@'.Str::upper(Str::random(6));
        $user->update(['password' => $motDePasse, 'doit_changer_mot_de_passe' => true]);
        $user->tokens()->delete();
        JournalActivite::consigner('reinitialisation', 'utilisateurs', "Mot de passe réinitialisé : {$user->name}", $user);

        return response()->json(['message' => 'Mot de passe réinitialisé.', 'mot_de_passe_temporaire' => $motDePasse]);
    }

    protected function validerUtilisateur(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($id)],
            'matricule' => ['nullable', 'string', 'max:30', Rule::unique('users', 'matricule')->ignore($id)],
            'telephone' => ['nullable', 'string', 'max:40'],
            'profil_id' => ['required', 'exists:profils,id'],
            'agent_id' => ['nullable', 'exists:agents,id', Rule::unique('users', 'agent_id')->ignore($id)],
            'etablissement_id' => ['nullable', 'exists:etablissements,id'],
            'province_id' => ['nullable', 'exists:provinces,id'],
            'etat' => ['nullable', Rule::in(['actif', 'inactif'])],
            'password' => [$id ? 'prohibited' : 'nullable', 'string', 'min:8'],
        ], ['agent_id.unique' => 'Cet agent a déjà un compte utilisateur.']);
    }

    protected function verifierProfilSysteme(Request $request, int $profilId): void
    {
        $profil = Profil::find($profilId);
        if ($profil?->systeme && $request->user()->profil?->code !== 'ADMIN') {
            abort(403, 'Seul l\'administrateur système peut attribuer ce profil.');
        }
    }

    // ---------------------------------------------------------------- Profils

    public function profils(): JsonResponse
    {
        return response()->json(['data' => Profil::withCount(['utilisateurs', 'options'])->orderBy('libelle')->get()]);
    }

    public function profil(Profil $profil): JsonResponse
    {
        return response()->json(['data' => $profil->loadCount('utilisateurs'), 'option_ids' => $profil->options()->pluck('options.id')]);
    }

    public function creerProfil(Request $request): JsonResponse
    {
        $profil = Profil::create($this->validerProfil($request));
        JournalActivite::consigner('creation', 'profils', "Profil créé : {$profil->libelle}", $profil);

        return response()->json(['data' => $profil], 201);
    }

    public function modifierProfil(Request $request, Profil $profil): JsonResponse
    {
        $profil->update($this->validerProfil($request, $profil->id));
        JournalActivite::consigner('modification', 'profils', "Profil modifié : {$profil->libelle}", $profil);

        return response()->json(['data' => $profil]);
    }

    public function supprimerProfil(Profil $profil): JsonResponse
    {
        if ($profil->systeme) {
            return response()->json(['message' => 'Un profil système ne peut pas être supprimé.'], 422);
        }
        if ($profil->utilisateurs()->exists()) {
            return response()->json(['message' => 'Ce profil est attribué à des utilisateurs : réaffectez-les d\'abord.'], 422);
        }
        $profil->delete();
        JournalActivite::consigner('suppression', 'profils', "Profil supprimé : {$profil->libelle}");

        return response()->json(['message' => 'Profil supprimé.']);
    }

    /** Attribue les options (accès et gestion) au profil. Une option de gestion entraîne son option d'accès parente. */
    public function habiliterProfil(Request $request, Profil $profil): JsonResponse
    {
        $ids = collect($request->validate(['option_ids' => ['present', 'array'], 'option_ids.*' => ['integer', 'exists:options,id']])['option_ids']);
        $parents = Option::whereIn('id', $ids)->whereNotNull('parent_id')->pluck('parent_id');
        $ids = $ids->merge($parents)->unique()->values();

        if ($profil->code === 'ADMIN') {
            $requises = Option::whereIn('code', ['PROFILS', 'PROFIL_HABILITER', 'UTILISATEURS'])->pluck('id');
            $ids = $ids->merge($requises)->unique()->values();
        }

        $profil->options()->sync($ids);
        JournalActivite::consigner('habilitation', 'profils', "Habilitations du profil {$profil->libelle} mises à jour (".$ids->count().' options)', $profil);

        return response()->json(['message' => 'Habilitations enregistrées.', 'option_ids' => $ids]);
    }

    protected function validerProfil(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_]+$/', Rule::unique('profils', 'code')->ignore($id)],
            'libelle' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'portee' => ['required', Rule::in(Profil::PORTEES)],
        ], ['code.regex' => 'Le code ne contient que des majuscules, chiffres et « _ ».']);
    }

    // ---------------------------------------------------------------- Options

    /** Arborescence : options d'accès et leurs options de gestion. */
    public function options(): JsonResponse
    {
        $acces = Option::where('type', Option::ACCES)->with('enfants')->withCount('profils')->orderBy('ordre')->get();

        return response()->json(['data' => $acces]);
    }

    public function modifierOption(Request $request, Option $option): JsonResponse
    {
        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:250'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:999'],
            'groupe' => ['nullable', 'string', 'max:60'],
        ]);
        $option->update($donnees);
        JournalActivite::consigner('modification', 'options', "Option modifiée : {$option->code}", $option);

        return response()->json(['data' => $option]);
    }

    // ---------------------------------------------------------------- Journal

    public function journal(Request $request): JsonResponse
    {
        $q = JournalActivite::query()->with('utilisateur:id,name,email');
        Recherche::appliquer($q, ['description', 'action'], $request->query('q'));
        foreach (['module', 'user_id', 'action'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        if ($d = $request->query('date_debut')) {
            $q->where('created_at', '>=', $d.' 00:00:00');
        }
        if ($d = $request->query('date_fin')) {
            $q->where('created_at', '<=', $d.' 23:59:59');
        }

        return response()->json($q->orderByDesc('id')->paginate((int) $request->query('par_page', 25)));
    }
}
