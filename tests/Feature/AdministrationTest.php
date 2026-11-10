<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\Profil;
use App\Models\User;
use Database\Seeders\DemoSeeder;

class AdministrationTest extends GrhmenTestCase
{
    public function test_gestion_des_utilisateurs(): void
    {
        $admin = $this->connecter($this->utilisateur('ADMIN'));
        $profil = Profil::where('code', 'CENSEUR')->value('id');

        $r = $this->postJson('/api/v1/utilisateurs', ['name' => 'Censeur Test', 'email' => 'CENSEUR.T@test.ga', 'profil_id' => $profil, 'etablissement_id' => $this->lycee->id])->assertCreated();
        $id = $r->json('data.id');
        $this->assertNotEmpty($r->json('mot_de_passe_temporaire'));
        $this->assertDatabaseHas('users', ['id' => $id, 'email' => 'censeur.t@test.ga', 'doit_changer_mot_de_passe' => true]);

        $this->postJson('/api/v1/utilisateurs', ['name' => 'X', 'email' => 'censeur.t@test.ga', 'profil_id' => $profil])->assertJsonValidationErrors('email');
        $this->putJson("/api/v1/utilisateurs/$id", ['name' => 'Censeur modifié', 'email' => 'censeur.t@test.ga', 'profil_id' => $profil, 'etat' => 'inactif'])->assertOk();
        $this->postJson("/api/v1/utilisateurs/$id/reinitialiser")->assertOk()->assertJsonStructure(['mot_de_passe_temporaire']);
        $this->putJson("/api/v1/utilisateurs/{$admin->id}", ['name' => 'Admin', 'email' => $admin->email, 'profil_id' => $admin->profil_id, 'etat' => 'inactif'])->assertStatus(422);
        $this->deleteJson("/api/v1/utilisateurs/{$admin->id}")->assertStatus(422);
        $this->deleteJson("/api/v1/utilisateurs/$id")->assertOk();
        $this->assertNull(User::find($id));
    }

    public function test_un_agent_n_a_qu_un_seul_compte(): void
    {
        $this->connecter($this->utilisateur('ADMIN'));
        $agent = $this->agent($this->lycee);
        $this->utilisateur('ENSEIGNANT', $this->lycee, $agent);

        $this->postJson('/api/v1/utilisateurs', ['name' => 'Doublon', 'email' => 'd@test.ga', 'profil_id' => Profil::where('code', 'ENSEIGNANT')->value('id'), 'agent_id' => $agent->id])
            ->assertJsonValidationErrors('agent_id');
    }

    public function test_referentiels_crud(): void
    {
        $this->connecter($this->utilisateur('ADMIN'));

        $id = $this->postJson('/api/v1/referentiels/disciplines', ['code' => 'MUS', 'libelle' => 'Éducation musicale'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/referentiels/disciplines', ['code' => 'MUS', 'libelle' => 'Doublon'])->assertJsonValidationErrors('code');
        $this->putJson("/api/v1/referentiels/disciplines/$id", ['code' => 'MUS', 'libelle' => 'Musique'])->assertOk()->assertJsonPath('data.libelle', 'Musique');
        $this->deleteJson("/api/v1/referentiels/disciplines/$id")->assertOk();
        $this->getJson('/api/v1/referentiels/inconnu')->assertNotFound();
    }

    public function test_une_seule_annee_scolaire_active(): void
    {
        $this->connecter($this->utilisateur('ADMIN'));

        $this->postJson('/api/v1/referentiels/annees-scolaires', ['libelle' => '2030-2031', 'date_debut' => '2030-09-15', 'date_fin' => '2031-07-15', 'active' => true])->assertCreated();
        $actives = collect($this->getJson('/api/v1/referentiels/annees-scolaires')->json('data'))->where('active', true);
        $this->assertCount(1, $actives);
        $this->assertSame('2030-2031', $actives->first()['libelle']);
    }

    public function test_un_referentiel_utilise_ne_peut_pas_etre_supprime(): void
    {
        $this->connecter($this->utilisateur('ADMIN'));
        $province = $this->lycee->province_id;

        $this->deleteJson("/api/v1/referentiels/provinces/$province")->assertStatus(422);
    }

    public function test_etablissements_crud(): void
    {
        $this->connecter($this->utilisateur('ADMIN'));
        $donnees = ['code' => 'EP-TEST', 'nom' => 'École publique test', 'type' => 'ecole_primaire', 'province_id' => $this->lycee->province_id, 'ville' => 'Ntoum', 'heure_debut' => '07:30', 'heure_fin' => '12:30'];

        $id = $this->postJson('/api/v1/etablissements', $donnees)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/etablissements', $donnees)->assertJsonValidationErrors('code');
        $this->putJson("/api/v1/etablissements/$id", array_merge($donnees, ['effectif_eleves' => 320]))->assertOk();
        $this->getJson("/api/v1/etablissements/$id")->assertOk()->assertJsonPath('data.effectif_eleves', 320);
        $this->deleteJson("/api/v1/etablissements/$id")->assertOk();

        $this->agent($this->lycee);
        $this->deleteJson("/api/v1/etablissements/{$this->lycee->id}")->assertStatus(422);
        $this->assertNotNull(Etablissement::find($this->lycee->id));
    }

    public function test_journal_d_audit(): void
    {
        $this->connecter($this->utilisateur('ADMIN'));
        $this->postJson('/api/v1/referentiels/disciplines', ['code' => 'LAT', 'libelle' => 'Latin']);

        $r = $this->getJson('/api/v1/journal?module=referentiels')->assertOk();
        $this->assertSame(1, $r->json('total'));
        $this->assertStringContainsString('Latin', $r->json('data.0.description'));
    }

    public function test_listes_des_formulaires(): void
    {
        $agent = $this->agentEnService($this->lycee);
        $this->connecter($this->utilisateur('ENSEIGNANT', $this->lycee, $agent));

        $r = $this->getJson('/api/v1/listes')->assertOk();
        $this->assertCount(1, $r->json('provinces'));
        $this->assertSame($this->lycee->province_id, $r->json('provinces.0.id'));
        $this->assertCount(1, $r->json('etablissements'), 'Un enseignant ne voit que son établissement.');
        $this->assertArrayHasKey('types_cessation', $r->json('enums'));
    }

    public function test_tableaux_de_bord_selon_le_profil(): void
    {
        $agent = $this->agentEnService($this->lycee);
        $this->connecter($this->utilisateur('ENSEIGNANT', $this->lycee, $agent));
        $this->getJson('/api/v1/tableau-de-bord')->assertOk()->assertJsonPath('type', 'personnel')->assertJsonPath('agent.id', $agent->id);

        $this->connecter($this->utilisateur('DRH'));
        $this->getJson('/api/v1/tableau-de-bord')->assertOk()->assertJsonPath('type', 'gestion')->assertJsonPath('indicateurs.effectif_actif', 1);
    }

    public function test_rapport_de_synthese(): void
    {
        $this->agentEnService($this->lycee, ['date_naissance' => now()->subYears(62)->toDateString()]);
        $this->agentEnService($this->lyceeAutreProvince, ['sexe' => 'M']);
        $this->connecter($this->utilisateur('DRH'));

        $r = $this->getJson('/api/v1/rapports/synthese')->assertOk();
        $r->assertJsonPath('effectif_actif', 2);
        $this->assertCount(2, $r->json('par_province'));
        $this->assertCount(1, $r->json('departs_retraite'));
    }

    public function test_le_jeu_de_demonstration_se_charge(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertGreaterThan(100, \App\Models\Agent::count());
        $this->assertTrue(User::where('email', 'censeur@grhmen.ga')->exists());
        $this->assertGreaterThan(1000, \App\Models\Presence::count());
    }
}
