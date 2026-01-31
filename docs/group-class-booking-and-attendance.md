# Group Class Session Booking and Attendance Documentation

## Overview
This document describes the complete implementation of group class session booking and attendance management in the gym management system. The system allows clients to book group class sessions and enables staff/coaches to mark attendance with different statuses (attended, no-show, cancelled).

---

## Core Principles

1. **Group classes are part of membership** - No session deduction from PT packages
2. **Capacity-based booking** - Clients can only book if spots are available
3. **Capacity based on total bookings** - All booking statuses (except 'cancelled') count toward capacity
4. **No overbooking** - Once capacity is reached, no more bookings allowed
5. **Flexible attendance tracking** - Support for multiple attendance statuses
6. **Bulk operations** - Ability to mark all bookings as attended at once
7. **Schedule updates affect bookings** - When session details change, bookings are updated accordingly

---

## Database Structure

### Table: `tb_class_session_bookings`

**Purpose**: Stores client bookings for group class sessions

**Schema**:
```sql
CREATE TABLE tb_class_session_bookings (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    class_schedule_session_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    status ENUM('booked', 'attended', 'no_show', 'cancelled') DEFAULT 'booked',
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (class_schedule_session_id) 
        REFERENCES tb_class_schedule_sessions(id) 
        ON DELETE CASCADE,
    FOREIGN KEY (customer_id) 
        REFERENCES tb_customers(id) 
        ON DELETE CASCADE,
    
    UNIQUE KEY unique_booking (class_schedule_session_id, customer_id)
);
```

**Fields**:
- `id`: Primary key
- `account_id`: Account/organization identifier
- `class_schedule_session_id`: Reference to the class session
- `customer_id`: Reference to the client who booked
- `status`: Booking/attendance status
  - `booked`: Initial booking status
  - `attended`: Client attended the class
  - `no_show`: Client did not show up
  - `cancelled`: Booking was cancelled
- `notes`: Optional notes about the booking
- `created_by`: User who created the booking
- `updated_by`: User who last updated the booking

**Constraints**:
- Unique constraint on `(class_schedule_session_id, customer_id)` prevents duplicate bookings
- Cascade delete when session or customer is deleted

---

## Capacity Management

### Booking Capacity Check

**Logic**:
1. Get session capacity from `tb_class_schedule_sessions.capacity`
2. Count all bookings for the session EXCEPT those with status 'cancelled'
3. If `total_bookings >= capacity`: Session is full
4. Allow booking only if `total_bookings < capacity`

**Important**: All booking statuses count toward capacity EXCEPT 'cancelled'. This ensures:
- No overbooking (strict capacity enforcement)
- Cancelled bookings free up spots for new bookings
- Capacity is enforced at booking time, not attendance time

**Statuses that count toward capacity**:
- `booked` - Client has booked but not yet attended
- `attended` - Client attended the class
- `no_show` - Client didn't show up (still counts as booked spot)

**Statuses that do NOT count toward capacity**:
- `cancelled` - Booking was cancelled, spot is available

### Example Scenario

**Session Capacity**: 20 spots

**Bookings**:
- 18 clients booked (status: 'booked')
- 2 clients attended (status: 'attended')
- 0 clients cancelled (status: 'cancelled')

**Result**: Session is FULL (18 + 2 = 20). No more bookings allowed.

**After Cancellation**:
- 1 client cancels (status: 'cancelled')
- **Result**: Session has 1 available spot (20 - 19 = 1). New bookings allowed.

### Capacity Check in Booking Flow

**When booking a session**:
1. Count existing bookings (excluding 'cancelled')
2. If count >= capacity: Reject booking with "Session is full"
3. If count < capacity: Allow booking

**When cancelling a booking**:
1. Change status to 'cancelled'
2. Spot becomes available for new bookings
3. Other clients can now book this session

---

## Schedule Update Handling

### When Class Schedule Session is Updated

**Scenario**: Coach/Staff updates a class schedule session (time, date, or other details)

**Decision**: Bookings should be **updated to match** the new session details, NOT cancelled.

**Rationale**:
- Clients have already committed to the class
- Changing session details shouldn't invalidate existing bookings
- Better user experience - clients don't need to rebook

### Update Scenarios

#### Scenario 1: Time Change
**Before**:
- Session: Jan 20, 2026 at 9:00 AM
- Bookings: 5 clients booked

**Action**: Update session time to 10:00 AM

**Result**:
- Session time updated to 10:00 AM
- All 5 bookings remain active
- Clients are now booked for 10:00 AM session
- **Note**: Consider sending notification to booked clients about time change

#### Scenario 2: Date Change
**Before**:
- Session: Jan 20, 2026 at 9:00 AM
- Bookings: 5 clients booked

**Action**: Update session date to Jan 21, 2026

**Result**:
- Session date updated to Jan 21, 2026
- All 5 bookings remain active
- Clients are now booked for Jan 21, 2026 session
- **Note**: Consider sending notification to booked clients about date change

#### Scenario 3: Both Date and Time Change
**Before**:
- Session: Jan 20, 2026 at 9:00 AM
- Bookings: 5 clients booked

**Action**: Update session to Jan 21, 2026 at 10:00 AM

**Result**:
- Session date and time updated
- All 5 bookings remain active
- Clients are now booked for new date/time
- **Note**: Consider sending notification to booked clients

#### Scenario 4: Session Deletion
**Before**:
- Session: Jan 20, 2026 at 9:00 AM
- Bookings: 5 clients booked

**Action**: Delete the session

**Result**:
- Session is deleted (cascade delete)
- All bookings are automatically deleted (database cascade)
- **Note**: Consider sending cancellation notification to booked clients before deletion

### Implementation Details

**Key Points**:
- Bookings reference session by `class_schedule_session_id`
- When session is updated, bookings remain linked (foreign key relationship)
- Bookings automatically reflect new session details when fetched
- No manual update needed for bookings table

**Frontend Behavior**:
- When session is updated in calendar, bookings remain intact
- Attendance form still shows all bookings for the session
- Bookings display updated session date/time automatically

---

## Backend Implementation

### 1. Model: `ClassSessionBooking`

**Location**: `app/Models/Core/ClassSessionBooking.php`

**Relationships**:
- `classScheduleSession()`: BelongsTo `ClassScheduleSession`
- `customer()`: BelongsTo `Customer`
- `creator()`: BelongsTo `User` (created_by)
- `updater()`: BelongsTo `User` (updated_by)

---

### 2. Repository: `ClassSessionBookingRepository`

**Location**: `app/Repositories/Core/ClassSessionBookingRepository.php`

**Key Methods**:

#### `getBookingsBySessionId(int $sessionId, GenericData $genericData): Collection`
- Retrieves all bookings for a specific class session
- Includes customer, creator, and updater relationships
- Filtered by account_id

#### `createBooking(array $data): ClassSessionBooking`
- Creates a new booking record
- Validates unique constraint (handled by database)

#### `updateBookingStatus(int $id, string $status, GenericData $genericData): bool`
- Updates the status of a specific booking
- Updates `updated_by` field
- Returns boolean indicating success

#### `updateAllBookingsStatus(int $sessionId, string $status, GenericData $genericData): int`
- Updates all bookings for a session to the same status
- Used for "mark all as attended" functionality
- Returns count of updated records

#### `checkExistingBooking(int $sessionId, int $customerId, GenericData $genericData): ?ClassSessionBooking`
- Checks if a customer already has a booking for a session
- Prevents duplicate bookings

#### `getBookingsCount(int $sessionId, GenericData $genericData): int`
- Counts all bookings for a session (excluding 'cancelled')
- Used for capacity checking

---

### 3. Service: `ClassSessionBookingService`

**Location**: `app/Services/Core/ClassSessionBookingService.php`

**Dependencies**:
- `ClassSessionBookingRepository`
- `ClassScheduleSessionRepository`

**Key Methods**:

#### `bookSession(int $sessionId, int $customerId, ?string $notes, GenericData $genericData): void`
**Flow**:
1. Validate session exists
2. Check session capacity (count all bookings except 'cancelled')
3. Check for existing booking
4. Create booking with status 'booked'
5. All operations in database transaction

**Validation**:
- Session must exist
- Session must have available capacity (all bookings except 'cancelled' count)
- Customer must not have existing booking for same session

**Exceptions**:
- `Class session not found`
- `Class session is full` (when total bookings >= capacity)
- `Customer already booked this session`

#### `updateAttendanceStatus(int $bookingId, string $status, GenericData $genericData): void`
**Flow**:
1. Validate booking exists
2. Update booking status
3. Update `updated_by` field

**Valid Statuses**:
- `attended`
- `no_show`
- `cancelled`

#### `markAllAsAttended(int $sessionId, GenericData $genericData): void`
**Flow**:
1. Update all bookings for session to 'attended' status
2. Update `updated_by` for all records

**Use Case**: When coach/staff wants to mark all booked clients as attended at once

#### `getBookingsBySession(int $sessionId, GenericData $genericData): Collection`
**Flow**:
1. Retrieve all bookings for session
2. Include customer relationships
3. Return collection

---

### 4. Controller: `ClassSessionBookingController`

**Location**: `app/Http/Controllers/Core/ClassSessionBookingController.php`

**Endpoints**:

#### `POST /api/class-session-bookings`
**Purpose**: Book a class session for a client

**Request Body**:
```json
{
  "sessionId": 1,
  "customerId": 5,
  "notes": "Optional booking notes"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Class session booked successfully",
  "data": null
}
```

#### `GET /api/class-session-bookings/session/{sessionId}`
**Purpose**: Get all bookings for a specific session

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "sessionId": 1,
      "customer": {
        "id": 5,
        "firstName": "John",
        "lastName": "Doe",
        "email": "john@example.com"
      },
      "status": "booked",
      "notes": null,
      "createdAt": "2026-01-20T10:00:00.000000Z",
      "updatedAt": "2026-01-20T10:00:00.000000Z"
    }
  ]
}
```

#### `PUT /api/class-session-bookings/{bookingId}/attendance`
**Purpose**: Update attendance status for a specific booking

**Request Body**:
```json
{
  "status": "attended"
}
```

**Valid Status Values**:
- `attended`
- `no_show`
- `cancelled`

**Response**:
```json
{
  "success": true,
  "message": "Attendance status updated successfully",
  "data": null
}
```

#### `PUT /api/class-session-bookings/session/{sessionId}/mark-all-attended`
**Purpose**: Mark all bookings for a session as attended

**Response**:
```json
{
  "success": true,
  "message": "All bookings marked as attended",
  "data": null
}
```

---

### 5. Resource: `ClassSessionBookingResource`

**Location**: `app/Http/Resources/Core/ClassSessionBookingResource.php`

**Transforms**:
- Booking ID
- Session ID
- Customer information (when loaded)
- Status
- Notes
- Timestamps

---

### 6. Routes

**Location**: `routes/api.php`

```php
Route::prefix('class-session-bookings')->group(function () {
    Route::post('/', [ClassSessionBookingController::class, 'bookSession']);
    Route::get('/session/{sessionId}', [ClassSessionBookingController::class, 'getBookingsBySession']);
    Route::put('/{bookingId}/attendance', [ClassSessionBookingController::class, 'updateAttendanceStatus']);
    Route::put('/session/{sessionId}/mark-all-attended', [ClassSessionBookingController::class, 'markAllAsAttended']);
});
```

**All routes are protected by** `FirebaseAuthMiddleware`

---

## Frontend Implementation

### 1. Service: `classSessionBookingService`

**Location**: `src/services/classSessionBookingService.js`

**Methods**:

#### `bookSession(sessionId, customerId, notes = '')`
- Makes POST request to book a session
- Returns promise with response data

#### `getBookingsBySession(sessionId)`
- Makes GET request to retrieve bookings
- Returns promise with bookings array

#### `updateAttendanceStatus(bookingId, status)`
- Makes PUT request to update booking status
- Returns promise with response data

#### `markAllAsAttended(sessionId)`
- Makes PUT request to mark all bookings as attended
- Returns promise with response data

---

### 2. Hooks: `useClassSessionBookings`

**Location**: `src/hooks/useClassSessionBookings.js`

**Exported Hooks**:

#### `useBookClassSession()`
- Mutation hook for booking a session
- Invalidates bookings cache on success
- Returns mutation object with `mutateAsync` method

#### `useClassSessionBookings(sessionId, options = {})`
- Query hook for fetching bookings
- Automatically refetches when sessionId changes
- Supports `enabled` option

#### `useUpdateAttendanceStatus()`
- Mutation hook for updating individual booking status
- Invalidates bookings cache on success

#### `useMarkAllAsAttended()`
- Mutation hook for marking all bookings as attended
- Invalidates bookings cache on success

---

### 3. Component Updates

#### `GroupClassBookingForm.jsx`
**Location**: `src/pages/common/forms/GroupClassBookingForm.jsx`

**Updates**:
- Integrate `useBookClassSession` hook
- Call booking API on form submit
- Show success/error messages
- Close modal on successful booking
- Filter out full sessions from dropdown (if backend provides capacity data)

#### `ClassAttendanceForm.jsx`
**Location**: `src/pages/common/forms/ClassAttendanceForm.jsx`

**Updates**:
- Fetch bookings using `useClassSessionBookings`
- Display list of booked clients
- Show status badges for each booking
- Add action buttons for each booking:
  - Mark as Attended
  - Mark as No-Show
  - Mark as Cancelled
- Add "Mark All as Attended" button
- Remove PT package deduction warning (group classes don't deduct)

**UI Elements**:
- Session details card (date, time, capacity)
- Bookings list with:
  - Client name
  - Current status badge
  - Action buttons
- Bulk action: "Mark All as Attended" button
- Save/Cancel buttons

---

## User Flows

### Flow 1: Client Books Group Class Session

**Actor**: Staff/Admin

**Steps**:
1. Navigate to Calendar view
2. Click "Book Group Class" button
3. Modal opens with `GroupClassBookingForm`
4. Search and select client
5. Search and select class session
   - **Capacity Check**: System filters out full sessions from dropdown
   - Only sessions with available spots are shown
6. (Optional) Add notes
7. Click "Book Class" button
8. System validates:
   - Session exists
   - Session has capacity (counts all bookings except 'cancelled')
   - Client not already booked
9. Booking created with status 'booked'
10. Success message shown
11. Modal closes
12. Calendar refreshes

**Database State**:
```
tb_class_session_bookings:
- id: 1
- class_schedule_session_id: 5
- customer_id: 10
- status: 'booked'
- notes: null
- created_by: 2 (staff user)
```

---

### Flow 2: View and Mark Attendance

**Actor**: Coach/Staff/Admin

**Steps**:
1. Navigate to Calendar view
2. Click on a group class session in calendar
3. `ClassAttendanceForm` modal opens
4. System fetches all bookings for session
5. Modal displays:
   - Session details (date, time, capacity)
   - List of booked clients with current status
6. For each client, user can:
   - Mark as Attended
   - Mark as No-Show
   - Mark as Cancelled
7. User can click "Mark All as Attended" to update all at once
8. Changes saved immediately
9. Success message shown
10. Modal updates to reflect new statuses

**Database State After Marking All as Attended**:
```
tb_class_session_bookings:
- id: 1, status: 'attended', updated_by: 3
- id: 2, status: 'attended', updated_by: 3
- id: 3, status: 'attended', updated_by: 3
```

---

### Flow 3: Individual Status Update

**Actor**: Coach/Staff/Admin

**Steps**:
1. Open attendance form for session
2. See list of booked clients
3. Click "Mark as No-Show" for specific client
4. System updates that booking's status
5. UI updates to show new status badge
6. Other bookings remain unchanged

**Database State**:
```
Before:
- id: 1, status: 'booked'
- id: 2, status: 'booked'

After (marking booking 1 as no-show):
- id: 1, status: 'no_show', updated_by: 3
- id: 2, status: 'booked' (unchanged)
```

---

## Status Flow Diagram

```
Initial State: booked
    │
    ├─→ attended (Client showed up)
    │
    ├─→ no_show (Client didn't show up)
    │
    └─→ cancelled (Booking cancelled)
```

**Note**: Status can be changed multiple times (e.g., booked → attended → cancelled if refund needed)

---

## Testing Scenarios

### Scenario 1: Successful Booking
1. Create test session with capacity 10
2. Book 5 clients
3. Verify all bookings created with status 'booked'
4. Verify capacity check allows more bookings

### Scenario 2: Capacity Limit
1. Create test session with capacity 5
2. Book 5 clients (status: 'booked')
3. Try to book 6th client
4. Verify booking is rejected with "session is full" error
5. Cancel 1 booking (status: 'cancelled')
6. Try to book again
7. Verify booking is now allowed (1 spot available)

### Scenario 3: Duplicate Booking Prevention
1. Book client A for session 1
2. Try to book client A for session 1 again
3. Verify booking is rejected with "already booked" error

### Scenario 4: Status Updates
1. Create booking with status 'booked'
2. Update to 'attended'
3. Verify status changed
4. Update to 'no_show'
5. Verify status changed again

### Scenario 5: Bulk Mark All Attended
1. Create 10 bookings for a session
2. Click "Mark All as Attended"
3. Verify all 10 bookings updated to 'attended'
4. Verify `updated_by` field set correctly

### Scenario 6: Schedule Update with Bookings
1. Create session: Jan 20, 2026 at 9:00 AM, capacity 10
2. Book 5 clients
3. Update session time to 10:00 AM
4. Verify all 5 bookings still exist
5. Verify bookings are linked to updated session
6. Fetch bookings and verify they show new time (10:00 AM)

### Scenario 7: Session Deletion with Bookings
1. Create session with 5 bookings
2. Delete the session
3. Verify session is deleted
4. Verify all 5 bookings are cascade deleted
5. Verify no orphaned booking records

---

## Validation Checklist

Use this checklist to verify the implementation:

### Backend
- [ ] Migration creates table with correct schema
- [ ] Model has correct relationships
- [ ] Repository methods work correctly
- [ ] Service validates capacity before booking (counts all except 'cancelled')
- [ ] Service prevents duplicate bookings
- [ ] Service updates status correctly
- [ ] Service marks all as attended correctly
- [ ] Controller returns correct responses
- [ ] Resource transforms data correctly
- [ ] Routes are properly protected
- [ ] Capacity check includes all statuses except 'cancelled'

### Frontend
- [ ] Service methods make correct API calls
- [ ] Hooks handle loading/error states
- [ ] Booking form validates inputs
- [ ] Booking form calls API on submit
- [ ] Booking form filters out full sessions (if backend provides data)
- [ ] Attendance form fetches bookings
- [ ] Attendance form displays client list
- [ ] Status badges show correct colors
- [ ] Individual status updates work
- [ ] Bulk "mark all" works
- [ ] Error messages display correctly
- [ ] Success messages display correctly
- [ ] Calendar refreshes after booking

### Integration
- [ ] Calendar shows booking counts
- [ ] Clicking session opens attendance form
- [ ] Attendance form shows correct session details
- [ ] Bookings list updates after status changes
- [ ] Capacity check prevents overbooking
- [ ] Duplicate booking prevented
- [ ] Schedule updates don't break bookings

---

## Future Enhancements

### Potential Features

1. **Booking Cancellation by Client**: Allow clients to cancel their own bookings through a client portal
   - Self-service cancellation
   - Automatic spot availability update
   - Cancellation notifications

2. **Waitlist System**: Queue clients when session is full
   - Automatic promotion when spot opens
   - Waitlist notifications
   - Priority management

3. **Booking Reminders**: Send notifications before class
   - Email/SMS reminders 24 hours before
   - Reminder customization
   - Reduce no-show rates

4. **Attendance History**: View client's attendance history
   - Client attendance dashboard
   - Attendance statistics
   - Pattern analysis (frequent classes, preferred times)

5. **Class Analytics**: Track attendance rates, popular classes
   - Class popularity metrics
   - Attendance rate tracking
   - Capacity utilization reports
   - Revenue per class analysis

6. **Recurring Bookings**: Allow clients to book multiple sessions at once
   - "Book all sessions this month" feature
   - Recurring booking management
   - Bulk cancellation

7. **Booking Limits**: Limit number of bookings per client per period
   - Daily/weekly/monthly booking limits
   - Prevent booking hoarding
   - Fair access distribution

8. **Session Notifications**: Notify clients about schedule changes
   - Automatic notifications when session time/date changes
   - Cancellation notifications
   - Reminder notifications

9. **Advanced Capacity Management**: 
   - Reserve spots for specific clients (VIP)
   - Override capacity for special cases
   - Dynamic capacity adjustment

10. **Integration with Membership**: 
    - Check active membership before booking
    - Different booking rules for different membership tiers
    - Membership-based booking limits

11. **Attendance Tracking Enhancements**:
    - QR code check-in
    - Automatic attendance via app
    - Late arrival tracking
    - Early departure tracking

12. **Reporting and Analytics**:
    - Class attendance reports
    - Client booking patterns
    - Revenue reports per class
    - Coach performance metrics

13. **Mobile App Features**:
    - Mobile booking interface
    - Push notifications
    - In-app check-in
    - Booking calendar view

14. **Payment Integration**:
    - Pay-per-class option
    - Class package purchases
    - Refund handling for cancellations

15. **Social Features**:
    - See who else is attending
    - Friend booking coordination
    - Class reviews and ratings

---

## Notes

1. **No PT Package Deduction**: Group classes are part of membership, not PT packages
2. **Capacity Based on Total Bookings**: All statuses except 'cancelled' count toward capacity
3. **Strict Capacity Enforcement**: No overbooking allowed
4. **Flexible Status Changes**: Status can be changed multiple times
5. **Cascade Deletes**: Bookings are deleted when session or customer is deleted
6. **Audit Trail**: `created_by` and `updated_by` track who made changes
7. **Unique Constraint**: Prevents duplicate bookings at database level
8. **Schedule Updates Preserve Bookings**: When session details change, bookings remain linked

---

## Questions to Verify

1. Can clients book multiple sessions? ✅ YES (if capacity allows)
2. Can clients book the same session twice? ❌ NO (unique constraint)
3. Does booking deduct from PT package? ❌ NO (group classes are membership-based)
4. Can status be changed multiple times? ✅ YES
5. Does 'booked' status count toward capacity? ✅ YES (all statuses except 'cancelled')
6. Can clients book when session is at capacity? ❌ NO (strict capacity enforcement)
7. What happens when session is updated? ✅ Bookings remain active and linked to updated session
8. What happens when session is deleted? ✅ All bookings are cascade deleted
9. Are bookings deleted when customer is deleted? ✅ YES (cascade)
10. Can staff mark all as attended at once? ✅ YES
11. Is there an audit trail? ✅ YES (created_by, updated_by)
12. Can bookings be cancelled? ✅ YES (status: 'cancelled', frees up spot)
13. Do cancelled bookings free up capacity? ✅ YES (cancelled bookings don't count toward capacity)

---

*Last Updated: [Date]*
*Version: 1.0*
