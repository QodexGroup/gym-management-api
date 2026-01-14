# Billing and Membership Flow Documentation

## Overview
This document describes the complete flow of billing and membership management in the gym management system. The system follows a **unified payment-based extension** approach where membership is extended only when payment is made, not when bills are created.

---

## Core Principle
**Membership is extended when payment is made, not when bills are created.**

---

## Bill Types

### 1. Custom Amount
- **Purpose**: One-time service charges
- **Membership Impact**: None
- **Flow**: Create bill → Payment → No membership change

### 2. Reactivation Fee
- **Purpose**: Reactivate expired membership with 1 free month
- **Membership Impact**: 
  1. Voids expired membership balances
  2. Creates new membership with 1 free month (same plan as last expired membership)
- **Flow**: 
  1. Create bill → Voids all expired membership bills (sets net_amount = paid_amount)
  2. Payment → Creates new membership with:
     - Same plan as last expired membership
     - Start date = payment date
     - End date = payment date + 1 month (free month)
     - Status = active
  3. After free month → Automated system creates bill for next period

### 3. Membership Subscription
- **Purpose**: Membership renewal/activation
- **Membership Impact**: Creates or extends membership
- **Flow**: Varies based on scenario (see below)

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
     - bill_date = membership_end_date (next period start)
     - gross_amount = membership_plan.price
     - status = ACTIVE
   - **Membership**: ❌ No change (waiting for payment)

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
   - Payment made (even partial)
   - **Check**: `billDate >= membership_end_date` (renewal bill)
   - **Action**: ✅ Extend membership immediately
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
  2. Void all expired membership bills (net_amount = paid_amount)
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
│       ├─ Check if payment made
│       ├─ Find membership
│       │   ├─ No membership? → Create membership (new member)
│       │   └─ Has membership? → Check if renewal bill
│       │       └─ bill_date >= membership_end_date? → Extend membership
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
- [ ] Customer balance recalculated after bill creation
- [ ] Customer balance recalculated after payment

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

*Last Updated: Based on current implementation*
*Version: 1.0*
