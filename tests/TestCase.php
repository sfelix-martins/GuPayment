<?php

namespace Potelo\GuPayment\Tests;

use Potelo\GuPayment\GuPaymentServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            GuPaymentServiceProvider::class,
        ];
    }
}
