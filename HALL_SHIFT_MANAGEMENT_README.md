# 🎬 Cinema Hall & Shift Management System

This document describes the complete implementation of the Cinema Hall and Shift management system for the Movie Night Registration System.

## 📋 Overview

The system provides full CRUD (Create, Read, Update, Delete) operations for managing cinema halls and shifts through a modern, responsive web interface. All operations are performed using AJAX calls to a dedicated API endpoint, providing real-time updates without page refreshes.

## 🏗️ Architecture

### Backend (PHP)
- **File**: `admin-hall-shift-api.php`
- **Authentication**: Admin session validation
- **Security**: CSRF token validation for all write operations
- **Database**: Direct PDO operations with prepared statements
- **Response Format**: JSON with consistent structure

### Frontend (JavaScript)
- **Location**: `seat-layout-editor.php` (embedded in modals)
- **Framework**: Vanilla JavaScript with Bootstrap 5
- **UI**: Responsive modals with dark theme
- **Notifications**: Toast messages for user feedback

## 🎯 Features Implemented

### Cinema Hall Management
- ✅ **List Active Halls** - Display all active cinema halls in a table
- ✅ **Add New Hall** - Create new cinema halls with validation
- ✅ **Edit Hall** - Update hall name, attendee limits, and seat counts
- ✅ **Deactivate Hall** - Soft delete with registration validation
- ✅ **Real-time Updates** - Dynamic refresh of dropdowns and lists

### Shift Management
- ✅ **List Shifts by Hall** - Display shifts for selected cinema hall
- ✅ **Add New Shift** - Create shifts with time slots and seat configuration
- ✅ **Edit Shift** - Update shift details including times and seat counts
- ✅ **Deactivate Shift** - Soft delete with registration validation
- ✅ **Hall-based Organization** - Shifts are tied to specific halls

### User Experience
- ✅ **Responsive Design** - Works on desktop and mobile devices
- ✅ **Toast Notifications** - Success/error feedback for all operations
- ✅ **Confirmation Dialogs** - Safety prompts for destructive actions
- ✅ **Form Validation** - Client and server-side validation
- ✅ **Loading States** - Visual feedback during API calls

## 🔧 API Endpoints

### Cinema Hall Endpoints

#### GET `/admin-hall-shift-api.php?action=get_active_halls`
**Description**: Retrieve all active cinema halls
**Response**:
```json
{
  "success": true,
  "halls": [
    {
      "id": 1,
      "hall_name": "Cinema Hall 1",
      "max_attendees_per_booking": 3,
      "total_seats": 72,
      "is_active": 1,
      "created_at": "2025-07-09 05:26:18",
      "updated_at": "2025-07-09 05:26:18"
    }
  ]
}
```

#### POST `/admin-hall-shift-api.php`
**Action**: `add_hall`
**Parameters**:
- `hall_name` (string, required)
- `max_attendees_per_booking` (integer, required)
- `total_seats` (integer, required)
- `csrf_token` (string, required)

**Response**:
```json
{
  "success": true,
  "message": "Hall added successfully",
  "hall_id": 3
}
```

#### POST `/admin-hall-shift-api.php`
**Action**: `update_hall`
**Parameters**:
- `hall_id` (integer, required)
- `hall_name` (string, required)
- `max_attendees_per_booking` (integer, required)
- `total_seats` (integer, required)
- `csrf_token` (string, required)

#### POST `/admin-hall-shift-api.php`
**Action**: `deactivate_hall`
**Parameters**:
- `hall_id` (integer, required)
- `csrf_token` (string, required)

### Shift Endpoints

#### GET `/admin-hall-shift-api.php?action=get_shifts_by_hall&hall_id={id}`
**Description**: Retrieve all active shifts for a specific hall
**Response**:
```json
{
  "success": true,
  "shifts": [
    {
      "id": 1,
      "hall_id": 1,
      "shift_name": "Normal Shift",
      "shift_code": "NORMAL_SHIFT",
      "seat_prefix": "",
      "seat_count": 72,
      "start_time": "19:00:00",
      "end_time": "22:00:00",
      "is_active": 1,
      "created_at": "2025-07-09 05:26:18"
    }
  ]
}
```

#### POST `/admin-hall-shift-api.php`
**Action**: `add_shift`
**Parameters**:
- `hall_id` (integer, required)
- `shift_name` (string, required)
- `shift_code` (string, required)
- `seat_prefix` (string, optional)
- `seat_count` (integer, required)
- `start_time` (time, required)
- `end_time` (time, required)
- `csrf_token` (string, required)

#### POST `/admin-hall-shift-api.php`
**Action**: `update_shift`
**Parameters**: Same as add_shift + `shift_id` (integer, required)

#### POST `/admin-hall-shift-api.php`
**Action**: `deactivate_shift`
**Parameters**:
- `shift_id` (integer, required)
- `csrf_token` (string, required)

## 🎨 Frontend Implementation

### Modal Structure

#### Cinema Hall Modal
```html
<div class="modal fade" id="hallSettingsModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <!-- Hall List Section -->
      <div id="hallListSection">
        <!-- Table with active halls -->
      </div>
      
      <!-- Add/Edit Form Section -->
      <div id="hallFormSection" style="display: none;">
        <!-- Form for adding/editing halls -->
      </div>
    </div>
  </div>
</div>
```

#### Shift Modal
```html
<div class="modal fade" id="shiftSettingsModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <!-- Hall Selection -->
      <select id="shiftHallSelector">
        <!-- Hall options -->
      </select>
      
      <!-- Shift List Section -->
      <div id="shiftListSection">
        <!-- Table with shifts for selected hall -->
      </div>
      
      <!-- Add/Edit Form Section -->
      <div id="shiftFormSection" style="display: none;">
        <!-- Form for adding/editing shifts -->
      </div>
    </div>
  </div>
</div>
```

### JavaScript Functions

#### Cinema Hall Functions
- `loadCinemaHalls()` - Fetch and display active halls
- `displayHalls(halls)` - Render halls in table format
- `showAddHallForm()` - Switch to add hall form
- `showHallList()` - Switch back to hall list
- `editHall(hallId)` - Load hall data into edit form
- `deactivateHall(hallId)` - Soft delete hall with confirmation
- `submitHallForm()` - Handle form submission (add/update)

#### Shift Functions
- `loadShiftsByHall(hallId)` - Fetch shifts for specific hall
- `displayShifts(shifts)` - Render shifts in table format
- `showAddShiftForm()` - Switch to add shift form
- `showShiftList()` - Switch back to shift list
- `editShift(shiftId)` - Load shift data into edit form
- `deactivateShift(shiftId)` - Soft delete shift with confirmation
- `submitShiftForm()` - Handle form submission (add/update)

#### Utility Functions
- `refreshDropdowns()` - Update hall and shift dropdowns
- `showToast(message, type)` - Display notification messages
- `createToastContainer()` - Initialize toast notification system

## 🔒 Security Features

### Authentication
- Admin session validation on all API endpoints
- Redirect to login if not authenticated

### CSRF Protection
- CSRF token validation for all write operations
- Token generation and validation using existing system

### Input Validation
- Server-side validation for all inputs
- SQL injection prevention with prepared statements
- XSS prevention with input sanitization

### Business Logic Validation
- Prevent deactivation of halls/shifts with active registrations
- Duplicate name prevention
- Data integrity checks

## 🎨 Styling

### Dark Theme
- Consistent with existing application design
- Gold accent colors (#ffd700)
- Dark backgrounds with transparency
- Hover effects and transitions

### Responsive Design
- Bootstrap 5 grid system
- Mobile-friendly modal layouts
- Flexible table designs
- Touch-friendly buttons

### Visual Feedback
- Loading states during API calls
- Toast notifications for all actions
- Confirmation dialogs for destructive actions
- Form validation indicators

## 🧪 Testing

A comprehensive test file (`test-hall-shift-management.html`) is provided to verify all functionality:

### Test Features
- Individual API endpoint testing
- Full workflow testing
- Error handling verification
- Response format validation

### Test Scenarios
1. **Get Active Halls** - Verify hall listing
2. **Add Test Hall** - Create new hall
3. **Update Hall** - Modify existing hall
4. **Deactivate Hall** - Soft delete hall
5. **Get Shifts by Hall** - List shifts for hall
6. **Add Test Shift** - Create new shift
7. **Update Shift** - Modify existing shift
8. **Deactivate Shift** - Soft delete shift
9. **Full Workflow** - Complete CRUD cycle

## 🚀 Usage Instructions

### For Administrators

1. **Access Management**: Navigate to the Seat Layout Editor
2. **Manage Halls**: Click the hall settings button (⚙️) next to the hall selector
3. **Manage Shifts**: Click the shift settings button (⚙️) next to the shift selector

### Hall Management
1. View all active halls in the table
2. Click "Add New Hall" to create a new hall
3. Click the edit button (✏️) to modify an existing hall
4. Click the delete button (🗑️) to deactivate a hall

### Shift Management
1. Select a hall from the dropdown
2. View all shifts for that hall
3. Click "Add New Shift" to create a new shift
4. Click the edit button (✏️) to modify an existing shift
5. Click the delete button (🗑️) to deactivate a shift

## 🔧 Configuration

### Database Requirements
The system uses existing database tables:
- `cinema_halls` - Hall information
- `shifts` - Shift information
- `registrations` - For validation checks

### File Dependencies
- `config.php` - Database connection and utility functions
- `admin-hall-shift-api.php` - API endpoints
- `seat-layout-editor.php` - Frontend interface

## 🐛 Troubleshooting

### Common Issues

1. **"Unauthorized" Error**
   - Ensure admin is logged in
   - Check session validity

2. **"Security validation failed"**
   - Verify CSRF token is being sent
   - Check token generation in config.php

3. **"Cannot deactivate hall/shift with active registrations"**
   - This is expected behavior for data integrity
   - Cancel or complete registrations first

4. **Modal not opening**
   - Check Bootstrap 5 is loaded
   - Verify modal IDs match JavaScript references

### Debug Mode
Enable error reporting in PHP to see detailed error messages:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 📈 Future Enhancements

### Potential Improvements
- **Bulk Operations** - Add/delete multiple halls/shifts
- **Import/Export** - CSV import/export functionality
- **Audit Trail** - Detailed activity logging
- **Advanced Validation** - Time conflict detection for shifts
- **Drag & Drop** - Visual seat layout management
- **Real-time Updates** - WebSocket integration for live updates

### Performance Optimizations
- **Caching** - Redis/Memcached for frequently accessed data
- **Pagination** - For large numbers of halls/shifts
- **Lazy Loading** - Load data on demand
- **CDN Integration** - For static assets

## 📞 Support

For technical support or feature requests:
1. Check the troubleshooting section above
2. Review the test file for functionality verification
3. Examine browser console for JavaScript errors
4. Check server error logs for PHP issues

---

**Version**: 1.0  
**Last Updated**: January 2025  
**Compatibility**: PHP 7.4+, MySQL 5.7+, Modern Browsers 