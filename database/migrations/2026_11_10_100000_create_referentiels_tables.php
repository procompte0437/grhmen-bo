<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Référentiels : provinces, corps, grades, disciplines, fonctions et années scolaires. */
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('nom');
            $table->string('chef_lieu')->nullable();
            $table->timestamps();
        });

        Schema::create('corps', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('libelle');
            // enseignant_primaire, enseignant_secondaire, administratif
            $table->string('categorie', 40);
            $table->timestamps();
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('libelle');
            $table->string('hierarchie', 5)->nullable(); // A1, A2, B1...
            $table->unsignedSmallInteger('indice_base')->nullable();
            $table->timestamps();
        });

        Schema::create('disciplines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('libelle');
            $table->timestamps();
        });

        Schema::create('fonctions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('libelle');
            $table->boolean('enseignante')->default(false);
            $table->timestamps();
        });

        Schema::create('annees_scolaires', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 20)->unique();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->boolean('active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annees_scolaires');
        Schema::dropIfExists('fonctions');
        Schema::dropIfExists('disciplines');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('corps');
        Schema::dropIfExists('provinces');
    }
};
