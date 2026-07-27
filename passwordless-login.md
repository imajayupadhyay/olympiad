# Passwordless Student Login

The student portal uses a single adaptive login field. Email addresses receive a
Brevo OTP; mobile numbers receive an AiSensy WhatsApp Authentication OTP. Email
and password remain available through **Use password instead**. The separate
administrator login is not part of this flow.

## Production configuration

```env
OTP_PEPPER=
AISENSY_API_KEY=
AISENSY_CAMPAIGN_NAME=neo_student_login_otp
AISENSY_ENDPOINT=https://backend.aisensy.com/campaign/t1/api/v2
AISENSY_SOURCE=neo_student_login
SESSION_SECURE_COOKIE=true
```

`OTP_PEPPER` must be a dedicated high-entropy deployment secret. Do not prefix
the AiSensy key with `VITE_`, commit it, paste it into browser code, or place it
in an admin-editable database setting.

Brevo continues to use the existing `BREVO_*` configuration. Both channels are
reported to the login page as available only when their required server-side
credentials exist.

## AiSensy checklist

1. In the AiSensy project, create a **Text / Authentication** template named,
   for example, `student_login_otp`.
2. Configure a five-minute expiry warning, security warning, and Copy Code
   action, then wait for Meta approval.
3. Create a live **API Campaign** from that template and copy its exact campaign
   name to `AISENSY_CAMPAIGN_NAME`.
4. As the project owner, copy/generate the project API key from Manage/Developer
   and store it as `AISENSY_API_KEY` on the server.
5. Test the campaign against an Indian number. AiSensy's Authentication payload
   expects the same code in both `templateParams[0]` and the URL button's text
   parameter; the integration sends both.

Official references:

- https://wiki.aisensy.com/en/articles/11501833-how-to-create-and-automate-the-authentication-whatsapp-template-messages
- https://www.postman.com/aisensy/aisensy/request/pmsqd7w/authentication-template

## Security model

- Six-digit CSPRNG code; five-minute expiry; maximum five attempts.
- Only the latest challenge is usable; successful challenges are consumed
  atomically under a database row lock.
- OTP hashes are bcrypt hashes of a dedicated HMAC/pepper digest, limiting both
  online guessing and database-only offline attacks.
- The browser receives only masked destinations and never receives an OTP,
  candidate account list, or student name before successful verification.
- Delivery jobs implement Laravel `ShouldBeEncrypted`, so the destination,
  name, and code are encrypted inside database/failed queue payloads.
- Provider response bodies, destination values, names, and codes are not logged.
- Requests use generic responses for unknown, inactive, and administrator
  identifiers; those identifiers never trigger a delivery.
- Send limits apply per IP, per identifier, and per day. Resends have a
  60-second cooldown and invalidate the earlier challenge.
- Email OTP verifies `email_verified_at`; WhatsApp OTP verifies
  `phone_verified_at`. Every successful login rotates the session ID.

## Parent numbers and siblings

`phone_e164` is intentionally indexed but not unique because a parent number may
belong to multiple students. The WhatsApp message stays neutral for shared
numbers. Only after the OTP succeeds does the browser receive the linked student
names and show the account chooser.

## Deployment

```bash
php artisan migrate --force
php artisan config:cache
php artisan queue:restart
```

A queue worker is required for OTP delivery. Run the passwordless authentication
tests after deployment configuration:

```bash
php artisan test tests/Feature/Auth/PasswordlessLoginTest.php
```
