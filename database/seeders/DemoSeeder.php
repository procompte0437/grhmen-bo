<?php

namespace Database\Seeders;

use App\Models\Affectation;
use App\Models\Agent;
use App\Models\AnneeScolaire;
use App\Models\CessationService;
use App\Models\Conge;
use App\Models\Corps;
use App\Models\DebutCours;
use App\Models\Discipline;
use App\Models\Etablissement;
use App\Models\Fonction;
use App\Models\Grade;
use App\Models\JournalActivite;
use App\Models\PriseService;
use App\Models\Profil;
use App\Models\Province;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Données de démonstration : établissements, personnel, carrières, présences et comptes de test. */
class DemoSeeder extends Seeder
{
    protected array $noms = ['MBA', 'NGUEMA', 'ONDO', 'OBAME', 'MOUSSAVOU', 'MABIKA', 'NDONG', 'ELLA', 'BOUSSOUGOU', 'KOUMBA', 'MINTSA', 'NZE', 'BEKALE', 'EKOMIE', 'MOUELE', 'NZAMBA', 'OGANDAGA', 'IBINGA', 'MAVOUNGOU', 'NZIENGUI', 'MOMBO', 'ASSELE', 'ENGONGA', 'MAKAYA', 'OYANE', 'MBADINGA', 'NTOUTOUME', 'ABESSOLO', 'BOUKANDOU', 'MIHINDOU'];

    protected array $prenomsH = ['Jean-Pierre', 'Serge', 'Patrick', 'Landry', 'Brice', 'Christian', 'Rodrigue', 'Hervé', 'Ghislain', 'Franck', 'Yannick', 'Arsène', 'Thierry', 'Guy-Roger', 'Fabrice'];

    protected array $prenomsF = ['Marie-Claire', 'Sylvie', 'Prisca', 'Nadège', 'Christelle', 'Odile', 'Pélagie', 'Carine', 'Gisèle', 'Laetitia', 'Huguette', 'Flore', 'Rachelle', 'Annick', 'Judith'];

    protected int $compteurMatricule = 100200;

    protected $ref;

    public function run(): void
    {
        mt_srand(241);
        $this->ref = [
            'province' => Province::pluck('id', 'code'),
            'corps' => Corps::pluck('id', 'code'),
            'grade' => Grade::pluck('id', 'code'),
            'discipline' => Discipline::pluck('id', 'code'),
            'fonction' => Fonction::pluck('id', 'code'),
            'profil' => Profil::pluck('id', 'code'),
        ];
        $annee = AnneeScolaire::active();

        $etabs = $this->etablissements();
        $admin = User::updateOrCreate(['email' => 'admin@grhmen.ga'], [
            'name' => 'Administrateur GRHMEN', 'password' => Hash::make('password'), 'profil_id' => $this->ref['profil']['ADMIN'], 'etat' => 'actif',
        ]);

        // ---- Personnel des établissements
        $plan = ['lycee' => 8, 'lycee_technique' => 6, 'college' => 6, 'ecole_primaire' => 5, 'ecole_pre_primaire' => 3, 'direction_centrale' => 4, 'academie' => 3];
        $tous = collect();
        foreach ($etabs as $code => $etab) {
            $n = $plan[$etab->type];
            for ($i = 0; $i < $n; $i++) {
                $tous->push($this->creerAgent($etab));
            }
        }

        // ---- Personnes nommées des comptes de démonstration
        $lnlm = $etabs['LNLM'];
        $comptes = [
            ['drh@grhmen.ga', 'DRH', $etabs['DGRH'], ['nom' => 'MOUSSAVOU', 'prenom' => 'Albertine', 'sexe' => 'F', 'categorie' => 'administratif', 'fonction' => 'DIR_CENT', 'corps' => 'ADM', 'grade' => 'A1']],
            ['gestionnaire@grhmen.ga', 'GESTIONNAIRE_RH', $etabs['DGRH'], ['nom' => 'NDONG', 'prenom' => 'Rodrigue', 'sexe' => 'M', 'categorie' => 'administratif', 'fonction' => 'CHEF_SERV', 'corps' => 'ATT', 'grade' => 'A2']],
            ['dap.estuaire@grhmen.ga', 'DAP', $etabs['AP-EST'], ['nom' => 'OBAME', 'prenom' => 'Ghislain', 'sexe' => 'M', 'categorie' => 'administratif', 'fonction' => 'DAP', 'corps' => 'ADM', 'grade' => 'A1']],
            ['proviseur@grhmen.ga', 'CHEF_ETAB', $lnlm, ['nom' => 'MINTSA', 'prenom' => 'Hervé', 'sexe' => 'M', 'categorie' => 'enseignant_secondaire', 'fonction' => 'PROV', 'corps' => 'PLC', 'grade' => 'A1', 'discipline' => 'PHILO']],
            ['censeur@grhmen.ga', 'CENSEUR', $lnlm, ['nom' => 'ELLA', 'prenom' => 'Odile', 'sexe' => 'F', 'categorie' => 'enseignant_secondaire', 'fonction' => 'CENS', 'corps' => 'PLC', 'grade' => 'A1', 'discipline' => 'FR']],
            ['enseignant@grhmen.ga', 'ENSEIGNANT', $lnlm, ['nom' => 'NGUEMA', 'prenom' => 'Marie-Claire', 'sexe' => 'F', 'categorie' => 'enseignant_secondaire', 'fonction' => 'ENS', 'corps' => 'PCEG', 'grade' => 'A2', 'discipline' => 'MATH']],
            ['directeur.ecole@grhmen.ga', 'CHEF_ETAB', $etabs['EP-GLASS'], ['nom' => 'KOUMBA', 'prenom' => 'Pélagie', 'sexe' => 'F', 'categorie' => 'enseignant_primaire', 'fonction' => 'DIR_ECOLE', 'corps' => 'INST', 'grade' => 'B1', 'discipline' => 'POLY']],
            ['agent@grhmen.ga', 'AGENT_ADMIN', $etabs['DGRH'], ['nom' => 'BOUSSOUGOU', 'prenom' => 'Landry', 'sexe' => 'M', 'categorie' => 'administratif', 'fonction' => 'AGENT_ADM', 'corps' => 'SEC', 'grade' => 'B2']],
        ];
        $agentsComptes = [];
        foreach ($comptes as [$email, $profil, $etab, $attrs]) {
            $agent = $this->creerAgent($etab, $attrs);
            $tous->push($agent);
            $agentsComptes[$email] = $agent;
            $this->creerCompte($email, $agent, $profil, $etab);
        }

        // ---- Carrières : affectation en service et prise de service confirmée pour tout le monde
        $chefs = [];
        foreach ($tous as $agent) {
            $this->carriere($agent, $annee, $admin);
        }

        // ---- Nouvel enseignant affecté il y a 3 jours, en attente de prise de service (compte de démo)
        $nouveau = $this->creerAgent($etabs['LPIG'], ['nom' => 'OBAME', 'prenom' => 'Junior', 'sexe' => 'M', 'categorie' => 'enseignant_secondaire', 'fonction' => 'ENS', 'corps' => 'PCEG', 'grade' => 'A2', 'discipline' => 'PC', 'date_recrutement' => now()->subYears(2)->toDateString()]);
        $this->carriere($nouveau, $annee, $admin);
        $this->affecter($nouveau, $lnlm, 'mutation', now()->subDays(3), 'en_attente_prise_service', $annee, $admin);
        $this->creerCompte('nouveau.enseignant@grhmen.ga', $nouveau->fresh(), 'ENSEIGNANT', $lnlm);
        $tous->push($nouveau->fresh());

        // ---- Autres affectations en attente (dont certaines de plus de 30 jours → cessation pour non-prise de service)
        $enAttente = [
            ['LNLM', 1, 'premiere_affectation', 'ANG'], ['LNLM', 6, 'premiere_affectation', 'SVT'],
            ['LJAA', 45, 'premiere_affectation', 'HG'], ['LDD', 38, 'mutation', 'FR'], ['CES-NZENG', 52, 'premiere_affectation', 'MATH'],
            ['EP-OWENDO', 12, 'premiere_affectation', 'POLY'], ['LYC-OYEM', 33, 'redeploiement', 'EPS'],
        ];
        foreach ($enAttente as [$codeEtab, $jours, $nature, $disc]) {
            $etab = $etabs[$codeEtab];
            $categorie = $etab->type === 'ecole_primaire' ? 'enseignant_primaire' : 'enseignant_secondaire';
            $agent = $this->creerAgent($etab, ['categorie' => $categorie, 'discipline' => $disc, 'date_recrutement' => now()->subDays($jours + 30)->toDateString()]);
            $agent->update(['etablissement_id' => null]);
            $this->affecter($agent, $etab, $nature, now()->subDays($jours), 'en_attente_prise_service', $annee, $admin);
        }

        // ---- Une prise de service déclarée par un agent et à confirmer par le chef (Lycée Léon Mba)
        $declarant = $this->creerAgent($lnlm, ['categorie' => 'enseignant_secondaire', 'discipline' => 'ESP', 'nom' => 'MAKAYA', 'prenom' => 'Christelle', 'sexe' => 'F']);
        $declarant->update(['etablissement_id' => null]);
        $aff = $this->affecter($declarant, $lnlm, 'mutation', now()->subDays(8), 'en_attente_prise_service', $annee, $admin);
        PriseService::create([
            'affectation_id' => $aff->id, 'agent_id' => $declarant->id, 'etablissement_id' => $lnlm->id, 'numero' => 'PS-'.now()->year.'-DEMO01',
            'date_prise_service' => now()->subDays(6)->toDateString(), 'statut' => 'declaree', 'observations' => 'Arrivée par le vol de Franceville.',
        ]);

        // ---- Débuts des cours
        $debutAnnee = Carbon::parse($annee->date_debut)->addDays(6);
        $enService = Agent::whereIn('categorie', ['enseignant_primaire', 'enseignant_secondaire'])->where('statut', 'actif')
            ->whereHas('affectations', fn ($a) => $a->where('statut', 'en_service'))->get();
        $declaresEnAttente = 0;
        foreach ($enService as $i => $agent) {
            $tirage = mt_rand(1, 100);
            $estDemo = $agent->id === $agentsComptes['enseignant@grhmen.ga']->id;
            if (! $estDemo && $tirage > 88) {
                continue; // pas encore de début des cours
            }
            $enAttenteDC = ! $estDemo && $agent->etablissement_id === $lnlm->id && $declaresEnAttente < 3 && $tirage > 60;
            $declaresEnAttente += $enAttenteDC ? 1 : 0;
            $aff = $agent->affectations()->where('statut', 'en_service')->first();
            DebutCours::create([
                'agent_id' => $agent->id, 'etablissement_id' => $agent->etablissement_id, 'affectation_id' => $aff->id,
                'discipline_id' => $agent->discipline_id, 'annee_scolaire_id' => $annee->id,
                'date_debut' => $enAttenteDC ? now()->subDays(mt_rand(2, 5))->toDateString() : $debutAnnee->copy()->addDays(mt_rand(0, 4))->toDateString(),
                'classes' => $this->classes($agent), 'volume_horaire' => $agent->categorie === 'enseignant_primaire' ? 30 : mt_rand(12, 20),
                'statut' => $enAttenteDC ? 'declare' : 'confirme',
                'traite_par' => $enAttenteDC ? null : $admin->id, 'date_traitement' => $enAttenteDC ? null : $debutAnnee,
            ]);
        }

        // ---- Présences des 15 derniers jours ouvrés (hors aujourd'hui)
        $this->presences($lnlm, $agentsComptes);

        // ---- Cessations de service
        $retraite = $this->creerAgent($etabs['LJAA'], ['categorie' => 'enseignant_secondaire', 'discipline' => 'HG', 'date_naissance' => now()->subYears(60)->subMonths(2)->toDateString()]);
        $this->carriere($retraite, $annee, $admin);
        $c = CessationService::create(['agent_id' => $retraite->id, 'etablissement_id' => $retraite->etablissement_id, 'affectation_id' => $retraite->affectations()->first()->id,
            'numero' => 'CS-'.now()->year.'-DEMO01', 'type' => 'retraite', 'date_cessation' => now()->subDays(20)->toDateString(),
            'motif' => 'Admission à la retraite, limite d\'âge atteinte.', 'statut' => 'validee', 'signalee_par' => $admin->id, 'validee_par' => $admin->id, 'date_validation' => now()->subDays(18)]);
        $retraite->update(['statut' => 'retraite']);
        $c->affectation->update(['statut' => 'cessation']);

        $abandon = $tous->first(fn ($a) => $a->etablissement_id === $etabs['CES-NZENG']->id && $a->estEnseignant());
        CessationService::create(['agent_id' => $abandon->id, 'etablissement_id' => $abandon->etablissement_id, 'affectation_id' => $abandon->affectations()->first()?->id,
            'numero' => 'CS-'.now()->year.'-DEMO02', 'type' => 'abandon_poste', 'date_cessation' => now()->subDays(4)->toDateString(),
            'motif' => 'Absent sans justification depuis plus de 15 jours ouvrables malgré deux mises en demeure.', 'statut' => 'signalee', 'signalee_par' => $admin->id]);

        // ---- Congés
        $ens = $agentsComptes['enseignant@grhmen.ga'];
        Conge::create(['agent_id' => $ens->id, 'etablissement_id' => $ens->etablissement_id, 'type' => 'annuel', 'date_debut' => now()->subMonths(3)->startOfMonth()->toDateString(),
            'date_fin' => now()->subMonths(3)->startOfMonth()->addDays(9)->toDateString(), 'nombre_jours' => 8, 'motif' => 'Congé annuel', 'statut' => 'approuvee', 'decision_par' => $admin->id, 'date_decision' => now()->subMonths(4)]);
        $collegues = $tous->filter(fn ($a) => $a->etablissement_id === $lnlm->id && $a->id !== $ens->id && ! in_array($a->id, collect($agentsComptes)->pluck('id')->all(), true))->values();
        Conge::create(['agent_id' => $collegues[0]->id, 'etablissement_id' => $lnlm->id, 'type' => 'maladie', 'date_debut' => now()->addDays(2)->toDateString(),
            'date_fin' => now()->addDays(8)->toDateString(), 'nombre_jours' => 5, 'motif' => 'Intervention chirurgicale programmée (certificat médical joint).', 'statut' => 'soumise']);
        Conge::create(['agent_id' => $collegues[1]->id, 'etablissement_id' => $lnlm->id, 'type' => 'exceptionnel', 'date_debut' => now()->addDays(5)->toDateString(),
            'date_fin' => now()->addDays(7)->toDateString(), 'nombre_jours' => 3, 'motif' => 'Obsèques d\'un parent à Oyem.', 'statut' => 'avis_favorable', 'avis_par' => $admin->id, 'avis_chef' => 'Cours rattrapés par un collègue.']);

        JournalActivite::create(['user_id' => $admin->id, 'action' => 'initialisation', 'module' => 'systeme', 'description' => 'Initialisation des données de démonstration GRHMEN']);
    }

    protected function etablissements(): array
    {
        $liste = [
            ['LNLM', 'Lycée national Léon Mba', 'lycee', 'G1', 'Libreville', 2400, 0.3925, 9.4536],
            ['LPIG', 'Lycée Paul Indjendjet Gondjout', 'lycee', 'G1', 'Libreville', 2100, 0.4100, 9.4450],
            ['LTO', 'Lycée technique d\'Owendo', 'lycee_technique', 'G1', 'Owendo', 1300, 0.2900, 9.5000],
            ['CES-NZENG', 'CES de Nzeng-Ayong', 'college', 'G1', 'Libreville', 1800, 0.4300, 9.4800],
            ['EP-GLASS', 'École publique de Glass', 'ecole_primaire', 'G1', 'Libreville', 650, 0.3800, 9.4400],
            ['EP-OWENDO', 'École publique d\'Owendo centre', 'ecole_primaire', 'G1', 'Owendo', 520, 0.3000, 9.5050],
            ['LDD', 'Lycée Djoué Dabany', 'lycee', 'G2', 'Franceville', 1600, -1.6333, 13.5833],
            ['EP-POTOS', 'École publique de Potos', 'ecole_primaire', 'G2', 'Franceville', 480, -1.6400, 13.5900],
            ['LJAA', 'Lycée Joseph Ambouroué Avaro', 'lycee', 'G8', 'Port-Gentil', 1900, -0.7193, 8.7815],
            ['CES-PG', 'CES de Port-Gentil', 'college', 'G8', 'Port-Gentil', 1200, -0.7200, 8.7900],
            ['LYC-OYEM', 'Lycée public d\'Oyem', 'lycee', 'G9', 'Oyem', 1400, 1.5995, 11.5793],
            ['EP-OYEM', 'École publique d\'Oyem centre', 'ecole_primaire', 'G9', 'Oyem', 560, 1.6000, 11.5800],
            ['LYC-LAMB', 'Lycée public de Lambaréné', 'lycee', 'G3', 'Lambaréné', 1100, -0.7000, 10.2333],
            ['LYC-MOUILA', 'Lycée public de Mouila', 'lycee', 'G4', 'Mouila', 1000, -1.8667, 11.0556],
            ["EPP-GLASS", "École pré-primaire publique de Glass", "ecole_pre_primaire", "G1", "Libreville", 180, 0.3810, 9.4420],
            // Structures centrales (education-nationale.gouv.ga, « Nos directions et services »).
            ["SG-MEN", "Secrétariat Général", "direction_centrale", "G1", "Libreville", null, 0.3900, 9.4500, "+241 01 72 28 30", "Immeuble des 9 étages"],
            ["DGRH", "Direction Générale des Ressources Humaines (DGRH)", "direction_centrale", "G1", "Libreville", null, 0.3900, 9.4500, "+241 04 12 91 91", "Immeuble des 9 étages"],
            ["DGESN", "Direction Générale de l'Enseignement scolaire et Normal", "direction_centrale", "G1", "Libreville", null, 0.3901, 9.4501, "+241 07 23 13 61", "Immeuble des 9 étages"],
            ["IGS", "Inspection Générale des Services", "direction_centrale", "G1", "Libreville", null, 0.3902, 9.4502, "+241 06 24 64 62", "Immeuble des 9 étages"],
            ["DCSI", "Direction Centrale des Systèmes d'Information", "direction_centrale", "G1", "Libreville", null, 0.3903, 9.4503, "+241 74 84 62 02", "Immeuble des 9 étages"],
            // Directions d'académie provinciales.
            ["AP-EST", "Direction d'Académie Provinciale de l'Estuaire", "academie", "G1", "Libreville", null, 0.3950, 9.4550, "+241 01 74 01 10", "Boulevard Jean-Paul II"],
            ["AP-HO", "Direction d'Académie Provinciale du Haut-Ogooué", "academie", "G2", "Franceville", null, -1.6350, 13.5850, "+241 01 67 73 25"],
            ["AP-MO", "Direction d'Académie Provinciale du Moyen-Ogooué", "academie", "G3", "Lambaréné", null, -0.7000, 10.2400, "+241 01 58 10 30"],
            ["AP-NGO", "Direction d'Académie Provinciale de la Ngounié", "academie", "G4", "Mouila", null, -1.8650, 11.0560, "+241 01 86 11 57"],
            ["AP-NYA", "Direction d'Académie Provinciale de la Nyanga", "academie", "G5", "Tchibanga", null, -2.9330, 11.0170, "+241 01 82 02 26"],
            ["AP-OI", "Direction d'Académie Provinciale de l'Ogooué-Ivindo", "academie", "G6", "Makokou", null, 0.5700, 12.8600, "+241 01 90 33 11"],
            ["AP-OL", "Direction d'Académie Provinciale de l'Ogooué-Lolo", "academie", "G7", "Koulamoutou", null, -1.1350, 12.4700, "+241 01 65 50 77"],
            ["AP-OM", "Direction d'Académie Provinciale de l'Ogooué-Maritime", "academie", "G8", "Port-Gentil", null, -0.7200, 8.7800, "+241 01 55 20 49"],
            ["AP-WN", "Direction d'Académie Provinciale du Woleu-Ntem", "academie", "G9", "Oyem", null, 1.6000, 11.5800, "+241 01 98 61 02"],
        ];
        $etabs = [];
        foreach ($liste as $ligne) {
            [$code, $nom, $type, $prov, $ville, $eleves, $lat, $lng, $tel, $adresse] = $ligne + [8 => null, 9 => null];
            $etabs[$code] = Etablissement::updateOrCreate(['code' => $code], [
                'nom' => $nom, 'type' => $type, 'province_id' => $this->ref['province'][$prov], 'ville' => $ville,
                'effectif_eleves' => $eleves, 'latitude' => $lat, 'longitude' => $lng, 'actif' => true,
                'telephone' => $tel ?? '+241 0'.mt_rand(1, 7).' '.mt_rand(10, 99).' '.mt_rand(10, 99).' '.mt_rand(10, 99),
                'email' => strtolower(str_replace(['-', ' '], '', $code)).'@education.gouv.ga',
                'heure_debut' => in_array($type, ['direction_centrale', 'academie'], true) ? '07:30:00' : '07:30:00',
                'heure_fin' => in_array($type, ['direction_centrale', 'academie'], true) ? '15:30:00' : '17:00:00',
                'adresse' => $adresse ? $adresse.', '.$ville : 'BP '.mt_rand(100, 9999).', '.$ville,
            ]);
        }

        return $etabs;
    }

    protected function creerAgent(Etablissement $etab, array $attrs = []): Agent
    {
        $sexe = $attrs['sexe'] ?? (mt_rand(0, 1) ? 'M' : 'F');
        $categorie = $attrs['categorie'] ?? match ($etab->type) {
            'ecole_primaire', 'ecole_pre_primaire' => 'enseignant_primaire',
            'direction_centrale', 'academie' => 'administratif',
            default => 'enseignant_secondaire',
        };
        $disciplines = ['FR', 'MATH', 'ANG', 'ESP', 'HG', 'SVT', 'PC', 'PHILO', 'EPS', 'ECO', 'INFO'];
        $disc = $attrs['discipline'] ?? match ($categorie) {
            'enseignant_primaire' => 'POLY',
            'enseignant_secondaire' => $disciplines[array_rand($disciplines)],
            default => null,
        };
        $corps = $attrs['corps'] ?? match ($categorie) {
            'enseignant_primaire' => mt_rand(0, 3) ? 'INST' : 'IA',
            'enseignant_secondaire' => ['PCEG', 'PLC', 'PLC', 'PCEG'][mt_rand(0, 3)],
            default => ['ADM', 'ATT', 'SEC'][mt_rand(0, 2)],
        };
        $grade = $attrs['grade'] ?? match ($corps) {
            'PLC', 'ADM' => 'A1', 'PCEG', 'ATT', 'PCET' => 'A2', 'INST' => 'B1', 'IA', 'SEC' => 'B2', default => 'C',
        };
        $fonction = $attrs['fonction'] ?? ($categorie === 'administratif' ? (mt_rand(0, 4) ? 'AGENT_ADM' : 'CHEF_SERV') : ($categorie === 'enseignant_primaire' ? 'ENS_TIT' : 'ENS'));
        $prenom = $attrs['prenom'] ?? ($sexe === 'M' ? $this->prenomsH[array_rand($this->prenomsH)] : $this->prenomsF[array_rand($this->prenomsF)]);
        $nom = $attrs['nom'] ?? $this->noms[array_rand($this->noms)];
        $naissance = $attrs['date_naissance'] ?? now()->subYears(mt_rand(26, 58))->subDays(mt_rand(0, 360))->toDateString();
        $recrutement = $attrs['date_recrutement'] ?? Carbon::parse($naissance)->addYears(mt_rand(23, 27))->min(now()->subYear())->toDateString();
        $this->compteurMatricule += mt_rand(3, 47);

        return Agent::create([
            'matricule' => $this->compteurMatricule.chr(65 + mt_rand(0, 25)),
            'nom' => $nom, 'prenom' => $prenom, 'sexe' => $sexe,
            'date_naissance' => $naissance,
            'lieu_naissance' => ['Libreville', 'Oyem', 'Franceville', 'Port-Gentil', 'Lambaréné', 'Mouila', 'Tchibanga', 'Makokou', 'Koulamoutou', 'Bitam'][mt_rand(0, 9)],
            'nationalite' => 'Gabonaise',
            'situation_matrimoniale' => ['celibataire', 'marie', 'marie', 'union_libre', 'divorce'][mt_rand(0, 4)],
            'nombre_enfants' => mt_rand(0, 5),
            'numero_piece' => 'CNI-'.mt_rand(1000000, 9999999),
            'telephone' => '+241 0'.[6, 7][mt_rand(0, 1)].' '.mt_rand(10, 99).' '.mt_rand(10, 99).' '.mt_rand(10, 99),
            'email' => null,
            'adresse' => $etab->ville,
            'categorie' => $categorie,
            'corps_id' => $this->ref['corps'][$corps] ?? null,
            'grade_id' => $this->ref['grade'][$grade] ?? null,
            'echelon' => mt_rand(1, 10),
            'discipline_id' => $disc ? $this->ref['discipline'][$disc] : null,
            'fonction_id' => $this->ref['fonction'][$fonction],
            'etablissement_id' => $etab->id,
            'diplome' => match ($categorie) {
                'enseignant_primaire' => 'CAP d\'instituteur (ENI)',
                'enseignant_secondaire' => ['CAPES (ENS Libreville)', 'CAPC (ENS Libreville)', 'Master + CAPES'][mt_rand(0, 2)],
                default => ['Licence en droit', 'Master en gestion des ressources humaines', 'BTS Secrétariat', 'Diplôme ENA'][mt_rand(0, 3)],
            },
            'date_recrutement' => $recrutement,
            'statut' => 'actif',
        ]);
    }

    protected function creerCompte(string $email, Agent $agent, string $profil, Etablissement $etab): User
    {
        $agent->update(['email' => $email]);

        return User::updateOrCreate(['email' => $email], [
            'name' => $agent->prenom.' '.$agent->nom,
            'matricule' => $agent->matricule,
            'telephone' => $agent->telephone,
            'password' => Hash::make('password'),
            'profil_id' => $this->ref['profil'][$profil],
            'agent_id' => $agent->id,
            'etablissement_id' => $etab->id,
            'province_id' => $etab->province_id,
            'etat' => 'actif',
        ]);
    }

    /** Affectation actuelle en service (avec éventuellement une affectation antérieure terminée). */
    protected function carriere(Agent $agent, AnneeScolaire $annee, User $admin): void
    {
        $etab = $agent->etablissement;
        $recrutement = Carbon::parse($agent->date_recrutement);
        $dateEffet = $recrutement->copy();
        if ($recrutement->diffInYears(now()) > 6 && mt_rand(0, 1)) {
            $ancien = Etablissement::where('id', '!=', $etab->id)->where('type', $etab->type)->inRandomOrder()->first();
            if ($ancien) {
                $a = $this->affecter($agent, $ancien, 'premiere_affectation', $recrutement, 'terminee', null, $admin);
                $this->priseService($a, $recrutement->copy()->addDays(mt_rand(2, 10)), $admin);
                $dateEffet = $recrutement->copy()->addYears(mt_rand(3, 6));
            }
        }
        $nature = $dateEffet->equalTo($recrutement) ? 'premiere_affectation' : ['mutation', 'mutation', 'nomination', 'redeploiement'][mt_rand(0, 3)];
        $a = $this->affecter($agent, $etab, $nature, $dateEffet, 'en_service', $dateEffet->greaterThan(Carbon::parse($annee->date_debut)) ? $annee : null, $admin);
        $this->priseService($a, $dateEffet->copy()->addDays(mt_rand(1, 8))->min(now()), $admin);
        $agent->update(['etablissement_id' => $etab->id]);
    }

    protected function affecter(Agent $agent, Etablissement $etab, string $nature, Carbon $dateEffet, string $statut, ?AnneeScolaire $annee, User $admin): Affectation
    {
        if (in_array($statut, ['en_attente_prise_service', 'en_service'], true)) {
            Affectation::where('agent_id', $agent->id)->whereIn('statut', ['en_attente_prise_service', 'en_service'])->update(['statut' => 'terminee']);
        }
        $a = Affectation::create([
            'agent_id' => $agent->id, 'etablissement_id' => $etab->id, 'etablissement_origine_id' => $agent->etablissement_id !== $etab->id ? $agent->etablissement_id : null,
            'fonction_id' => $agent->fonction_id, 'discipline_id' => $agent->discipline_id, 'annee_scolaire_id' => $annee?->id,
            'nature' => $nature, 'numero_decision' => sprintf('%04d/MEN/SG/DRH', mt_rand(1, 3999)),
            'date_decision' => $dateEffet->copy()->subDays(mt_rand(10, 40))->toDateString(), 'date_effet' => $dateEffet->toDateString(),
            'statut' => $statut, 'cree_par' => $admin->id,
        ]);
        if ($statut === 'en_attente_prise_service') {
            $agent->update(['etablissement_id' => $etab->id]);
        }

        return $a;
    }

    protected function priseService(Affectation $a, Carbon $date, User $admin): void
    {
        PriseService::create([
            'affectation_id' => $a->id, 'agent_id' => $a->agent_id, 'etablissement_id' => $a->etablissement_id,
            'numero' => 'PS-'.$date->year.'-'.strtoupper(substr(md5($a->id.'ps'), 0, 6)),
            'date_prise_service' => $date->toDateString(), 'statut' => 'confirmee', 'traitee_par' => $admin->id, 'date_traitement' => $date,
        ]);
    }

    protected function classes(Agent $agent): string
    {
        if ($agent->categorie === 'enseignant_primaire') {
            return ['CP1', 'CP2', 'CE1', 'CE2', 'CM1', 'CM2'][mt_rand(0, 5)];
        }
        $niveaux = ['6e', '5e', '4e', '3e', '2nde', '1ère', 'Tle'];
        $choix = array_rand($niveaux, 3);

        return implode(', ', array_map(fn ($i) => $niveaux[$i].' '.chr(65 + mt_rand(0, 3)), $choix));
    }

    protected function presences(Etablissement $lnlm, array $agentsComptes): void
    {
        $agents = Agent::where('statut', 'actif')->whereNotNull('etablissement_id')
            ->whereHas('affectations', fn ($a) => $a->where('statut', 'en_service'))->get(['id', 'etablissement_id']);
        $jours = [];
        for ($d = now()->subDay(); count($jours) < 15; $d->subDay()) {
            if (! $d->isWeekend()) {
                $jours[] = $d->toDateString();
            }
        }
        $lignes = [];
        $maintenant = now();
        foreach ($agents as $agent) {
            foreach ($jours as $k => $jour) {
                $t = mt_rand(1, 100);
                $statut = $t <= 84 ? 'present' : ($t <= 92 ? 'retard' : ($t <= 97 ? 'absent' : 'absence_justifiee'));
                $absent = in_array($statut, ['absent', 'absence_justifiee'], true);
                // Les deux derniers jours au Lycée Léon Mba : pointages des agents encore à valider par le censeur.
                $aValider = $agent->etablissement_id === $lnlm->id && $k < 2 && ! $absent;
                $lignes[] = [
                    'agent_id' => $agent->id, 'etablissement_id' => $agent->etablissement_id, 'date' => $jour,
                    'heure_arrivee' => $absent ? null : ($statut === 'retard' ? sprintf('08:%02d:00', mt_rand(0, 45)) : sprintf('07:%02d:00', mt_rand(5, 40))),
                    'heure_depart' => $absent ? null : sprintf('%02d:%02d:00', mt_rand(15, 17), mt_rand(0, 59)),
                    'statut' => $statut, 'mode' => $absent ? 'saisie' : 'pointage',
                    'validation' => $aValider ? 'en_attente' : 'validee',
                    'observations' => $statut === 'absence_justifiee' ? 'Certificat médical' : null,
                    'created_at' => $maintenant, 'updated_at' => $maintenant,
                ];
            }
        }
        foreach (array_chunk($lignes, 500) as $paquet) {
            DB::table('presences')->insert($paquet);
        }

        // Aujourd'hui : quelques arrivées déjà pointées au Lycée Léon Mba (à valider), hors comptes de démonstration.
        $exclus = collect($agentsComptes)->pluck('id')->all();
        $collegues = Agent::where('etablissement_id', $lnlm->id)->where('statut', 'actif')->whereNotIn('id', $exclus)
            ->whereHas('affectations', fn ($a) => $a->where('statut', 'en_service'))->limit(5)->get();
        foreach ($collegues as $i => $a) {
            DB::table('presences')->insert([
                'agent_id' => $a->id, 'etablissement_id' => $lnlm->id, 'date' => now()->toDateString(),
                'heure_arrivee' => sprintf('07:%02d:00', 10 + $i * 7), 'statut' => 'present', 'mode' => 'pointage',
                'validation' => 'en_attente', 'created_at' => $maintenant, 'updated_at' => $maintenant,
            ]);
        }
    }
}
