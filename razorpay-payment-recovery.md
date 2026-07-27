# Razorpay payment completion and recovery

The application has three independent, idempotent ways to complete a Razorpay
payment. A captured payment therefore does not depend on the customer keeping a
browser tab open.

1. The signed browser verification endpoint completes ordinary checkout flows.
2. `/marketing` supplies a signed `callback_url` with `redirect: true`, which works
   in Instagram/Facebook WebViews where Razorpay's JavaScript handler is unavailable.
3. The Razorpay webhook completes `payment.captured` and `order.paid` events.
4. `payments:reconcile-razorpay` runs every five minutes and queries Razorpay for
   pending orders missed by both callback paths.

Every path uses the same atomic `PaymentService` fulfilment method. The payment row
is locked before it becomes paid, so simultaneous callback, webhook, and scheduler
requests cannot duplicate enrolments, emails, coupon redemption, or referral work.
Checkout retries reuse the original Razorpay order instead of overwriting its ID, so
a delayed UPI completion always remains traceable.

## Production configuration

Set the live values in the production environment:

```dotenv
APP_URL=https://neoexam.org
RAZORPAY_KEY_ID=...
RAZORPAY_KEY_SECRET=...
RAZORPAY_WEBHOOK_SECRET=...
RAZORPAY_RECONCILIATION_DELAY_MINUTES=2
RAZORPAY_RECONCILIATION_LOOKBACK_DAYS=30
RAZORPAY_RECONCILIATION_BATCH_SIZE=100
```

`RAZORPAY_WEBHOOK_SECRET` is the independent secret chosen while creating the
webhook. It is not the Razorpay API key secret. Refresh Laravel's cached production
configuration after changing environment values.

In Razorpay **Live Mode → Developers → Webhooks**, configure:

- URL: `https://neoexam.org/razorpay/webhook`
- Events: `payment.captured` and `order.paid`
- Secret: exactly the value stored as `RAZORPAY_WEBHOOK_SECRET`

Laravel's scheduler must run once per minute on the server:

```cron
* * * * * cd /absolute/path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

The application schedules reconciliation every five minutes with overlap and
multi-server protection. Pending captured orders from the preceding 30 days,
including payments created before this release, are recovered automatically.

## Verification and monitoring

- `php artisan schedule:list` should show `payments:reconcile-razorpay` every five minutes.
- Razorpay webhook delivery history should show HTTP 200 responses.
- A missing webhook secret returns HTTP 503; a mismatched signature returns HTTP 400.
- Amount, currency, order ID, order status, and captured payment status must all match
  before scheduled recovery grants access.
- Mismatched webhook or API results are refused and written to the Laravel log.

The command supports a bounded diagnostic run when needed:

```bash
php artisan payments:reconcile-razorpay --limit=100
```

This is not required per payment; the scheduler runs the same recovery automatically.
