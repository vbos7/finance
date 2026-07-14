<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Divida extends Model
{
    protected $fillable = ['descricao', 'destino', 'valor', 'vencimento', 'status'];

    /** @var array<string, string> */
    protected $casts = [
        'vencimento' => 'date',
        'valor'      => 'decimal:2',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
