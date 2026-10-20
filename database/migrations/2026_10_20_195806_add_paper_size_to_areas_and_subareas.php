<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega columnas de tamaño de papel a areas y subareas.
     * Las columnas son idénticas en ambas tablas para mantener consistencia.
     */
    public function up(): void
    {
        // === AREAS ===
        Schema::table('areas', function (Blueprint $table) {
            $table->enum('template_paper_size', ['A4', 'A5', 'Oficio', 'Carta', 'custom'])
                  ->default('A4')
                  ->after('template_back');

            $table->enum('template_orientation', ['portrait', 'landscape'])
                  ->default('landscape')
                  ->after('template_paper_size');

            $table->decimal('template_width_cm', 8, 2)
                  ->nullable()
                  ->after('template_orientation');

            $table->decimal('template_height_cm', 8, 2)
                  ->nullable()
                  ->after('template_width_cm');
        });

        // === SUBAREAS ===
        Schema::table('subareas', function (Blueprint $table) {
            $table->enum('template_paper_size', ['A4', 'A5', 'Oficio', 'Carta', 'custom'])
                  ->default('A4')
                  ->after('template_back');

            $table->enum('template_orientation', ['portrait', 'landscape'])
                  ->default('landscape')
                  ->after('template_paper_size');

            $table->decimal('template_width_cm', 8, 2)
                  ->nullable()
                  ->after('template_orientation');

            $table->decimal('template_height_cm', 8, 2)
                  ->nullable()
                  ->after('template_width_cm');
        });
    }

    /**
     * Revierte los cambios.
     */
    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropColumn([
                'template_paper_size',
                'template_orientation',
                'template_width_cm',
                'template_height_cm',
            ]);
        });

        Schema::table('subareas', function (Blueprint $table) {
            $table->dropColumn([
                'template_paper_size',
                'template_orientation',
                'template_width_cm',
                'template_height_cm',
            ]);
        });
    }
};