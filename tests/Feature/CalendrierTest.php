<?php

namespace Tests\Feature;

use App\Models\Presence;
use App\Support\Calendrier;
use Carbon\Carbon;

class CalendrierTest extends GrhmenTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_jours_feries_officiels(): void
    {
        $this->assertSame('Journée de l\'Enseignant', Calendrier::ferie(Carbon::parse('2026-03-23')));
        $this->assertSame('Fête nationale', Calendrier::ferie(Carbon::parse('2027-08-17')));
        $this->assertNull(Calendrier::ferie(Carbon::parse('2026-03-24')));
        // Semaine de l'Ascension 2026 : du lundi 11 au vendredi 15 mai, le jeudi 14 est férié.
        $this->assertSame(4, Calendrier::joursOuvres(Carbon::parse('2026-05-11'), Carbon::parse('2026-05-15')));
    }

    public function test_un_conge_ne_decompte_pas_les_jours_feries(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 09:00:00'));
        $agent = $this->agentEnService($this->lycee);
        $this->connecter($this->utilisateur('ENSEIGNANT', $this->lycee, $agent));

        $id = $this->postJson('/api/v1/moi/conges', ['type' => 'annuel', 'date_debut' => '2026-05-11', 'date_fin' => '2026-05-15', 'motif' => 'Congé'])
            ->assertCreated()->assertJsonPath('data.nombre_jours', 4)->json('data.id');

        $this->connecter($this->utilisateur('DAP', $this->lycee));
        $this->postJson("/api/v1/conges/$id/decision", ['approuver' => true])->assertOk();
        $this->assertSame(4, Presence::where('agent_id', $agent->id)->count(), 'Pas d\'absence justifiée saisie le jour férié.');
    }

    public function test_les_listes_exposent_le_ministere_et_les_feries(): void
    {
        $this->connecter($this->utilisateur('DRH'));
        $r = $this->getJson('/api/v1/listes')->assertOk();
        $this->assertStringContainsString('Instruction civique', $r->json('ministere.nom'));
        $this->assertArrayHasKey('ecole_pre_primaire', $r->json('enums.types_etablissement'));
        $this->assertNotEmpty($r->json('feries'));
    }
}
