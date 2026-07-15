# Google Sheets Payment Request Sync

Every time a payment request is created (invoice payment, reactivation fee, or trial/subscription upgrade — all with receipt attachment), a queued job posts the row to a Google Apps Script webhook, which appends it to your Google Sheet.

## How it works

```
POST /account-subscription/payment-request (or reactivation / upgrade)
  → AccountPaymentRequestRepository::create*()
    → AccountPaymentRequestObserver::created()          (skipped if webhook URL not set)
      → SyncPaymentRequestToGoogleSheetJob (queued, 3 tries, backoff 10s/60s/180s)
        → HTTP POST to Apps Script web app
          → appendRow() in your sheet
```

A Sheets outage can never block or fail a payment submission — the job runs on the queue after the record is saved.

## Columns written to the sheet

| Submitted At | Request ID | Account ID | Account Name | Payment For | Amount | Payment Type | Status | Receipt URL | Receipt File Name | Requested By |
|---|---|---|---|---|---|---|---|---|---|---|

`Receipt URL` is the raw Firebase storage path (e.g. `receipts/invoices/receipt-123.png`), same as the `receipt_url` column in `account_payment_requests`.

## Setup

### 1. Create the sheet + Apps Script

1. Open (or create) your Google Sheet.
2. Extensions → Apps Script.
3. Replace the default code with the script below.
4. Set `SECRET` to a random string (e.g. run `php -r "echo bin2hex(random_bytes(16));"`).

```javascript
// Google Apps Script — paste into Extensions > Apps Script
const SECRET = 'CHANGE_ME_RANDOM_STRING';
const SHEET_NAME = 'Payment Requests'; // tab name; created if missing

const HEADERS = [
  'Submitted At', 'Request ID', 'Account ID', 'Account Name',
  'Payment For', 'Amount', 'Payment Type', 'Status',
  'Receipt URL', 'Receipt File Name', 'Requested By',
];

function doPost(e) {
  let payload;
  try {
    payload = JSON.parse(e.postData.contents);
  } catch (err) {
    return json({ status: 'error', message: 'invalid json' });
  }

  if (payload.secret !== SECRET) {
    return json({ status: 'error', message: 'unauthorized' });
  }

  const lock = LockService.getScriptLock();
  lock.waitLock(10000); // serialize concurrent appends
  try {
    const ss = SpreadsheetApp.getActiveSpreadsheet();
    let sheet = ss.getSheetByName(SHEET_NAME);
    if (!sheet) {
      sheet = ss.insertSheet(SHEET_NAME);
      sheet.appendRow(HEADERS);
      sheet.setFrozenRows(1);
    }

    sheet.appendRow([
      payload.submittedAt || '',
      payload.paymentRequestId || '',
      payload.accountId || '',
      payload.accountName || '',
      payload.paymentFor || '',
      payload.amount || 0,
      payload.paymentType || '',
      payload.status || '',
      payload.receiptUrl || '',
      payload.receiptFileName || '',
      payload.requestedBy || '',
    ]);

    return json({ status: 'ok' });
  } finally {
    lock.releaseLock();
  }
}

function json(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
```

### 2. Deploy as web app

1. Deploy → New deployment → type **Web app**.
2. Execute as: **Me**. Who has access: **Anyone**. (Required so Laravel can POST without Google OAuth; the shared secret is the auth.)
3. Authorize when prompted, then copy the web app URL (`https://script.google.com/macros/s/.../exec`).

> After editing the script later, use **Deploy → Manage deployments → Edit → New version**, otherwise the URL keeps serving the old code.

### 3. Configure Laravel

```env
GOOGLE_SHEETS_PAYMENT_WEBHOOK_URL=https://script.google.com/macros/s/XXXX/exec
GOOGLE_SHEETS_WEBHOOK_SECRET=same_value_as_SECRET_in_the_script
```

Then `php artisan config:clear`. Make sure a queue worker is running (`php artisan queue:work`), since `QUEUE_CONNECTION=database`.

Leaving `GOOGLE_SHEETS_PAYMENT_WEBHOOK_URL` empty disables the sync entirely (this is why tests are unaffected).

### 4. Test it

```bash
curl -L -X POST "$GOOGLE_SHEETS_PAYMENT_WEBHOOK_URL" \
  -H 'Content-Type: application/json' \
  -d '{"secret":"YOUR_SECRET","submittedAt":"2026-07-04 12:00:00","paymentRequestId":999,"accountId":1,"accountName":"Test Gym","paymentFor":"Invoice","amount":1500,"paymentType":"gcash","status":"pending","receiptUrl":"receipts/test.png","receiptFileName":"test.png","requestedBy":"Denz Dev"}'
```

(`-L` matters — Apps Script replies with a 302 redirect; Laravel's HTTP client follows it automatically.)

A row should appear in the sheet, then submit a real payment request through the app to verify end-to-end.

## Failure behavior

- Webhook down/erroring: job retries 3 times (10s → 60s → 180s), then lands in `failed_jobs`. Retry later with `php artisan queue:retry all`.
- The payment request itself is never affected.
- Failures are logged as `Google Sheet payment sync failed`.
