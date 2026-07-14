<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DespesaVariavel extends Model
{
    protected $table = 'despesas_variaveis';

    protected $fillable = ['descricao', 'categoria', 'valor', 'data', 'forma', 'balanco'];

    /** @var array<string, string> */
    protected $casts = [
        'data'    => 'date',
        'balanco' => 'date',
        'valor'   => 'decimal:2',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
