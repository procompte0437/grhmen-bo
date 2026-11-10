<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etablissements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('nom');
            // ecole_primaire, college, lycee, lycee_technique, direction_centrale, academie
            $table->string('type', 40);
            $table->foreignId('province_id')->constrained('provinces');
            $table->string('ville')->nullable();
            $table->string('adresse')->nullable();
            $table->string('telephone', 40)->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('effectif_eleves')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->time('heure_debut')->default('07:30:00');
            $table->time('heure_fin')->default('17:00:00');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 30)->unique();
            $table->string('nom');
            $table->string('prenom');
            $table->string('sexe', 1); // M / F
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->string('nationalite')->default('Gabonaise');
            $table->string('situation_matrimoniale', 30)->nullable();
            $table->unsignedTinyInteger('nombre_enfants')->default(0);
            $table->string('numero_piece', 40)->nullable();
            $table->string('telephone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('adresse')->nullable();
            // enseignant_primaire, enseignant_secondaire, administratif
            $table->string('categorie', 40);
            $table->foreignId('corps_id')->nullable()->constrained('corps')->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->unsignedTinyInteger('echelon')->nullable();
            $table->foreignId('discipline_id')->nullable()->constrained('disciplines')->nullOnDelete();
            $table->foreignId('fonction_id')->nullable()->constrained('fonctions')->nullOnDelete();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->nullOnDelete();
            $table->string('diplome')->nullable();
            $table->date('date_recrutement')->nullable();
            $table->date('date_depart_retraite')->nullable();
            // actif, suspendu, disponibilite, detache, retraite, decede, demissionnaire
            $table->string('statut', 30)->default('actif');
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['categorie', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
        Schema::dropIfExists('etablissements');
    }
};
