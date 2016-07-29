<?php

namespace Potelo\GuPayment\Http\Controllers;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Potelo\GuPayment\Subscription;
use Stripe\Event as StripeEvent;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    /**
     * Handle a Iugu webhook call.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->all();

        /*if (! $this->isInTestingEnvironment() && ! $this->eventExistsOnStripe($payload['event'])) {
            return;
        }*/

        $method = 'handle'.studly_case(str_replace('.', '_', $payload['event']));

        if (method_exists($this, $method)) {
            return $this->{$method}($payload);
        } else {
            return $this->missingMethod();
        }
    }

    /**
     * Handle a suspended customer from a Iugu subscription.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleSubscriptionSuspended(array $payload)
    {
        $subscription = Subscription::where('iugu_id', $payload['data']['id'])->first();

        $subscription->markAsCancelled();

        return new Response('Webhook Handled', 200);
    }

    /**
     *
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleSubscriptionExpired(array $payload)
    {
        $subscription = Subscription::where('iugu_id', $payload['data']['id'])->first();

        $subscription->fill(['ends_at' => Carbon::createFromFormat('Y-m-d', $payload['data']['expires_at'])])->save();

        return new Response('Webhook Handled', 200);
    }


    /**
     * Handle calls to missing methods on the controller.
     *
     * @param  array   $parameters
     * @return mixed
     */
    public function missingMethod($parameters = [])
    {
        return new Response;
    }
}
