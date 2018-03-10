<?php

namespace Potelo\GuPayment\Tests\Fixtures;

use Potelo\GuPayment\GuPaymentTrait as Billable;
use Illuminate\Database\Eloquent\Model as Eloquent;

class User extends Eloquent
{
    use Billable;
}
