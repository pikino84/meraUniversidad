<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // Nombre de la categoría
            $table->string('name');

            // Categoría padre
            // NULL = categoría principal
            // ID = pertenece a otra categoría
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
