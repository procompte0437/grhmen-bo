<?php

namespace Database\Seeders;

use App\Models\Option;
use App\Models\Profil;
use App\Support\CatalogueHabilitations;
use Illuminate\Database\Seeder;

/** Crée les options d'accès / de gestion et les profils par défaut avec leurs habilitations. */
class HabilitationSeeder extends Seeder
{
    public function run(): void
    {
        $ordre = 0;
        foreach (CatalogueHabilitations::options() as $acces) {
            $ordre += 10;
            $parent = Option::updateOrCreate(['code' => $acces['code']], [
                'libelle' => $acces['libelle'],
                'type' => Option::ACCES,
                'groupe' => $acces['groupe'],
                'route' => $acces['route'],
                'icone' => $acces['icone'],
                'ordre' => $ordre,
                'parent_id' => null,
            ]);
            $sous = 0;
            foreach ($acces['gestion'] as $code => $libelle) {
                Option::updateOrCreate(['code' => $code], [
                    'libelle' => $libelle,
                    'type' => Option::GESTION,
                    'parent_id' => $parent->id,
                    'groupe' => $acces['groupe'],
                    'ordre' => ++$sous,
                ]);
            }
        }

        $toutes = Option::pluck('id', 'code');
        foreach (CatalogueHabilitations::profils() as $code => $def) {
            $profil = Profil::updateOrCreate(['code' => $code], [
                'libelle' => $def['libelle'],
                'description' => $def['description'],
                'portee' => $def['portee'],
                'systeme' => $def['systeme'],
            ]);
            $ids = $def['options'] === ['*'] ? $toutes->values() : $toutes->only($def['options'])->values();
            $profil->options()->sync($ids);
        }
    }
}
