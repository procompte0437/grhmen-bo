<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalActivite;
use App\Models\Option;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'identifiant' => ['required', 'string'],
            'mot_de_passe' => ['required', 'string'],
        ], [], ['identifiant' => 'e-mail ou matricule', 'mot_de_passe' => 'mot de passe']);

        $identifiant = trim($donnees['identifiant']);
        $user = User::where('email', mb_strtolower($identifiant))->orWhere('matricule', $identifiant)->first();

        if (! $user || ! Hash::check($donnees['mot_de_passe'], $user->password)) {
            throw ValidationException::withMessages(['identifiant' => 'Identifiant ou mot de passe incorrect.']);
        }

        if ($user->etat !== 'actif') {
            throw ValidationException::withMessages(['identifiant' => 'Ce compte est désactivé. Contactez l\'administrateur.']);
        }

        $user->forceFill(['derniere_connexion' => now()])->save();
        $token = $user->createToken('grhmen')->plainTextToken;

        auth()->setUser($user);
        JournalActivite::consigner('connexion', 'auth', 'Connexion de '.$user->name, $user);

        return response()->json(['token' => $token, 'user' => self::session($user)]);
    }

    public function moi(Request $request): JsonResponse
    {
        return response()->json(['user' => self::session($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $jeton = $request->user()->currentAccessToken();
        if ($jeton && method_exists($jeton, 'delete')) {
            $jeton->delete();
        }

        return response()->json(['message' => 'Déconnexion effectuée.']);
    }

    public function changerMotDePasse(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'ancien' => ['required', 'string'],
            'nouveau' => ['required', 'string', 'min:8', 'confirmed', 'different:ancien'],
        ], [], ['ancien' => 'mot de passe actuel', 'nouveau' => 'nouveau mot de passe']);

        $user = $request->user();
        if (! Hash::check($donnees['ancien'], $user->password)) {
            throw ValidationException::withMessages(['ancien' => 'Le mot de passe actuel est incorrect.']);
        }

        $user->update(['password' => $donnees['nouveau'], 'doit_changer_mot_de_passe' => false]);
        JournalActivite::consigner('mot_de_passe', 'auth', 'Changement de mot de passe', $user);

        return response()->json(['message' => 'Mot de passe modifié.', 'user' => self::session($user->fresh())]);
    }

    /** Données de session : identité, profil, menu (options d'accès) et actions (options de gestion). */
    public static function session(User $user): array
    {
        $user->loadMissing(['profil.options', 'agent.fonction', 'agent.discipline', 'etablissement.province', 'province']);
        $options = $user->profil?->options ?? collect();
        // « Mon espace » n'a de sens que pour un compte rattaché à un dossier agent.
        if (! $user->agent_id) {
            $options = $options->reject(fn (Option $o) => $o->groupe === 'Mon espace');
        }

        $acces = $options->where('type', Option::ACCES)->sortBy('ordre')->values()->map(fn (Option $o) => [
            'code' => $o->code,
            'libelle' => $o->libelle,
            'groupe' => $o->groupe,
            'route' => $o->route,
            'icone' => $o->icone,
            'ordre' => $o->ordre,
        ]);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'matricule' => $user->matricule,
            'telephone' => $user->telephone,
            'etat' => $user->etat,
            'doit_changer_mot_de_passe' => $user->doit_changer_mot_de_passe,
            'derniere_connexion' => $user->derniere_connexion,
            'profil' => $user->profil ? [
                'id' => $user->profil->id,
                'code' => $user->profil->code,
                'libelle' => $user->profil->libelle,
                'portee' => $user->profil->portee,
            ] : null,
            'agent' => $user->agent ? [
                'id' => $user->agent->id,
                'matricule' => $user->agent->matricule,
                'nom_complet' => $user->agent->nom_complet,
                'categorie' => $user->agent->categorie,
                'fonction' => $user->agent->fonction?->libelle,
                'discipline' => $user->agent->discipline?->libelle,
            ] : null,
            'etablissement' => $user->etablissement ? [
                'id' => $user->etablissement->id,
                'nom' => $user->etablissement->nom,
                'province' => $user->etablissement->province?->nom,
            ] : null,
            'province' => $user->province ? ['id' => $user->province->id, 'nom' => $user->province->nom] : null,
            'options_acces' => $acces,
            'options_gestion' => $options->where('type', Option::GESTION)->pluck('code')->values(),
        ];
    }
}
