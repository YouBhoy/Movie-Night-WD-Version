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
Your existing `employees` table already has the correct structure. Run the SQL commands in `employees_table.sql` to update department assignments:

```sql
-- Update existing employee data to include proper departments for shift mapping
UPDATE `employees` SET `department` = 'Engineering' WHERE `emp_number` = 'WD001';
UPDATE `employees` SET `department` = 'Marketing' WHERE `emp_number` = 'WD002';
UPDATE `employees` SET `department` = 'IT' WHERE `emp_number` = 'WD003';
UPDATE `employees` SET `department` = 'HR' WHERE `emp_number` = 'WD004';
UPDATE `employees` SET `department` = 'Finance' WHERE `emp_number` = 'WD005';
UPDATE `employees` SET `department` = 'Testing' WHERE `emp_number` = 'WD007';
```

### 2. Department to Shift Mapping
The system automatically maps departments to shifts:

| Department | Assigned Shift | Hall |
|------------|----------------|------|
| Engineering, IT, Operations | Normal Shift | Hall 1 |
| Marketing, Sales, HR | Crew A (Off/Rest Day) | Hall 2 |
| Finance, Accounting, Legal | Crew B (Off/Rest Day) | Hall 2 |
| Testing, Quality, Support | Crew C (Day Shift) | Hall 1 |

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
3. Prevents duplicate registrations
4. Enforces hall assignment based on shift

## Testing

### Test Employee Numbers
Use these sample employee numbers for testing:
- `WD001` → John Smith (Engineering) → Normal Shift → Hall 1
- `WD002` → Sarah Johnson (Marketing) → Crew A → Hall 2
- `WD003` → Mike Chen (IT) → Normal Shift → Hall 1
- `WD004` → Lisa Rodriguez (HR) → Crew A → Hall 2
- `WD005` → David Kim (Finance) → Crew B → Hall 2
- `WD007` → Test User (Testing) → Crew C → Hall 1

### Admin Login Credentials
Use these credentials to access the admin panel:
- **Username**: `admin` | **Password**: `admin123`
- **Username**: `manager` | **Password**: `manager456`

### Test Scenarios
1. **Valid employee**: Should auto-fill and allow registration
2. **Invalid employee**: Should show "Employee not found"
3. **Already registered**: Should show "Already registered"
4. **Wrong name**: Should show "Name doesn't match"

## Security Features

- ✅ CSRF protection maintained
- ✅ Input sanitization
- ✅ SQL injection prevention
- ✅ Rate limiting
- ✅ Server-side validation
- ✅ Employee verification

## Hall Assignment Logic

| Department | Assigned Shift | Assigned Hall | Max Attendees |
|------------|----------------|---------------|---------------|
| Engineering, IT, Operations | Normal Shift | Cinema Hall 1 | 3             |
| Testing, Quality, Support | Crew C (Day Shift) | Cinema Hall 1 | 3             |
| Marketing, Sales, HR | Crew A (Off/Rest Day) | Cinema Hall 2 | 3             |
| Finance, Accounting, Legal | Crew B (Off/Rest Day) | Cinema Hall 2 | 3             |

## Troubleshooting

### Common Issues

1. **"Employee not found"**
   - Check if employee exists in employees table
   - Verify employee number format (case-insensitive)
   - Ensure employee has `is_active = 1`

2. **"This employee number is already registered"**
   - This means the employee already has an active registration in the registrations table
   - Run `clear_existing_registrations.sql` to clear test data
   - Or check the registrations table for existing entries

3. **"Name doesn't match"**
   - Ensure name in employees table matches exactly
   - Check for extra spaces or typos

4. **Shift not auto-filling**
   - Verify department name in employees table
   - Check the `mapDepartmentToShift()` function mapping
   - Check JavaScript console for errors

5. **Hall not assigning correctly**
   - Verify shift names match the expected format
   - Check `getHallIdForShift()` function logic

### Database Queries for Debugging

```sql
-- Check if employee exists
SELECT * FROM employees WHERE emp_number = 'EMP001';

-- Check shifts table
SELECT * FROM shifts WHERE is_active = 1;

-- Check registrations
SELECT * FROM registrations WHERE emp_number = 'EMP001';
```

## Future Enhancements

- Employee photo upload
- Department-based filtering
- Bulk employee import
- Employee search functionality
- Registration history per employee 