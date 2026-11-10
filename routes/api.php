<?php

use App\Http\Controllers\Api\AdministrationController as Admin;
use App\Http\Controllers\Api\AffectationController as Affectations;
use App\Http\Controllers\Api\AgentController as Agents;
use App\Http\Controllers\Api\AuthController as Auth;
use App\Http\Controllers\Api\CessationController as Cessations;
use App\Http\Controllers\Api\CongeController as Conges;
use App\Http\Controllers\Api\DocumentController as Documents;
use App\Http\Controllers\Api\DebutCoursController as DebutsCours;
use App\Http\Controllers\Api\EtablissementController as Etablissements;
use App\Http\Controllers\Api\MonEspaceController as MonEspace;
use App\Http\Controllers\Api\PresenceController as Presences;
use App\Http\Controllers\Api\PriseServiceController as PrisesService;
use App\Http\Controllers\Api\RapportController as Rapports;
use App\Http\Controllers\Api\ReferentielController as Referentiels;
use App\Http\Controllers\Api\TableauBordController as TableauBord;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('sante', fn () => ['application' => 'GRHMEN', 'statut' => 'ok', 'heure' => now()->toIso8601String()]);
    Route::post('auth/connexion', [Auth::class, 'login'])->middleware('throttle:30,1');

    Route::middleware('auth:sanctum')->group(function () {
        // Session
        Route::get('auth/moi', [Auth::class, 'moi']);
        Route::post('auth/deconnexion', [Auth::class, 'logout']);
        Route::put('auth/mot-de-passe', [Auth::class, 'changerMotDePasse']);
        Route::get('listes', [Referentiels::class, 'listes']);
        Route::get('badges', [TableauBord::class, 'badges']);

        Route::get('tableau-de-bord', [TableauBord::class, 'index'])->middleware('option:TABLEAU_BORD');

        // Mon espace (agent connecté)
        Route::get('moi/dossier', [MonEspace::class, 'dossier'])->middleware('option:MON_DOSSIER');
        Route::get('moi/services', [MonEspace::class, 'services'])->middleware('option:MES_SERVICES');
        Route::post('moi/prise-service', [PrisesService::class, 'declarer'])->middleware('option:PRISE_SERVICE_DECLARER');
        Route::post('moi/debut-cours', [DebutsCours::class, 'declarer'])->middleware('option:DEBUT_COURS_DECLARER');
        Route::get('moi/presences', [Presences::class, 'moi'])->middleware('option:MON_POINTAGE');
        Route::post('moi/pointage', [Presences::class, 'pointer'])->middleware('option:PRESENCE_POINTER');
        Route::get('moi/conges', [Conges::class, 'mesConges'])->middleware('option:MES_CONGES');
        Route::post('moi/conges', [Conges::class, 'store'])->middleware('option:CONGE_DEMANDER');
        Route::delete('moi/conges/{conge}', [Conges::class, 'annulerMaDemande'])->middleware('option:CONGE_DEMANDER');

        // Personnel
        Route::get('agents', [Agents::class, 'index'])->middleware('option:PERSONNEL,AFFECTATIONS,CESSATIONS');
        Route::get('agents/{agent}', [Agents::class, 'show'])->middleware('option:PERSONNEL');
        Route::post('agents', [Agents::class, 'store'])->middleware('option:AGENT_CREER');
        Route::put('agents/{agent}', [Agents::class, 'update'])->middleware('option:AGENT_MODIFIER');
        Route::delete('agents/{agent}', [Agents::class, 'destroy'])->middleware('option:AGENT_SUPPRIMER');
        Route::post('agents/{agent}/compte', [Agents::class, 'creerCompte'])->middleware('option:AGENT_CREER_COMPTE');

        // Affectations
        Route::get('affectations', [Affectations::class, 'index'])->middleware('option:AFFECTATIONS,PRISES_SERVICE');
        Route::get('affectations/{affectation}', [Affectations::class, 'show'])->middleware('option:AFFECTATIONS');
        Route::post('affectations', [Affectations::class, 'store'])->middleware('option:AFFECTATION_CREER');
        Route::put('affectations/{affectation}', [Affectations::class, 'update'])->middleware('option:AFFECTATION_MODIFIER');
        Route::post('affectations/{affectation}/annuler', [Affectations::class, 'annuler'])->middleware('option:AFFECTATION_ANNULER');

        // Prises de service
        Route::get('prises-service', [PrisesService::class, 'index'])->middleware('option:PRISES_SERVICE');
        Route::get('prises-service/{priseService}', [PrisesService::class, 'show'])->middleware('option:PRISES_SERVICE,MES_SERVICES');
        Route::post('prises-service', [PrisesService::class, 'enregistrer'])->middleware('option:PRISE_SERVICE_ENREGISTRER');
        Route::post('prises-service/{priseService}/confirmer', [PrisesService::class, 'confirmer'])->middleware('option:PRISE_SERVICE_CONFIRMER');
        Route::post('prises-service/{priseService}/rejeter', [PrisesService::class, 'rejeter'])->middleware('option:PRISE_SERVICE_CONFIRMER');

        // Débuts des cours
        Route::get('debuts-cours', [DebutsCours::class, 'index'])->middleware('option:DEBUTS_COURS');
        Route::get('debuts-cours/sans-debut', [DebutsCours::class, 'sansDebut'])->middleware('option:DEBUTS_COURS');
        Route::post('debuts-cours', [DebutsCours::class, 'enregistrer'])->middleware('option:DEBUT_COURS_ENREGISTRER');
        Route::post('debuts-cours/{debutCours}/confirmer', [DebutsCours::class, 'confirmer'])->middleware('option:DEBUT_COURS_CONFIRMER');
        Route::post('debuts-cours/{debutCours}/rejeter', [DebutsCours::class, 'rejeter'])->middleware('option:DEBUT_COURS_CONFIRMER');

        // Présences au poste
        Route::get('presences', [Presences::class, 'index'])->middleware('option:PRESENCES');
        Route::get('presences/feuille', [Presences::class, 'feuille'])->middleware('option:PRESENCES');
        Route::get('presences/statistiques', [Presences::class, 'statistiques'])->middleware('option:PRESENCES,RAPPORTS');
        Route::post('presences/saisie', [Presences::class, 'saisir'])->middleware('option:PRESENCE_SAISIR');
        Route::post('presences/validation', [Presences::class, 'valider'])->middleware('option:PRESENCE_VALIDER');

        // Cessations de service
        Route::get('cessations', [Cessations::class, 'index'])->middleware('option:CESSATIONS');
        Route::get('cessations/{cessation}', [Cessations::class, 'show'])->middleware('option:CESSATIONS');
        Route::post('cessations', [Cessations::class, 'store'])->middleware('option:CESSATION_SIGNALER');
        Route::post('cessations/generer', [Cessations::class, 'genererNonPriseService'])->middleware('option:CESSATION_GENERER');
        Route::post('cessations/{cessation}/valider', [Cessations::class, 'valider'])->middleware('option:CESSATION_VALIDER');
        Route::post('cessations/{cessation}/annuler', [Cessations::class, 'annuler'])->middleware('option:CESSATION_ANNULER,CESSATION_VALIDER');

        // Congés
        Route::get('conges', [Conges::class, 'index'])->middleware('option:CONGES');
        Route::post('conges/{conge}/avis', [Conges::class, 'avis'])->middleware('option:CONGE_AVIS');
        Route::post('conges/{conge}/decision', [Conges::class, 'decider'])->middleware('option:CONGE_APPROUVER');

        // Établissements
        Route::get('etablissements', [Etablissements::class, 'index'])->middleware('option:ETABLISSEMENTS');
        Route::get('etablissements/{etablissement}', [Etablissements::class, 'show'])->middleware('option:ETABLISSEMENTS');
        Route::post('etablissements', [Etablissements::class, 'store'])->middleware('option:ETAB_CREER');
        Route::put('etablissements/{etablissement}', [Etablissements::class, 'update'])->middleware('option:ETAB_MODIFIER');
        Route::delete('etablissements/{etablissement}', [Etablissements::class, 'destroy'])->middleware('option:ETAB_SUPPRIMER');

        // Rapports
        Route::get('rapports/synthese', [Rapports::class, 'synthese'])->middleware('option:RAPPORTS');

        // Documents officiels imprimables (habilitation et périmètre vérifiés par type).
        Route::get('documents/{type}/{id}', [Documents::class, 'show'])->whereNumber('id');

        // Référentiels
        Route::get('referentiels/{type}', [Referentiels::class, 'index'])->middleware('option:REFERENTIELS');
        Route::post('referentiels/{type}', [Referentiels::class, 'store'])->middleware('option:REFERENTIEL_GERER');
        Route::put('referentiels/{type}/{id}', [Referentiels::class, 'update'])->middleware('option:REFERENTIEL_GERER');
        Route::delete('referentiels/{type}/{id}', [Referentiels::class, 'destroy'])->middleware('option:REFERENTIEL_GERER');

        // Administration
        Route::get('utilisateurs', [Admin::class, 'utilisateurs'])->middleware('option:UTILISATEURS');
        Route::post('utilisateurs', [Admin::class, 'creerUtilisateur'])->middleware('option:UTILISATEUR_CREER');
        Route::put('utilisateurs/{user}', [Admin::class, 'modifierUtilisateur'])->middleware('option:UTILISATEUR_MODIFIER');
        Route::delete('utilisateurs/{user}', [Admin::class, 'supprimerUtilisateur'])->middleware('option:UTILISATEUR_SUPPRIMER');
        Route::post('utilisateurs/{user}/reinitialiser', [Admin::class, 'reinitialiserMotDePasse'])->middleware('option:UTILISATEUR_REINITIALISER');

        Route::get('profils', [Admin::class, 'profils'])->middleware('option:PROFILS,UTILISATEURS');
        Route::get('profils/{profil}', [Admin::class, 'profil'])->middleware('option:PROFILS');
        Route::post('profils', [Admin::class, 'creerProfil'])->middleware('option:PROFIL_CREER');
        Route::put('profils/{profil}', [Admin::class, 'modifierProfil'])->middleware('option:PROFIL_MODIFIER');
        Route::delete('profils/{profil}', [Admin::class, 'supprimerProfil'])->middleware('option:PROFIL_SUPPRIMER');
        Route::put('profils/{profil}/options', [Admin::class, 'habiliterProfil'])->middleware('option:PROFIL_HABILITER');

        Route::get('options', [Admin::class, 'options'])->middleware('option:PROFILS');
        Route::put('options/{option}', [Admin::class, 'modifierOption'])->middleware('option:PROFIL_MODIFIER');

        Route::get('journal', [Admin::class, 'journal'])->middleware('option:JOURNAL');
    });
});
