# GuPayment

## Introdução

GuPayment é baseado no Laravel Cashier e fornece uma interface para controlar assinaturas do iugu.com

## Instalação

Instale esse pacote pelo composer:

```
composer require potelo/gu-payment
```

Adicione o ServiceProvider em config/app.php

```php
Potelo\GuPayment\GuPaymentServiceProvider::class,
```

Antes de usar o GuPayment você precisa preparar o banco de dados. Você precisa adicionar algumas colunas no seu modelo
de Usuario e criar uma tabela para as assinaturas com o nome 'subscriptions'.

```php
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

```php
use Potelo\GuPayment\GuPaymentTrait;

class User extends Authenticatable
{
    use GuPaymentTrait;
}
```

Agora vamos adicionar em config/services.php duas configurações. A classe do usuário e o nome da tabela
utilizada para gerenciar as assinaturas, a mesma escolhida na criação do migration

```php
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

### Criando assinaturas

Para criar uma assinatura, primeiro você precisa ter uma instância de um usuário que extende o GuPaymentTrait. Você então deve usar o método `newSubscription` para criar uma assinatura:
```php
$user = User::find(1);

$user->newSubscription('main', 'gold')->create($creditCardToken);
```
O primeiro argumento deve ser o nome da assinatura. Esse nome não será utilizado no Iugu.com, apenas na sua aplicação. Se sua aplicação tiver apenas um tipo de assinatura, você pode chamá-la de principal ou primária. O segundo argumento é o identificador do plano no Iugu.com.

O método `create` automaticamente criará uma assinatura no Iugu.com e atualizará o seu banco de dados com o ID do cliente referente ao Iugu e outras informações relevantes. Você pode chamar o `create` sem passar nenhum parâmetro ou informar o token do cartão de crédito para que o usuário tenha uma forma de pagamento padrão. Veja como gerar o token em [iugu.js](https://iugu.com/referencias/iugu-js)

#### Dados adicionais
Se você desejar adicionar informações extras ao usuário e assinatura, basta passar um terceiro parâmetro no método `newSubscription` para informações adicionais da assinatura e/ou um segundo parâmetro no método `create` para informações adicionais do cliente:
```php
$user = User::find(1);

$user->newSubscription('main', 'gold', ['adicional_assinatura' => 'boa assinatura'])
     ->create(NULL, [
	     'name' => $user->nome,
	     'adicional_cliente' => 'bom cliente'
	 ]);
```
Para mais informações dos campos que são suportados pelo Iugu confira a [Documentação oficial](https://iugu.com/referencias/api#assinaturas)

### Checando status da assinatura

Uma vez que o usuário assine um plano na sua aplicação, você pode verificar o status dessa assinatura através de alguns métodos. O método `subscribed` retorna **true** se o usuário possui uma assinatura ativa, mesmo se estiver no período trial:
```php
if ($user->subscribed('main')) {
    //
}
```

O método `subscribed`pode ser utilizado em um route middleware, permitindo que você filtre o acesso de rotas baseado no status da assinatura do usuário:

```php
public function handle($request, Closure $next)
{
    if ($request->user() && ! $request->user()->subscribed('main')) {
        // This user is not a paying customer...
        return redirect('billing');
    }

    return $next($request);
}
```

Se você precisa saber se um a assinatura de um usuário está no período trial, você pode usar o método `onTrial`. Esse método pode ser útil para informar ao usuário que ele está no período de testes, por exemplo:
```php
if ($user->subscription('main')->onTrial()) {
    //
}
```
O método `onPlan` pode ser usado para saber se o usuário está assinando um determinado plano. Por exemplo, para verificar se o usuário assina o plano **gold**:
```php
if ($user->onPlan('gold')) {
    //
}
```

Para saber se uma assinatura foi cancelada, basta usar o método `cancelled` na assinatura:
```php
if ($user->subscription('main')->cancelled()) {
    //
}
```
Você também pode checar se uma assinatura foi cancelada mas o usuário ainda se encontra no "período de carência". Por exemplo, se um usuário cancelar a assinatura no dia 5 de Março mas a data de vencimento é apenas no dia 10, ele está nesse período de carência até o dia 10. Para saber basta utilizar o método `onGracePeriod`:

```php
if ($user->subscription('main')->onGracePeriod()) {
    //
}
```

### Mudando o plano da assinatura
Se um usuário já possui uma assinatura, ele pode querer mudar para algum outro plano. Por exemplo, um usuário do plano **gold** pode querer economizar e mudar para o plano **silver**. Para mudar o plano de um usuário em uma assinatura, basta usar o método `swap` da seguinte forma:

```php
$user = App\User::find(1);

$user->subscription('main')->swap('silver');
```
### Cancelando assinaturas
Para cancelar uma assinatura, basta chamar o método `cancel` na assinatura do usuário:
```php
$user->subscription('main')->cancel();
```

### Reativando assinaturas
Se um usuário tem uma assinatura cancelada e gostaria de reativá-la, basta utilizar o método `resume`. Ele precisa está no "período de carência" para conseguir reativá-la:
```php
$user->subscription('main')->resume();
```
