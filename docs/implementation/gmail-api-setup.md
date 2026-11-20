# Gmail API transactional email setup

SafariTrak sends transactional mail through the Gmail API using OAuth 2.0 user authorization. It does not use a Google service account. The same Google Cloud project can host the existing Maps APIs and Gmail API, but the browser Maps key is separate from the server-side OAuth client.

## Google Cloud setup

1. Select the Google Cloud project already used by SafariTrak Maps and enable **Gmail API**.
2. Configure Google Auth Platform consent/branding and the authorized audience. For an External app in Testing status, add the dedicated sender mailbox as a test user. Google may expire refresh tokens for apps left in Testing; complete the consent-screen publication/verification requirements before production use.
3. Create an OAuth client ID for a **Desktop app**. Do not create a service account. Keep the downloaded client secret private.
4. Choose the dedicated Gmail mailbox to send SafariTrak mail. The OAuth-authorized account must match `GMAIL_SENDER_EMAIL` or have that sender configured as an authorized Gmail alias.
5. Use the loopback redirect URI `http://127.0.0.1:53682/` in the SafariTrak configuration. The CLI binds to this IPv4 loopback address and must run on the same computer where you open the authorization URL. Keep the URI identical wherever the OAuth client configuration requests it.

The only requested scope is `https://www.googleapis.com/auth/gmail.send`. OAuth consent and token refresh happen on the server/local CLI; the Gmail API key, client secret, refresh token, and access tokens are never sent to a SafariTrak browser page or JavaScript.

## Configure the backend

For a local Windows/XAMPP setup, create the ignored file `backend/config/env.local.php` (do not commit it) and return the five required values:

```php
<?php

return [
    'GOOGLE_CLIENT_ID' => 'your-oauth-client-id',
    'GOOGLE_CLIENT_SECRET' => 'your-oauth-client-secret',
    'GOOGLE_REDIRECT_URI' => 'http://127.0.0.1:53682/',
    'GOOGLE_REFRESH_TOKEN' => '',
    'GMAIL_SENDER_EMAIL' => 'safari.trak.sender@gmail.com',
];
```

Replace example values locally; do not paste live credentials into source files, issue comments, browser developer tools, or commits. The file is ignored by Git. Restrict its Windows NTFS ACL to the PHP/Apache service account and administrators. For production, prefer the host's protected environment/secret manager and set the same exact variable names there; the process environment takes precedence over the local file.

The variable names are also listed blank in `.env.example` for reference. That file is not a secrets store.

## Authorize the sender and save a refresh token

With the OAuth client ID, secret, and loopback redirect URI configured in `env.local.php`, run from the project root:

```powershell
php backend/scripts/gmail-oauth-setup.php
```

The CLI prints a Google authorization URL. Open it on the same computer, sign in as the dedicated sender, and approve Gmail sending. The script listens only on loopback, validates a one-time OAuth state value, exchanges the authorization code directly with Google over verified HTTPS, and saves the refresh token into the ignored `backend/config/env.local.php`. It does not print the token or return it in the browser response. Keep the CLI running until it reports completion. If the redirect port is busy, stop the other local listener and retry.

If Google does not issue a refresh token, revoke SafariTrak's previous consent in the sender's Google Account, then run the CLI again; it requests offline access and consent. Never paste an access token into configuration: the server obtains short-lived access tokens from the refresh token as needed.

## Email behavior

- `st_send_mail()` is the reusable backend entry point. When all five Google variables are present, it sends MIME messages via `gmail.users.messages.send`; an incomplete OAuth configuration fails closed rather than silently switching providers.
- Existing SMTP settings remain a compatibility fallback only when no Google mail variables are configured. Remove unused SMTP secrets after confirming Gmail API delivery.
- Verification, welcome, password-reset, password-changed (reset or settings), trusted-contact invitation, and persistent-group invitation mail use branded HTML and plain-text alternatives.
- Outbound transactional mail is limited to 400 messages per sender per day and 5 per recipient per hour using the existing database-backed rate-limit table. Existing registration, resend, and invitation request limits still apply.
- Delivery failures are logged without logging tokens, email body content, recipient addresses, or OAuth response bodies. Existing API responses continue to avoid exposing OAuth credentials. Some flows expose only their existing delivery status; password-reset requests remain generic to avoid account enumeration.

## Verification and operations

Use the configured app's verification/resend and password-reset flows, then send a persistent-group invitation to a controlled test mailbox. Confirm receipt, SafariTrak branding, the HTML and plain-text versions, and the expected expiry/click behavior. Do not use real users as test recipients.

The Gmail account's sending quota and Google's OAuth verification policy still apply. Monitor PHP's protected server logs for sanitized HTTP status/error codes. If access is revoked or the refresh token expires, run the authorization CLI again. Rotate/revoke OAuth credentials in Google Cloud and the sender's Google Account when an operator or secret is compromised.
