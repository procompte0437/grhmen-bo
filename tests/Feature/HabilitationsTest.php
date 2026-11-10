<?php

namespace Tests\Feature;

use App\Models\Option;
use App\Models\Profil;

class HabilitationsTest extends GrhmenTestCase
{
    public function test_le_catalogue_contient_options_d_acces_et_de_gestion(): void
    {
        $this->assertGreaterThan(15, Option::where('type', 'acces')->count());
        $this->assertGreaterThan(30, Option::where('type', 'gestion')->count());
        $this->assertSame(0, Option::where('type', 'gestion')->whereNull('parent_id')->count(), 'Chaque option de gestion est rattachée à une option d\'accès.');
    }

    public function test_l_administrateur_a_toutes_les_options(): void
    {
        $this->assertSame(Option::count(), Profil::where('code', 'ADMIN')->first()->options()->count());
    }

    public function test_un_enseignant_ne_voit_que_son_espace(): void
    {
        $agent = $this->agentEnService($this->lycee);
        $this->connecter($this->utilisateur('ENSEIGNANT', $this->lycee, $agent));

        $groupes = collect($this->getJson('/api/v1/auth/moi')->json('user.options_acces'))->pluck('groupe')->unique()->values()->all();
        $this->assertEqualsCanonicalizing(['Général', 'Mon espace'], $groupes);

        foreach (['/api/v1/agents', '/api/v1/affectations', '/api/v1/presences', '/api/v1/utilisateurs', '/api/v1/profils', '/api/v1/rapports/synthese'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
    }

    public function test_une_option_de_gestion_est_exigee_pour_agir(): void
    {
        // Le gestionnaire RH peut lire les cessations mais pas les valider.
        $agent = $this->agentEnService($this->lycee);
        $this->connecter($this->utilisateur('GESTIONNAIRE_RH'));
        $id = $this->postJson('/api/v1/cessations', ['agent_id' => $agent->id, 'type' => 'abandon_poste', 'date_cessation' => now()->toDateString(), 'motif' => 'Absent'])
            ->assertCreated()->json('data.id');
        $this->getJson('/api/v1/cessations')->assertOk();
        $this->postJson("/api/v1/cessations/$id/valider")->assertForbidden()->assertJsonPath('options_requises', ['CESSATION_VALIDER']);
    }

    public function test_retirer_une_option_au_profil_bloque_immediatement_l_acces(): void
    {
        $admin = $this->utilisateur('ADMIN');
        $drh = $this->utilisateur('DRH');
        $profil = Profil::where('code', 'DRH')->first();

        $this->connecter($drh);
        $this->getJson('/api/v1/journal')->assertOk();

        $this->connecter($admin);
        $ids = $profil->options()->where('code', '!=', 'JOURNAL')->pluck('options.id')->all();
        $this->putJson("/api/v1/profils/{$profil->id}/options", ['option_ids' => $ids])->assertOk();

        $this->connecter($drh->fresh());
        $this->getJson('/api/v1/journal')->assertForbidden();
        $codes = collect($this->getJson('/api/v1/auth/moi')->json('user.options_acces'))->pluck('code');
        $this->assertFalse($codes->contains('JOURNAL'), 'L\'élément disparaît du menu latéral.');
    }

    public function test_cocher_une_option_de_gestion_ajoute_son_option_d_acces(): void
    {
        $this->connecter($this->utilisateur('ADMIN'));
        $profil = Profil::create(['code' => 'INSPECTEUR', 'libelle' => 'Inspecteur pédagogique', 'portee' => 'provincial']);
        $gestion = Option::where('code', 'DEBUT_COURS_CONFIRMER')->first();

        $r = $this->putJson("/api/v1/profils/{$profil->id}/options", ['option_ids' => [$gestion->id]])->assertOk();

        $this->assertContains($gestion->parent_id, $r->json('option_ids'));
        $this->assertTrue($profil->options()->where('code', 'DEBUTS_COURS')->exists());
    }

    public function test_creation_modification_suppression_de_profil(): void
    {
        $this->connecter($this->utilisateur('ADMIN'));

        $id = $this->postJson('/api/v1/profils', ['code' => 'INSPECTEUR', 'libelle' => 'Inspecteur', 'portee' => 'provincial'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/profils', ['code' => 'inspecteur minuscule', 'libelle' => 'X', 'portee' => 'provincial'])->assertStatus(422);
        $this->putJson("/api/v1/profils/$id", ['code' => 'INSPECTEUR', 'libelle' => 'Inspecteur pédagogique', 'portee' => 'national'])->assertOk();
        $this->deleteJson("/api/v1/profils/$id")->assertOk();

        $admin = Profil::where('code', 'ADMIN')->first();
        $this->deleteJson("/api/v1/profils/{$admin->id}")->assertStatus(422);
    }

    public function test_un_profil_attribue_ne_peut_pas_etre_supprime(): void
    {
        $this->connecter($this->utilisateur('ADMIN'));
        $this->utilisateur('CENSEUR', $this->lycee);
        $censeur = Profil::where('code', 'CENSEUR')->first();

        $this->deleteJson("/api/v1/profils/{$censeur->id}")->assertStatus(422);
    }

    public function test_l_administrateur_garde_toujours_la_gestion_des_habilitations(): void
    {
        $this->connecter($this->utilisateur('ADMIN'));
        $admin = Profil::where('code', 'ADMIN')->first();

        $this->putJson("/api/v1/profils/{$admin->id}/options", ['option_ids' => []])->assertOk();

        $this->assertTrue($admin->options()->where('code', 'PROFIL_HABILITER')->exists());
    }

    public function test_arborescence_des_options(): void
    {
        $this->connecter($this->utilisateur('ADMIN'));
        $r = $this->getJson('/api/v1/options')->assertOk();
        $personnel = collect($r->json('data'))->firstWhere('code', 'PERSONNEL');
        $this->assertContains('AGENT_CREER', collect($personnel['enfants'])->pluck('code'));
    }
}
