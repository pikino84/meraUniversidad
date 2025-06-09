<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLoungesTable extends Migration
{
    public function up()
    {
        Schema::create('lounges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('terminal')->nullable();
            $table->integer('capacity')->nullable();
            $table->boolean('status')->default(true); // Activa/Inactiva
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('lounges');
    }
}
