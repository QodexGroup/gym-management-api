## Account Subscription & Billing Flow (API)

### Overview

This document describes the **account-level subscription and billing flow** in the `gym-management-api` backend, including:

- Account signup and initial subscription plan
- Invoice generation and account locking
- Client payment requests (+ receipt uploads)
- Admin approval / rejection flows
- Reactivation fee flow with free month and forced monthly plan

This is separate from the legacy membership billing flow in `billing-and-membership-flow.md`.

---

## 1. Signup & Initial Subscription

### 1.1 Signup Flow

- **Service**: `AccountSignUpService`
- **Repositories**:
  - `AccountRepository::createAccountFromSignup()`
  - `UsersRepository::createUserFromSignup()`
  - `AccountSubscriptionPlanRepository::createTrialAccountSubscriptionPlan()` (or equivalent)

**Flow:**

1. Client calls the signup endpoint with:
   - Account details (name, email, phone, billing info)
   - User details (owner)
   - Selected `subscription_plan_id` (trial or paid)
2. Service validates that the user/email does not already exist (idempotency is handled by middleware, not by the service).
3. Service orchestrates:
   - Create `Account`
   - Create `User` (owner)
   - Create initial `AccountSubscriptionPlan`:
     - For **trial plans**: set `trial_starts_at` / `trial_ends_at`, leave `subscription_ends_at` `null` until billing logic runs.
     - For **paid plans**: set `subscription_starts_at` according to activation date and interval; `subscription_ends_at` is computed later by billing.
4. No invoices are created during signup. Billing lifecycle handles invoices later (see section 2).

### 1.2 Fetching & Updating Account (Client)

- **Controller**: `Account\AccountSubscription\AccountController`
- **Methods**:
  - `getAccount()`: returns the authenticated user’s `Account` with `activeAccountSubscriptionPlan.subscriptionPlan` via `AccountResource`.
  - `updateAccount()`: updates account + billing information via `AccountRepository::updateAccount()`.

**Notes:**

- Account and billing information are stored on the same `accounts` table; there is no separate billing info model.
- `AccountResource` flattens billing information and exposes `activeAccountSubscriptionPlan` via `AccountSubscriptionPlanResource`.

---

## 2. Invoice Generation & Account Locking

### 2.1 Billing Cycle Days

- **Constants**: `BillingCycleConstant`
  - `CYCLE_DAY_DUE = 5`   → invoice generation day
  - `CYCLE_DAY_LOCK = 10` → account lock day

### 2.2 Scheduled Commands

- **Generate Invoices**
  - **Command**: `GenerateInvoicesCommand`
  - **Signature**: `account-billing:generate-invoices`
  - **Schedule**: `monthlyOn(5, '06:00')` in `routes/console.php`
  - **Service**: `BillingLifecycleService::generateInvoicesForCurrentCycle()`

- **Lock Accounts**
  - **Command**: `LockAccountsCommand`
  - **Signature**: `account-billing:lock-accounts`
  - **Schedule**: `monthlyOn(10, '06:00')`
  - **Service**: `BillingLifecycleService::lockAccountsWithUnpaidInvoiceForCurrentPeriod()`

### 2.3 Invoice Generation (5th of the Month)

- **Service**: `BillingLifecycleService`
- **Repositories**:
  - `AccountSubscriptionPlanRepository::chunkBillableByInterval()`
  - `AccountInvoiceRepository::createGeneratedInvoice()`

**Flow:**

1. On the 5th, `generateInvoicesForCurrentCycle()` runs:
   - Computes current billing period for:
     - Monthly, Quarterly, Annual (5th-based anchors).
   - For each interval, calls `generateInvoicesForInterval($billingPeriod, $interval)`.
2. `chunkBillableByInterval()` returns active `AccountSubscriptionPlan` rows:
   - Non-trial `SubscriptionPlan` with matching `interval`.
   - `subscription_starts_at` is not null and ≤ cycle start.
   - `subscription_ends_at` is null or > cycle start.
3. For each ASP:
   - `generateInvoiceForPeriod()` computes:
     - `period_from` = cycle start (5th).
     - `period_to` = next cycle start - 1 day (inclusive).
     - Proration (if subscription/trial ends mid-period) via `calculateProrate()`.
   - Builds `invoice_details` with:
     - `invoiceType = TYPE_SUBSCRIPTION`
     - `subscriptionPlan` (static plan data)
     - `accountSubscriptionPlan` (ASP data)
     - Optional `prorate` details.
   - Calls `AccountInvoiceRepository::createGeneratedInvoice($payload)`:
     - Creates an `AccountInvoice` with:
       - `status = pending`
       - `invoice_number` auto-generated in `AccountInvoice::booted()` from its `id`.

**Key Guarantees:**

- One invoice per `account_id + billing_period` (checked via `existsByAccountAndBillingPeriod()`).
- `period_to` is inclusive (next cycle start - 1 day) to ensure consistent day counts.

### 2.4 Locking Accounts (10th of the Month)

- **Service**: `BillingLifecycleService::lockAccountsWithUnpaidInvoiceForCurrentPeriod()`
- **Repositories**:
  - `AccountInvoiceRepository::getPendingByBillingPeriods()`
  - `AccountSubscriptionPlanRepository::lockAccountsByIds()`

**Flow:**

1. Computes current billing periods for all intervals (`currentBillingPeriodKeys()`).
2. Retrieves **pending** invoices (`status = pending`) for any of those periods.
3. Sends lock notice emails via `SendAccountInvoiceNotificationJob` (type `TYPE_LOCK_NOTICE`).
4. Locks accounts at the **subscription plan level**:
   - `AccountSubscriptionPlanRepository::lockAccountsByIds($accountIds)` sets `locked_at` on the active `AccountSubscriptionPlan` for each account.
   - `accounts.status` remains `active`, so login is still allowed.
   - Backend permission checks (e.g. `canCreatePaidResources()`) plus frontend logic treat locked accounts as unable to use paid features until reactivation.

### 2.5 Deactivating Delinquent Accounts (End of Month)

- **Service**: `BillingLifecycleService::deactivateDelinquentAccounts()`
- **Command**: `DeactivateDelinquentAccountsCommand`
- **Signature**: `account-billing:deactivate-accounts`
- **Schedule**: `lastDayOfMonth('00:00')` in `routes/console.php`

**Flow:**

1. Determines a cutoff date for long‑locked accounts (e.g. `locked_at` at least one full billing cycle in the past).
2. Finds accounts whose `AccountSubscriptionPlan.locked_at` is older than the cutoff.
3. Among those, finds accounts that **still have pending invoices**.
4. Deactivates those accounts:
   - `AccountRepository::deactivateActiveAccountsByIds($accountIds)` sets `accounts.status = 'deactivated'`.
5. Deactivated accounts:
   - Cannot proceed past the login screen.
   - Are surfaced to the client via `/auth/me` (`UserResource::isAccountDeactivated` and `AccountResource::status`), so the frontend can show a banner and block access.

---

## 3. Payment Requests & Receipts

### 3.1 Client Payment Request Flow

- **Controller**: `Account\AccountSubscription\AccountPaymentRequestController`
  - `getPaymentRequests(GenericRequest)`:
    - Uses `AccountPaymentRequestRepository::paginateByAccount($genericData)` ordered by `created_at desc`.
    - Returns paginated `AccountPaymentRequestResource` data.
  - `createPaymentRequest(AccountPaymentRequestRequest)`:
    - Validated fields: `invoiceId`, `receiptUrl`, `receiptFileName`.
    - Calls `AccountPaymentRequestService::createInvoicePaymentRequest($genericData)` to create a payment request linked to an `AccountInvoice`.
  - `createSubscriptionRequest(AccountSubscriptionRequestRequest)`:
    - Owner selects a new paid plan while authenticated.
    - Behavior depends on whether the account is still in trial or already has an active paid subscription.
  - `createReactivationPaymentRequest(AccountReactivationPaymentRequestRequest)`:
    - Validated fields: `receiptUrl`, `receiptFileName`.
    - Calls `AccountPaymentRequestService::createReactivationPaymentRequest($genericData)` to create a **standalone reactivation fee** payment request:
      - `payment_transaction = 'Reactivation Fee'`
      - `payment_transaction_id = null`
      - `amount` set from configured reactivation fee (e.g. ₱1,200).

- **Request**: `AccountPaymentRequestRequest`
  - Ensures `invoiceId` exists in `account_invoices`.
  - `receiptUrl` is a **string path** to a file in Firebase Storage (frontend already uploaded).

- **Request**: `AccountReactivationPaymentRequestRequest`
  - `receiptUrl` is a **string path** to a file in Firebase Storage (frontend already uploaded).
  - `receiptFileName` is an optional, human‑readable name.

- **Request**: `AccountSubscriptionRequestRequest`
  - `subscriptionPlanId` must be a valid non-trial paid plan.
  - For trial upgrades (when admin approval is required), receipt upload is required:
    - `receiptUrl`
    - `receiptFileName`

- **Service**: `AccountPaymentRequestService::createInvoicePaymentRequest()`
  - Validates:
    - Invoice exists and belongs to the current account.
    - Invoice is not already `paid`.
    - No existing pending payment request for this invoice:
      - `AccountPaymentRequestRepository::hasPendingForAccount($accountId, AccountInvoice::class, $invoiceId)`.
  - Within a transaction:
    - Calls `AccountPaymentRequestRepository::createInvoicePaymentRequest($genericData, $invoice)` which:
      - Creates `AccountPaymentRequest` with:
        - `account_id` from user
        - `payment_transaction = AccountInvoice::class`
        - `payment_transaction_id = invoice id`
        - `amount` = invoice total amount
        - `receipt_url` = raw `receiptUrl` from request (Firebase path)
        - `receipt_file_name` (optional)
        - `status = pending`
        - `requested_by` = user id
        - `payment_details` = `{ invoice_number, total_amount }`
  - Returns the payment request with `account` and `paymentTransaction` relations loaded.

- **Service**: `AccountPaymentRequestService::createReactivationPaymentRequest()`
  - Validates:
    - No existing pending reactivation payment request for the same account:
      - `AccountPaymentRequestRepository::hasPendingForAccount($accountId, 'Reactivation Fee', null)`.
  - Within a transaction:
    - Calls `AccountPaymentRequestRepository::createReactivationPaymentRequest($genericData, amount)` which:
      - Creates `AccountPaymentRequest` with:
        - `account_id` from user
        - `payment_transaction = 'Reactivation Fee'`
        - `payment_transaction_id = null`
        - `amount` = fixed reactivation fee
        - `receipt_url` / `receipt_file_name`
        - `status = pending`
        - `requested_by` = user id
        - `payment_details` = `{ type: 'reactivation_fee', amount }`
  - Returns the payment request with `account` loaded.

- **Service**: `AccountPaymentRequestService::createSubscriptionRequest()`
  - Active flow selection:
    - If the owner's current subscription ASP is still in trial (`subscription_starts_at === null`):
      - Creates a standalone subscription upgrade payment request:
        - `payment_transaction = AccountSubscriptionPlan::class`
        - `payment_transaction_id = <current ASP id>`
        - `payment_details = { type: 'subscription_upgrade', subscriptionPlanId, subscriptionPlanName, interval, requestedAt }`
      - Requires Firebase receipt upload (`receiptUrl`).
      - Returns message: upgrade will start after admin approval.
    - If the owner already has an active paid subscription:
      - Stores a pending plan via `AccountSubscriptionPlanRepository::updatePlanSelection($asp, $newPlan, $effectiveAt)`.
      - Takes effect on the next billing cycle when pending selection is applied during due-day generation.

**Important:**

- **Backend does NOT upload files**. The frontend:
  - Uploads receipts to Firebase with `useFileUpload` / `fileUploadService`.
  - Sends only the storage path (`receiptUrl`) and metadata.

### 3.2 Admin Payment Review (List Only)

- **Controller**: `Admin\AdminPaymentRequestController`
  - `getPendingPaymentRequests(GenericRequest)`:
    - Uses `AdminPaymentRequestRepository::getPendingPaymentRequests()`.
    - Returns `AccountPaymentRequestResource::collection($requests)`.
  - No direct approve/reject endpoints yet; those are currently driven by commands and backoffice flows.

---

## 4. Admin Approval, Reactivation & Free Month

### 4.1 Approval of Invoice & Standalone Payments

- **Service**: `AdminPaymentRequestService::approve($requestId, $adminUserId)`
- **Flow (transactional):**

1. Load pending request:
   - `AccountPaymentRequestRepository::findPendingById($requestId)`.
2. Load `account` and `paymentTransaction`.
3. Handle based on `payment_transaction`:
  - If `payment_transaction = AccountInvoice::class`:
    - Load invoice with relations (`AccountInvoiceRepository::findByIdWithRelations()`).
    - Mark invoice paid (`markAsPaid()`).
    - If **not** a reactivation fee (`!isReactivationFeeInvoice()`):
      - Retrieve ASP from invoice.
      - If ASP has a non-trial `SubscriptionPlan`:
        - Call `AccountSubscriptionPlanRepository::activatePaidSubscriptionPlan($asp)`:
          - Sets subscription window for the plan’s interval from the current billing cycle (5th).
        - Activate account via `AccountRepository::activateAccountById()`.
  - If `payment_transaction = AccountSubscriptionPlan::class`:
    - Approves a trial upgrade payment (`payment_details.type = 'subscription_upgrade'`).
    - Computes subscription start/end (immediately after trial ends if still active; otherwise from `requestedAt`).
    - Applies subscription window to the ASP via `AccountSubscriptionPlanRepository::applyReactivationWindow()`.
    - Clears `trial_starts_at` / `trial_ends_at` to prevent trial-based proration from triggering.
    - Activates the account and voids any pending subscription invoices (if generated).
  - If `payment_transaction = 'Reactivation Fee'`:
    - Approves a standalone reactivation fee (no `AccountInvoice` exists).
    - Applies free month + switches to the default monthly paid plan.
    - Activates the account and voids unpaid subscription invoices.
4. Mark payment request as approved:
   - `AccountPaymentRequestRepository::markAsApproved($request, $adminUserId)`.

### 4.2 Reactivation Fee Processing (Free Month + Monthly Plan)

Note: standalone reactivation fee payments are already applied inside `AdminPaymentRequestService::approve()` when `payment_transaction = 'Reactivation Fee'`.
The command below is for legacy/invoice-based reactivation fee processing (approved invoice payment requests).

- **Service**: `AdminPaymentRequestService::processApprovedReactivations($accountId = null, $limit = 200)`
- **Command**: `ProcessReactivationPaymentsCommand` (`account-billing:process-reactivations`)

**Goal:**

- For **approved reactivation fee invoices**:
  - Reactivate account.
  - Void old unpaid invoices.
  - Switch to **monthly plan** (regardless of previous interval).
  - Apply **one free month**.
  - Record full reactivation metadata in `payment_details`.

**Flow:**

1. Fetch approved invoice payment requests:
   - `AccountPaymentRequestRepository::getApprovedInvoiceRequests($accountId, $limit)`:
     - `payment_transaction = AccountInvoice::class`
     - `status = approved`
2. For each request:
   - Skip if `payment_details['reactivationProcessed'] === true`.
   - Skip if no `payment_transaction_id`.
   - Load invoice with relations.
   - Skip if invoice is **not** a reactivation invoice:
     - Determined by `isReactivationFeeInvoice()` → `invoice_details['invoiceType'] === TYPE_REACTIVATION_FEE`.
3. Inside a transaction:
   - Load latest ASP for account with plan:
     - `AccountSubscriptionPlanRepository::findLatestByAccountIdWithPlan($accountId)`.
     - Skip if there is no ASP or plan, or plan **is trial**.
   - Determine `paidAt`:
     - Use `request->approved_at` (startOfDay) or `now()->startOfDay()`.
   - Compute next cycle start:
     - `nextCycleStart = nextCycleStartAfterPayment($paidAt)`:
       - If `day >= CYCLE_DAY_DUE (5)`: add 1 month, then set to 5th.
       - Else: set to 5th of current month.
   - Fetch default monthly plan:
     - `SubscriptionPlanRepository::findDefaultMonthlyPaidPlan()`:
       - First non-trial plan with `interval = 'month'` ordered by price.
   - Compute subscription end:
     - `subscriptionEndsAt = BillingLifecycleService::nextCycleStart(nextCycleStart, INTERVAL_MONTH)->addMonthNoOverflow()`
       - One full **monthly** billing interval from `nextCycleStart`.
       - Then one additional free month.
   - Apply reactivation window and **switch to monthly** plan:

     ```php
     $this->accountSubscriptionPlanRepository->applyReactivationWindow(
         $asp,
         $monthlyPlan,
         $nextCycleStart,
         $subscriptionEndsAt
     );
     ```

     - Keeps existing trial dates.
     - Updates:
       - `subscription_plan_id` → monthly plan
       - `plan_name` → monthly plan name
       - `subscription_starts_at` / `subscription_ends_at`
       - `locked_at = null`

   - Reactivate account:
     - `AccountRepository::activateAccountById($accountId)`.
   - Void old unpaid invoices except the reactivation one:
     - `AccountInvoiceRepository::voidUnpaidByAccountIdExceptInvoice($accountId, $invoiceId)`.
   - Update `payment_details`:
     - `reactivationProcessed = true`
     - `reactivationProcessedAt = now()`
     - `reactivation` object:
       - `paidAt`
       - `prorateFrom` = `paidAt`
       - `prorateTo` = `nextCycleStart - 1 day`
       - `subscriptionStartsAt` = `nextCycleStart`
       - `subscriptionEndsAt` = `subscriptionEndsAt - 1 day` (display end)
       - `freeMonthApplied = true`
       - `invoiceType = TYPE_REACTIVATION_FEE`
     - Persisted via `AccountPaymentRequestRepository::updatePaymentDetails()`.

**Summary:**

- After a reactivation fee is approved + processed:
  - Account is **active**.
  - ASP uses the **default monthly paid plan**, regardless of previous interval (quarterly/annual → monthly).
  - One free month is applied on top of one full monthly interval.
  - All old unpaid invoices are **voided**, except the reactivation invoice.

### 4.3 Admin Reject Payment Request

- **Service**: `AdminPaymentRequestService::reject($requestId, $adminUserId, $reason)`
  - Finds pending request by id.
  - Uses `AccountPaymentRequestRepository::markAsRejected()` to:
    - `status = rejected`
    - `approved_by = adminUserId`
    - `approved_at = now()`
    - `rejection_reason = reason`
  - Returns the fresh request with `account` and `paymentTransaction`.

**Note:**  
As of now, reject is not wired to a specific command or HTTP endpoint; it is intended for admin-facing flows (e.g. an admin API or backoffice tooling).

---

## 5. Plan Change (Client)

Client-driven plan changes happen via:

- `POST /accounts/subscription-request` (controller: `AccountPaymentRequestController::createSubscriptionRequest()`).

Behavior:
- Trial upgrade (when the current ASP has `subscription_starts_at === null`):
  - Owner submits `subscriptionPlanId` plus receipt (`receiptUrl`, `receiptFileName`).
  - Backend creates a standalone subscription upgrade payment request (admin approval required).
  - Paid subscription window starts after admin approval (computed by `AdminPaymentRequestService::processTrialUpgradeApproval()`).
- Upgrade/downgrade while already active paid:
  - Owner submits `subscriptionPlanId`.
  - Backend stores a pending plan on ASP (`pending_subscription_plan_id`, `pending_plan_effective_at`).
  - The current plan remains unchanged until due day; the pending plan is applied on the next billing cycle before invoice generation.

---

## 6. Quick Reference

- **Client**
  - Signup + initial subscription: `AccountSignUpService`
  - Get/update account (incl. billing): `Account\AccountSubscription\AccountController`
  - List subscription plans: `SubscriptionPlanController::getSubscriptionPlans()`
  - Create invoice payment request: `AccountPaymentRequestController::createPaymentRequest()`
  - Create reactivation payment request: `AccountPaymentRequestController::createReactivationPaymentRequest()`
  - List own payment requests (paginated): `AccountPaymentRequestController::getPaymentRequests()`

- **Admin**
  - List pending payment requests: `AdminPaymentRequestController::getPendingPaymentRequests()`
  - Approve request (normal invoice): `AdminPaymentRequestService::approve()`
  - Reject request: `AdminPaymentRequestService::reject()`
  - Process reactivations (free month + monthly plan): `AdminPaymentRequestService::processApprovedReactivations()`
  - Command: `account-billing:process-reactivations`
  - Command: `payment-request:approve` (approves a single pending request by `account_id`)
  - Command: `payment-request:approve-or-update` (approves pending requests, and ensures latest approved ones are processed)

- **Billing Lifecycle**
  - Generate invoices on 5th: `GenerateInvoicesCommand` → `BillingLifecycleService::generateInvoicesForCurrentCycle()`
  - Lock accounts on 10th: `LockAccountsCommand` → `BillingLifecycleService::lockAccountsWithUnpaidInvoiceForCurrentPeriod()`
  - Deactivate delinquent accounts on last day of month: `DeactivateDelinquentAccountsCommand` → `BillingLifecycleService::deactivateDelinquentAccounts()`

---

## 7. End-to-End Operational Flow

This section summarizes the complete account subscription lifecycle from signup up to billing, locking, and admin approval paths.

### 7.1 Signup to Active Subscription

1. Account owner signs up and selects a plan (trial or paid).
2. Backend creates:
   - `Account`
   - owner `User`
   - one `AccountSubscriptionPlan` (ASP) row for the account
3. No invoice is generated at signup time; billing jobs handle invoices on cycle day.

### 7.2 Due-Day Billing Cycle (5th)

1. `account-billing:generate-invoices` runs.
2. For each interval (monthly/quarterly/annual), billable ASPs are selected.
3. Before generating invoices, pending plan selections due on this cycle are applied:
   - if `pending_subscription_plan_id` exists and `pending_plan_effective_at <= cycleStart`,
   - ASP is switched to pending plan and pending fields are cleared.
4. Invoice is generated with current effective plan and proration rules.

### 7.3 Lock Cycle (10th) and Deactivation

1. `account-billing:lock-accounts` runs on the 10th.
2. Accounts with pending invoices in current billing periods are locked at ASP level (`locked_at`).
3. On month end, `account-billing:deactivate-accounts` may deactivate long-locked accounts that still have pending invoices.

### 7.4 Client Payment Submission

For receipts, frontend uploads file to Firebase, then sends path metadata to API.

- Invoice payment: creates payment request linked to `AccountInvoice::class`.
- Reactivation fee: creates standalone payment request with `payment_transaction = 'Reactivation Fee'`.
- Trial upgrade payment: creates standalone payment request linked to ASP (`AccountSubscriptionPlan::class` + `payment_transaction_id = asp.id`).

### 7.5 Admin Approval Behavior

`AdminPaymentRequestService::approve()` handles all supported payment request types:

- `AccountInvoice::class`: marks invoice paid and activates paid subscription window (non-reactivation invoices).
- `AccountSubscriptionPlan::class` (`subscription_upgrade`): applies paid window for trial-upgrade flow, activates account, clears trial fields, voids pending invoices.
- `'Reactivation Fee'`: applies reactivation window (monthly + free month), activates account, and voids pending invoices.

### 7.6 Plan Change Behavior (Owner-Initiated)

- **Trial -> Paid**
  - Requires receipt and admin approval.
  - Creates `subscription_upgrade` payment request.
  - Effective after admin approval.

- **Active Paid -> Another Paid Plan** (e.g., monthly -> quarterly)
  - No admin approval required.
  - No immediate plan switch in `subscription_plan_id`.
  - Stores:
    - `pending_subscription_plan_id`
    - `pending_plan_effective_at`
  - Auto-applies on next due-day cycle before invoice generation.

---
*Last Updated: 2026-03-19*
*Version: 1.4*

