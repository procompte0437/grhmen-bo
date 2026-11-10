<?php

namespace Tests\Feature;

use App\Models\Option;
use App\Models\Profil;

class PerimetreTest extends GrhmenTestCase
{
    public function test_le_proviseur_ne_voit_que_le_personnel_de_son_etablissement(): void
    {
        $this->agent($this->lycee);
        $this->agent($this->lycee);
        $etranger = $this->agent($this->college);
        $this->connecter($this->utilisateur('CHEF_ETAB', $this->lycee));

        $r = $this->getJson('/api/v1/agents')->assertOk();
        $this->assertSame(2, $r->json('total'));
        $this->getJson("/api/v1/agents/{$etranger->id}")->assertForbidden();
    }

    public function test_le_directeur_d_academie_voit_sa_province(): void
    {
        $this->agent($this->lycee);
        $this->agent($this->college);
        $autre = $this->agent($this->lyceeAutreProvince);
        $this->connecter($this->utilisateur('DAP', $this->lycee));

        $this->assertSame(2, $this->getJson('/api/v1/agents')->json('total'));
        $this->getJson("/api/v1/agents/{$autre->id}")->assertForbidden();
    }

    public function test_le_drh_voit_tout_le_ministere(): void
    {
        $this->agent($this->lycee);
        $this->agent($this->college);
        $this->agent($this->lyceeAutreProvince);
        $this->connecter($this->utilisateur('DRH'));

        $this->assertSame(3, $this->getJson('/api/v1/agents')->json('total'));
    }

    public function test_le_censeur_ne_peut_pas_lire_la_feuille_d_un_autre_etablissement(): void
    {
        $this->connecter($this->utilisateur('CENSEUR', $this->lycee));

        $this->getJson("/api/v1/presences/feuille?etablissement_id={$this->lycee->id}")->assertOk();
        $this->getJson("/api/v1/presences/feuille?etablissement_id={$this->college->id}")->assertForbidden();
    }

    public function test_recherche_et_filtres_du_personnel(): void
    {
        $this->agent($this->lycee, ['nom' => 'MOUSSAVOU', 'sexe' => 'M']);
        $this->agent($this->lycee, ['nom' => 'NGUEMA', 'sexe' => 'F', 'categorie' => 'administratif']);
        $this->connecter($this->utilisateur('DRH'));

        $this->assertSame(1, $this->getJson('/api/v1/agents?q=mouss')->json('total'));
        $this->assertSame(1, $this->getJson('/api/v1/agents?categorie=administratif')->json('total'));
        $this->assertSame(1, $this->getJson('/api/v1/agents?sexe=M')->json('total'));
        $this->assertSame(2, $this->getJson("/api/v1/agents?province_id={$this->lycee->province_id}")->json('total'));
    }

    public function test_le_choix_d_etablissement_limite_le_personnel_affiche(): void
    {
        $this->agent($this->lycee);
        $this->agent($this->lycee);
        $ailleurs = $this->agent($this->lyceeAutreProvince);
        $this->connecter($this->utilisateur('DRH'));

        $this->withHeader('X-Etablissement', (string) $this->lycee->id)
            ->getJson('/api/v1/agents')->assertOk()->assertJsonPath('total', 2);
        $this->withHeader('X-Etablissement', (string) $this->lycee->id)
            ->getJson("/api/v1/agents/{$ailleurs->id}")->assertForbidden();
    }

    public function test_le_proviseur_ne_voit_que_son_etablissement_dans_les_listes(): void
    {
        $this->connecter($this->utilisateur('CHEF_ETAB', $this->lycee));

        $r = $this->getJson('/api/v1/listes')->assertOk();
        $this->assertCount(1, $r->json('etablissements'));
        $this->assertSame($this->lycee->id, $r->json('etablissements.0.id'));
        $this->assertCount(1, $r->json('provinces'));
    }

    public function test_le_directeur_d_academie_ne_voit_que_les_etablissements_de_sa_province(): void
    {
        $this->connecter($this->utilisateur('DAP', $this->lycee));

        $ids = collect($this->getJson('/api/v1/listes')->json('etablissements'))->pluck('id');
        $this->assertTrue($ids->contains($this->lycee->id));
        $this->assertTrue($ids->contains($this->college->id));
        $this->assertFalse($ids->contains($this->lyceeAutreProvince->id));
    }

    public function test_retirer_voir_tous_les_etablissements_limite_le_drh_au_sien(): void
    {
        $this->agent($this->lycee);
        $this->agent($this->college);
        $profil = Profil::where('code', 'DRH')->first();
        $profil->options()->detach(Option::where('code', 'VOIR_TOUS_ETABLISSEMENTS')->value('id'));
        $this->connecter($this->utilisateur('DRH', $this->lycee));

        $this->assertSame(1, $this->getJson('/api/v1/agents')->json('total'));
        $this->assertCount(1, $this->getJson('/api/v1/listes')->json('etablissements'));
        $this->getJson("/api/v1/agents?etablissement_id={$this->college->id}")->assertJsonPath('total', 0);
    }
}
