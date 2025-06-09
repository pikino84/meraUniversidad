<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLoungeUserTable extends Migration
{
    public function up()
    {
        Schema::create('lounge_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lounge_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('lounge_user');
    }
}
