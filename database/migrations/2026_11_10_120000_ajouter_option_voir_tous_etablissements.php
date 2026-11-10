<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Droit « voir tous les établissements », accordé par défaut à l'administrateur,
 * à la DGRH et au gestionnaire RH. Il se retire ensuite dans les habilitations.
 */
return new class extends Migration
{
    public function up(): void
    {
        $parent = DB::table('options')->where('code', 'ETABLISSEMENTS')->first();
        if (! $parent) {
            return;
        }

        $existe = DB::table('options')->where('code', 'VOIR_TOUS_ETABLISSEMENTS')->first();
        $id = $existe->id ?? DB::table('options')->insertGetId([
            'code' => 'VOIR_TOUS_ETABLISSEMENTS',
            'libelle' => 'Voir tous les établissements du ministère',
            'type' => 'gestion',
            'parent_id' => $parent->id,
            'groupe' => 'Pilotage',
            'ordre' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $profils = DB::table('profils')->whereIn('code', ['ADMIN', 'DRH', 'GESTIONNAIRE_RH'])->pluck('id');
        foreach ($profils as $profilId) {
            $deja = DB::table('option_profil')->where('option_id', $id)->where('profil_id', $profilId)->exists();
            if (! $deja) {
                DB::table('option_profil')->insert([
                    'option_id' => $id,
                    'profil_id' => $profilId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('options')->where('code', 'VOIR_TOUS_ETABLISSEMENTS')->value('id');
        if ($id) {
            DB::table('option_profil')->where('option_id', $id)->delete();
            DB::table('options')->where('id', $id)->delete();
        }
    }
};
