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
        // PASO 1: Agregar campos solo si NO existen
        Schema::table('persons', function (Blueprint $table) {
            // Verificar y agregar area_id solo si NO existe
            if (!Schema::hasColumn('persons', 'area_id')) {
                $table->foreignId('area_id')
                      ->after('email')
                      ->constrained('areas')
                      ->onDelete('cascade');
            }
            
            // Verificar y agregar user_id solo si NO existe
            if (!Schema::hasColumn('persons', 'user_id')) {
                $table->foreignId('user_id')
                      ->after('area_id')
                      ->nullable()
                      ->constrained('users')
                      ->onDelete('set null');
            }
        });
        
        // PASO 2: Agregar índice único al email solo si NO existe
        $indexes = Schema::getIndexes('persons');
        $emailHasUniqueIndex = false;
        
        foreach ($indexes as $index) {
            if (in_array('email', $index['columns']) && $index['unique']) {
                $emailHasUniqueIndex = true;
                break;
            }
        }
        
        if (!$emailHasUniqueIndex) {
            Schema::table('persons', function (Blueprint $table) {
                $table->unique('email');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            // Eliminar índice único de email si existe
            $indexes = Schema::getIndexes('persons');
            foreach ($indexes as $index) {
                if (in_array('email', $index['columns']) && $index['unique']) {
                    $table->dropUnique($index['name']);
                    break;
                }
            }
            
            // Eliminar foreign keys y columnas si existen
            if (Schema::hasColumn('persons', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            
            if (Schema::hasColumn('persons', 'area_id')) {
                $table->dropForeign(['area_id']);
                $table->dropColumn('area_id');
            }
        });
    }
};