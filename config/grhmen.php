<?php

/*
 * Paramètres métier de GRHMEN.
 * Sources : education-nationale.gouv.ga (calendrier scolaire révisé de février 2026, contacts).
 */
return [
    'ministere' => [
        'nom' => 'Ministère de l\'Éducation nationale, de l\'Instruction civique et de la Formation professionnelle',
        'adresse' => 'Immeuble des 9 étages, Libreville',
        'telephone' => '(+241) 01 72 28 30',
    ],

    // Délai (jours) au-delà duquel une affectation sans prise de service peut donner lieu à cessation.
    'delai_non_prise_service' => (int) env('GRHMEN_DELAI_NON_PRISE_SERVICE', 30),

    // Tolérance (minutes) avant qu'une arrivée soit comptée en retard.
    'tolerance_retard' => (int) env('GRHMEN_TOLERANCE_RETARD', 15),

    // Jours fériés fixes (mois-jour), chaque année.
    'feries_fixes' => [
        '01-01' => 'Jour de l\'an',
        '05-01' => 'Fête du Travail',
        '08-15' => 'Assomption',
        '08-17' => 'Fête nationale',
        '11-01' => 'Toussaint',
        '12-25' => 'Noël',
    ],

    // Jours fériés et journées particulières datés (calendrier officiel publié par le ministère).
    'feries_dates' => [
        '2026-03-23' => 'Journée de l\'Enseignant',
        '2026-04-06' => 'Lundi de Pâques',
        '2026-05-14' => 'Ascension',
        '2026-05-25' => 'Lundi de Pentecôte',
    ],
];
