<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de cursos de Capacitación (docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md §6). Se carga del
 * Excel "Catalogo_MERA_v*.xlsx"; la calificación mínima, si tiene evaluación, si cuenta para el
 * seguimiento y los códigos alternos se editan en el panel y la importación no los pisa.
 * Solo aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();          // NRM-HIG-002-EL-26 (sin título)
            $table->string('route', 30);                   // Normativo, Negocio, Competencias, Desarrollo Personal
            $table->string('sub', 10);                     // SEG, HIG, LID…
            $table->unsignedSmallInteger('number');
            $table->string('modality', 5);                 // EL, BL, PR
            $table->unsignedSmallInteger('year');
            $table->string('country', 10)->nullable();     // MX…; null = GLOBAL
            $table->string('title', 255);
            $table->string('short_title', 150);
            $table->string('reference', 150)->nullable();  // NOM / referencia
            $table->string('competency', 150)->nullable();
            $table->string('level', 100)->nullable();      // a quién va dirigido ("Todo MERA", "Cocina"…)
            $table->decimal('hours', 6, 2)->nullable();
            $table->string('instructor', 150)->nullable();
            $table->json('aliases')->nullable();           // otros códigos con los que aparece en la plataforma
            $table->unsignedTinyInteger('passing_grade')->default(80);
            $table->boolean('has_evaluation')->default(true);
            $table->boolean('in_tracking')->default(true); // cuenta para el seguimiento anual
            $table->timestamps();

            $table->index(['year', 'route']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_catalog');
    }
};
