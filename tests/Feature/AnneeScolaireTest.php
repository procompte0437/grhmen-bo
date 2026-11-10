<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\CessationService;
use App\Models\Presence;

/** Les écrans suivent l'année scolaire choisie dans la barre du haut (en-tête X-Annee-Scolaire). */
class AnneeScolaireTest extends GrhmenTestCase
{
    protected function annee(string $libelle): AnneeScolaire
    {
        return AnneeScolaire::where('libelle', $libelle)->firstOrFail();
    }

    public function test_les_presences_suivent_l_annee_consultee(): void
    {
        $agent = $this->agentEnService($this->lycee);
        Presence::create(['agent_id' => $agent->id, 'etablissement_id' => $this->lycee->id, 'date' => '2026-03-10', 'statut' => 'present']);
        Presence::create(['agent_id' => $agent->id, 'etablissement_id' => $this->lycee->id, 'date' => '2026-10-12', 'statut' => 'absent']);
        $this->connecter($this->utilisateur('DRH'));

        // Sans en-tête : année active (2026-2027).
        $this->getJson('/api/v1/presences')->assertJsonPath('total', 1)->assertJsonPath('data.0.date', '2026-10-12');

        $ancienne = $this->annee('2025-2026');
        $this->withHeader('X-Annee-Scolaire', (string) $ancienne->id)->getJson('/api/v1/presences')
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.date', '2026-03-10');
    }

    public function test_les_cessations_et_le_tableau_de_bord_suivent_l_annee(): void
    {
        $agent = $this->agentEnService($this->lycee);
        CessationService::create(['agent_id' => $agent->id, 'etablissement_id' => $this->lycee->id, 'numero' => 'CS-A', 'type' => 'retraite', 'date_cessation' => '2026-02-01', 'statut' => 'validee']);
        $this->connecter($this->utilisateur('DRH'));
        $ancienne = $this->annee('2025-2026');

        $this->getJson('/api/v1/cessations')->assertJsonPath('total', 0);
        $this->withHeader('X-Annee-Scolaire', (string) $ancienne->id)->getJson('/api/v1/cessations')->assertJsonPath('total', 1);

        $this->withHeader('X-Annee-Scolaire', (string) $ancienne->id)->getJson('/api/v1/tableau-de-bord')
            ->assertJsonPath('annee_scolaire', '2025-2026')->assertJsonPath('annee_active', false)
            ->assertJsonPath('evolution_presences.9.date', '2026-07-31');
    }

    public function test_une_affectation_en_cours_reste_visible_d_une_annee_a_l_autre(): void
    {
        $this->agentEnService($this->lycee); // affectation en service depuis 2020
        $this->connecter($this->utilisateur('DRH'));

        $this->getJson('/api/v1/affectations')->assertJsonPath('total', 1);
        $this->withHeader('X-Annee-Scolaire', (string) $this->annee('2025-2026')->id)->getJson('/api/v1/affectations')->assertJsonPath('total', 1);
    }

    public function test_une_annee_inconnue_retombe_sur_l_annee_active(): void
    {
        $this->connecter($this->utilisateur('DRH'));
        $this->withHeader('X-Annee-Scolaire', '9999')->getJson('/api/v1/tableau-de-bord')->assertJsonPath('annee_scolaire', '2026-2027');
    }
}
