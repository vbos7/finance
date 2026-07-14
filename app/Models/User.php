<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /** @return HasMany<Ganho, $this> */
    public function ganhos(): HasMany
    {
        return $this->hasMany(Ganho::class);
    }

    /** @return HasMany<DespesaFixa, $this> */
    public function despesasFixas(): HasMany
    {
        return $this->hasMany(DespesaFixa::class);
    }

    /** @return HasMany<DespesaVariavel, $this> */
    public function despesasVariaveis(): HasMany
    {
        return $this->hasMany(DespesaVariavel::class);
    }

    /** @return HasMany<Divida, $this> */
    public function dividas(): HasMany
    {
        return $this->hasMany(Divida::class);
    }

    /** @return HasMany<Investimento, $this> */
    public function investimentos(): HasMany
    {
        return $this->hasMany(Investimento::class);
    }

    /** @return HasMany<Meta, $this> */
    public function metas(): HasMany
    {
        return $this->hasMany(Meta::class);
    }

    /** @return HasMany<FonteRenda, $this> */
    public function fontesRenda(): HasMany
    {
        return $this->hasMany(FonteRenda::class);
    }

    /** @return HasMany<Categoria, $this> */
    public function categorias(): HasMany
    {
        return $this->hasMany(Categoria::class);
    }

    /** @return HasMany<FormaPagamento, $this> */
    public function formasPagamento(): HasMany
    {
        return $this->hasMany(FormaPagamento::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'is_admin',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at'       => 'datetime',
        'password'                => 'hashed',
        'two_factor_confirmed_at' => 'datetime',
        'is_admin'                => 'boolean',
    ];
}
