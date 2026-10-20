<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Encuestas de satisfacción de un corte del seguimiento (INSTRUCTIVO_REPORTE_PERSONAS.md §6.1): una
 * por curso. Solo agregados y el texto de las respuestas abiertas; nunca nombre ni número de
 * empleado. Solo aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_report_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progress_report_id')->constrained('progress_reports')->cascadeOnDelete();
            $table->foreignId('catalog_course_id')->nullable()->constrained('course_catalog')->nullOnDelete();
            $table->string('course_code', 40)->nullable();
            $table->string('title', 255);
            $table->unsignedInteger('responses');
            $table->unsignedInteger('active');
            $table->unsignedInteger('not_in_cut')->default(0); // respondieron sin tener el curso en el corte
            $table->json('data');
            $table->json('warnings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['progress_report_id', 'course_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_report_surveys');
    }
};
