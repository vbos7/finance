<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('fontes_renda', function (Blueprint $table) {
            $table->string('icone')->nullable()->after('nome');
        });
        Schema::table('categorias', function (Blueprint $table) {
            $table->string('icone')->nullable()->after('nome');
        });
        Schema::table('formas_pagamento', function (Blueprint $table) {
            $table->string('icone')->nullable()->after('nome');
        });
        Schema::table('metas', function (Blueprint $table) {
            $table->string('icone')->nullable()->after('nome');
        });
    }

    public function down(): void
    {
        Schema::table('fontes_renda', fn (Blueprint $t) => $t->dropColumn('icone'));
        Schema::table('categorias', fn (Blueprint $t) => $t->dropColumn('icone'));
        Schema::table('formas_pagamento', fn (Blueprint $t) => $t->dropColumn('icone'));
        Schema::table('metas', fn (Blueprint $t) => $t->dropColumn('icone'));
    }
};
