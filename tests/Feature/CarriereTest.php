<?php

namespace Tests\Feature;

use App\Models\Affectation;
use App\Models\Agent;

/** Circuit complet : affectation → prise de service → début des cours. */
class CarriereTest extends GrhmenTestCase
{
    protected function affecter(Agent $agent, $etab, array $attrs = [])
    {
        return $this->postJson('/api/v1/affectations', array_merge([
            'agent_id' => $agent->id, 'etablissement_id' => $etab->id, 'nature' => 'mutation',
            'numero_decision' => '0001/MEN/SG/DRH', 'date_decision' => now()->subDays(10)->toDateString(), 'date_effet' => now()->subDays(2)->toDateString(),
        ], $attrs));
    }

    public function test_circuit_complet_affectation_prise_de_service_debut_des_cours(): void
    {
        $gestionnaire = $this->utilisateur('GESTIONNAIRE_RH');
        $agent = $this->agentEnService($this->college);
        $enseignant = $this->utilisateur('ENSEIGNANT', $this->college, $agent);
        $censeur = $this->utilisateur('CENSEUR', $this->lycee);

        // 1. Mutation vers le lycée : l'ancienne affectation se termine, l'agent est rattaché au lycée.
        $this->connecter($gestionnaire);
        $affId = $this->affecter($agent, $this->lycee)->assertCreated()->assertJsonPath('data.statut', 'en_attente_prise_service')->json('data.id');
        $this->assertSame($this->lycee->id, $agent->fresh()->etablissement_id);
        $this->assertSame(1, Affectation::where('agent_id', $agent->id)->where('statut', 'terminee')->count());
        $this->assertSame($this->lycee->id, $enseignant->fresh()->etablissement_id, 'Le compte suit l\'agent.');

        // 2. L'enseignant voit son affectation et déclare sa prise de service.
        $this->connecter($enseignant->fresh());
        $this->getJson('/api/v1/moi/services')->assertOk()->assertJsonPath('affectation.id', $affId);
        $this->postJson('/api/v1/moi/debut-cours', ['date_debut' => now()->toDateString(), 'classes' => '2nde A', 'volume_horaire' => 18])
            ->assertStatus(422); // impossible avant la prise de service confirmée
        $psId = $this->postJson('/api/v1/moi/prise-service', ['date_prise_service' => now()->subDay()->toDateString()])
            ->assertCreated()->assertJsonPath('data.statut', 'declaree')->json('data.id');
        $this->postJson('/api/v1/moi/prise-service', ['date_prise_service' => now()->toDateString()])->assertStatus(422);

        // 3. Le censeur du lycée confirme : l'affectation passe « en service ».
        $this->connecter($censeur);
        $this->getJson('/api/v1/badges')->assertJsonPath('data.PRISES_SERVICE', 1);
        $this->postJson("/api/v1/prises-service/$psId/confirmer")->assertOk()->assertJsonPath('data.statut', 'confirmee');
        $this->assertSame('en_service', Affectation::find($affId)->statut);

        // 4. L'enseignant déclare son début des cours, le censeur le confirme.
        $this->connecter($enseignant->fresh());
        $dcId = $this->postJson('/api/v1/moi/debut-cours', ['date_debut' => now()->toDateString(), 'classes' => '2nde A, 1ère C', 'volume_horaire' => 18])
            ->assertCreated()->json('data.id');

        $this->connecter($censeur);
        $this->postJson("/api/v1/debuts-cours/$dcId/confirmer")->assertOk()->assertJsonPath('data.statut', 'confirme');
        $this->postJson("/api/v1/debuts-cours/$dcId/confirmer")->assertStatus(422);
    }

    public function test_la_prise_de_service_ne_peut_preceder_la_date_d_effet(): void
    {
        $this->connecter($this->utilisateur('GESTIONNAIRE_RH'));
        $agent = $this->agent($this->college);
        $affId = $this->affecter($agent, $this->lycee, ['date_effet' => now()->subDay()->toDateString()])->json('data.id');

        $this->connecter($this->utilisateur('CHEF_ETAB', $this->lycee));
        $this->postJson('/api/v1/prises-service', ['affectation_id' => $affId, 'date_prise_service' => now()->subDays(5)->toDateString()])->assertStatus(422);
        $this->postJson('/api/v1/prises-service', ['affectation_id' => $affId, 'date_prise_service' => now()->addDay()->toDateString()])->assertJsonValidationErrors('date_prise_service');
    }

    public function test_le_chef_enregistre_et_confirme_en_une_fois(): void
    {
        $this->connecter($this->utilisateur('GESTIONNAIRE_RH'));
        $agent = $this->agent($this->college);
        $affId = $this->affecter($agent, $this->lycee)->json('data.id');

        $this->connecter($this->utilisateur('CHEF_ETAB', $this->lycee));
        $this->postJson('/api/v1/prises-service', ['affectation_id' => $affId, 'date_prise_service' => now()->toDateString(), 'confirmer' => true])
            ->assertOk()->assertJsonPath('data.statut', 'confirmee');
        $this->assertSame('en_service', Affectation::find($affId)->statut);
    }

    public function test_un_chef_ne_confirme_pas_hors_de_son_etablissement(): void
    {
        $this->connecter($this->utilisateur('GESTIONNAIRE_RH'));
        $agent = $this->agent($this->college);
        $affId = $this->affecter($agent, $this->lycee)->json('data.id');

        $this->connecter($this->utilisateur('CHEF_ETAB', $this->college));
        $this->postJson('/api/v1/prises-service', ['affectation_id' => $affId, 'date_prise_service' => now()->toDateString()])->assertForbidden();
    }

    public function test_rejet_d_une_prise_de_service_puis_nouvelle_declaration(): void
    {
        $this->connecter($this->utilisateur('GESTIONNAIRE_RH'));
        $agent = $this->agent($this->college);
        $enseignant = $this->utilisateur('ENSEIGNANT', $this->college, $agent);
        $this->affecter($agent, $this->lycee);

        $this->connecter($enseignant->fresh());
        $psId = $this->postJson('/api/v1/moi/prise-service', ['date_prise_service' => now()->toDateString()])->json('data.id');

        $this->connecter($this->utilisateur('CENSEUR', $this->lycee));
        $this->postJson("/api/v1/prises-service/$psId/rejeter", [])->assertJsonValidationErrors('motif');
        $this->postJson("/api/v1/prises-service/$psId/rejeter", ['motif' => 'Agent non présenté'])->assertOk()->assertJsonPath('data.statut', 'rejetee');

        $this->connecter($enseignant->fresh());
        $this->postJson('/api/v1/moi/prise-service', ['date_prise_service' => now()->toDateString()])->assertCreated();
    }

    public function test_annulation_d_une_affectation_restaure_le_poste_d_origine(): void
    {
        $this->connecter($this->utilisateur('DRH'));
        $agent = $this->agentEnService($this->college);
        $affId = $this->affecter($agent, $this->lycee)->json('data.id');

        $this->postJson("/api/v1/affectations/$affId/annuler", ['motif' => 'Erreur de saisie'])->assertOk()->assertJsonPath('data.statut', 'annulee');

        $this->assertSame($this->college->id, $agent->fresh()->etablissement_id);
        $this->assertSame(1, Affectation::where('agent_id', $agent->id)->where('statut', 'en_service')->count());
    }

    public function test_modification_d_une_affectation_en_attente(): void
    {
        $this->connecter($this->utilisateur('DRH'));
        $agent = $this->agent($this->college);
        $affId = $this->affecter($agent, $this->lycee)->json('data.id');

        $this->putJson("/api/v1/affectations/$affId", ['nature' => 'nomination', 'date_effet' => now()->toDateString(), 'numero_decision' => '0099/MEN'])
            ->assertOk()->assertJsonPath('data.nature', 'nomination');
    }

    public function test_un_agent_retraite_ne_peut_pas_etre_affecte(): void
    {
        $this->connecter($this->utilisateur('DRH'));
        $agent = $this->agent($this->college, ['statut' => 'retraite']);

        $this->affecter($agent, $this->lycee)->assertStatus(422);
    }

    public function test_filtre_des_affectations_sans_prise_de_service(): void
    {
        $this->connecter($this->utilisateur('DRH'));
        $this->affecter($this->agent($this->college), $this->lycee, ['date_effet' => now()->subDays(40)->toDateString()]);
        $this->affecter($this->agent($this->college), $this->lycee, ['date_effet' => now()->subDays(5)->toDateString()]);

        $this->assertSame(1, $this->getJson('/api/v1/affectations?retard_jours=30')->json('total'));
        $this->assertSame(2, $this->getJson('/api/v1/affectations?statut=en_attente_prise_service')->json('total'));
    }

    public function test_liste_des_enseignants_sans_debut_des_cours(): void
    {
        $sans = $this->agentEnService($this->lycee);
        $this->agentEnService($this->lycee, ['categorie' => 'administratif']);
        $this->connecter($this->utilisateur('CENSEUR', $this->lycee));

        $ids = collect($this->getJson('/api/v1/debuts-cours/sans-debut')->json('data'))->pluck('id');
        $this->assertEquals([$sans->id], $ids->all());

        $this->postJson('/api/v1/debuts-cours', ['agent_id' => $sans->id, 'date_debut' => now()->toDateString(), 'classes' => '6e A', 'volume_horaire' => 16, 'confirmer' => true])
            ->assertCreated()->assertJsonPath('data.statut', 'confirme');
        $this->assertCount(0, $this->getJson('/api/v1/debuts-cours/sans-debut')->json('data'));
    }
}
