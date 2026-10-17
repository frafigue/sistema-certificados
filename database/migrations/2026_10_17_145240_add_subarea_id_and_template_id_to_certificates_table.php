<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->foreignId('subarea_id')->nullable()->constrained('subareas')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('certificate_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['subarea_id']);
            $table->dropColumn('subarea_id');
            $table->dropForeign(['template_id']);
            $table->dropColumn('template_id');
        });
    }
};