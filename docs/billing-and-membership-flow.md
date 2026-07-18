# Billing and Membership Flow Documentation

## Overview
This document describes the complete flow of billing and membership management in the gym management system. The system follows a **unified payment-based extension** approach where membership is extended only when payment is made, not when bills are created.

---

## Core Principle
**Membership is extended when payment is made, not when bills are created.**

> **Configurable behavior:** Several behaviors below are now driven by per-account **Membership Settings** (System Settings → Membership Settings), stored in the generic `system_settings` table and read via `MembershipSettingService`. See [Configurable Membership Settings](#configurable-membership-settings). Defaults are chosen to preserve the original behavior described in this document, so an account that never touches the settings behaves exactly as before.

## Billing Period and History Locking

### Billing Period Format
- `billing_period` uses `mdY` format from `bill_date`
- Example: `2026-02-25` becomes `02252026`

### Lock Rule for Previous Cycle Bills
- A membership subscription bill is **editable** only if it belongs to the current cycle. A previous-cycle bill (`bill_date < current membership_start_date`) is history for editing purposes and cannot be edited.
- A previous-cycle bill **remains payable** — its outstanding balance can still be collected, and the payment does not change the current membership (bill_date < membership_end_date → no membership extension).
- Current cycle start is based on the member's latest `membership_start_date`
- Previous-cycle bills stay visible in reports and history views, and continue to count toward the customer's outstanding balance until paid or voided.

### Edit Guard (net vs paid)
- A bill's `net_amount` can **never** be edited below the amount already paid on it. `updateBill` throws if the new net is less than `paid_amount` ("delete/adjust payments first"); the bill form blocks it inline (red field, disabled Save).
- After any edit, `bill_status` is **recomputed** from the new net vs paid (`active` / `partial` / `paid`) so status can't go stale.

### Reactivation Interaction
- Reactivation fee creation voids old expired outstanding membership bills (sets `bill_status = voided`)
- Voided bills cannot receive new payments and are excluded from the customer balance (balance excludes bills whose status is `voided`)
- After reactivation payment creates a new membership cycle, previous-cycle membership bills cannot be **updated**, but their outstanding balance can still be **paid**

---

## Configurable Membership Settings

All keys are per-account and live in `account_system_settings` (`account_id`, `set_key`, `set_value`), served by the generic `/system-settings` endpoint (`AccountSystemSettingService`). The service exposes them in camelCase; defaults preserve the legacy behavior.

| Setting (camelCase) | Values | Default | Effect |
|---|---|---|---|
| `grantMembershipOn` | `first_payment` / `full_payment` | `first_payment` | When a membership bill payment grants/extends coverage. `first_payment` activates on any partial payment; `full_payment` waits until the bill is fully paid. Enforced in `handleAutomatedBillPayment`. |
| `allowPartialPayments` | bool | `true` | When on, staff can record a partial amount and leave a balance open. When **off**, every payment must settle the bill's **full remaining balance** — enforced in `validatePaymentRequest` and on the payment form (amount locked to the remaining). |
| `gracePeriodDays` | int (0–365) | `7` | Days a member stays past-due before expiry. Does **not** gate check-in; only relevant to class booking (below). |
| `requireMembershipForClassBooking` | bool | `true` | Gates **group-class booking** on an active membership. Walk-in / facility use is always open. |
| `allowClassBookingDuringGrace` | bool | `false` | Lets past-due members book classes until the grace period ends. |
| `requireReactivationFee` | bool | `true` | When off, the Reactivation Fee bill type is hidden/blocked; lapsed members reactivate by paying a normal membership bill. |
| `reactivationFeeAmount` | decimal | `0` | The fixed reactivation fee. The amount is **read-only** on the bill form and enforced server-side (a client cannot override it). |
| `grantReactivationPromo` | bool | `true` | Whether paying a reactivation fee grants a free/promo period. |
| `reactivationPromoLength` + `reactivationPromoUnit` | int + `days`/`months` | `1` `months` | Length of the reactivation promo period. Replaces the old hard-coded "1 free month". |
| `planChangeMode` | `next_renewal` / `immediate_proration` | `next_renewal` | How a mid-cycle plan change is applied. See [Scenario F](#scenario-f-mid-cycle-plan-change). |
| `downgradeCreditMode` | `extend_days` / `forfeit` | `extend_days` | Under immediate proration, what happens to the unused value on a **downgrade**. Only relevant when `planChangeMode = immediate_proration`. |
| `allowManualMembershipBills` | bool | `true` | When off, staff cannot manually create a membership bill: both the ad-hoc Bills-endpoint path **and** manually renewing/re-billing a member who **already has an active membership** (Memberships tab) are blocked. New members and lapsed/expired members (onboarding + reactivation) are always allowed. |
| `allowPayPreviousCycleBills` | bool | `true` | Whether previous-cycle outstanding balances can still be collected. |
| `allowEditPreviousCycleBills` | bool | `false` | Whether previous-cycle bills can be edited (off = history locked). |
| `billingAnchor` + `fixedBillingDay` | `anniversary`/`fixed_day` + int (1–28) | `anniversary` | `anniversary` renews on the member's own cycle date. `fixed_day` renews every member on the same day of the month; a member who isn't yet aligned gets **one prorated "month + gap" cycle** (a full period stretched to the next billing day, billed prorated, payable now) and is aligned from then on — no coverage gap. See [Scenario G](#scenario-g-fixed-day-billing--prorated-alignment). |

**Reactivation promo is granted ONLY via the reactivation-fee flow.** Paying a normal membership bill never grants a promo period.

---

## Bill Types

### 1. Custom Amount
- **Purpose**: One-time service charges
- **Membership Impact**: None
- **Flow**: Create bill → Payment → No membership change

### 2. Reactivation Fee
- **Purpose**: Reactivate an expired membership, optionally with a free/promo period
- **Availability**: Only offered when `requireReactivationFee = true`; the amount is fixed to `reactivationFeeAmount` (read-only on the form, enforced server-side)
- **Membership Impact**: 
  1. Voids expired membership balances
  2. Creates a new membership (same plan as last expired membership)
- **Flow**: 
  1. Create bill → Voids all expired membership bills (sets net_amount = paid_amount)
  2. Payment → Creates new membership with:
     - Same plan as last expired membership
     - Start date = payment date
     - End date = payment date + promo length **if** `grantReactivationPromo = true` (length/unit from `reactivationPromoLength`/`reactivationPromoUnit`, default 1 month); otherwise a normal full plan period
     - Status = active
  3. After the promo/period → Automated system creates bill for next period
- **Note**: The free/promo period is granted **only** through this flow. Defaults (`grantReactivationPromo = true`, `1 month`) reproduce the original "1 free month" behavior.

### 3. Membership Subscription
- **Purpose**: Membership renewal/activation
- **Membership Impact**: Creates or extends membership
- **Flow**: Varies based on scenario (see below). A mid-cycle switch to a *different* plan is governed by `planChangeMode` — see [Scenario F](#scenario-f-mid-cycle-plan-change).
- **Manual creation**: When `allowManualMembershipBills = false`, staff cannot manually create a membership bill — this includes manually renewing/re-billing a member who already has an active membership (Memberships tab). Onboarding a new member and reactivating a lapsed member are always allowed.

---

## Membership Subscription Bill Scenarios

### Scenario A: New Member (No Existing Membership)

#### Flow:
1. **Bill Creation** (`CustomerBillService::create()`)
   - User creates membership subscription bill
   - **Check**: `isNewMember = !currentMembership`
   - **Action**: ✅ Create membership immediately
   - **Membership**: Created with start_date = bill_date, end_date = bill_date + plan_period
   - **Bill**: Created with status = ACTIVE

2. **Payment** (`CustomerPaymentService::addPayment()`)
   - User pays the bill
   - **Check**: Membership already exists
   - **Action**: ✅ No membership change needed
   - **Result**: Bill status updated to PAID/PARTIAL

#### Example:
- **Date**: Dec 14, 2025
- **Action**: Create bill for "Monthly Plan" (₱1,000)
- **Result**: 
  - Membership: Dec 14, 2025 → Jan 14, 2026
  - Bill: Created, unpaid
- **Payment**: Dec 15, 2025 (₱1,000)
- **Result**: 
  - Membership: Still Dec 14, 2025 → Jan 14, 2026
  - Bill: Status = PAID

---

### Scenario B: Current Period Bill (Bill Date ≤ Membership End Date)

#### Flow:
1. **Bill Creation** (`CustomerBillService::create()`)
   - User creates bill for current/expired period
   - **Check**: `isCurrentPeriod = billDate <= membership_end_date`
   - **Action**: ✅ Create/update membership immediately
   - **Membership**: Created/updated with new dates
   - **Bill**: Created with status = ACTIVE

2. **Payment** (`CustomerPaymentService::addPayment()`)
   - User pays the bill
   - **Check**: Membership already exists
   - **Action**: ✅ No membership change needed
   - **Result**: Bill status updated to PAID/PARTIAL

#### Example:
- **Current Membership**: Dec 1, 2025 → Dec 31, 2025
- **Date**: Dec 20, 2025
- **Action**: Create bill for "Monthly Plan" (₱1,000) with bill_date = Dec 20, 2025
- **Result**: 
  - Membership: Dec 20, 2025 → Jan 20, 2026 (updated)
  - Bill: Created, unpaid
- **Payment**: Dec 22, 2025 (₱1,000)
- **Result**: 
  - Membership: Still Dec 20, 2025 → Jan 20, 2026
  - Bill: Status = PAID

---

### Scenario C: Future Renewal Bill (Manual) - Bill Date > Membership End Date

#### Flow:
1. **Bill Creation** (`CustomerBillService::create()`)
   - User creates bill for future period
   - **Check**: `billDate > membership_end_date`
   - **Action**: ❌ Do NOT create membership yet
   - **Membership**: No change
   - **Bill**: Created with status = ACTIVE, bill_date = future date

2. **Payment** (`CustomerPaymentService::handleAutomatedBillPayment()`)
   - User pays the bill (even partial payment)
   - **Check**: `billDate >= membership_end_date` (renewal bill)
   - **Action**: ✅ Extend membership
   - **Membership**: Extended from bill_date to bill_date + plan_period
   - **Result**: Bill status updated to PAID/PARTIAL

#### Example:
- **Current Membership**: Dec 14, 2025 → Jan 14, 2026
- **Date**: Jan 7, 2026
- **Action**: Create bill for "Monthly Plan" (₱1,000) with bill_date = Jan 14, 2026
- **Result**: 
  - Membership: Still Dec 14, 2025 → Jan 14, 2026 (no change)
  - Bill: Created for Jan 14, 2026, unpaid
- **Payment**: Jan 10, 2026 (₱1,000) - Early payment
- **Result**: 
  - Membership: Extended to Jan 14, 2026 → Feb 14, 2026
  - Bill: Status = PAID

---

### Scenario D: Reactivation Fee Payment

#### Flow:
1. **Bill Creation** (`CustomerBillService::create()`)
   - User creates reactivation fee bill
   - **Action**: ✅ Void all expired membership bills (sets net_amount = paid_amount)
   - **Membership**: No change (still expired)
   - **Bill**: Created with status = ACTIVE

2. **Payment** (`CustomerPaymentService::handleReactivationFeePayment()`)
   - User pays reactivation fee (even partial)
   - **Action**: ✅ Create new membership with free month
   - **Process**:
     1. Find last expired membership
     2. Get membership plan from expired membership
     3. Create new membership:
        - Same plan as last expired membership
        - Start date = payment date
        - End date = payment date + 1 month (free month)
        - Status = active
   - **Result**: New active membership created

3. **After Free Month** (`CheckMembershipExpiration` command)
   - 7 days before free month expires
   - **Action**: Automated system creates bill for next period
   - **Bill**: Created for next period (end_date → end_date + plan_period)

#### Example:
- **Last Expired Membership**: Monthly Plan (Dec 14, 2025 → Jan 14, 2026, expired)
- **Date**: Jan 20, 2026
- **Action**: Create reactivation fee bill (₱500)
- **Result**: 
  - Expired bills voided
  - Bill: Created, unpaid
  - Membership: Still expired
- **Payment**: Jan 20, 2026 (₱500)
- **Result**: 
  - New Membership: Monthly Plan (Jan 20, 2026 → Feb 20, 2026) - 1 free month
  - Bill: Status = PAID
- **Feb 7, 2026** (7 days before free month expires):
  - **Automated System**: Creates bill for Feb 20, 2026 → Mar 20, 2026 period
  - **Bill**: Created, unpaid
- **Feb 20, 2026** (Free month expires):
  - If bill paid: Membership extends to Mar 20, 2026
  - If bill unpaid: Membership expires

#### Example with Quarterly Plan:
- **Last Expired Membership**: Quarterly Plan (3 months, Dec 14, 2025 → Mar 14, 2026, expired)
- **Date**: Mar 20, 2026
- **Action**: Create reactivation fee bill (₱500)
- **Payment**: Mar 20, 2026 (₱500)
- **Result**: 
  - New Membership: Quarterly Plan (Mar 20, 2026 → Apr 20, 2026) - 1 free month (not 3 months)
  - Bill: Status = PAID
- **Apr 7, 2026** (7 days before free month expires):
  - **Automated System**: Creates bill for Apr 20, 2026 → Jul 20, 2026 period (3 months, full quarterly period)
  - **Bill**: Created, unpaid

---

### Scenario E: Automated Renewal Cycle

#### Phase 1: 7 Days Before Expiration (`CheckMembershipExpiration` command)

**Trigger**: Daily cron job, 7 days before membership expires

**Flow**:
1. **Notification**
   - Send notification to customer about expiring membership
   - Example: "Your membership expires on Jan 14, 2026"

2. **Automated Bill Creation**
   - Calculate next period: start = membership_end_date, end = start + plan_period
   - **Check**: If automated bill already exists for this period
   - **Action**: ✅ Create automated bill
   - **Bill**: 
     - bill_type = MEMBERSHIP_SUBSCRIPTION
     - bill_date = next period start (= membership_end_date; snapped to `fixedBillingDay` when `billingAnchor = fixed_day`)
     - billable_id / gross_amount = the renewal plan's id / price (the **pending** plan when a `next_renewal` change is scheduled, otherwise the current plan)
     - status = ACTIVE
   - **Membership**: ❌ No change (waiting for payment; a scheduled plan change is applied on payment, not here)

**Example**:
- **Current Membership**: Dec 14, 2025 → Jan 14, 2026
- **Date**: Jan 7, 2026 (7 days before expiration)
- **Action**: Automated system creates bill
- **Result**: 
  - Notification: Sent to customer
  - Bill: Created for Jan 14, 2026 → Feb 14, 2026 period
  - Membership: Still Dec 14, 2025 → Jan 14, 2026 (no change)

---

#### Phase 2: Payment Made (Anytime)

**Flow**:
1. **User Pays Automated Bill** (`CustomerPaymentService::addPayment()`)
   - Payment made — extension requires meeting the `grantMembershipOn` policy: `first_payment` (any partial) or `full_payment` (bill fully paid)
   - **Check**: `billDate >= membership_end_date` (renewal bill)
   - **Action**: ✅ Extend membership immediately (and switch to the pending plan if a `next_renewal` change was scheduled)
   - **Membership**: Extended from bill_date to bill_date + plan_period
   - **Result**: Bill status updated to PAID/PARTIAL

**Example**:
- **Date**: Jan 10, 2026 (4 days before expiration)
- **Action**: Customer pays automated bill (₱1,000)
- **Result**: 
  - Membership: Extended to Jan 14, 2026 → Feb 14, 2026
  - Bill: Status = PAID
  - **Note**: Membership extended even before expiration date

---

#### Phase 3: Expiration Day (`MembershipPlanChecker` command)

**Trigger**: Daily cron job, on/after membership expiration date

**Flow**:
1. **Check for Payment**
   - Find automated bill for renewal period (bill_date = membership_end_date)
   - **Check**: `automatedBill.paid_amount > 0`
   
2. **Decision**:
   - **If Payment Made** (even partial):
     - ✅ Skip expiration
     - Membership remains active (already extended by payment)
     - Log: "Skipped - Automated bill has payment"
   
   - **If No Payment**:
     - ❌ Expire membership
     - Update membership status = EXPIRED
     - Log: "Updated membership to Expired"

**Example - Payment Made**:
- **Date**: Jan 14, 2026 (expiration day)
- **Current Membership**: Jan 14, 2026 → Feb 14, 2026 (already extended)
- **Automated Bill**: Paid (₱1,000)
- **Result**: 
  - Membership: Status = ACTIVE (not expired)
  - Log: "Skipped - Automated bill has payment"

**Example - No Payment**:
- **Date**: Jan 14, 2026 (expiration day)
- **Current Membership**: Dec 14, 2025 → Jan 14, 2026
- **Automated Bill**: Unpaid (₱0)
- **Result**: 
  - Membership: Status = EXPIRED
  - Log: "Updated membership to Expired"

---

### Scenario F: Mid-Cycle Plan Change

A **mid-cycle plan change** is switching an *active, not-yet-expired* membership to a **different** plan (via the Memberships tab → `CustomerService::createOrUpdateMembership`). New members, expired memberships, and re-selecting the same plan are **not** plan changes — they follow the normal full assignment (create membership + full-price bill). How a real plan change is applied depends on `planChangeMode`.

#### Mode 1: `next_renewal` (default)

The member keeps their current plan and paid period; the new plan takes effect at the next renewal. **No charge now.**

- **On change**: `pending_plan_id` is set on the current membership. Nothing else changes.
- **At renewal** (`CheckMembershipExpiration`): the automated renewal bill is created for the **pending** plan (its id + price). The membership is **not** switched yet and the pending flag is **not** cleared — so an unpaid or skipped renewal never loses the old plan.
- **On renewal payment** (`handleAutomatedBillPayment`): the membership is switched to the new plan (pending flag cleared) and extended for the new period.

**Example**:
- Current: Basic (₱1,000), Dec 1 → Dec 31. Admin switches to Premium (₱2,000) on Dec 10.
- Dec 10: `pending_plan_id = Premium`. Member stays on Basic until Dec 31.
- ~Dec 24: automated renewal bill created for **Premium** (₱2,000), dated Dec 31.
- Member pays it → membership switches to Premium and extends Dec 31 → Jan 31.

#### Mode 2: `immediate_proration`

The switch happens now, prorated over the **remaining days** of the current cycle. The current cycle's original bill is **not** voided (the member keeps what they paid).

- `fraction = remainingDays / totalDays` (both inclusive), `diff = (newPrice − oldPrice) × fraction`.
- **Upgrade** (`diff > 0`): a prorated **Custom Amount** adjustment bill is raised for the difference; the membership switches to the new plan, same end date.
- **Downgrade** (`diff < 0`): the leftover value is settled per `downgradeCreditMode`:
  - `extend_days` (default): the leftover value becomes extra days on the new (cheaper) plan → the end date is pushed later.
  - `forfeit`: the membership switches now, same end date, no charge and no extension.

**Upgrade example**: Basic (₱1,000) → Premium (₱2,000), 20 of 30 days remaining. `diff = (2000 − 1000) × 20/30 ≈ ₱666.67` → adjustment bill for ₱666.67; plan becomes Premium; end date unchanged.

**Downgrade example (`extend_days`)**: Premium (₱2,000) → Basic (₱1,000), 20 of 30 days remaining. Leftover ≈ ₱666.67; at Basic's daily rate (~₱33.33) that's ~20 extra days → end date extended by ~20 days; plan becomes Basic; no bill.

---

### Scenario G: Fixed-Day Billing — Prorated Alignment

Applies only when `billingAnchor = fixed_day`. Every member is billed on the same day of the month (`fixedBillingDay`). A member whose current period does **not** already end on the day before that billing day gets **one prorated "month + gap" cycle** to align — after which every cycle is a clean full period on the billing day. There is **no coverage gap** and **no proration** on anniversary billing.

#### How the renewal job decides (`CheckMembershipExpiration`)
1. `cycleStart` = day after current coverage (`membership_end_date + 1`).
2. `naturalNextStart` = a full plan period from `cycleStart` (where the next cycle would begin with no alignment).
3. `alignedNextStart` = the fixed billing day on/after `naturalNextStart`.
4. If `alignedNextStart > naturalNextStart` → **prorated alignment cycle**: one bill dated `cycleStart` (payable now), covering `cycleStart → alignedNextStart − 1`, billed **prorated for the actual days** (so it can exceed one month). `coverage_end_date` is stored on the bill.
5. Otherwise (anniversary, or already aligned) → normal full-period renewal bill.

On payment, the membership is extended to the bill's `coverage_end_date` (not a full plan period) — honored in the renewal, new-member, and pending-plan payment paths.

#### Example (reactivation + fixed day 10)
- Reactivate **Jul 1**, 15-day promo → member active **Jul 1 – Jul 15** (free).
- Renewal job: `cycleStart` = Jul 16, a full month → Aug 16, next billing day on/after that = **Sep 10**. Since Sep 10 > Aug 16, it creates **one prorated bill dated Jul 16 covering Jul 16 → Sep 9** (~55 days, ≈ 1.83× the monthly price). Payable immediately, so the member stays continuously active and can book classes.
- From then on: clean full months on the 10th — **Sep 10 → Oct 9**, **Oct 10 → Nov 9**, …

New members who join mid-month under fixed-day billing align the same way (one prorated cycle at first renewal).

---

## Complete Cycle Example

### Timeline:
- **Dec 14, 2025**: Customer joins, membership created
  - Membership: Dec 14, 2025 → Jan 14, 2026
  - Bill: Created and paid

- **Jan 7, 2026** (7 days before expiration):
  - **Automated System** (`CheckMembershipExpiration`):
    - Notification sent
    - Automated bill created: bill_date = Jan 14, 2026, amount = ₱1,000
    - Membership: Still Dec 14, 2025 → Jan 14, 2026 (no change)

- **Jan 10, 2026** (Customer pays early):
  - **Payment Made**:
    - Payment: ₱1,000
    - Membership: Extended to Jan 14, 2026 → Feb 14, 2026
    - Bill: Status = PAID

- **Jan 14, 2026** (Expiration day):
  - **Automated System** (`MembershipPlanChecker`):
    - Check: Automated bill has payment
    - Result: Skip expiration (membership already extended)
    - Membership: Status = ACTIVE

- **Feb 7, 2026** (7 days before next expiration):
  - **Automated System** (`CheckMembershipExpiration`):
    - Notification sent
    - Automated bill created: bill_date = Feb 14, 2026, amount = ₱1,000
    - Membership: Still Jan 14, 2026 → Feb 14, 2026 (no change)

- **Feb 14, 2026** (Expiration day, no payment):
  - **Automated System** (`MembershipPlanChecker`):
    - Check: Automated bill has no payment
    - Result: Expire membership
    - Membership: Status = EXPIRED

---

## Key Decision Points

### 1. Bill Creation Decision Tree

```
Is bill_type = MEMBERSHIP_SUBSCRIPTION?
├─ NO → Create bill only
└─ YES → Check membership status
    ├─ No existing membership?
    │   └─ YES → Create membership immediately
    └─ Has existing membership?
        ├─ bill_date <= membership_end_date?
        │   └─ YES → Create/update membership immediately
        └─ bill_date > membership_end_date?
            └─ YES → Create bill only (wait for payment)
```

### 2. Payment Decision Tree

```
Is bill_type = MEMBERSHIP_SUBSCRIPTION?
├─ NO → Update bill status only
└─ YES → Check membership
    ├─ No existing membership?
    │   └─ YES → Create membership (new member)
    └─ Has existing membership?
        ├─ bill_date >= membership_end_date?
        │   └─ YES → Extend membership
        └─ bill_date < membership_end_date?
            └─ YES → No membership change (already active)
```

### 3. Expiration Decision Tree

```
Is membership_end_date < today?
├─ NO → Skip (not expired yet)
└─ YES → Check automated bill
    ├─ Automated bill exists?
    │   ├─ NO → Expire membership
    │   └─ YES → Check payment
    │       ├─ paid_amount > 0?
    │       │   └─ YES → Skip expiration (payment made)
    │       └─ paid_amount = 0?
    │           └─ YES → Expire membership
    └─ No automated bill?
        └─ YES → Expire membership
```

---

## Special Cases

### 1. Reactivation Fee
- **When**: Customer has expired membership
- **Action**: 
  1. Create reactivation fee bill
  2. Void all expired membership bills (`CustomerBillRepository::voidBill` sets `bill_status = voided`; voided bills are excluded from the balance by status)
  3. Customer only pays reactivation fee
  4. Membership remains expired until new subscription bill is paid

### 2. Early Payment on Automated Bill
- **When**: Customer pays automated bill before expiration
- **Action**: 
  1. Membership extended immediately
  2. On expiration day, system skips expiration (payment already made)

### 3. Partial Payment
- **When**: Customer pays partial amount on renewal bill
- **Action**: 
  1. Membership extended (even with partial payment)
  2. Bill status = PARTIAL
  3. On expiration day, system skips expiration (payment made)

### 4. Manual Bill Before Automated Bill
- **When**: User creates manual renewal bill before automated system creates one
- **Action**: 
  1. Manual bill created (no membership change)
  2. When automated system runs, it checks if bill exists
  3. If bill exists, automated system skips creation
  4. Payment on either bill extends membership

### 5. Manual Bill After Automated Bill
- **When**: User creates manual renewal bill after automated bill exists
- **Action**: 
  1. Manual bill created (no membership change)
  2. Both bills exist (manual + automated)
  3. Payment on either bill extends membership
  4. **Note**: This creates duplicate bills - consider preventing this in UI

---

## Code Flow Summary

### Bill Creation Flow
```
CustomerBillService::create()
├─ Check bill_type
│   ├─ CUSTOM_AMOUNT → Create bill only
│   ├─ REACTIVATION_FEE → Void expired bills → Create bill
│   └─ MEMBERSHIP_SUBSCRIPTION → Check membership
│       ├─ New member? → Create membership → Create bill
│       ├─ Current period? → Create/update membership → Create bill
│       └─ Future period? → Create bill only
└─ Recalculate customer balance
```

### Payment Flow
```
CustomerPaymentService::addPayment()
├─ Validate payment
├─ Create payment record
├─ Update bill (paid_amount, status)
├─ Recalculate customer balance
├─ Check bill type
│   ├─ REACTIVATION_FEE → handleReactivationFeePayment()
│   │   ├─ Find last expired membership
│   │   ├─ Get membership plan from expired membership
│   │   ├─ Create new membership with free month
│   │   │   └─ Start = payment date, End = payment date + 1 month
│   │   └─ Log result
│   └─ MEMBERSHIP_SUBSCRIPTION → handleAutomatedBillPayment()
│       ├─ Check if membership subscription bill
│       ├─ Check grantMembershipOn policy (first_payment / full_payment)
│       ├─ Find membership for the bill's plan
│       │   ├─ Found? → Check if renewal bill → Extend membership
│       │   ├─ Not found but membership has pending_plan_id = bill's plan?
│       │   │   └─ YES → Apply scheduled plan change → switch plan + extend
│       │   └─ Otherwise → Create membership (new member)
│       └─ Log result
└─ Send payment notification
```

### Automated Expiration Check Flow
```
CheckMembershipExpiration (Daily, 7 days before)
├─ Find memberships expiring in 7 days
├─ For each membership:
│   ├─ Send notification
│   ├─ Calculate next period dates
│   ├─ Check if automated bill exists
│   └─ If not exists → Create automated bill
└─ Log results
```

### Expiration Processing Flow
```
MembershipPlanChecker (Daily, on expiration day)
├─ Find expired memberships
├─ For each membership:
│   ├─ Find automated bill for renewal period
│   ├─ Check if bill has payment
│   │   ├─ Has payment? → Skip expiration
│   │   └─ No payment? → Expire membership
│   └─ Log result
└─ Log summary
```

---

## Database State Examples

### State 1: New Member Bill Created
```
CustomerBill:
- bill_type: "Membership Subscription"
- bill_date: "2025-12-14"
- bill_status: "active"
- net_amount: 1000
- paid_amount: 0

CustomerMembership:
- membership_start_date: "2025-12-14"
- membership_end_date: "2026-01-14"
- status: "active"
```

### State 2: Automated Bill Created (7 days before)
```
CustomerBill (Original):
- bill_type: "Membership Subscription"
- bill_date: "2025-12-14"
- bill_status: "paid"
- net_amount: 1000
- paid_amount: 1000

CustomerBill (Automated):
- bill_type: "Membership Subscription"
- bill_date: "2026-01-14"
- bill_status: "active"
- net_amount: 1000
- paid_amount: 0

CustomerMembership:
- membership_start_date: "2025-12-14"
- membership_end_date: "2026-01-14"
- status: "active"
```

### State 3: Payment Made on Automated Bill
```
CustomerBill (Automated):
- bill_type: "Membership Subscription"
- bill_date: "2026-01-14"
- bill_status: "paid"
- net_amount: 1000
- paid_amount: 1000

CustomerMembership:
- membership_start_date: "2026-01-14"
- membership_end_date: "2026-02-14"
- status: "active"
```

### State 4: Membership Expired (No Payment)
```
CustomerBill (Automated):
- bill_type: "Membership Subscription"
- bill_date: "2026-01-14"
- bill_status: "active"
- net_amount: 1000
- paid_amount: 0

CustomerMembership:
- membership_start_date: "2025-12-14"
- membership_end_date: "2026-01-14"
- status: "expired"
```

### State 5: Reactivation Fee Payment
```
CustomerBill (Reactivation Fee):
- bill_type: "Reactivation Fee"
- bill_date: "2026-01-20"
- bill_status: "paid"
- net_amount: 500
- paid_amount: 500

CustomerMembership (New - Free Month):
- membership_plan_id: [Same as last expired membership]
- membership_start_date: "2026-01-20"
- membership_end_date: "2026-02-20"
- status: "active"
```

### State 6: After Free Month (Automated Bill Created)
```
CustomerBill (Reactivation Fee):
- bill_type: "Reactivation Fee"
- bill_date: "2026-01-20"
- bill_status: "paid"
- net_amount: 500
- paid_amount: 500

CustomerBill (Automated - Next Period):
- bill_type: "Membership Subscription"
- bill_date: "2026-02-20"
- bill_status: "active"
- net_amount: 1000
- paid_amount: 0

CustomerMembership:
- membership_start_date: "2026-01-20"
- membership_end_date: "2026-02-20"
- status: "active"
```

---

## Validation Checklist

Use this checklist to verify the implementation:

- [ ] New member bill creates membership immediately
- [ ] Current period bill creates/updates membership immediately
- [ ] Future renewal bill does NOT create membership (waits for payment)
- [ ] Payment on new member bill doesn't change membership (already created)
- [ ] Payment on renewal bill extends membership
- [ ] Automated bill created 7 days before expiration
- [ ] Automated bill does NOT create membership (waits for payment)
- [ ] Payment on automated bill extends membership
- [ ] Early payment on automated bill extends membership before expiration
- [ ] Partial payment on renewal bill extends membership
- [ ] Expiration check skips if automated bill has payment
- [ ] Expiration check expires membership if automated bill has no payment
- [ ] Reactivation fee voids expired membership balances
- [ ] Payment on voided bill is blocked
- [ ] Payment on previous-cycle membership bill is allowed (outstanding balance collectible)
- [ ] Paying a previous-cycle bill does NOT extend the current membership
- [ ] Update on previous-cycle membership bill is blocked
- [ ] Previous-cycle bills are still visible in history
- [ ] Customer balance recalculated after bill creation
- [ ] Customer balance recalculated after payment

**Settings-driven behavior**

- [ ] `grantMembershipOn = full_payment` does not extend until the bill is fully paid
- [ ] `requireReactivationFee = false` hides/blocks the Reactivation Fee bill type
- [ ] Reactivation fee amount is read-only and enforced server-side
- [ ] `grantReactivationPromo`/length/unit control the reactivation period; promo only via reactivation flow
- [ ] `allowPartialPayments = false` rejects any payment below the bill's full remaining balance (backend + form)
- [ ] `allowManualMembershipBills = false` blocks manual membership bills AND active-member manual renewal; onboarding/reactivation still work
- [ ] `allowPayPreviousCycleBills` / `allowEditPreviousCycleBills` toggles honored
- [ ] Editing a bill can't set net below paid; `bill_status` recomputed after edit
- [ ] `requireMembershipForClassBooking` / `allowClassBookingDuringGrace` / `gracePeriodDays` gate only class booking, not check-in
- [ ] `billingAnchor = fixed_day`: a non-aligned member gets ONE prorated month+gap cycle (payable now), then aligns; no coverage gap
- [ ] Anniversary billing bills a normal full period (no proration, no gap)
- [ ] Plan change, `next_renewal`: sets pending plan, keeps old plan until the renewal bill is paid
- [ ] Plan change, `immediate_proration` upgrade: raises a prorated adjustment bill, switches plan
- [ ] Plan change, `immediate_proration` downgrade: `extend_days` extends end date, `forfeit` does not
- [ ] Re-selecting the same plan / new / expired members still use the full assignment path

---

## Notes

1. **Bill Date is Critical**: The `bill_date` field determines when membership starts/extends
2. **Payment Triggers Extension**: Membership is only extended when payment is made, not when bill is created
3. **Partial Payments Work**: Even partial payment extends membership
4. **Early Payments Allowed**: Customers can pay automated bills before expiration
5. **Automated System is Non-Intrusive**: It only creates bills, doesn't change membership until payment
6. **Consistent Behavior**: Manual and automated bills follow the same rules

---

## Questions to Verify

1. Does the system create membership immediately for new members? ✅ YES
2. Does the system wait for payment before extending membership for renewals? ✅ YES
3. Can customers pay automated bills early? ✅ YES
4. Does partial payment extend membership? ✅ YES
5. Does the system prevent duplicate automated bills? ✅ YES
6. Does expiration check consider payment status? ✅ YES
7. Does reactivation fee payment create membership with free month? ✅ YES
8. Does reactivation fee use same plan as last expired membership? ✅ YES
9. Is free month exactly 1 month regardless of plan type? ✅ YES

---

*Last Updated: 2026-07-11 — v3.0: renamed store to `account_system_settings`; added fixed-day prorated alignment (Scenario G), enforced partial-payment setting, bill edit guard (net ≥ paid + status recompute), and active-member manual-renewal gate*
*Version: 3.0*
