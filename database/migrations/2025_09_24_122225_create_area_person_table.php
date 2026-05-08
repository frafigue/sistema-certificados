<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('area_person', function (Blueprint $table) {
            $table->id();

            // Clave Foránea para 'areas'. Esto funcionará si tu tabla de áreas se llama 'areas'.
            $table->foreignId('area_id')->constrained()->onDelete('cascade');
            
            // CORRECCIÓN CLAVE: Se especifica 'persons' porque tu tabla se llama así.
            $table->foreignId('person_id')->constrained('persons')->onDelete('cascade');
            
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('area_person');
    }
};