<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('investimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('produto');
            $table->string('empresa');
            $table->decimal('valor', 10, 2);
            $table->integer('quantidade');
            $table->string('tipo_ativo');
            $table->decimal('provento', 10, 2)->default(0);
            $table->string('frequencia');
            $table->date('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investimentos');
    }
};
