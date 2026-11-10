<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Habilitations : les options (d'accès = éléments du menu latéral, de gestion = actions)
     * sont attribuées aux profils, et chaque utilisateur reçoit un profil.
     */
    public function up(): void
    {
        Schema::create('profils', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('libelle');
            $table->text('description')->nullable();
            // national, provincial, etablissement, personnel
            $table->string('portee', 20)->default('personnel');
            $table->boolean('systeme')->default(false);
            $table->timestamps();
        });

        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('libelle');
            $table->string('type', 10); // acces | gestion
            $table->foreignId('parent_id')->nullable()->constrained('options')->nullOnDelete();
            $table->string('groupe', 60)->nullable(); // section du menu latéral
            $table->string('route', 120)->nullable();
            $table->string('icone', 40)->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('option_profil', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_id')->constrained('options')->cascadeOnDelete();
            $table->foreignId('profil_id')->constrained('profils')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['option_id', 'profil_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('matricule', 30)->nullable()->unique();
            $table->string('telephone', 40)->nullable();
            $table->foreignId('profil_id')->nullable()->constrained('profils')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->nullOnDelete();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->string('etat', 20)->default('actif');
            $table->boolean('doit_changer_mot_de_passe')->default(false);
            $table->timestamp('derniere_connexion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profil_id');
            $table->dropConstrainedForeignId('agent_id');
            $table->dropConstrainedForeignId('etablissement_id');
            $table->dropConstrainedForeignId('province_id');
            $table->dropColumn(['matricule', 'telephone', 'etat', 'doit_changer_mot_de_passe', 'derniere_connexion']);
        });
        Schema::dropIfExists('option_profil');
        Schema::dropIfExists('options');
        Schema::dropIfExists('profils');
    }
};
