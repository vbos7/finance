<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FonteRenda extends Model
{
    protected $table = 'fontes_renda';

    protected $fillable = ['nome', 'icone', 'meta_anual'];

    /** @var array<string, string> */
    protected $casts = [
        'meta_anual' => 'decimal:2',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
