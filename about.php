<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header('Location: admin-login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #667eea;
            margin: 0;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
        }
        
        .logout-btn:hover {
            background: #c82333;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .table th {
            background: #667eea;
            color: white;
            font-weight: bold;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
        }
        
        .badge-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn:hover {
            background: #5a6fd8;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎬 Admin Dashboard</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">0</div>
                <div class="stat-label">Total Registrations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">0</div>
                <div class="stat-label">Available Seats</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">3</div>
                <div class="stat-label">Active Shifts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">3</div>
                <div class="stat-label">Cinema Halls</div>
            </div>
        </div>
        
        <div class="card">
            <h2>Recent Registrations</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Employee</th>
                        <th>Name</th>
                        <th>Attendees</th>
                        <th>Seats</th>
                        <th>Hall</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                            No registrations found. This is a demo version.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="card">
            <h2>System Information</h2>
            <p><strong>Movie:</strong> Thunderbolts*</p>
            <p><strong>Date:</strong> Friday, May 16, 2025</p>
            <p><strong>Time:</strong> 8:30 PM</p>
            <p><strong>Location:</strong> WD Campus Cinema Complex</p>
            <p><strong>Status:</strong> <span class="badge badge-success">Active</span></p>
        </div>
        
        <div class="card">
            <h2>Quick Actions</h2>
            <p>This is a minimal demo version for free hosting. Full functionality would include:</p>
            <ul>
                <li>View and manage all registrations</li>
                <li>Export registration data</li>
                <li>Manage cinema halls and shifts</li>
                <li>Configure event settings</li>
                <li>Send notifications to attendees</li>
            </ul>
        </div>
    </div>
</body>
</html>
