# GuPayment


# Laravel Cashier

[![Build Status](https://travis-ci.org/laravel/cashier.svg)](https://travis-ci.org/laravel/cashier)
[![Total Downloads](https://poser.pugx.org/laravel/cashier/d/total.svg)](https://packagist.org/packages/laravel/cashier)
[![Latest Stable Version](https://poser.pugx.org/laravel/cashier/v/stable.svg)](https://packagist.org/packages/laravel/cashier)
[![Latest Unstable Version](https://poser.pugx.org/laravel/cashier/v/unstable.svg)](https://packagist.org/packages/laravel/cashier)
[![License](https://poser.pugx.org/laravel/cashier/license.svg)](https://packagist.org/packages/laravel/cashier)

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
Schema::table('users[SUA_TABELA_DE_USUARIO]', function ($table) {
    $table->string('iugu_id')->nullable();
});

Schema::create('subscriptions', function ($table) {
    $table->increments('id');
    $table->integer('user_id');
    $table->string('name');
    $table->string('stripe_id');
    $table->string('stripe_plan');
    $table->integer('quantity');
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('ends_at')->nullable();
    $table->timestamps();
});
```

Uma vez criado os migrations, basta rodar o comando php artisan migrate