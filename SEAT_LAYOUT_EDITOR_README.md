# Seat Layout Editor - Admin Panel Feature

## Overview
The Seat Layout Editor is a powerful visual tool that allows administrators to create, modify, and manage seat arrangements for each Cinema Hall and Shift combination in the Movie Night Registration System.

## 🎯 Key Features

### ✅ Visual Grid-Based Editor
- **Interactive Grid Interface**: Click-based seat management with visual feedback
- **Multiple Grid Sizes**: Support for 10x10, 12x8, 15x10, and 20x10 layouts
- **Real-time Updates**: Instant visual feedback when modifying seats
- **Responsive Design**: Works seamlessly on desktop and mobile devices

### ✅ Seat Management
- **Add New Seats**: Create seats at specific positions with custom status
- **Delete Existing Seats**: Remove seats with confirmation dialog
- **Status Toggle**: Cycle through available → occupied → blocked → reserved
- **Bulk Operations**: Save entire layouts at once

### ✅ Database Integration
- **Hall + Shift Specific**: Each seat is tied to a specific hall_id and shift_id
- **Status Tracking**: Maintains seat status (available, occupied, blocked, reserved)
- **Audit Trail**: Logs all seat modifications for security
- **Data Integrity**: Prevents duplicate seats and maintains relationships

## 🚀 Getting Started

### 1. Database Setup
Run the SQL script to create the seats table:

```sql
-- Execute seats_table_setup.sql in your MySQL database
source seats_table_setup.sql;
```

### 2. Access the Editor
1. Log in to the admin panel
2. Click the "🪑 Seat Layout" button in the header
3. Or navigate directly to `seat-layout-editor.php`

### 3. Basic Usage
1. **Select Hall & Shift**: Choose the cinema hall and shift combination
2. **Choose Grid Size**: Select the appropriate layout size
3. **Edit Seats**: Click on seats to modify their status
4. **Add Seats**: Click empty positions or use the "Add New Seat" form
5. **Save Changes**: Click "Save Layout" to persist changes

## 📋 Detailed Usage Guide

### Selecting Hall and Shift
- **Cinema Hall**: Choose from available halls (Hall 1, Hall 2, etc.)
- **Shift**: Select the specific shift for that hall
- **Grid Size**: Choose the layout dimensions (10x10, 12x8, etc.)

### Seat Status Management
Each seat can have one of four statuses:

| Status | Color | Description |
|--------|-------|-------------|
| **Available** | Green | Open for registration |
| **Occupied** | Red | Currently taken by a registrant |
| **Blocked** | Gray | Temporarily unavailable |
| **Reserved** | Orange | Reserved for special purposes |

### Adding New Seats
**Method 1: Click Empty Positions**
- Click on any empty grid position
- Seat will be created with "Available" status

**Method 2: Manual Entry**
- Enter Row Letter (A-Z)
- Enter Seat Position (1, 2, 3, etc.)
- Select Status
- Click "Add" button

### Deleting Seats
- Hover over an existing seat
- Click the red "×" button that appears
- Confirm deletion in the dialog

### Saving Layout Changes
- Click "Save Layout" button
- System will:
  - Delete all existing seats for the hall/shift combination
  - Insert the new seat configuration
  - Log the changes for audit purposes

## 🔧 Technical Implementation

### Database Schema
```sql
CREATE TABLE `seats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hall_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `row_letter` char(1) NOT NULL,
  `seat_position` int(11) NOT NULL,
  `seat_number` varchar(10) NOT NULL,
  `status` enum('available','occupied','blocked','reserved') NOT NULL DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_seat` (`hall_id`, `shift_id`, `seat_number`)
);
```

### File Structure
```
seat-layout-editor.php          # Main editor interface
admin-api.php                   # API endpoints for seat operations
seats_table_setup.sql          # Database setup script
```

### API Endpoints
- `GET admin-api.php?action=get_seat_layout&hall_id=X&shift_id=Y`
- `POST seat-layout-editor.php` (save_layout, delete_seat, add_seat)

## 🎨 UI/UX Features

### Visual Design
- **Cinema-Style Theme**: Dark background with gold accents
- **Intuitive Icons**: Font Awesome icons for better UX
- **Color-Coded Status**: Easy-to-understand seat status indicators
- **Responsive Layout**: Adapts to different screen sizes

### User Experience
- **Drag-Free Interface**: Simple click-based interactions
- **Visual Feedback**: Immediate response to user actions
- **Confirmation Dialogs**: Prevents accidental deletions
- **Loading States**: Clear indication of background operations

## 🔒 Security Features

### Authentication
- Admin-only access required
- Session-based authentication
- CSRF token protection

### Data Validation
- Input sanitization for all user inputs
- SQL injection prevention with prepared statements
- XSS protection with output encoding

### Audit Logging
- All seat modifications are logged
- Admin activity tracking
- Timestamp and user information recorded

## 📱 Mobile Compatibility

### Responsive Features
- Touch-friendly interface
- Optimized for mobile screens
- Swipe-friendly grid navigation
- Adaptive button sizes

### Mobile-Specific Considerations
- Larger touch targets
- Simplified navigation
- Optimized loading times
- Reduced data usage

## 🛠️ Troubleshooting

### Common Issues

**"No seats found" message**
- Check if hall and shift are selected
- Verify database connection
- Ensure seats table exists

**"Error saving layout"**
- Check database permissions
- Verify CSRF token is valid
- Check for duplicate seat numbers

**Grid not displaying properly**
- Clear browser cache
- Check JavaScript console for errors
- Verify all required files are loaded

### Performance Tips
- Use appropriate grid sizes for your needs
- Avoid creating extremely large layouts (>200 seats)
- Clear browser cache regularly
- Use modern browsers for best performance

## 🔄 Integration with Existing System

### Registration System
- Seats created in the editor are immediately available for registration
- Status changes are reflected in real-time
- No conflicts with existing registration logic

### Admin Dashboard
- Seat layout editor accessible from main admin dashboard
- Consistent styling and navigation
- Integrated with existing admin authentication

### Export System
- Seat data included in export functionality
- Maintains data integrity across all systems

## 📈 Future Enhancements

### Planned Features
- **Drag & Drop**: Visual seat rearrangement
- **Bulk Operations**: Select multiple seats at once
- **Template System**: Save and reuse layouts
- **Advanced Grid**: Custom row/column configurations
- **Seat Categories**: Premium, standard, accessible seating
- **Import/Export**: CSV/Excel file support

### Performance Improvements
- **Lazy Loading**: Load seats on demand
- **Caching**: Redis-based seat status caching
- **Optimization**: Reduced database queries
- **Compression**: Optimized asset delivery

## 📞 Support

For technical support or feature requests:
- Check the logs in `/logs/php_errors.log`
- Review database connection settings
- Verify file permissions
- Contact system administrator

---

**Version**: 1.0.0  
**Last Updated**: January 2025  
**Compatibility**: PHP 7.4+, MySQL 5.7+ 