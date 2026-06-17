# Stripe Testing Testing Flow

###  On Windows, the easiest way to install the Stripe CLI is via package managers like winget or scoop. Once installed, you can authenticate with your Stripe account and start forwarding webhook events to your local Laravel app.

## Installation Options for Windows
1. Using Winget (Recommended)
```bash
winget install Stripe.StripeCLI
```

* Works on Windows 10/11 with Winget enabled.
* Automatically adds stripe to your PATH.

2. Using Scoop
```bash
scoop bucket add stripe https://github.com/stripe/scoop-stripe-cli.git
scoop install stripe

```
* Requires Scoop package manager.
* Good alternative if Winget isn’t available.

3. Using npm
```bash
npm install -g @stripe/cli
```
* Requires Node.js installed.
* Installs globally via npm.

## Authentication
After installation, log in to Stripe:
```bash
stripe login
```
* This opens a browser window to authenticate your account.
* You’ll get a pairing code to confirm.

## Testing Webhooks Locally
1. Start your Laravel server:
```bash
php artisan serve
```

2. Run Stripe listener:
```bash
stripe listen --forward-to http://localhost:8000/webhooks/stripe
```

3. Trigger test events:
```bash
stripe trigger payment_intent.succeeded
```
