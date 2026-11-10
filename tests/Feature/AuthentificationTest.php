<?php

namespace Tests\Feature;

class AuthentificationTest extends GrhmenTestCase
{
    public function test_connexion_par_email_retourne_un_jeton_et_le_menu_du_profil(): void
    {
        $this->utilisateur('DRH', null, null, ['email' => 'drh@test.ga']);

        $r = $this->postJson('/api/v1/auth/connexion', ['identifiant' => 'drh@test.ga', 'mot_de_passe' => 'password']);

        $r->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'profil', 'options_acces', 'options_gestion']]);
        $codes = collect($r->json('user.options_acces'))->pluck('code');
        $this->assertTrue($codes->contains('PERSONNEL'));
        $this->assertFalse($codes->contains('UTILISATEURS'), 'Le DRH n\'administre pas les comptes.');
        $this->assertContains('CESSATION_VALIDER', $r->json('user.options_gestion'));
    }

    public function test_connexion_par_matricule(): void
    {
        $agent = $this->agent($this->lycee, ['matricule' => '123456X']);
        $this->utilisateur('ENSEIGNANT', $this->lycee, $agent, ['matricule' => '123456X']);

        $this->postJson('/api/v1/auth/connexion', ['identifiant' => '123456X', 'mot_de_passe' => 'password'])->assertOk();
    }

    public function test_mauvais_mot_de_passe_est_refuse(): void
    {
        $this->utilisateur('DRH', null, null, ['email' => 'x@test.ga']);

        $this->postJson('/api/v1/auth/connexion', ['identifiant' => 'x@test.ga', 'mot_de_passe' => 'faux'])
            ->assertStatus(422)->assertJsonValidationErrors('identifiant');
    }

    public function test_compte_inactif_ne_peut_pas_se_connecter(): void
    {
        $this->utilisateur('DRH', null, null, ['email' => 'inactif@test.ga', 'etat' => 'inactif']);

        $this->postJson('/api/v1/auth/connexion', ['identifiant' => 'inactif@test.ga', 'mot_de_passe' => 'password'])->assertStatus(422);
    }

    public function test_les_routes_protegees_exigent_une_authentification(): void
    {
        $this->getJson('/api/v1/auth/moi')->assertUnauthorized();
        $this->getJson('/api/v1/agents')->assertUnauthorized();
    }

    public function test_changement_de_mot_de_passe(): void
    {
        $user = $this->connecter($this->utilisateur('DRH', null, null, ['doit_changer_mot_de_passe' => true]));

        $this->putJson('/api/v1/auth/mot-de-passe', ['ancien' => 'faux', 'nouveau' => 'NouveauMdp1', 'nouveau_confirmation' => 'NouveauMdp1'])->assertStatus(422);
        $this->putJson('/api/v1/auth/mot-de-passe', ['ancien' => 'password', 'nouveau' => 'NouveauMdp1', 'nouveau_confirmation' => 'NouveauMdp1'])
            ->assertOk()->assertJsonPath('user.doit_changer_mot_de_passe', false);

        $this->assertTrue(\Hash::check('NouveauMdp1', $user->fresh()->password));
    }

    public function test_mon_espace_est_masque_pour_un_compte_sans_dossier_agent(): void
    {
        $this->connecter($this->utilisateur('ADMIN'));

        $codes = collect($this->getJson('/api/v1/auth/moi')->json('user.options_acces'))->pluck('code');
        $this->assertFalse($codes->contains('MON_POINTAGE'));
        $this->assertTrue($codes->contains('PROFILS'));
    }

    public function test_deconnexion_revoque_le_jeton(): void
    {
        $this->utilisateur('DRH', null, null, ['email' => 'out@test.ga']);
        $jeton = $this->postJson('/api/v1/auth/connexion', ['identifiant' => 'out@test.ga', 'mot_de_passe' => 'password'])->json('token');

        $this->app['auth']->forgetGuards();
        $this->withToken($jeton)->postJson('/api/v1/auth/deconnexion')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();
        $this->withToken($jeton)->getJson('/api/v1/auth/moi')->assertUnauthorized();
    }
}
