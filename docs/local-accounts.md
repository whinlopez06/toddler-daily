# Local accounts

There is no shared default username or password. As approved on 2026-11-01, create an account directly at http://localhost:4300/register without an invitation code. Enter your name, family name, email, a password of at least 12 characters with matching confirmation, timezone and privacy acknowledgement. Each account owns a separate private family. Email verification remains required before accessing family records.

Invitation access and free/paid tiers are deferred. Earlier invitation commands and tables remain dormant; generating a code does not control signup. Hosted signup and external onboarding still require the readiness gates in PROJECT_PLAN.md.

## Verification emails in native local development

Mailpit is a local test inbox. Messages are captured on the computer, rather than delivered to Gmail or other external mailboxes. Do not configure external forwarding/relaying for this local setup.

Start it from the repository root if it is not already running:

```powershell
& '.\.local\mailpit\mailpit.exe' --listen 127.0.0.1:8025 --smtp 127.0.0.1:1025 --database '.local/mailpit/messages.db' --max-age 7d --disable-version-check --label 'Toddler Daily local inbox'
```

Then:

1. Open http://localhost:4300/register and complete registration with a password of at least 12 characters.
2. Open http://127.0.0.1:8025 and select the verification message for that address.
3. Follow its verification link while signed in to the app.
4. Return to http://localhost:4300. Future sign-ins use the chosen email and password.

If no email arrives in your personal mailbox, that is expected for this local setup. Check the local inbox above. The development verification screen also has an **Open local email inbox** link. If the inbox will not open, start Mailpit with the command above, sign in to the existing account, and click **Resend verification**. An account may already have been saved even if the original email failed; do not try to register that email again. Follow the newest verification message while signed in.

Mailpit was downloaded from the official axllent/mailpit GitHub release v1.33.0; its ZIP was checked against the release asset's published SHA256 digest. The binary, mailbox and download are ignored under .local. The binary is machine-specific and must not be committed or shipped with production.

Laravel local SMTP settings are MAIL_HOST=127.0.0.1 and MAIL_PORT=1025. Local Mailpit is separate from Docker's optional Mailpit service; do not start both on the same ports. A hosted deployment needs a real configured sender and must not point to the developer's local inbox.

No account is automatically created, no password is selected by the operator, and email verification remains enabled.

## Reset a forgotten password

Open http://localhost:4300/forgot-password and request a reset link with the account email. Recovery signs out any existing browser session before submitting, because Laravel's recovery endpoints are guest-only. In local development, open the newest **Reset your password** message at http://127.0.0.1:8025 rather than checking a personal mailbox. The link expires after 60 minutes and can only be used once. The user should enter and confirm their new password (at least 12 characters), save it, then sign in. Resetting a password does not verify an unverified email account.

A completed reset now takes you automatically to the sign-in page with a password-updated message, after explicit confirmation from Laravel. Enter the new password there; a browser password manager may still fill the older saved one. Requesting the reset email alone does not change the password. If a corrected login previously stayed blocked by an old email-field error, that stale-error bug has been fixed.
