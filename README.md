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

## Assinaturas

Para criar uma assinatura, primeiro você precisa ter uma instância de um usuário que extende o GuPaymentTrait. Você então deve usar o método `newSubscription` para criar uma assinatura:
```
$user = User::find(1);

$user->newSubscription('main', 'gold')->create($creditCardToken);
```
O primeiro argumento deve ser o nome da assinatura. Esse nome não será utilizado no Iugu.com, apenas na sua aplicação. Se sua aplicação tiver apenas um tipo de assinatura, você pode chamá-la de principal ou primária. O segundo argumento é o identificador do plano no Iugu.com.

O método `create` automaticamente criará uma assinatura no Iugu.com e atualizará o seu banco de dados com o ID do cliente referente ao Iugu e outras informações relevantes. Você pode chamar o `create` sem passar nenhum parâmetro ou informar o token do cartão de crédito para que o usuário tenha uma forma de pagamento padrão. Veja como gerar o token em [iugu.js](https://iugu.com/referencias/iugu-js)

### Dados adicionais
Se você desejar adicionar informações extras ao usuário e assinatura, basta passar um terceiro parâmetro no método `newSubscription` para informações adicionais da assinatura e/ou um segundo parâmetro no método `create` para informações adicionais do cliente:
```
$user = User::find(1);

$user->newSubscription('main', 'gold', ['adicional_assinatura' => 'boa assinatura'])
     ->create(NULL, [
	     'name' => $user->nome,
	     'adicional_cliente' => 'bom cliente'
	 ]);
```
Para mais informações dos campos que são suportados pelo Iugu confira a [Documentação oficial](https://iugu.com/referencias/api#assinaturas)