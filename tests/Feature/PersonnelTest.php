<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Profil;

class PersonnelTest extends GrhmenTestCase
{
    protected function donnees(array $surcharge = []): array
    {
        return array_merge([
            'matricule' => '555001A', 'nom' => 'ondo', 'prenom' => 'Brice', 'sexe' => 'M', 'date_naissance' => '1990-01-15',
            'categorie' => 'enseignant_secondaire', 'etablissement_id' => $this->lycee->id, 'telephone' => '+241 06 00 00 00',
        ], $surcharge);
    }

    public function test_creation_d_un_agent(): void
    {
        $this->connecter($this->utilisateur('GESTIONNAIRE_RH'));

        $r = $this->postJson('/api/v1/agents', $this->donnees())->assertCreated();

        $r->assertJsonPath('data.nom', 'ONDO')->assertJsonPath('data.statut', 'actif')->assertJsonPath('data.nationalite', 'Gabonaise');
        $this->assertDatabaseHas('journal_activites', ['module' => 'personnel', 'action' => 'creation']);
    }

    public function test_validation_du_formulaire_agent(): void
    {
        $this->connecter($this->utilisateur('GESTIONNAIRE_RH'));
        $this->agent($this->lycee, ['matricule' => 'DOUBLON']);

        $this->postJson('/api/v1/agents', [])->assertStatus(422)->assertJsonValidationErrors(['matricule', 'nom', 'prenom', 'sexe', 'categorie']);
        $this->postJson('/api/v1/agents', $this->donnees(['matricule' => 'DOUBLON']))->assertJsonValidationErrors('matricule');
        $this->postJson('/api/v1/agents', $this->donnees(['date_naissance' => now()->subYears(10)->toDateString()]))->assertJsonValidationErrors('date_naissance');
        $this->postJson('/api/v1/agents', $this->donnees(['sexe' => 'X']))->assertJsonValidationErrors('sexe');
        $this->postJson('/api/v1/agents', $this->donnees(['email' => 'pas-un-email']))->assertJsonValidationErrors('email');
    }

    public function test_modification_et_suppression_logique(): void
    {
        $this->connecter($this->utilisateur('DRH'));
        $agent = $this->agent($this->lycee);

        $this->putJson("/api/v1/agents/{$agent->id}", $this->donnees(['matricule' => $agent->matricule, 'prenom' => 'Modifié', 'echelon' => 5]))
            ->assertOk()->assertJsonPath('data.prenom', 'Modifié')->assertJsonPath('data.echelon', 5);

        $this->deleteJson("/api/v1/agents/{$agent->id}")->assertOk();
        $this->assertSoftDeleted('agents', ['id' => $agent->id]);
    }

    public function test_le_gestionnaire_ne_peut_pas_supprimer(): void
    {
        $this->connecter($this->utilisateur('GESTIONNAIRE_RH'));
        $agent = $this->agent($this->lycee);

        $this->deleteJson("/api/v1/agents/{$agent->id}")->assertForbidden();
    }

    public function test_dossier_complet_de_l_agent(): void
    {
        $this->connecter($this->utilisateur('DRH'));
        $agent = $this->agentEnService($this->lycee);

        $r = $this->getJson("/api/v1/agents/{$agent->id}")->assertOk();
        $r->assertJsonStructure(['data' => ['agent' => ['affectations', 'prises_service', 'debuts_cours', 'cessations', 'conges'], 'presences_recentes', 'synthese_presence_mois']]);
    }

    public function test_creation_du_compte_utilisateur_d_un_agent(): void
    {
        $this->connecter($this->utilisateur('DRH'));
        $agent = $this->agentEnService($this->lycee);
        $profil = Profil::where('code', 'ENSEIGNANT')->first();

        $r = $this->postJson("/api/v1/agents/{$agent->id}/compte", ['email' => 'nouvel.agent@test.ga', 'profil_id' => $profil->id])->assertCreated();
        $mdp = $r->json('mot_de_passe_temporaire');

        $this->postJson("/api/v1/agents/{$agent->id}/compte", ['email' => 'autre@test.ga', 'profil_id' => $profil->id])->assertStatus(422);

        // L'agent peut se connecter avec son matricule et doit changer son mot de passe.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/connexion', ['identifiant' => $agent->matricule, 'mot_de_passe' => $mdp])
            ->assertOk()->assertJsonPath('user.doit_changer_mot_de_passe', true)->assertJsonPath('user.agent.id', $agent->id);
    }

    public function test_seul_l_admin_attribue_le_profil_administrateur(): void
    {
        $this->connecter($this->utilisateur('DRH'));
        $agent = $this->agent($this->lycee);

        $this->postJson("/api/v1/agents/{$agent->id}/compte", ['email' => 'pirate@test.ga', 'profil_id' => Profil::where('code', 'ADMIN')->value('id')])->assertForbidden();
    }

    public function test_export_complet_sans_pagination(): void
    {
        $this->connecter($this->utilisateur('DRH'));
        foreach (range(1, 20) as $_) {
            $this->agent($this->lycee);
        }

        $this->assertCount(20, $this->getJson('/api/v1/agents?tout=1')->json('data'));
        $this->assertCount(15, $this->getJson('/api/v1/agents')->json('data'));
        $this->assertSame(20, Agent::count());
    }
}
