<?php
/**
 * Test Script for Employee Deactivation and Seat Freeing
 * 
 * This script tests the functionality that automatically frees seats when an employee is deactivated.
 * 
 * Usage: Run this script in your browser to test the functionality.
 */

require_once 'config.php';

echo "<h1>Employee Deactivation & Seat Freeing Test</h1>";

try {
    $pdo = getDBConnection();
    
    // Test 1: Check if required tables exist
    echo "<h2>Test 1: Database Structure Check</h2>";
    
    $tables = ['employees', 'registrations', 'seats', 'admin_activity_log'];
    $missingTables = [];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() === 0) {
            $missingTables[] = $table;
        }
    }
    
    if (empty($missingTables)) {
        echo "<p style='color: green;'>✅ All required tables exist</p>";
    } else {
        echo "<p style='color: red;'>❌ Missing tables: " . implode(', ', $missingTables) . "</p>";
        exit;
    }
    
    // Test 2: Check if stored procedure exists
    echo "<h2>Test 2: Stored Procedure Check</h2>";
    
    $stmt = $pdo->query("SHOW PROCEDURE STATUS WHERE Name = 'freeSeatsByRegistration'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✅ freeSeatsByRegistration stored procedure exists</p>";
    } else {
        echo "<p style='color: red;'>❌ freeSeatsByRegistration stored procedure not found</p>";
        exit;
    }
    
    // Test 3: Check current employee and registration status
    echo "<h2>Test 3: Current Status</h2>";
    
    // Count employees
    $stmt = $pdo->query("SELECT COUNT(*) FROM employees");
    $totalEmployees = $stmt->fetchColumn();
    echo "<p>Total employees: $totalEmployees</p>";
    
    // Count active employees
    $stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE is_active = 1");
    $activeEmployees = $stmt->fetchColumn();
    echo "<p>Active employees: $activeEmployees</p>";
    
    // Count registrations
    $stmt = $pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'active'");
    $activeRegistrations = $stmt->fetchColumn();
    echo "<p>Active registrations: $activeRegistrations</p>";
    
    // Count occupied seats
    $stmt = $pdo->query("SELECT COUNT(*) FROM seats WHERE status = 'occupied'");
    $occupiedSeats = $stmt->fetchColumn();
    echo "<p>Occupied seats: $occupiedSeats</p>";
    
    // Test 4: Show employees with active registrations
    echo "<h2>Test 4: Employees with Active Registrations</h2>";
    
    $stmt = $pdo->query("
        SELECT 
            e.emp_number,
            e.full_name,
            e.is_active,
            r.id as registration_id,
            r.selected_seats,
            r.hall_id,
            r.shift_id
        FROM employees e
        LEFT JOIN registrations r ON e.emp_number = r.emp_number AND r.status = 'active'
        WHERE r.id IS NOT NULL
        ORDER BY e.full_name
    ");
    
    $employeesWithRegistrations = $stmt->fetchAll();
    
    if (empty($employeesWithRegistrations)) {
        echo "<p style='color: orange;'>⚠️ No employees with active registrations found</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Employee #</th><th>Name</th><th>Status</th><th>Registration ID</th><th>Selected Seats</th><th>Hall</th><th>Shift</th></tr>";
        
        foreach ($employeesWithRegistrations as $emp) {
            $status = $emp['is_active'] ? 'Active' : 'Inactive';
            $statusColor = $emp['is_active'] ? 'green' : 'red';
            
            echo "<tr>";
            echo "<td>{$emp['emp_number']}</td>";
            echo "<td>{$emp['full_name']}</td>";
            echo "<td style='color: $statusColor;'>$status</td>";
            echo "<td>{$emp['registration_id']}</td>";
            echo "<td>{$emp['selected_seats']}</td>";
            echo "<td>{$emp['hall_id']}</td>";
            echo "<td>{$emp['shift_id']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test 5: Manual test instructions
    echo "<h2>Test 5: Manual Testing Instructions</h2>";
    echo "<div style='background: #f0f0f0; padding: 1rem; border-radius: 8px;'>";
    echo "<h3>To test the functionality:</h3>";
    echo "<ol>";
    echo "<li>Go to <a href='admin.php?tab=employees' target='_blank'>Admin Panel > Employee Settings</a></li>";
    echo "<li>Look for employees marked with 📋 'Has Registration'</li>";
    echo "<li>Click 'Deactivate' on one of these employees</li>";
    echo "<li>Confirm the action in the dialog</li>";
    echo "<li>Check that:</li>";
    echo "<ul>";
    echo "<li>The employee status changes to 'Inactive'</li>";
    echo "<li>The registration is cancelled</li>";
    echo "<li>The seats are freed (check seat status)</li>";
    echo "<li>Activity is logged in admin_activity_log</li>";
    echo "</ul>";
    echo "</ol>";
    echo "</div>";
    
    // Test 6: Show recent admin activity
    echo "<h2>Test 6: Recent Admin Activity</h2>";
    
    $stmt = $pdo->query("
        SELECT 
            admin_user,
            action,
            target_type,
            details,
            created_at
        FROM admin_activity_log 
        WHERE action IN ('employee_activated', 'employee_deactivated')
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    
    $recentActivity = $stmt->fetchAll();
    
    if (empty($recentActivity)) {
        echo "<p style='color: orange;'>⚠️ No recent employee activation/deactivation activity found</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Admin User</th><th>Action</th><th>Target</th><th>Details</th><th>Date</th></tr>";
        
        foreach ($recentActivity as $activity) {
            echo "<tr>";
            echo "<td>{$activity['admin_user']}</td>";
            echo "<td>{$activity['action']}</td>";
            echo "<td>{$activity['target_type']}</td>";
            echo "<td>{$activity['details']}</td>";
            echo "<td>{$activity['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><small>Test completed at: " . date('Y-m-d H:i:s') . "</small></p>";
?> 