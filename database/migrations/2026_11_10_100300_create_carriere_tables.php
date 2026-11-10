<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Affectations, prises de service, débuts des cours, présences, cessations, congés, journal. */
    public function up(): void
    {
        Schema::create('affectations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('etablissement_id')->constrained('etablissements');
            $table->foreignId('etablissement_origine_id')->nullable()->constrained('etablissements')->nullOnDelete();
            $table->foreignId('fonction_id')->nullable()->constrained('fonctions')->nullOnDelete();
            $table->foreignId('discipline_id')->nullable()->constrained('disciplines')->nullOnDelete();
            $table->foreignId('annee_scolaire_id')->nullable()->constrained('annees_scolaires')->nullOnDelete();
            // premiere_affectation, mutation, nomination, permutation, redeploiement, reintegration
            $table->string('nature', 30);
            $table->string('numero_decision', 60)->nullable();
            $table->date('date_decision')->nullable();
            $table->date('date_effet');
            // en_attente_prise_service, en_service, terminee, cessation, annulee
            $table->string('statut', 30)->default('en_attente_prise_service');
            $table->text('observations')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['etablissement_id', 'statut']);
        });

        Schema::create('prises_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affectation_id')->constrained('affectations')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('etablissement_id')->constrained('etablissements');
            $table->string('numero', 40)->unique();
            $table->date('date_prise_service');
            // declaree, confirmee, rejetee
            $table->string('statut', 20)->default('declaree');
            $table->text('observations')->nullable();
            $table->foreignId('declaree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('traitee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('date_traitement')->nullable();
            $table->string('motif_rejet')->nullable();
            $table->timestamps();
        });

        Schema::create('debuts_cours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('etablissement_id')->constrained('etablissements');
            $table->foreignId('affectation_id')->nullable()->constrained('affectations')->nullOnDelete();
            $table->foreignId('discipline_id')->nullable()->constrained('disciplines')->nullOnDelete();
            $table->foreignId('annee_scolaire_id')->nullable()->constrained('annees_scolaires')->nullOnDelete();
            $table->date('date_debut');
            $table->string('classes')->nullable();
            $table->unsignedSmallInteger('volume_horaire')->nullable(); // heures / semaine
            $table->string('statut', 20)->default('declare'); // declare, confirme, rejete
            $table->text('observations')->nullable();
            $table->foreignId('declare_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('date_traitement')->nullable();
            $table->string('motif_rejet')->nullable();
            $table->timestamps();
        });

        Schema::create('presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('etablissement_id')->constrained('etablissements');
            $table->date('date');
            $table->time('heure_arrivee')->nullable();
            $table->time('heure_depart')->nullable();
            // present, retard, absent, absence_justifiee, mission
            $table->string('statut', 30)->default('present');
            $table->string('mode', 20)->default('pointage'); // pointage | saisie
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            // en_attente, validee, rejetee
            $table->string('validation', 20)->default('en_attente');
            $table->foreignId('saisie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('date_validation')->nullable();
            $table->string('observations')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'date']);
            $table->index(['etablissement_id', 'date']);
        });

        Schema::create('cessations_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->nullOnDelete();
            $table->foreignId('affectation_id')->nullable()->constrained('affectations')->nullOnDelete();
            $table->string('numero', 40)->unique();
            // non_prise_service, abandon_poste, mutation, retraite, deces, demission, disponibilite, detachement, revocation
            $table->string('type', 30);
            $table->date('date_cessation');
            $table->text('motif')->nullable();
            $table->string('statut', 20)->default('signalee'); // signalee, validee, annulee
            $table->boolean('generee_automatiquement')->default(false);
            $table->foreignId('signalee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('date_validation')->nullable();
            $table->timestamps();
        });

        Schema::create('conges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->nullOnDelete();
            // annuel, maladie, maternite, paternite, exceptionnel, formation
            $table->string('type', 30);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->unsignedSmallInteger('nombre_jours');
            $table->text('motif')->nullable();
            // soumise, avis_favorable, avis_defavorable, approuvee, rejetee
            $table->string('statut', 20)->default('soumise');
            $table->string('avis_chef')->nullable();
            $table->foreignId('avis_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decision_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('commentaire_decision')->nullable();
            $table->timestamp('date_decision')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_activites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('module', 40);
            $table->string('description');
            $table->string('cible_type', 60)->nullable();
            $table->unsignedBigInteger('cible_id')->nullable();
            $table->string('adresse_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['module', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_activites');
        Schema::dropIfExists('conges');
        Schema::dropIfExists('cessations_service');
        Schema::dropIfExists('presences');
        Schema::dropIfExists('debuts_cours');
        Schema::dropIfExists('prises_service');
        Schema::dropIfExists('affectations');
    }
};
