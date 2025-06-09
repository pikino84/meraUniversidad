<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgencyLoungeTable extends Migration
{
    public function up()
    {
        Schema::create('agency_lounge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lounge_id')->constrained()->onDelete('cascade');
            $table->foreignId('agency_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('agency_lounge');
    }
}
