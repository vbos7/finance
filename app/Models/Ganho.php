<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ganho extends Model
{
    protected $fillable = ['descricao', 'fonte', 'data', 'valor'];

    /** @var array<string, string> */
    protected $casts = [
        'data'  => 'date',
        'valor' => 'decimal:2',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
