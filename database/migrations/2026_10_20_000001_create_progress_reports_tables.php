<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguimiento anual por persona (docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md §6): un corte por
 * Excel "Reporte de progreso de capacitaciones", sus personas y una fila por persona × curso.
 * Solo aditiva. No se guardan RFC, CURP, nómina ni dirección (§8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_reports', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->date('cut_date');
            $table->json('summary')->nullable();
            $table->json('warnings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('cut_date');
        });

        Schema::create('progress_report_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progress_report_id')->constrained('progress_reports')->cascadeOnDelete();
            $table->string('employee_number', 30);
            $table->string('full_name', 200);
            $table->string('locality', 80);
            $table->string('work_center', 150)->nullable();
            $table->string('area', 80);
            $table->string('position', 150);

            $table->unique(['progress_report_id', 'employee_number']);
            $table->index(['progress_report_id', 'locality']);
        });

        Schema::create('progress_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progress_report_id')->constrained('progress_reports')->cascadeOnDelete();
            $table->foreignId('progress_report_person_id')->constrained('progress_report_people')->cascadeOnDelete();
            $table->foreignId('catalog_course_id')->nullable()->constrained('course_catalog')->nullOnDelete();
            $table->string('platform_code', 40);           // código con el que viene en la plataforma
            $table->string('course_code', 40);             // código del catálogo (o el de la plataforma si no está)
            $table->unsignedSmallInteger('year');
            $table->decimal('progress', 5, 4);
            $table->decimal('grade', 5, 2)->nullable();
            $table->decimal('hours', 6, 2)->default(0);
            $table->unsignedTinyInteger('status');         // ProgressReportItem::STATUS_*
            $table->date('started_at')->nullable();
            $table->date('completed_at')->nullable();

            $table->index(['progress_report_id', 'year', 'course_code']);
            $table->index(['progress_report_id', 'status']);
            $table->index('progress_report_person_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_report_items');
        Schema::dropIfExists('progress_report_people');
        Schema::dropIfExists('progress_reports');
    }
};
