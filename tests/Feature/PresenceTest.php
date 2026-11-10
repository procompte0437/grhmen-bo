<?php

namespace Tests\Feature;

use App\Models\Presence;
use Carbon\Carbon;

class PresenceTest extends GrhmenTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pointage_arrivee_a_l_heure_puis_depart(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 07:20:00'));
        $agent = $this->agentEnService($this->lycee);
        $this->connecter($this->utilisateur('ENSEIGNANT', $this->lycee, $agent));

        $this->postJson('/api/v1/moi/pointage', ['type' => 'depart'])->assertStatus(422);
        $this->postJson('/api/v1/moi/pointage', ['type' => 'arrivee', 'latitude' => 0.39, 'longitude' => 9.45])
            ->assertOk()->assertJsonPath('data.statut', 'present')->assertJsonPath('data.validation', 'en_attente');
        $this->postJson('/api/v1/moi/pointage', ['type' => 'arrivee'])->assertStatus(422);

        Carbon::setTestNow(Carbon::parse('2026-11-10 16:45:00'));
        $this->postJson('/api/v1/moi/pointage', ['type' => 'depart'])->assertOk()->assertJsonPath('data.heure_depart', '16:45:00');
        $this->postJson('/api/v1/moi/pointage', ['type' => 'depart'])->assertStatus(422);
    }

    public function test_arrivee_tardive_comptee_en_retard(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:05:00'));
        $agent = $this->agentEnService($this->lycee);
        $this->connecter($this->utilisateur('ENSEIGNANT', $this->lycee, $agent));

        $this->postJson('/api/v1/moi/pointage', ['type' => 'arrivee'])->assertOk()->assertJsonPath('data.statut', 'retard');
    }

    public function test_un_agent_suspendu_ne_peut_pas_pointer(): void
    {
        $agent = $this->agentEnService($this->lycee, ['statut' => 'suspendu']);
        $this->connecter($this->utilisateur('ENSEIGNANT', $this->lycee, $agent));

        $this->postJson('/api/v1/moi/pointage', ['type' => 'arrivee'])->assertStatus(422);
    }

    public function test_un_agent_sans_prise_de_service_ne_pointe_pas_et_n_est_pas_sur_la_feuille(): void
    {
        $nouveau = $this->agent($this->lycee); // affecté mais pas encore en service
        $this->agentEnService($this->lycee);
        $this->connecter($this->utilisateur('ENSEIGNANT', $this->lycee, $nouveau));
        $this->postJson('/api/v1/moi/pointage', ['type' => 'arrivee'])->assertStatus(422);

        $this->connecter($this->utilisateur('CENSEUR', $this->lycee));
        $this->assertCount(1, $this->getJson('/api/v1/presences/feuille')->json('lignes'));
    }

    public function test_historique_mensuel_de_l_agent(): void
    {
        $agent = $this->agentEnService($this->lycee);
        foreach (['2026-11-02' => 'present', '2026-11-03' => 'retard', '2026-11-04' => 'absent'] as $date => $statut) {
            Presence::create(['agent_id' => $agent->id, 'etablissement_id' => $this->lycee->id, 'date' => $date, 'statut' => $statut, 'validation' => 'validee']);
        }
        $this->connecter($this->utilisateur('ENSEIGNANT', $this->lycee, $agent));

        $r = $this->getJson('/api/v1/moi/presences?mois=2026-11')->assertOk();
        $this->assertCount(3, $r->json('data'));
        $r->assertJsonPath('synthese.absent', 1)->assertJsonPath('synthese.retard', 1);
        $this->assertEquals(66.7, $r->json('synthese.taux_presence'));
    }

    public function test_feuille_de_presence_et_saisie_par_le_censeur(): void
    {
        $a1 = $this->agentEnService($this->lycee);
        $a2 = $this->agentEnService($this->lycee);
        $intrus = $this->agentEnService($this->college);
        $censeur = $this->connecter($this->utilisateur('CENSEUR', $this->lycee));
        $date = now()->toDateString();

        $feuille = $this->getJson("/api/v1/presences/feuille?date=$date")->assertOk();
        $this->assertCount(2, $feuille->json('lignes'));
        $feuille->assertJsonPath('synthese.non_renseigne', 2);

        $this->postJson('/api/v1/presences/saisie', [
            'date' => $date, 'etablissement_id' => $this->lycee->id,
            'lignes' => [
                ['agent_id' => $a1->id, 'statut' => 'present', 'heure_arrivee' => '07:25'],
                ['agent_id' => $a2->id, 'statut' => 'absent', 'observations' => 'Sans nouvelles'],
                ['agent_id' => $intrus->id, 'statut' => 'present'],
            ],
        ])->assertOk()->assertJsonPath('nombre', 2);

        $this->assertDatabaseHas('presences', ['agent_id' => $a2->id, 'statut' => 'absent', 'validation' => 'validee', 'validee_par' => $censeur->id]);
        $this->assertDatabaseMissing('presences', ['agent_id' => $intrus->id]);
        $this->getJson("/api/v1/presences/feuille?date=$date")->assertJsonPath('synthese.non_renseigne', 0);
    }

    public function test_validation_en_masse_des_pointages(): void
    {
        $a1 = $this->agentEnService($this->lycee);
        $a2 = $this->agentEnService($this->lycee);
        $autre = $this->agentEnService($this->college);
        $p1 = Presence::create(['agent_id' => $a1->id, 'etablissement_id' => $this->lycee->id, 'date' => now()->toDateString(), 'heure_arrivee' => '07:10:00', 'statut' => 'present']);
        $p2 = Presence::create(['agent_id' => $a2->id, 'etablissement_id' => $this->lycee->id, 'date' => now()->toDateString(), 'heure_arrivee' => '07:12:00', 'statut' => 'present']);
        $p3 = Presence::create(['agent_id' => $autre->id, 'etablissement_id' => $this->college->id, 'date' => now()->toDateString(), 'heure_arrivee' => '07:12:00', 'statut' => 'present']);
        $this->connecter($this->utilisateur('CENSEUR', $this->lycee));

        $this->getJson('/api/v1/presences?validation=en_attente')->assertJsonPath('total', 2);
        $this->postJson('/api/v1/presences/validation', ['ids' => [$p1->id, $p3->id], 'decision' => 'validee'])->assertJsonPath('nombre', 1);
        $this->postJson('/api/v1/presences/validation', ['ids' => [$p2->id], 'decision' => 'rejetee'])->assertJsonPath('nombre', 1);

        $this->assertSame('validee', $p1->fresh()->validation);
        $this->assertSame('en_attente', $p3->fresh()->validation, 'Hors périmètre : non traité.');
        $this->assertSame('absent', $p2->fresh()->statut);
    }

    public function test_l_enseignant_ne_peut_pas_saisir_la_feuille(): void
    {
        $agent = $this->agentEnService($this->lycee);
        $this->connecter($this->utilisateur('ENSEIGNANT', $this->lycee, $agent));

        $this->postJson('/api/v1/presences/saisie', ['date' => now()->toDateString(), 'etablissement_id' => $this->lycee->id, 'lignes' => [['agent_id' => $agent->id, 'statut' => 'present']]])->assertForbidden();
    }

    public function test_statistiques_par_etablissement(): void
    {
        $a = $this->agentEnService($this->lycee);
        foreach (['2026-11-02' => 'present', '2026-11-03' => 'absent', '2026-11-04' => 'present', '2026-11-05' => 'retard'] as $d => $s) {
            Presence::create(['agent_id' => $a->id, 'etablissement_id' => $this->lycee->id, 'date' => $d, 'statut' => $s]);
        }
        $this->connecter($this->utilisateur('DRH'));

        $r = $this->getJson('/api/v1/presences/statistiques?date_debut=2026-11-01&date_fin=2026-11-30')->assertOk();
        $this->assertEquals(75.0, $r->json('data.0.taux_presence'));
        $this->assertSame(1, $r->json('data.0.absent'));
    }
}
