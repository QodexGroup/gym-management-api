# Walk-in and Check-in Implementation

## Overview
This document describes how walk-in sessions and check-in/check-out are implemented in the backend API.
The flow supports two paths:
1. Manual walk-in creation and customer check-in.
2. QR kiosk check-in and check-out by customer UUID.

The implementation spans routes, controller, service, repository, models, requests, and constants.

---

## Core Concepts
- A walk-in is a daily session per account stored in `tb_walkin` with a `date` field.
- A walk-in customer record is stored in `tb_customer_walkin` with `check_in_time`, `check_out_time`, and `status`.
- A customer can only be checked in once per day per account.
- Check-out only transitions from `INSIDE` to `OUTSIDE`.

---

## Data Model

### Walkin (`tb_walkin`)
- `account_id`
- `date` (cast to `date`)
- `created_by`, `updated_by`
- Soft deletes enabled

Relationships:
- `walkin -> walkinCustomers` (hasMany)

### WalkinCustomer (`tb_customer_walkin`)
- `walkin_id`
- `customer_id`
- `check_in_time` (cast to `datetime`)
- `check_out_time` (cast to `datetime`)
- `status` (`INSIDE`, `OUTSIDE`, `CANCELLED`)
- Soft deletes enabled

Relationships:
- `walkinCustomer -> walkin` (belongsTo)
- `walkinCustomer -> customer` (belongsTo)

---

## Status Constants
Defined in `App\Constants\WalkinCustomerConstant`:
- `INSIDE`
- `OUTSIDE`
- `CANCELLED`

---

## API Endpoints
Base prefix: `/api/walkins`

### Walk-in Session
- `POST /` create walk-in for today
- `GET /` get today walk-in for account

### Walk-in Customers
- `GET /{walkinId}/customers` list customers for a walk-in (paginated)
- `POST /{walkinId}/customers` create walk-in customer (check-in)
- `PUT /customers/{id}/check-out` check out a walk-in customer
- `PUT /customers/{id}/cancel` cancel a walk-in customer

### QR Kiosk Convenience Endpoints
- `POST /qr-checkin` check in by customer UUID
- `PUT /qr-checkout` check out by customer UUID

---

## Request Validation

### WalkinRequest
Extends `GenericRequest` and accepts:
- `customerId` (optional integer)

Used by:
- `POST /walkins` (create walk-in)
- `POST /walkins/{walkinId}/customers` (manual check-in)

### QrCheckinRequest
Extends `GenericRequest` and requires:
- `uuid` (required, UUID string)

Used by:
- `POST /walkins/qr-checkin`
- `PUT /walkins/qr-checkout`

---

## Implementation Flow

### 1. Manual Walk-in Creation
Controller: `WalkinController::createWalkin`
Repository: `WalkinRepository::createWalkin`

Flow:
1. Build `GenericData` from request.
2. Set `account_id`, `created_by`, `updated_by`, and `date = today`.
3. Create walk-in record in `tb_walkin`.

### 2. Manual Check-in (Walkin Customer)
Controller: `WalkinController::createWalkinCustomer`
Service: `WalkinService::createWalkinCustomer`
Repository: `WalkinRepository::createWalkinCustomer`

Flow:
1. Ensure today’s walk-in exists.
2. Check for existing walk-in customer for this walk-in and customer.
3. If exists, throw error: "Walkin customer already exists".
4. Create walk-in customer with:
   - `check_in_time = now`
   - `status = INSIDE`

### 3. Manual Check-out
Controller: `WalkinController::checkOutWalkinCustomer`
Repository: `WalkinRepository::checkOutWalkinCustomer`

Flow:
1. Find walk-in customer by ID.
2. Set `check_out_time = now`.
3. Set `status = OUTSIDE`.

### 4. Manual Cancel
Controller: `WalkinController::cancelWalkinCustomer`
Repository: `WalkinRepository::cancelWalkinCustomer`

Flow:
1. Find walk-in customer by ID.
2. Set `status = CANCELLED`.

### 5. QR Check-in (Kiosk)
Controller: `WalkinController::qrCheckIn`
Service: `WalkinService::qrCheckIn`
Repository: `WalkinRepository`

Flow:
1. Find customer by UUID and account.
2. Get today’s walk-in, or create it if missing.
3. Check if customer already checked in today.
4. If already checked in, throw error: "Customer is already checked in".
5. Create walk-in customer with `check_in_time = now` and `status = INSIDE`.

### 6. QR Check-out (Kiosk)
Controller: `WalkinController::qrCheckOut`
Service: `WalkinService::qrCheckOut`
Repository: `WalkinRepository`

Flow:
1. Find customer by UUID and account.
2. Get today’s walk-in. If missing, return error.
3. Find walk-in customer for today.
4. If missing, return error: "Customer is not checked in".
5. If status is not `INSIDE`, return error: "Customer is already checked out or cancelled".
6. Set `check_out_time = now` and `status = OUTSIDE`.

---

## Error Handling
- Service methods log errors via `Log::error` and rethrow.
- Controller methods return API errors using `ApiResponse::error`.

Common error messages:
- "Walkin not found"
- "Walkin customer already exists"
- "Customer is already checked in"
- "Customer not found"
- "No walk-in session found for today"
- "Customer is not checked in"
- "Customer is already checked out or cancelled"

---

## Pagination and Filters
`getPaginatedWalkinCustomers` and `getPaginatedWalkinByCustomer` use `GenericData` to apply:
- relations
- filters
- sorts
- pagination

---

## Implementation References
- `app/Http/Controllers/Common/WalkinController.php`
- `app/Services/Common/WalkinService.php`
- `app/Repositories/Common/WalkinRepository.php`
- `app/Models/Common/Walkin.php`
- `app/Models/Common/WalkinCustomer.php`
- `app/Constants/WalkinCustomerConstant.php`
- `app/Http/Requests/Common/WalkinRequest.php`
- `app/Http/Requests/Common/QrCheckinRequest.php`
- `routes/api.php`

---

*Last Updated: Based on current implementation*
*Version: 1.0*
