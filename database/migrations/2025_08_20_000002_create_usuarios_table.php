<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsuariosTable extends Migration
{
    public function up()
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id('id_usuario');
            $table->string('nombre');
            $table->string('telefono')->nullable();
            $table->string('correo')->unique();
            $table->string('contrasena');
            $table->unsignedBigInteger('id_rol');
            $table->boolean('activo')->default(true);
            $table->timestamp('fecha_creacion')->useCurrent();

            $table->foreign('id_rol')->references('id_rol')->on('roles')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('usuarios');
    }
}