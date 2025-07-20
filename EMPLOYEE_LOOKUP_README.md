# Employee Lookup System for Movie Night Registration

## Overview
The movie night registration system has been updated to implement employee-only registration with automatic field population based on employee number lookup.

## Key Features Implemented

### ✅ Employee Lookup
- Users enter their employee number
- System automatically fetches name and shift from employees table
- Real-time validation with immediate feedback

### ✅ Auto-fill Fields
- Name field is auto-filled and read-only
- Shift field is auto-filled and read-only
- Fields are locked to prevent manual editing

### ✅ Validation
- Only employees in the employees table can register
- Prevents duplicate registrations
- Validates employee name matches records

### ✅ Hall & Shift Logic
- **Cinema Hall 1**: Normal Shift, Crew C
- **Cinema Hall 2**: Crew A, Crew B
- Logic enforced in both frontend and backend

## Database Setup

### 1. Clear Existing Registrations (Optional)
If you have existing registrations in your database, you may want to clear them to test the new system:

```sql
-- Run the commands in clear_existing_registrations.sql
DELETE FROM registrations WHERE status = 'active';
UPDATE seats SET status = 'available', updated_at = NOW() WHERE status = 'occupied';
```

### 2. Update Existing Employees Table
Your existing `employees` table already has the correct structure. Run the SQL commands in `employees_table.sql` to update shift assignments:

```sql
-- Update existing employee data to include proper shifts for shift mapping
UPDATE `employees` SET `shift_id` = 1 WHERE `emp_number` = 'WD001';
UPDATE `employees` SET `shift_id` = 2 WHERE `emp_number` = 'WD002';
-- etc.
```

### 2. Shift Assignment
The system now assigns employees to shifts directly using the `shift_id` field.

### 3. Existing Shifts Table
Your existing shifts table already has the correct shift names:
- Normal Shift (Hall 1)
- Crew C (Day Shift) (Hall 1)
- Crew A (Off/Rest Day) (Hall 2)
- Crew B (Off/Rest Day) (Hall 2)

## Files Modified

### 1. `api.php`
- **Updated `handleCheckEmployee()`**: Now queries employees table
- **Updated `handleRegistration()`**: Added employee validation
- **New validation**: Checks employee exists and name matches

### 2. `index.php`
- **Updated registration form**: Employee number input with auto-lookup
- **Modified form fields**: Name and shift are now read-only
- **Added JavaScript functions**:
  - `lookupEmployee()`: Handles employee lookup
  - `getShiftIdByName()`: Maps shift names to IDs
  - `showSuccess()`: Success notifications
- **Updated UI text**: Changed from "open registration" to "employee registration"

### 3. `styles.css`
- **Added `.employee-notice`**: Styling for employee registration notice

## How It Works

### 1. Employee Entry
1. User enters employee number
2. System triggers lookup on blur (when user finishes typing)
3. API validates employee exists in database

### 2. Auto-fill Process
1. If employee found, name and shift are auto-filled
2. Fields become read-only
3. Shift selection triggers hall assignment
4. Seat map loads for assigned hall

### 3. Validation
1. Backend checks employee exists in employees table
2. Validates provided name matches employee record

## Future Enhancements

- Employee photo upload
- Bulk employee import
- Employee search functionality
- Registration history per employee 