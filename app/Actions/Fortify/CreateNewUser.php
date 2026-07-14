<?php

namespace App\Actions\Fortify;

use App\Concerns\{PasswordValidationRules, ProfileValidationRules};
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name'     => $input['name'],
            'email'    => $input['email'],
            'password' => $input['password'],
        ]);

        $this->seedDefaults($user);

        return $user;
    }

    private function seedDefaults(User $user): void
    {
        $user->metas()->createMany([
            ['nome' => 'Casamento', 'icone' => 'Gem', 'valor' => 50000],
            ['nome' => 'Imóvel', 'icone' => 'House', 'valor' => 200000],
            ['nome' => 'Investimentos', 'icone' => 'TrendingUp', 'valor' => 100000],
        ]);

        $user->fontesRenda()->createMany([
            ['nome' => 'Trabalho', 'icone' => 'Briefcase', 'meta_anual' => 120000],
            ['nome' => 'Investimentos', 'icone' => 'ChartNoAxesCombined', 'meta_anual' => 12000],
            ['nome' => 'Manutenções', 'icone' => 'Wrench', 'meta_anual' => 6000],
        ]);

        $user->categorias()->createMany([
            ['nome' => 'Casa', 'icone' => 'House', 'limite_anual' => 24000],
            ['nome' => 'Profissional', 'icone' => 'Briefcase', 'limite_anual' => 6000],
            ['nome' => 'Educação', 'icone' => 'GraduationCap', 'limite_anual' => 12000],
            ['nome' => 'Assinaturas', 'icone' => 'CreditCard', 'limite_anual' => 3600],
            ['nome' => 'Mercado', 'icone' => 'ShoppingCart', 'limite_anual' => 9600],
            ['nome' => 'Farmácia e Saúde', 'icone' => 'HeartPulse', 'limite_anual' => 4800],
            ['nome' => 'Transporte', 'icone' => 'Car', 'limite_anual' => 6000],
            ['nome' => 'Utilidades', 'icone' => 'Hammer', 'limite_anual' => 2400],
            ['nome' => 'Entretenimento', 'icone' => 'Gamepad2', 'limite_anual' => 3600],
            ['nome' => 'Juros e Taxas', 'icone' => 'TrendingDown', 'limite_anual' => 1200],
            ['nome' => 'Alimentação', 'icone' => 'UtensilsCrossed', 'limite_anual' => 7200],
            ['nome' => 'Shopping', 'icone' => 'ShoppingBag', 'limite_anual' => 4800],
        ]);

        $user->formasPagamento()->createMany([
            ['nome' => 'Dinheiro', 'icone' => 'Banknote', 'limite_anual' => 6000],
            ['nome' => 'Boleto', 'icone' => 'FileText', 'limite_anual' => 12000],
            ['nome' => 'Pix', 'icone' => 'Zap', 'limite_anual' => 24000],
            ['nome' => 'Cartão de Crédito', 'icone' => 'CreditCard', 'limite_anual' => 36000],
            ['nome' => 'Cartão de Débito', 'icone' => 'CreditCard', 'limite_anual' => 18000],
        ]);
    }
}
