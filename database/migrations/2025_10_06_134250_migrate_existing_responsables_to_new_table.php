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
        // Migrar responsables existentes de courses a course_responsables
        $courses = DB::table('courses')
            ->whereNotNull('capacitador_nombre')
            ->orWhereNotNull('coordinador_nombre')
            ->get();
        
        foreach ($courses as $course) {
            // Migrar Capacitador (Firma 1)
            if ($course->capacitador_nombre) {
                DB::table('course_responsables')->insert([
                    'course_id' => $course->id,
                    'nombre' => $course->capacitador_nombre,
                    'cargo' => 'Capacitador',
                    'signature_path' => $course->signature1_path,
                    'orden' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            // Migrar Coordinador (Firma 2)
            if ($course->coordinador_nombre) {
                DB::table('course_responsables')->insert([
                    'course_id' => $course->id,
                    'nombre' => $course->coordinador_nombre,
                    'cargo' => 'Coordinador',
                    'signature_path' => $course->signature2_path,
                    'orden' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Limpiar la tabla en caso de rollback
        DB::table('course_responsables')->truncate();
    }
};
