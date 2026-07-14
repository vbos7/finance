<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DespesaFixa extends Model
{
    protected $table = 'despesas_fixas';

    protected $fillable = ['descricao', 'categoria', 'valor', 'vencimento', 'status', 'data_pgto', 'forma'];

    /** @var array<string, string> */
    protected $casts = [
        'vencimento' => 'date',
        'data_pgto'  => 'date',
        'valor'      => 'decimal:2',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
