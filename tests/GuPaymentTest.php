<?php

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Potelo\GuPayment\Http\Controllers\WebhookController;

class GuPaymentTest extends PHPUnit_Framework_TestCase
{
    public static function setUpBeforeClass()
    {

        if (file_exists(__DIR__.'/../.env')) {
            $dotenv = new Dotenv\Dotenv(__DIR__.'/../');
            $dotenv->load();
        }
    }

    public function setUp()
    {
        Eloquent::unguard();

        $db = new DB;
        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $db->bootEloquent();
        $db->setAsGlobal();

        $this->schema()->create('users', function ($table) {
            $table->increments('id');
            $table->string('email');
            $table->string('name');
            $table->string('iugu_id')->nullable();
            $table->string('card_brand')->nullable();
            $table->string('card_last_four')->nullable();
            $table->timestamps();
        });

        $this->schema()->create('subscriptions', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('name');
            $table->string('iugu_id');
            $table->string('iugu_plan');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function tearDown()
    {
        $this->schema()->drop('users');
        $this->schema()->drop('subscriptions');
    }

    /**
     * Tests.
     */
    public function testSubscriptionsCanBeCreated()
    {
        $user = User::create([
            'email' => 'gabriel@teste.com.br',
            'name' => 'Gabriel Peixoto',
        ]);

        // Create Subscription
        $user->newSubscription('main', 'gold')->create($this->getTestToken());

        $this->assertEquals(1, count($user->subscriptions));
        $this->assertNotNull($user->subscription('main')->iugu_id);

        $this->assertTrue($user->subscribed('main'));
        $this->assertTrue($user->onPlan('gold'));
        $this->assertFalse($user->onPlan('something'));
        $this->assertTrue($user->subscribed('main', 'gold'));
        $this->assertFalse($user->subscribed('main', 'gold-2'));
        $this->assertTrue($user->subscription('main')->active());
        $this->assertFalse($user->subscription('main')->cancelled());
        $this->assertFalse($user->subscription('main')->onGracePeriod());

        // Cancel Subscription
        $subscription = $user->subscription('main');
        $subscription->cancel();

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertTrue($subscription->onGracePeriod());

        // Modify Ends Date To Past
        $oldGracePeriod = $subscription->ends_at;
        $subscription->fill(['ends_at' => Carbon::now()->subDays(5)])->save();

        $this->assertFalse($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());

        $subscription->fill(['ends_at' => $oldGracePeriod])->save();

        // Resume Subscription
        $subscription->resume();

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());

        // Swap Plan
        $subscription->swap('silver');

        $this->assertEquals('silver', $subscription->iugu_plan);

        // Invoice Tests
        $invoices = $user->invoices();
        $invoice = $invoices[1];

        $this->assertEquals('R$ 15,00', $invoice->total());
        $this->assertFalse($invoice->hasDiscount());
        $this->assertInstanceOf(Carbon::class, $invoice->date());
    }

    public function test_creating_subscription_with_trial()
    {
        $user = User::create([
            'email' => 'gabriel@teste.com.br',
            'name' => 'Gabriel Peixoto',
        ]);

        // Create Subscription
        $user->newSubscription('main', 'gold')
            ->trialDays(7)->create($this->getTestToken());

        $subscription = $user->subscription('main');

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onTrial());
        $this->assertEquals(Carbon::today()->addDays(7)->day, $subscription->trial_ends_at->day);

        // Cancel Subscription
        $subscription->cancel();

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onGracePeriod());

        // Resume Subscription
        $subscription->resume();

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->onTrial());
        $this->assertEquals(Carbon::today()->addDays(7)->day, $subscription->trial_ends_at->day);
    }

    public function test_marking_as_cancelled_from_webhook()
    {
        $user = User::create([
            'email' => 'gabriel@teste.com.br',
            'name' => 'Gabriel Peixoto',
        ]);

        // Create Subscription
        $user->newSubscription('main', 'gold')
            ->create($this->getTestToken());

        $subscription = $user->subscription('main');

        $request = Request::create('/', 'POST', [
            'event' => 'subscription.expired',
            'data' => [
                "id"  => $subscription->iugu_id,
                "customer_name" => "Gabriel Peixoto",
                "customer_email" => "gabriel@teste.com.br",
                "expires_at" => Carbon::now()->format('Y-m-d')
            ],
        ]);
        $controller = new WebhookController();
        $response = $controller->handleWebhook($request);
        $this->assertEquals(200, $response->getStatusCode());
        $user = $user->fresh();
        $subscription = $user->subscription('main');
        $this->assertTrue($subscription->cancelled());
    }

    protected function getTestToken()
    {
        Iugu::setApiKey(getenv('IUGU_APIKEY'));

        return Iugu_PaymentToken::create([
            "account_id" =>  getenv('ID_DA_CONTA'),
            "method" => "credit_card",
            "data" => [
                "number" => "4111111111111111",
                "verification_value" => "123",
                "first_name" => "Joao",
                "last_name" => "Silva",
                "month" => "12",
                "year" => "2013"
            ]
        ]);
    }

    /**
     * Schema Helpers.
     */
    protected function schema()
    {
        return $this->connection()->getSchemaBuilder();
    }

    protected function connection()
    {
        return Eloquent::getConnectionResolver()->connection();
    }
}

class User extends Illuminate\Database\Eloquent\Model
{
    use \Potelo\GuPayment\GuPaymentTrait;
}