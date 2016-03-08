<?php

namespace Potelo\GuPayment;

use Carbon\Carbon;

class SubscriptionBuilder
{
    /**
     * The user model that is subscribing.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $user;

    /**
     * The name of the subscription.
     *
     * @var string
     */
    protected $name;

    /**
     * The name of the plan being subscribed to.
     *
     * @var string
     */
    protected $plan;

    /**
     * The number of trial days to apply to the subscription.
     *
     * @var int|null
     */
    protected $trialDays;

    /**
     * Indicates that the trial should end immediately.
     *
     * @var bool
     */
    protected $skipTrial = false;

    /**
     * The coupon code being applied to the customer.
     *
     * @var string|null
     */
    protected $coupon;

    /**
     * Create a new subscription builder instance.
     *
     * @param  mixed  $user
     * @param  string  $name
     * @param  string  $plan
     */
    public function __construct($user, $name, $plan)
    {
        $this->user = $user;
        $this->name = $name;
        $this->plan = $plan;
    }

    /**
     * Specify the ending date of the trial.
     *
     * @param  int  $trialDays
     * @return $this
     */
    public function trialDays($trialDays)
    {
        $this->trialDays = $trialDays;

        return $this;
    }

    /**
     * Force the trial to end immediately.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->skipTrial = true;

        return $this;
    }

    /**
     * Add a new Stripe subscription to the user.
     *
     * @param  array  $options
     * @return \Potelo\GuPayment\Subscription
     */
    public function add(array $options = [])
    {
        return $this->create(null, $options);
    }

    /**
     * Create a new Stripe subscription.
     *
     * @param  string|null  $token
     * @param  array  $options
     * @return \Potelo\GuPayment\Subscription
     */
    public function create($token = null, array $options = [])
    {
        $customer = $this->getIuguCustomer($token, $options);

        $subscription = $this->user->createIuguSubscription($this->buildPayload($customer->id));

        if ($this->skipTrial) {
            $trialEndsAt = null;
        } else {
            $trialEndsAt = $this->trialDays ? Carbon::now()->addDays($this->trialDays) : null;
        }

        return $this->user->subscriptions()->create([
            'name' => $this->name,
            'iugu_id' => $subscription->id,
            'iugu_plan' => $this->plan,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => null,
        ]);
    }

    /**
     * Get the Iugu customer instance for the current user and token.
     *
     * @param  string|null  $token
     * @param  array  $options
     * @return \Iugu_Customer
     */
    protected function getIuguCustomer($token = null, array $options = [])
    {
        if (! $this->user->iugu_id) {
            $customer = $this->user->createAsIuguCustomer(
                $token, array_merge($options, array_filter(['coupon' => $this->coupon]))
            );
        } else {
            $customer = $this->user->asIuguCustomer();

            if ($token) {
                $this->user->updateCard($token);
            }
        }

        return $customer;
    }

    /**
     * Build the payload for subscription creation.
     *
     * @return array
     */
    protected function buildPayload($customerId)
    {
        return array_filter([
            'plan_identifier' => $this->plan,
            'expires_at' => $this->getTrialEndForPayload(),
            "customer_id" => $customerId,
        ]);
    }

    /**
     * Get the trial ending date for the Iugu payload.
     *
     * @return int|null
     */
    protected function getTrialEndForPayload()
    {
        if ($this->skipTrial) {
            return 'now';
        }

        if ($this->trialDays) {
            return Carbon::now()->addDays($this->trialDays)->getTimestamp();
        }
    }
}
