<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('destroy sem escopo remove apenas o registro do grupo', function () {
    $user  = User::factory()->create();
    $grupo = (string) Str::uuid();
    $g1    = $user->ganhos()->create(['descricao' => 'Salário', 'fonte' => 'Salário', 'data' => '2026-01-05', 'valor' => 100, 'grupo_id' => $grupo]);
    $user->ganhos()->create(['descricao' => 'Salário', 'fonte' => 'Salário', 'data' => '2026-02-05', 'valor' => 100, 'grupo_id' => $grupo]);

    $this->actingAs($user)
        ->delete(route('ganhos.destroy', $g1->id))
        ->assertRedirect();

    expect($user->ganhos()->count())->toBe(1);
});

test('destroy com escopo grupo remove todos os ganhos vinculados', function () {
    $user  = User::factory()->create();
    $grupo = (string) Str::uuid();
    $g1    = $user->ganhos()->create(['descricao' => 'Salário', 'fonte' => 'Salário', 'data' => '2026-01-05', 'valor' => 100, 'grupo_id' => $grupo]);
    $user->ganhos()->create(['descricao' => 'Salário', 'fonte' => 'Salário', 'data' => '2026-02-05', 'valor' => 100, 'grupo_id' => $grupo]);
    $avulso = $user->ganhos()->create(['descricao' => 'Bônus', 'fonte' => 'Salário', 'data' => '2026-03-05', 'valor' => 500]);

    $this->actingAs($user)
        ->delete(route('ganhos.destroy', $g1->id), ['escopo' => 'grupo'])
        ->assertRedirect();

    expect($user->ganhos()->pluck('id')->all())->toBe([$avulso->id]);
});

test('escopo grupo nao afeta grupos de outro usuario', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();
    $grupo = (string) Str::uuid();
    $meu   = $user->ganhos()->create(['descricao' => 'Salário', 'fonte' => 'Salário', 'data' => '2026-01-05', 'valor' => 100, 'grupo_id' => $grupo]);
    // Mesmo grupo_id, outro dono: não pode ser afetado
    $alheio = $other->ganhos()->create(['descricao' => 'Salário', 'fonte' => 'Salário', 'data' => '2026-01-05', 'valor' => 100, 'grupo_id' => $grupo]);

    $this->actingAs($user)
        ->delete(route('ganhos.destroy', $meu->id), ['escopo' => 'grupo'])
        ->assertRedirect();

    $this->assertDatabaseMissing('ganhos', ['id' => $meu->id]);
    $this->assertDatabaseHas('ganhos', ['id' => $alheio->id]);
});

test('escopo grupo em registro sem grupo remove apenas ele', function () {
    $user  = User::factory()->create();
    $ganho = $user->ganhos()->create(['descricao' => 'Bônus', 'fonte' => 'Salário', 'data' => '2026-01-05', 'valor' => 500]);
    $outro = $user->ganhos()->create(['descricao' => 'Extra', 'fonte' => 'Salário', 'data' => '2026-02-05', 'valor' => 300]);

    $this->actingAs($user)
        ->delete(route('ganhos.destroy', $ganho->id), ['escopo' => 'grupo'])
        ->assertRedirect();

    expect($user->ganhos()->pluck('id')->all())->toBe([$outro->id]);
});

test('escopo invalido e rejeitado', function () {
    $user  = User::factory()->create();
    $ganho = $user->ganhos()->create(['descricao' => 'Bônus', 'fonte' => 'Salário', 'data' => '2026-01-05', 'valor' => 500]);

    $this->actingAs($user)
        ->delete(route('ganhos.destroy', $ganho->id), ['escopo' => 'tudo'])
        ->assertSessionHasErrors(['escopo']);

    $this->assertDatabaseHas('ganhos', ['id' => $ganho->id]);
});

test('escopo grupo remove despesas fixas recorrentes vinculadas', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('despesas-fixas.store'), [
        'descricao'  => 'Aluguel',
        'categoria'  => 'Casa',
        'valor'      => 2000,
        'vencimento' => '10/01/2026',
        'status'     => 'Pendente',
        'dataLimite' => '04/2026',
    ]);

    $primeira = $user->despesasFixas()->orderBy('vencimento')->first();

    $this->actingAs($user)
        ->delete(route('despesas-fixas.destroy', $primeira->id), ['escopo' => 'grupo'])
        ->assertRedirect();

    expect($user->despesasFixas()->count())->toBe(0);
});

test('escopo grupo remove parcelas vinculadas de despesa variavel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('despesas-variaveis.store'), [
        'descricao' => 'Notebook',
        'categoria' => 'Shopping',
        'valor'     => 3000,
        'data'      => '15/01/2026',
        'forma'     => 'Cartão de Crédito',
        'balanco'   => '01/2026',
        'parcelas'  => 3,
    ]);

    $parcela = $user->despesasVariaveis()->orderBy('balanco')->first();
    expect($parcela->grupo_id)->not->toBeNull();

    $this->actingAs($user)
        ->delete(route('despesas-variaveis.destroy', $parcela->id), ['escopo' => 'grupo'])
        ->assertRedirect();

    expect($user->despesasVariaveis()->count())->toBe(0);
});

test('escopo grupo remove assinatura vinculada de despesa variavel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('despesas-variaveis.store'), [
        'descricao'  => 'Netflix',
        'categoria'  => 'Assinaturas',
        'valor'      => 45,
        'data'       => '12/01/2026',
        'balanco'    => '01/2026',
        'dataLimite' => '04/2026',
    ]);

    $assinatura = $user->despesasVariaveis()->orderBy('balanco')->first();

    $this->actingAs($user)
        ->delete(route('despesas-variaveis.destroy', $assinatura->id), ['escopo' => 'grupo'])
        ->assertRedirect();

    expect($user->despesasVariaveis()->count())->toBe(0);
});

test('escopo grupo remove dividas recorrentes vinculadas', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dividas.store'), [
        'descricao'  => 'Empréstimo',
        'destino'    => 'Banco',
        'valor'      => 500,
        'vencimento' => '10/01/2026',
        'status'     => 'Pendente',
        'dataLimite' => '04/2026',
    ]);

    $primeira = $user->dividas()->orderBy('vencimento')->first();

    $this->actingAs($user)
        ->delete(route('dividas.destroy', $primeira->id), ['escopo' => 'grupo'])
        ->assertRedirect();

    expect($user->dividas()->count())->toBe(0);
});

test('escopo grupo remove investimentos recorrentes vinculados', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('investimentos.store'), [
        'descricao'  => 'Aporte',
        'produto'    => 'ITSA4',
        'empresa'    => 'Itaúsa',
        'valor'      => 100,
        'quantidade' => 1,
        'tipoAtivo'  => 'Ação',
        'frequencia' => 'Mensal',
        'data'       => '10/01/2026',
        'dataLimite' => '04/2026',
    ]);

    $primeiro = $user->investimentos()->orderBy('data')->first();

    $this->actingAs($user)
        ->delete(route('investimentos.destroy', $primeiro->id), ['escopo' => 'grupo'])
        ->assertRedirect();

    expect($user->investimentos()->count())->toBe(0);
});

test('home expoe grupoId e grupoTotal contando todos os anos', function () {
    $user = User::factory()->create();

    // Recorrência que atravessa o ano: 3 registros, sendo 1 em 2027
    $this->actingAs($user)->post(route('ganhos.store'), [
        'descricao'  => 'Salário',
        'fonte'      => 'Salário',
        'data'       => '05/11/2026',
        'valor'      => 5000,
        'dataLimite' => '01/2027',
    ]);

    $this->actingAs($user)
        ->get(route('home', ['ano' => 2026]))
        ->assertInertia(fn ($page) => $page
            ->has('ganhos', 2)
            ->where('ganhos.0.grupoTotal', 3)
            ->where('ganhos.0.grupoId', $user->ganhos()->first()->grupo_id));
});

test('lancamento avulso vem com grupoTotal 1 e grupoId nulo', function () {
    $user = User::factory()->create();
    $user->ganhos()->create(['descricao' => 'Bônus', 'fonte' => 'Salário', 'data' => '2026-01-05', 'valor' => 500]);

    $this->actingAs($user)
        ->get(route('home', ['ano' => 2026]))
        ->assertInertia(fn ($page) => $page
            ->where('ganhos.0.grupoTotal', 1)
            ->where('ganhos.0.grupoId', null));
});

test('update com escopo grupo replica campos alterados e preserva datas', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('ganhos.store'), [
        'descricao'  => 'Salário',
        'fonte'      => 'Salário',
        'data'       => '05/01/2026',
        'valor'      => 5000,
        'dataLimite' => '03/2026',
    ]);

    $primeiro = $user->ganhos()->orderBy('data')->first();

    $this->actingAs($user)
        ->put(route('ganhos.update', $primeiro->id), [
            'descricao' => 'Salário Reajustado',
            'fonte'     => 'Salário',
            'data'      => '07/01/2026',
            'valor'     => 5500,
            'escopo'    => 'grupo',
        ])
        ->assertRedirect();

    $ganhos = $user->ganhos()->orderBy('data')->get();
    expect($ganhos->every(fn ($g) => $g->descricao === 'Salário Reajustado'))->toBeTrue();
    expect($ganhos->every(fn ($g) => (float) $g->valor === 5500.0))->toBeTrue();
    // Datas continuam individuais: só o registro editado mudou de dia
    expect($ganhos[0]->data->format('Y-m-d'))->toBe('2026-01-07');
    expect($ganhos[1]->data->format('Y-m-d'))->toBe('2026-02-05');
    expect($ganhos[2]->data->format('Y-m-d'))->toBe('2026-03-05');
});

test('update sem escopo altera apenas o registro editado', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('ganhos.store'), [
        'descricao'  => 'Salário',
        'fonte'      => 'Salário',
        'data'       => '05/01/2026',
        'valor'      => 5000,
        'dataLimite' => '02/2026',
    ]);

    $primeiro = $user->ganhos()->orderBy('data')->first();

    $this->actingAs($user)
        ->put(route('ganhos.update', $primeiro->id), [
            'descricao' => 'Salário Reajustado',
            'fonte'     => 'Salário',
            'data'      => '05/01/2026',
            'valor'     => 5500,
        ])
        ->assertRedirect();

    $ganhos = $user->ganhos()->orderBy('data')->get();
    expect($ganhos[0]->descricao)->toBe('Salário Reajustado');
    expect($ganhos[1]->descricao)->toBe('Salário');
    expect((float) $ganhos[1]->valor)->toBe(5000.0);
});

test('update em grupo preserva numeracao das parcelas nao alteradas', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('despesas-variaveis.store'), [
        'descricao' => 'Notebook',
        'categoria' => 'Shopping',
        'valor'     => 3000,
        'data'      => '15/01/2026',
        'forma'     => 'Cartão de Crédito',
        'balanco'   => '01/2026',
        'parcelas'  => 3,
    ]);

    $primeira = $user->despesasVariaveis()->orderBy('balanco')->first();

    // Altera apenas a categoria; a descrição "Notebook 1/3" segue igual à atual,
    // então não deve ser replicada por cima de "Notebook 2/3" e "Notebook 3/3"
    $this->actingAs($user)
        ->put(route('despesas-variaveis.update', $primeira->id), [
            'descricao' => 'Notebook 1/3',
            'categoria' => 'Eletrônicos',
            'valor'     => 1000,
            'data'      => '15/01/2026',
            'forma'     => 'Cartão de Crédito',
            'balanco'   => '01/2026',
            'escopo'    => 'grupo',
        ])
        ->assertRedirect();

    $parcelas = $user->despesasVariaveis()->orderBy('balanco')->get();
    expect($parcelas->every(fn ($p) => $p->categoria === 'Eletrônicos'))->toBeTrue();
    expect($parcelas[1]->descricao)->toBe('Notebook 2/3');
    expect($parcelas[2]->descricao)->toBe('Notebook 3/3');
});

test('update em grupo nao afeta registros de outro usuario', function () {
    $user   = User::factory()->create();
    $other  = User::factory()->create();
    $grupo  = (string) Str::uuid();
    $meu    = $user->ganhos()->create(['descricao' => 'Salário', 'fonte' => 'Salário', 'data' => '2026-01-05', 'valor' => 100, 'grupo_id' => $grupo]);
    $alheio = $other->ganhos()->create(['descricao' => 'Salário', 'fonte' => 'Salário', 'data' => '2026-01-05', 'valor' => 100, 'grupo_id' => $grupo]);

    $this->actingAs($user)
        ->put(route('ganhos.update', $meu->id), [
            'descricao' => 'Alterado',
            'fonte'     => 'Salário',
            'data'      => '05/01/2026',
            'valor'     => 999,
            'escopo'    => 'grupo',
        ])
        ->assertRedirect();

    expect($alheio->refresh()->descricao)->toBe('Salário');
    expect((float) $alheio->valor)->toBe(100.0);
});

test('update em grupo de despesa fixa preserva vencimento e pagamento individuais', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('despesas-fixas.store'), [
        'descricao'  => 'Internet',
        'categoria'  => 'Casa',
        'valor'      => 120,
        'vencimento' => '15/01/2026',
        'status'     => 'Pago',
        'dataPgto'   => '14/01/2026',
        'forma'      => 'Pix',
        'dataLimite' => '03/2026',
    ]);

    $primeira = $user->despesasFixas()->orderBy('vencimento')->first();

    $this->actingAs($user)
        ->put(route('despesas-fixas.update', $primeira->id), [
            'descricao'  => 'Internet Fibra',
            'categoria'  => 'Casa',
            'valor'      => 140,
            'vencimento' => '15/01/2026',
            'status'     => 'Pago',
            'dataPgto'   => '14/01/2026',
            'forma'      => 'Boleto',
            'escopo'     => 'grupo',
        ])
        ->assertRedirect();

    $despesas = $user->despesasFixas()->orderBy('vencimento')->get();
    expect($despesas->every(fn ($d) => $d->descricao === 'Internet Fibra'))->toBeTrue();
    expect($despesas->every(fn ($d) => $d->forma === 'Boleto'))->toBeTrue();
    expect($despesas[1]->vencimento->format('Y-m-d'))->toBe('2026-02-15');
    expect($despesas[1]->data_pgto->format('Y-m-d'))->toBe('2026-02-14');
    expect($despesas[2]->vencimento->format('Y-m-d'))->toBe('2026-03-15');
});

test('escopo grupo remove apenas o registro e os vinculados posteriores', function () {
    $user  = User::factory()->create();
    $grupo = (string) Str::uuid();
    $jan   = $user->ganhos()->create(['descricao' => 'Salário', 'fonte' => 'Salário', 'data' => '2026-01-05', 'valor' => 100, 'grupo_id' => $grupo]);
    $fev   = $user->ganhos()->create(['descricao' => 'Salário', 'fonte' => 'Salário', 'data' => '2026-02-05', 'valor' => 100, 'grupo_id' => $grupo]);
    $user->ganhos()->create(['descricao' => 'Salário', 'fonte' => 'Salário', 'data' => '2026-03-05', 'valor' => 100, 'grupo_id' => $grupo]);
    $user->ganhos()->create(['descricao' => 'Salário', 'fonte' => 'Salário', 'data' => '2026-04-05', 'valor' => 100, 'grupo_id' => $grupo]);

    $this->actingAs($user)
        ->delete(route('ganhos.destroy', $fev->id), ['escopo' => 'grupo'])
        ->assertRedirect();

    // Janeiro é anterior ao registro excluído e fica preservado
    expect($user->ganhos()->pluck('id')->all())->toBe([$jan->id]);
});

test('escopo grupo em despesa variavel usa o mes de balanco como referencia', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('despesas-variaveis.store'), [
        'descricao' => 'Notebook',
        'categoria' => 'Shopping',
        'valor'     => 3000,
        'data'      => '15/01/2026',
        'forma'     => 'Cartão de Crédito',
        'balanco'   => '01/2026',
        'parcelas'  => 3,
    ]);

    $segunda = $user->despesasVariaveis()->orderBy('balanco')->skip(1)->first();

    $this->actingAs($user)
        ->delete(route('despesas-variaveis.destroy', $segunda->id), ['escopo' => 'grupo'])
        ->assertRedirect();

    expect($user->despesasVariaveis()->pluck('descricao')->all())->toBe(['Notebook 1/3']);
});

test('home expoe grupoRestantes contando o registro e os posteriores', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('ganhos.store'), [
        'descricao'  => 'Salário',
        'fonte'      => 'Salário',
        'data'       => '05/11/2026',
        'valor'      => 5000,
        'dataLimite' => '01/2027',
    ]);
    $user->ganhos()->create(['descricao' => 'Bônus', 'fonte' => 'Salário', 'data' => '2026-12-20', 'valor' => 500]);

    $this->actingAs($user)
        ->get(route('home', ['ano' => 2026]))
        ->assertInertia(fn ($page) => $page
            ->where('ganhos.0.grupoRestantes', 3)
            ->where('ganhos.1.grupoRestantes', 2)
            ->where('ganhos.2.descricao', 'Bônus')
            ->where('ganhos.2.grupoRestantes', 1));
});
