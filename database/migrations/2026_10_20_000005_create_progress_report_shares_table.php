<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links para compartir un corte del seguimiento sin sesión (INSTRUCTIVO_REPORTE_PERSONAS.md §7.2.7): uno
 * por localidad (o de toda la empresa), sin nombres. Al desactivarlo, el link da 404. Solo aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_report_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progress_report_id')->constrained('progress_reports')->cascadeOnDelete();
            $table->string('locality', 80)->nullable(); // null = toda la empresa
            $table->string('token', 64)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['progress_report_id', 'locality']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_report_shares');
    }
};
