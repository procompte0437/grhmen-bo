<?php

namespace Tests\Feature;

use App\Models\Affectation;
use App\Models\CessationService;
use App\Models\Conge;
use App\Models\Presence;
use App\Models\PriseService;

/** Documents officiels imprimables : contenu, habilitations et périmètre. */
class DocumentTest extends GrhmenTestCase
{
    public function test_certificat_de_prise_de_service(): void
    {
        $agent = $this->agentEnService($this->lycee);
        $ps = PriseService::where('agent_id', $agent->id)->first();
        $this->connecter($this->utilisateur('CHEF_ETAB', $this->lycee));

        $r = $this->getJson("/api/v1/documents/prise-service/{$ps->id}")->assertOk();
        $r->assertJsonPath('type', 'prise-service')->assertJsonPath('data.agent.matricule', $agent->matricule);
        $this->assertStringContainsString('Instruction civique', $r->json('ministere.nom'));
        $this->assertDatabaseHas('journal_activites', ['module' => 'documents', 'action' => 'edition']);
    }

    public function test_hors_perimetre_le_document_est_refuse(): void
    {
        $agent = $this->agentEnService($this->college);
        $ps = PriseService::where('agent_id', $agent->id)->first();
        $this->connecter($this->utilisateur('CHEF_ETAB', $this->lycee));

        $this->getJson("/api/v1/documents/prise-service/{$ps->id}")->assertForbidden();
    }

    public function test_un_type_inconnu_renvoie_404(): void
    {
        $this->connecter($this->utilisateur('DRH'));
        $this->getJson('/api/v1/documents/inconnu/1')->assertNotFound();
    }

    public function test_titre_de_conge_avec_date_de_reprise(): void
    {
        $agent = $this->agentEnService($this->lycee);
        // Congé du lundi 11 au vendredi 15 mai 2026 : reprise le lundi 18 mai.
        $c = Conge::create(['agent_id' => $agent->id, 'etablissement_id' => $this->lycee->id, 'type' => 'annuel', 'date_debut' => '2026-05-11', 'date_fin' => '2026-05-15', 'nombre_jours' => 4, 'statut' => 'approuvee']);
        $this->connecter($this->utilisateur('ENSEIGNANT', $this->lycee, $agent));

        $this->getJson("/api/v1/documents/conge/{$c->id}")->assertOk()->assertJsonPath('data.date_reprise', '2026-05-18');
    }

    public function test_un_agent_n_edite_que_ses_propres_documents(): void
    {
        $moi = $this->agentEnService($this->lycee);
        $collegue = $this->agentEnService($this->lycee);
        $this->connecter($this->utilisateur('ENSEIGNANT', $this->lycee, $moi));

        $this->getJson("/api/v1/documents/attestation-travail/{$moi->id}")->assertOk()->assertJsonPath('data.agent.id', $moi->id);
        $this->getJson("/api/v1/documents/attestation-travail/{$collegue->id}")->assertForbidden();
        // L'enseignant n'a pas accès aux certificats de cessation.
        $cs = CessationService::create(['agent_id' => $moi->id, 'etablissement_id' => $this->lycee->id, 'numero' => 'CS-T1', 'type' => 'demission', 'date_cessation' => '2026-10-01', 'statut' => 'signalee']);
        $this->getJson("/api/v1/documents/cessation/{$cs->id}")->assertForbidden();
    }

    public function test_notification_de_retraite_selon_la_limite_d_age(): void
    {
        $agent = $this->agentEnService($this->lycee, ['date_naissance' => '1965-03-14', 'date_recrutement' => '1990-09-01']);
        $this->connecter($this->utilisateur('DRH'));

        $r = $this->getJson("/api/v1/documents/retraite/{$agent->id}")->assertOk();
        $limite = \App\Models\Agent::AGE_LIMITE_RETRAITE;
        $r->assertJsonPath('data.date_depart', (1965 + $limite).'-03-14')->assertJsonPath('data.age_limite', $limite);
    }

    public function test_notification_de_retraite_impossible_sans_date_de_naissance(): void
    {
        $agent = $this->agentEnService($this->lycee, ['date_naissance' => null]);
        $this->connecter($this->utilisateur('DRH'));

        $this->getJson("/api/v1/documents/retraite/{$agent->id}")->assertStatus(422);
    }

    public function test_notification_d_affectation(): void
    {
        $agent = $this->agent($this->college);
        $a = Affectation::create(['agent_id' => $agent->id, 'etablissement_id' => $this->lycee->id, 'etablissement_origine_id' => $this->college->id, 'nature' => 'mutation', 'numero_decision' => '0457/MEN/SG/DGRH', 'date_effet' => now()->toDateString(), 'statut' => 'en_attente_prise_service']);
        $this->connecter($this->utilisateur('GESTIONNAIRE_RH'));

        $this->getJson("/api/v1/documents/affectation/{$a->id}")->assertOk()
            ->assertJsonPath('data.numero_decision', '0457/MEN/SG/DGRH')->assertJsonPath('data.etablissement_origine.nom', $this->college->nom);
    }

    public function test_etat_mensuel_des_absences_au_poste(): void
    {
        $a1 = $this->agentEnService($this->lycee);
        $a2 = $this->agentEnService($this->lycee);
        foreach (['2026-10-05' => 'absent', '2026-10-06' => 'absent', '2026-10-07' => 'retard'] as $d => $s) {
            Presence::create(['agent_id' => $a1->id, 'etablissement_id' => $this->lycee->id, 'date' => $d, 'statut' => $s]);
        }
        Presence::create(['agent_id' => $a2->id, 'etablissement_id' => $this->lycee->id, 'date' => '2026-10-05', 'statut' => 'present']);
        $this->connecter($this->utilisateur('CHEF_ETAB', $this->lycee));

        $r = $this->getJson("/api/v1/documents/etat-absences/{$this->lycee->id}?mois=2026-10")->assertOk();
        $r->assertJsonPath('data.totaux.absences', 2)->assertJsonPath('data.totaux.agents_absents', 1)->assertJsonPath('data.date_limite_transmission', '2026-10-30');
        $ligne = collect($r->json('data.lignes'))->firstWhere('agent.id', $a1->id);
        $this->assertSame(['05/10', '06/10'], $ligne['dates_absence']);

        $this->getJson("/api/v1/documents/etat-absences/{$this->college->id}?mois=2026-10")->assertForbidden();
        $this->getJson("/api/v1/documents/etat-absences/{$this->lycee->id}?mois=octobre")->assertStatus(422);
    }
}
