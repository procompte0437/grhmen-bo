<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use App\Models\Corps;
use App\Models\Discipline;
use App\Models\Fonction;
use App\Models\Grade;
use App\Models\Province;
use Illuminate\Database\Seeder;

class ReferentielSeeder extends Seeder
{
    public function run(): void
    {
        $provinces = [
            ['G1', 'Estuaire', 'Libreville'], ['G2', 'Haut-Ogooué', 'Franceville'], ['G3', 'Moyen-Ogooué', 'Lambaréné'],
            ['G4', 'Ngounié', 'Mouila'], ['G5', 'Nyanga', 'Tchibanga'], ['G6', 'Ogooué-Ivindo', 'Makokou'],
            ['G7', 'Ogooué-Lolo', 'Koulamoutou'], ['G8', 'Ogooué-Maritime', 'Port-Gentil'], ['G9', 'Woleu-Ntem', 'Oyem'],
        ];
        foreach ($provinces as [$code, $nom, $chefLieu]) {
            Province::updateOrCreate(['code' => $code], ['nom' => $nom, 'chef_lieu' => $chefLieu]);
        }

        $corps = [
            ['INST', 'Instituteurs', 'enseignant_primaire'],
            ['IA', 'Instituteurs adjoints', 'enseignant_primaire'],
            ['PCEG', 'Professeurs certifiés d\'enseignement général', 'enseignant_secondaire'],
            ['PLC', 'Professeurs de lycée certifiés', 'enseignant_secondaire'],
            ['PCET', 'Professeurs certifiés d\'enseignement technique', 'enseignant_secondaire'],
            ['ADM', 'Administrateurs civils', 'administratif'],
            ['ATT', 'Attachés d\'administration', 'administratif'],
            ['SEC', 'Secrétaires d\'administration', 'administratif'],
        ];
        foreach ($corps as [$code, $libelle, $categorie]) {
            Corps::updateOrCreate(['code' => $code], ['libelle' => $libelle, 'categorie' => $categorie]);
        }

        $grades = [
            ['A1', 'Catégorie A, hiérarchie 1', 'A1', 1050], ['A2', 'Catégorie A, hiérarchie 2', 'A2', 850],
            ['B1', 'Catégorie B, hiérarchie 1', 'B1', 650], ['B2', 'Catégorie B, hiérarchie 2', 'B2', 520],
            ['C', 'Catégorie C', 'C', 400],
        ];
        foreach ($grades as [$code, $libelle, $h, $indice]) {
            Grade::updateOrCreate(['code' => $code], ['libelle' => $libelle, 'hierarchie' => $h, 'indice_base' => $indice]);
        }

        $disciplines = [
            ['FR', 'Français'], ['MATH', 'Mathématiques'], ['ANG', 'Anglais'], ['ESP', 'Espagnol'],
            ['HG', 'Histoire-Géographie'], ['SVT', 'Sciences de la vie et de la Terre'], ['PC', 'Physique-Chimie'],
            ['PHILO', 'Philosophie'], ['EPS', 'Éducation physique et sportive'], ['ECO', 'Économie'],
            ['INFO', 'Informatique'], ['POLY', 'Enseignement général du primaire'],
        ];
        foreach ($disciplines as [$code, $libelle]) {
            Discipline::updateOrCreate(['code' => $code], ['libelle' => $libelle]);
        }

        $fonctions = [
            ['ENS', 'Enseignant', true], ['ENS_TIT', 'Instituteur titulaire de classe', true],
            ['PROV', 'Proviseur', false], ['PRINC', 'Principal de collège', false], ['DIR_ECOLE', 'Directeur d\'école', false],
            ['CENS', 'Censeur', false], ['SG', 'Surveillant général', false], ['INTEND', 'Intendant', false],
            ['DIR_CENT', 'Directeur central', false], ['CHEF_SERV', 'Chef de service', false],
            ['AGENT_ADM', 'Agent administratif', false], ['DAP', 'Directeur d\'académie provinciale', false],
        ];
        foreach ($fonctions as [$code, $libelle, $ens]) {
            Fonction::updateOrCreate(['code' => $code], ['libelle' => $libelle, 'enseignante' => $ens]);
        }

        // Année scolaire en cours (septembre → juillet) et la précédente.
        $debut = now()->month >= 9 ? now()->year : now()->year - 1;
        AnneeScolaire::updateOrCreate(['libelle' => ($debut - 1).'-'.$debut], ['date_debut' => ($debut - 1).'-09-15', 'date_fin' => $debut.'-07-15', 'active' => false]);
        AnneeScolaire::updateOrCreate(['libelle' => $debut.'-'.($debut + 1)], ['date_debut' => $debut.'-09-15', 'date_fin' => ($debut + 1).'-07-15', 'active' => true]);
    }
}
