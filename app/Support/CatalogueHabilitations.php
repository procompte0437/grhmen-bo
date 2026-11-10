<?php

namespace App\Support;

/**
 * Catalogue de référence des habilitations.
 *
 * - Options d'accès : éléments du menu latéral (chaque écran).
 * - Options de gestion : actions possibles dans un écran (créer, valider...), rattachées à une option d'accès.
 *
 * Les profils reçoivent des options ; les utilisateurs reçoivent un profil.
 */
class CatalogueHabilitations
{
    /** @return array<int, array{code:string, libelle:string, groupe:string, route:string, icone:string, gestion:array<string,string>}> */
    public static function options(): array
    {
        return [
            ['code' => 'TABLEAU_BORD', 'libelle' => 'Tableau de bord', 'groupe' => 'Général', 'route' => '/', 'icone' => 'tableau', 'gestion' => []],

            ['code' => 'MON_DOSSIER', 'libelle' => 'Mon dossier', 'groupe' => 'Mon espace', 'route' => '/mon-dossier', 'icone' => 'dossier', 'gestion' => []],
            ['code' => 'MES_SERVICES', 'libelle' => 'Ma prise de service', 'groupe' => 'Mon espace', 'route' => '/mes-services', 'icone' => 'drapeau', 'gestion' => [
                'PRISE_SERVICE_DECLARER' => 'Déclarer sa prise de service',
                'DEBUT_COURS_DECLARER' => 'Déclarer son début des cours',
            ]],
            ['code' => 'MON_POINTAGE', 'libelle' => 'Mon pointage', 'groupe' => 'Mon espace', 'route' => '/mon-pointage', 'icone' => 'horloge', 'gestion' => [
                'PRESENCE_POINTER' => 'Pointer sa présence au poste (arrivée / départ)',
            ]],
            ['code' => 'MES_CONGES', 'libelle' => 'Mes congés', 'groupe' => 'Mon espace', 'route' => '/mes-conges', 'icone' => 'soleil', 'gestion' => [
                'CONGE_DEMANDER' => 'Demander un congé ou une autorisation d\'absence',
            ]],

            ['code' => 'PERSONNEL', 'libelle' => 'Personnel', 'groupe' => 'Gestion du personnel', 'route' => '/personnel', 'icone' => 'personnes', 'gestion' => [
                'AGENT_CREER' => 'Créer un agent',
                'AGENT_MODIFIER' => 'Modifier un agent',
                'AGENT_SUPPRIMER' => 'Supprimer un agent',
                'AGENT_EXPORTER' => 'Exporter la liste du personnel',
                'AGENT_CREER_COMPTE' => 'Créer le compte utilisateur d\'un agent',
            ]],
            ['code' => 'AFFECTATIONS', 'libelle' => 'Affectations', 'groupe' => 'Gestion du personnel', 'route' => '/affectations', 'icone' => 'fleches', 'gestion' => [
                'AFFECTATION_CREER' => 'Créer une affectation',
                'AFFECTATION_MODIFIER' => 'Modifier une affectation',
                'AFFECTATION_ANNULER' => 'Annuler une affectation',
            ]],
            ['code' => 'PRISES_SERVICE', 'libelle' => 'Prises de service', 'groupe' => 'Gestion du personnel', 'route' => '/prises-service', 'icone' => 'drapeau', 'gestion' => [
                'PRISE_SERVICE_ENREGISTRER' => 'Enregistrer la prise de service d\'un agent',
                'PRISE_SERVICE_CONFIRMER' => 'Confirmer ou rejeter une prise de service',
            ]],
            ['code' => 'DEBUTS_COURS', 'libelle' => 'Débuts des cours', 'groupe' => 'Gestion du personnel', 'route' => '/debuts-cours', 'icone' => 'livre', 'gestion' => [
                'DEBUT_COURS_ENREGISTRER' => 'Enregistrer le début des cours d\'un enseignant',
                'DEBUT_COURS_CONFIRMER' => 'Confirmer ou rejeter un début des cours',
            ]],
            ['code' => 'PRESENCES', 'libelle' => 'Présences au poste', 'groupe' => 'Gestion du personnel', 'route' => '/presences', 'icone' => 'coche', 'gestion' => [
                'PRESENCE_SAISIR' => 'Saisir la feuille de présence (appel)',
                'PRESENCE_VALIDER' => 'Valider les présences',
            ]],
            ['code' => 'CESSATIONS', 'libelle' => 'Cessations de service', 'groupe' => 'Gestion du personnel', 'route' => '/cessations', 'icone' => 'stop', 'gestion' => [
                'CESSATION_SIGNALER' => 'Signaler une cessation de service',
                'CESSATION_GENERER' => 'Générer les cessations pour non-prise de service',
                'CESSATION_VALIDER' => 'Valider une cessation de service',
                'CESSATION_ANNULER' => 'Annuler une cessation de service',
            ]],
            ['code' => 'CONGES', 'libelle' => 'Congés et absences', 'groupe' => 'Gestion du personnel', 'route' => '/conges', 'icone' => 'soleil', 'gestion' => [
                'CONGE_AVIS' => 'Donner l\'avis du chef sur une demande de congé',
                'CONGE_APPROUVER' => 'Approuver ou rejeter une demande de congé',
            ]],

            ['code' => 'ETABLISSEMENTS', 'libelle' => 'Établissements', 'groupe' => 'Pilotage', 'route' => '/etablissements', 'icone' => 'ecole', 'gestion' => [
                'ETAB_CREER' => 'Créer un établissement',
                'ETAB_MODIFIER' => 'Modifier un établissement',
                'ETAB_SUPPRIMER' => 'Supprimer un établissement',
            ]],
            ['code' => 'RAPPORTS', 'libelle' => 'Rapports et statistiques', 'groupe' => 'Pilotage', 'route' => '/rapports', 'icone' => 'graphique', 'gestion' => [
                'RAPPORT_EXPORTER' => 'Exporter les rapports',
            ]],

            ['code' => 'REFERENTIELS', 'libelle' => 'Référentiels', 'groupe' => 'Administration', 'route' => '/referentiels', 'icone' => 'liste', 'gestion' => [
                'REFERENTIEL_GERER' => 'Créer, modifier et supprimer les référentiels',
            ]],
            ['code' => 'UTILISATEURS', 'libelle' => 'Utilisateurs', 'groupe' => 'Administration', 'route' => '/utilisateurs', 'icone' => 'utilisateur', 'gestion' => [
                'UTILISATEUR_CREER' => 'Créer un utilisateur',
                'UTILISATEUR_MODIFIER' => 'Modifier un utilisateur (profil, état)',
                'UTILISATEUR_SUPPRIMER' => 'Supprimer un utilisateur',
                'UTILISATEUR_REINITIALISER' => 'Réinitialiser le mot de passe d\'un utilisateur',
            ]],
            ['code' => 'PROFILS', 'libelle' => 'Profils et habilitations', 'groupe' => 'Administration', 'route' => '/profils', 'icone' => 'bouclier', 'gestion' => [
                'PROFIL_CREER' => 'Créer un profil',
                'PROFIL_MODIFIER' => 'Modifier un profil',
                'PROFIL_SUPPRIMER' => 'Supprimer un profil',
                'PROFIL_HABILITER' => 'Attribuer les options à un profil',
            ]],
            ['code' => 'JOURNAL', 'libelle' => 'Journal d\'audit', 'groupe' => 'Administration', 'route' => '/journal', 'icone' => 'historique', 'gestion' => []],
        ];
    }

    /** Profils par défaut et options attribuées. '*' = toutes les options. */
    public static function profils(): array
    {
        $espacePersonnel = ['TABLEAU_BORD', 'MON_DOSSIER', 'MES_SERVICES', 'PRISE_SERVICE_DECLARER', 'MON_POINTAGE', 'PRESENCE_POINTER', 'MES_CONGES', 'CONGE_DEMANDER'];

        return [
            'ADMIN' => [
                'libelle' => 'Administrateur système',
                'description' => 'Paramètre l\'application, les profils et les habilitations. Accès complet.',
                'portee' => 'national',
                'systeme' => true,
                'options' => ['*'],
            ],
            'DRH' => [
                'libelle' => 'Directeur général des ressources humaines (DGRH)',
                'description' => 'Pilote le personnel au niveau national : affectations, cessations, congés, rapports.',
                'portee' => 'national',
                'systeme' => false,
                'options' => [
                    'TABLEAU_BORD', 'PERSONNEL', 'AGENT_CREER', 'AGENT_MODIFIER', 'AGENT_SUPPRIMER', 'AGENT_EXPORTER', 'AGENT_CREER_COMPTE',
                    'AFFECTATIONS', 'AFFECTATION_CREER', 'AFFECTATION_MODIFIER', 'AFFECTATION_ANNULER',
                    'PRISES_SERVICE', 'PRISE_SERVICE_CONFIRMER', 'DEBUTS_COURS', 'PRESENCES', 'PRESENCE_VALIDER',
                    'CESSATIONS', 'CESSATION_SIGNALER', 'CESSATION_GENERER', 'CESSATION_VALIDER', 'CESSATION_ANNULER',
                    'CONGES', 'CONGE_APPROUVER', 'ETABLISSEMENTS', 'ETAB_CREER', 'ETAB_MODIFIER',
                    'RAPPORTS', 'RAPPORT_EXPORTER', 'REFERENTIELS', 'JOURNAL',
                ],
            ],
            'GESTIONNAIRE_RH' => [
                'libelle' => 'Gestionnaire RH (DGRH)',
                'description' => 'Tient les dossiers du personnel et prépare les affectations.',
                'portee' => 'national',
                'systeme' => false,
                'options' => [
                    'TABLEAU_BORD', 'PERSONNEL', 'AGENT_CREER', 'AGENT_MODIFIER', 'AGENT_EXPORTER', 'AGENT_CREER_COMPTE',
                    'AFFECTATIONS', 'AFFECTATION_CREER', 'AFFECTATION_MODIFIER',
                    'PRISES_SERVICE', 'DEBUTS_COURS', 'PRESENCES', 'CESSATIONS', 'CESSATION_SIGNALER', 'CESSATION_GENERER',
                    'CONGES', 'ETABLISSEMENTS', 'RAPPORTS', 'RAPPORT_EXPORTER',
                ],
            ],
            'DAP' => [
                'libelle' => 'Directeur d\'académie provinciale',
                'description' => 'Suit les établissements de sa province et valide les cessations et les congés.',
                'portee' => 'provincial',
                'systeme' => false,
                'options' => [
                    'TABLEAU_BORD', 'PERSONNEL', 'AGENT_EXPORTER', 'AFFECTATIONS', 'PRISES_SERVICE', 'PRISE_SERVICE_CONFIRMER',
                    'DEBUTS_COURS', 'PRESENCES', 'PRESENCE_VALIDER', 'CESSATIONS', 'CESSATION_SIGNALER', 'CESSATION_GENERER',
                    'CESSATION_VALIDER', 'CONGES', 'CONGE_APPROUVER', 'ETABLISSEMENTS', 'RAPPORTS', 'RAPPORT_EXPORTER',
                ],
            ],
            'CHEF_ETAB' => [
                'libelle' => 'Chef d\'établissement (Proviseur, Principal, Directeur d\'école)',
                'description' => 'Atteste les prises de service, le début des cours et la présence au poste ; signale les cessations.',
                'portee' => 'etablissement',
                'systeme' => false,
                'options' => array_merge($espacePersonnel, [
                    'PERSONNEL', 'AFFECTATIONS', 'PRISES_SERVICE', 'PRISE_SERVICE_ENREGISTRER', 'PRISE_SERVICE_CONFIRMER',
                    'DEBUTS_COURS', 'DEBUT_COURS_ENREGISTRER', 'DEBUT_COURS_CONFIRMER', 'PRESENCES', 'PRESENCE_SAISIR', 'PRESENCE_VALIDER',
                    'CESSATIONS', 'CESSATION_SIGNALER', 'CESSATION_GENERER', 'CONGES', 'CONGE_AVIS', 'RAPPORTS',
                ]),
            ],
            'CENSEUR' => [
                'libelle' => 'Censeur (proviseur pédagogique)',
                'description' => 'Constate la prise de service et le début des cours, tient la feuille de présence.',
                'portee' => 'etablissement',
                'systeme' => false,
                'options' => array_merge($espacePersonnel, [
                    'PERSONNEL', 'PRISES_SERVICE', 'PRISE_SERVICE_ENREGISTRER', 'PRISE_SERVICE_CONFIRMER',
                    'DEBUTS_COURS', 'DEBUT_COURS_ENREGISTRER', 'DEBUT_COURS_CONFIRMER', 'PRESENCES', 'PRESENCE_SAISIR', 'PRESENCE_VALIDER',
                    'CESSATIONS', 'CESSATION_SIGNALER',
                ]),
            ],
            'ENSEIGNANT' => [
                'libelle' => 'Enseignant',
                'description' => 'Déclare sa prise de service et son début des cours, pointe sa présence, demande ses congés.',
                'portee' => 'personnel',
                'systeme' => false,
                'options' => array_merge($espacePersonnel, ['DEBUT_COURS_DECLARER']),
            ],
            'AGENT_ADMIN' => [
                'libelle' => 'Agent administratif du ministère',
                'description' => 'Personnel des services : pointe sa présence et suit son dossier.',
                'portee' => 'personnel',
                'systeme' => false,
                'options' => $espacePersonnel,
            ],
        ];
    }
}
