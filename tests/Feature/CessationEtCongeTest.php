<?php

namespace Tests\Feature;

use App\Models\Affectation;
use App\Models\CessationService;
use App\Models\Conge;
use App\Models\Presence;
use Carbon\Carbon;

class CessationEtCongeTest extends GrhmenTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_generation_des_cessations_pour_non_prise_de_service(): void
    {
        $enRetard = $this->agent($this->lycee);
        $recent = $this->agent($this->lycee);
        $hors = $this->agent($this->lyceeAutreProvince);
        $a1 = Affectation::create(['agent_id' => $enRetard->id, 'etablissement_id' => $this->lycee->id, 'nature' => 'premiere_affectation', 'date_effet' => now()->subDays(45)->toDateString(), 'statut' => 'en_attente_prise_service']);
        Affectation::create(['agent_id' => $recent->id, 'etablissement_id' => $this->lycee->id, 'nature' => 'mutation', 'date_effet' => now()->subDays(5)->toDateString(), 'statut' => 'en_attente_prise_service']);
        Affectation::create(['agent_id' => $hors->id, 'etablissement_id' => $this->lyceeAutreProvince->id, 'nature' => 'mutation', 'date_effet' => now()->subDays(90)->toDateString(), 'statut' => 'en_attente_prise_service']);

        $this->connecter($this->utilisateur('DAP', $this->lycee));
        $this->postJson('/api/v1/cessations/generer', ['delai_jours' => 30])->assertOk()->assertJsonPath('nombre', 1);
        $this->postJson('/api/v1/cessations/generer', ['delai_jours' => 30])->assertJsonPath('nombre', 0); // pas de doublon

        $c = CessationService::first();
        $this->assertSame('non_prise_service', $c->type);
        $this->assertTrue($c->generee_automatiquement);
        $this->assertSame($a1->id, $c->affectation_id);

        // Validation par le DAP : affectation close et agent suspendu.
        $this->postJson("/api/v1/cessations/{$c->id}/valider")->assertOk()->assertJsonPath('data.statut', 'validee');
        $this->assertSame('cessation', $a1->fresh()->statut);
        $this->assertSame('suspendu', $enRetard->fresh()->statut);
    }

    public function test_signalement_par_le_chef_et_validation_par_le_drh(): void
    {
        $agent = $this->agentEnService($this->lycee);
        $user = $this->utilisateur('ENSEIGNANT', $this->lycee, $agent);

        $this->connecter($this->utilisateur('CHEF_ETAB', $this->lycee));
        $this->postJson('/api/v1/cessations', ['agent_id' => $agent->id, 'type' => 'retraite', 'date_cessation' => now()->toDateString()])->assertJsonValidationErrors('motif');
        $id = $this->postJson('/api/v1/cessations', ['agent_id' => $agent->id, 'type' => 'retraite', 'date_cessation' => now()->toDateString(), 'motif' => 'Limite d\'âge'])
            ->assertCreated()->json('data.id');
        $this->postJson('/api/v1/cessations', ['agent_id' => $agent->id, 'type' => 'deces', 'date_cessation' => now()->toDateString(), 'motif' => 'x'])->assertStatus(422);
        $this->postJson("/api/v1/cessations/$id/valider")->assertForbidden(); // le chef signale, il ne valide pas

        $this->connecter($this->utilisateur('DRH'));
        $this->postJson("/api/v1/cessations/$id/valider")->assertOk();
        $this->assertSame('retraite', $agent->fresh()->statut);
        $this->assertSame('inactif', $user->fresh()->etat, 'Le compte d\'un retraité est désactivé.');
    }

    public function test_annulation_d_une_cessation(): void
    {
        $agent = $this->agentEnService($this->lycee);
        $this->connecter($this->utilisateur('DRH'));
        $id = $this->postJson('/api/v1/cessations', ['agent_id' => $agent->id, 'type' => 'abandon_poste', 'date_cessation' => now()->toDateString(), 'motif' => 'Absent'])->json('data.id');

        $this->postJson("/api/v1/cessations/$id/annuler", ['motif' => 'Reprise du service'])->assertOk()->assertJsonPath('data.statut', 'annulee');
        $this->assertSame('actif', $agent->fresh()->statut);
        $this->postJson("/api/v1/cessations/$id/valider")->assertStatus(422);
    }

    public function test_circuit_de_conge_demande_avis_decision(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-09 09:00:00')); // lundi
        $agent = $this->agentEnService($this->lycee);
        $enseignant = $this->utilisateur('ENSEIGNANT', $this->lycee, $agent);

        $this->connecter($enseignant);
        $this->postJson('/api/v1/moi/conges', ['type' => 'annuel', 'date_debut' => '2026-11-01', 'date_fin' => '2026-11-03', 'motif' => 'x'])->assertJsonValidationErrors('date_debut');
        $id = $this->postJson('/api/v1/moi/conges', ['type' => 'maladie', 'date_debut' => '2026-11-16', 'date_fin' => '2026-11-22', 'motif' => 'Hospitalisation'])
            ->assertCreated()->assertJsonPath('data.nombre_jours', 5)->json('data.id');
        $this->postJson('/api/v1/moi/conges', ['type' => 'annuel', 'date_debut' => '2026-11-20', 'date_fin' => '2026-11-25', 'motif' => 'Chevauche'])->assertStatus(422);

        $this->connecter($this->utilisateur('CHEF_ETAB', $this->lycee));
        $this->getJson('/api/v1/badges')->assertJsonPath('data.CONGES', 1);
        $this->postJson("/api/v1/conges/$id/avis", ['favorable' => true, 'commentaire' => 'Remplacement organisé'])->assertOk()->assertJsonPath('data.statut', 'avis_favorable');
        $this->postJson("/api/v1/conges/$id/decision", ['approuver' => true])->assertForbidden();

        $this->connecter($this->utilisateur('DAP', $this->lycee));
        $this->postJson("/api/v1/conges/$id/decision", ['approuver' => true])->assertOk()->assertJsonPath('data.statut', 'approuvee');

        // Les jours ouvrés sont portés en absence justifiée.
        $this->assertSame(5, Presence::where('agent_id', $agent->id)->where('statut', 'absence_justifiee')->count());
    }

    public function test_l_agent_retire_une_demande_non_traitee(): void
    {
        $agent = $this->agentEnService($this->lycee);
        $this->connecter($this->utilisateur('ENSEIGNANT', $this->lycee, $agent));
        $id = $this->postJson('/api/v1/moi/conges', ['type' => 'exceptionnel', 'date_debut' => now()->addDays(3)->toDateString(), 'date_fin' => now()->addDays(4)->toDateString(), 'motif' => 'Famille'])->json('data.id');

        $this->deleteJson("/api/v1/moi/conges/$id")->assertOk();
        $this->assertSame(0, Conge::count());
    }

    public function test_solde_de_conge_annuel(): void
    {
        $agent = $this->agentEnService($this->lycee);
        Conge::create(['agent_id' => $agent->id, 'type' => 'annuel', 'date_debut' => now()->startOfYear()->addDays(40)->toDateString(), 'date_fin' => now()->startOfYear()->addDays(50)->toDateString(), 'nombre_jours' => 8, 'statut' => 'approuvee']);
        $this->connecter($this->utilisateur('ENSEIGNANT', $this->lycee, $agent));

        $this->getJson('/api/v1/moi/conges')->assertJsonPath('solde_annuel.pris', 8)->assertJsonPath('solde_annuel.restant', 22);
    }
}
