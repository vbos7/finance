<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('despesas_variaveis', function (Blueprint $table) {
            $table->date('balanco')->nullable()->after('data');
        });

        DB::table('despesas_variaveis')->whereNull('balanco')->update(['balanco' => DB::raw('data')]);
    }

    public function down(): void
    {
        Schema::table('despesas_variaveis', function (Blueprint $table) {
            $table->dropColumn('balanco');
        });
    }
};
