<?php

use App\Http\Controllers\{CategoriaController, DespesaFixaController, DespesaVariavelController, DividaController, FonteRendaController, FormaPagamentoController, GanhoController, HomeController, InvestimentoController, MetaController};
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/teste', function () {
    return Inertia::render('teste');
})->name('teste');

Route::middleware('auth')->group(function () {
    Route::resource('ganhos', GanhoController::class)->only(['store', 'update', 'destroy']);
    Route::resource('despesas-fixas', DespesaFixaController::class)->only(['store', 'update', 'destroy']);
    Route::resource('despesas-variaveis', DespesaVariavelController::class)->only(['store', 'update', 'destroy']);
    Route::resource('dividas', DividaController::class)->only(['store', 'update', 'destroy']);
    Route::resource('investimentos', InvestimentoController::class)->only(['store', 'update', 'destroy']);
    Route::resource('metas', MetaController::class)->only(['store', 'update', 'destroy']);
    Route::post('metas/{meta}/investir', [MetaController::class, 'investir'])->name('metas.investir');
    Route::resource('fontes-renda', FonteRendaController::class)->only(['store', 'update', 'destroy']);
    Route::resource('categorias', CategoriaController::class)->only(['store', 'update', 'destroy']);
    Route::resource('formas-pagamento', FormaPagamentoController::class)->only(['store', 'update', 'destroy']);
});

Route::get('dashboard', function () {
    return Inertia::render('dashboard');
})->middleware(['auth', 'verified', 'admin'])->name('dashboard');

require __DIR__ . '/settings.php';
