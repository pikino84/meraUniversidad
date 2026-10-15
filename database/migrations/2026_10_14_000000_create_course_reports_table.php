<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reportes de curso por carpeta (docs/reportes/INSTRUCTIVO_REPORTES.md §5.1). Solo aditiva.
 *
 * Cada fila es un CORTE (fecha) del reporte de una carpeta. Se guardan solo agregados por
 * localidad/área/puesto y banderas 0/1 por respuesta: ningún Excel ni dato personal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_group_id')->constrained('course_groups')->cascadeOnDelete();
            $table->string('title', 150);
            $table->date('cut_date');
            $table->string('survey', 50);                 // clave de config/report_surveys.php
            $table->json('data');                         // cubo, inicio_mes, encuesta, catalogo (§7)
            $table->json('summary')->nullable();          // totales para la lista de cortes
            $table->json('warnings')->nullable();
            $table->json('pending')->nullable();          // comentarios por clasificar (texto, sin No. de empleado)
            $table->string('share_token', 64)->nullable()->unique(); // link para compartir sin sesión
            $table->timestamp('shared_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['course_group_id', 'cut_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_reports');
    }
};
