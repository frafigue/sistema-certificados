<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_histories', function (Blueprint $table) {
            $table->id();
            // Usuario que ejecutó la importación
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Batch Laravel
            $table->string('batch_id')->unique();

            // Nombre archivo importado
            $table->string('file_name')->nullable();

            // Métricas
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('success_rows')->default(0);
            $table->integer('failed_rows')->default(0);

            // Estados:
            // pending
            // processing
            // completed
            // failed
            $table->string('status')->default('pending');
            // timestamps operacionales
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            // errores generales
            $table->longText('general_error')->nullable();
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_histories');
    }
};