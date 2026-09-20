# Credit Ads Module
PHP 8.1+ / MySQL 8+. Demo-ready starter module.

## Setup
1. Create a MySQL database and import `database.sql`.
2. Edit `config/config.php` with DB credentials and `BASE_URL`.
3. Point Apache/Nginx document root at this folder.
4. Visit `register.php` and create a user.
5. Create an admin with `php create_admin.php email@example.com password`.
6. In production, replace the demo payment endpoint with Stripe/PayPal and configure webhooks.

## Flow
- Users register/login.
- Advertise shows balance and a demo 500-credit/$1 purchase endpoint.
- Create ad: max 40 chars, URL, framed/frameless.
- Framed ads run a basic server-side URL/header compatibility check before queueing.
- Admin approves/rejects.
- Earn lists approved ads.
- Frameless opens in a new tab; user returns and confirms completion.
- Framed runs in an iframe for 15 seconds, then requires a simple human check.
- Credits are awarded server-side after the viewing session is validated.

## Production notes
The included human check is intentionally a lightweight demo challenge, not a substitute for reCAPTCHA/Turnstile. Configure Cloudflare Turnstile or another CAPTCHA before production. Payment must also be completed through a real payment provider webhook; never trust a client-side payment success callback.

## Framed advertisement testing
Framed ads now have a live browser preview on `create_advertise.php`. Frameless ads do not use the iframe compatibility test and will open in a separate tab. The server performs an additional first-pass check of HTTP status, X-Frame-Options, and CSP headers before accepting a framed submission. Some cross-origin browser restrictions can only be confirmed by the browser itself, so final admin approval remains the authoritative step.
