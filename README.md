# GuPayment


# Laravel Cashier

## Introdução

GuPayment é baseado no Laravel Cashier e fornece uma interface para controlar assinaturas do iugu.com

## Instalação

Instale esse pacote pelo composer:

```
composer require potelo/gu-payment
```

Adicione o ServiceProvider em config/app.php

```
Potelo\GuPayment\GuPaymentServiceProvider::class,
```

Antes de usar o GuPayment você precisa preparar o banco de dados. Você precisa adicionar algumas colunas no seu modelo
de Usuario e criar uma tabela para as assinaturas com o nome 'subscriptions'.

```
Schema::table('[SUA_TABELA_DE_USUARIO]', function ($table) {
    $table->string('iugu_id')->nullable();
});

Schema::create('[SUA_TABELA_DE_ASSINATURAS]', function ($table) {
    $table->increments('id');
    $table->integer('user_id');
    $table->string('name');
    $table->string('iugu_id');
    $table->string('iugu_plan');
    $table->integer('quantity');
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('ends_at')->nullable();
    $table->timestamps();
});
```

Uma vez criado os migrations, basta rodar o comando php artisan migrate.

Vamos agora adicionar o Trait ao seu modelo do usuário.

```
use Potelo\GuPayment\GuPaymentTrait;

class User extends Authenticatable
{
    use GuPaymentTrait;
}
```

Agora vamos adicionar em config/services.php duas configurações. A classe do usuário e o nome da tabela
utilizada para gerenciar as assinaturas, a mesma escolhida na criação do migration

```
'iugu' => [
    'model'  => App\User::class,
    'key' => env('IUGU_APIKEY'),
    'signature_table' => env('GUPAYMENT_SIGNATURE_TABLE')
]
```

e no seu arquivo .env você coloca as informações do nome da tabela e da sua chave de api que o Iugu fornece.

```
IUGU_APIKEY=SUA_CHAVE
GUPAYMENT_SIGNATURE_TABLE=usuario_assinatura
```