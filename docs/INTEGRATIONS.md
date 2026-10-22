# External integrations – status and what is needed

## Current status (16 Oct 2026)

| Integration | Status |
|---|---|
| Manual slip upload (office) | ✅ Working – Payments desk → Payment slip inbox → Upload slip |
| Email collection of payment slips | ❌ Not built yet (planned Phase J). Needs mailbox credentials. |
| WhatsApp Business collection / notifications | ❌ Not built yet (planned Phase J). Needs Meta credentials. |
| Outgoing email (password reset, invoices, dealer payment notices, overdue reminders, daily summary) | ✅ Built and tested (15 Oct 2026). **Delivers only after SMTP is set in `.env`**; until then `MAIL_MAILER=log` writes messages to the log and the Email log shows "Not delivered". Not yet tested against a real mail server. |
| Scheduled tasks | ✅ Built and tested. Need the scheduler (cron / Task Scheduler) on the server. |
| Backups | ➖ Removed from the system at the company's request. Backups are the server team's responsibility (database dump + uploaded files). |
| Bank APIs | ❌ Not planned. Sri Lankan banks are reconciled by statement import (Phase I). |

Until email and WhatsApp are connected, staff save slips received by those channels with **Upload slip** and pick the channel,
and copy the acknowledgement text shown on the slip page into their reply.

## What you will need to provide

### Email (payment slips sent to a company address)
One of:
- An **IMAP mailbox** (host, port, username, app password) for e.g. `payments@yourcompany.lk`. The PHP `imap` extension
  is not installed in this PHP build; a pure-PHP IMAP library would be added. **or**
- An inbound-email service (Mailgun / SendGrid / Postmark inbound parse) that posts each email to a webhook in this system,
  plus its signing secret. This works without opening the mailbox to the server and is the recommended option.

### WhatsApp
The official **WhatsApp Business Platform (Cloud API)** via Meta:
- A Meta Business account, a WhatsApp Business phone number, the **phone number ID**, a **permanent access token**,
  the **app secret** (to verify webhook signatures) and a **verify token** of your choice.
- Approved **message templates** for: slip received (acknowledgement), payment verified, payment rejected.
- The system must be reachable on a public **HTTPS** address for Meta to deliver webhooks.

### Outgoing email (built – needs your mail account)
SMTP details (host, port, username, password, from-address), set in `.env` (`MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`,
`MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`). Google Workspace / Microsoft 365 need an app password or SMTP relay.
Add SPF/DKIM records for the sending domain so dealer emails do not land in spam. Then send a test from Setup → Scheduled tasks.
