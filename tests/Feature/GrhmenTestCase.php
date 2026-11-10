<?php

namespace Tests\Feature;

use App\Models\Affectation;
use App\Models\Agent;
use App\Models\Discipline;
use App\Models\Etablissement;
use App\Models\Fonction;
use App\Models\PriseService;
use App\Models\Profil;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\HabilitationSeeder;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Socle des tests : référentiels, habilitations et un petit jeu de données (2 provinces, 3 établissements). */
abstract class GrhmenTestCase extends TestCase
{
    use RefreshDatabase;

    protected Etablissement $lycee;

    protected Etablissement $college;

    protected Etablissement $lyceeAutreProvince;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferentielSeeder::class, HabilitationSeeder::class]);

        $estuaire = Province::where('code', 'G1')->first();
        $hautOgooue = Province::where('code', 'G2')->first();
        $this->lycee = Etablissement::create(['code' => 'LYC1', 'nom' => 'Lycée test Libreville', 'type' => 'lycee', 'province_id' => $estuaire->id, 'ville' => 'Libreville', 'heure_debut' => '07:30:00', 'heure_fin' => '17:00:00']);
        $this->college = Etablissement::create(['code' => 'CES1', 'nom' => 'CES test Owendo', 'type' => 'college', 'province_id' => $estuaire->id, 'ville' => 'Owendo']);
        $this->lyceeAutreProvince = Etablissement::create(['code' => 'LYC2', 'nom' => 'Lycée test Franceville', 'type' => 'lycee', 'province_id' => $hautOgooue->id, 'ville' => 'Franceville']);
    }

    protected function agent(Etablissement $etab, array $attrs = []): Agent
    {
        static $n = 1000;
        $n++;

        return Agent::create(array_merge([
            'matricule' => 'M'.$n,
            'nom' => 'NOM'.$n,
            'prenom' => 'Prénom',
            'sexe' => 'F',
            'date_naissance' => '1985-04-12',
            'categorie' => 'enseignant_secondaire',
            'discipline_id' => Discipline::where('code', 'MATH')->value('id'),
            'fonction_id' => Fonction::where('code', 'ENS')->value('id'),
            'etablissement_id' => $etab->id,
            'date_recrutement' => '2012-09-01',
            'statut' => 'actif',
        ], $attrs));
    }

    /** Agent en service : affectation confirmée par une prise de service. */
    protected function agentEnService(Etablissement $etab, array $attrs = []): Agent
    {
        $agent = $this->agent($etab, $attrs);
        $aff = Affectation::create(['agent_id' => $agent->id, 'etablissement_id' => $etab->id, 'nature' => 'premiere_affectation', 'date_effet' => '2020-09-01', 'statut' => 'en_service']);
        PriseService::create(['affectation_id' => $aff->id, 'agent_id' => $agent->id, 'etablissement_id' => $etab->id, 'numero' => 'PS-T-'.$agent->id, 'date_prise_service' => '2020-09-02', 'statut' => 'confirmee']);

        return $agent;
    }

    protected function utilisateur(string $profil, ?Etablissement $etab = null, ?Agent $agent = null, array $attrs = []): User
    {
        static $i = 0;
        $i++;

        return User::create(array_merge([
            'name' => "Utilisateur $profil $i",
            'email' => strtolower($profil).$i.'@test.ga',
            'password' => 'password',
            'profil_id' => Profil::where('code', $profil)->value('id'),
            'etablissement_id' => $etab?->id,
            'province_id' => $etab?->province_id,
            'agent_id' => $agent?->id,
            'etat' => 'actif',
        ], $attrs));
    }

    protected function connecter(User $user): User
    {
        Sanctum::actingAs($user);

        return $user;
    }
}
