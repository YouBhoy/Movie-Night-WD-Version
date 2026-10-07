function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[char]));
}
// Global variables
let allEmployees = [];
let showActiveEmployees = true;
let registrationFilter = 'all'; // 'all', 'registered', 'not_registered'

// Save individual event setting
function saveSetting(settingKey) {
    const input = document.getElementById(settingKey);
    const value = input.value.trim();

    if (value === '') {
        showToast('Please enter a value for ' + settingKey.replace('_', ' '), 'error');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update_event_setting');
    formData.append('setting_key', settingKey);
    formData.append('setting_value', value);
    formData.append('admin_csrf_token', document.querySelector('meta[name=admin-csrf-token]').content);

    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(settingKey.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase()) + ' Saved Successfully', 'success');
        } else {
            showToast('Error saving setting: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showToast('Error saving setting', 'error');
    });
}

// Load event settings
function loadEventSettings() {
    fetch('admin-api.php?action=get_event_settings')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const settings = data.settings;
                if (settings.movie_name) document.getElementById('movie_name').value = settings.movie_name;
                if (settings.movie_time) document.getElementById('movie_time').value = settings.movie_time;
                if (settings.movie_location) document.getElementById('movie_location').value = settings.movie_location;
                if (settings.max_attendees) document.getElementById('max_attendees').value = settings.max_attendees;

                showToast('Settings loaded successfully', 'success');
            } else {
                showToast('Error loading settings: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showToast('Error loading settings', 'error');
        });
}

// Load shifts for a given hall

// Load seat layout for selected hall and shift

// Render seat grid for a hall

// Toggle seat status

// Save seat layout

// Reset seat layout

// Add new employee
function addNewEmployee() {
    const empNumber = document.getElementById('new_emp_number').value.trim();
    const fullName = document.getElementById('new_full_name').value.trim();
    const shiftId = document.getElementById('new_shift_id').value;
    if (!empNumber || !fullName || !shiftId) {
        showToast('Please fill in all fields', 'error');
        return;
    }
    const adminCsrfToken = document.querySelector('meta[name=admin-csrf-token]').content;
    fetch('admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=add_employee&emp_number=${encodeURIComponent(empNumber)}&full_name=${encodeURIComponent(fullName)}&shift_id=${encodeURIComponent(shiftId)}&admin_csrf_token=${adminCsrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Employee added successfully', 'success');
            setTimeout(loadEmployees, 1000);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(() => showToast('Error adding employee', 'error'));
}

// Delete employee

// Toggle employee status

// Show toast notification
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;

    container.appendChild(toast);

    // Show toast
    setTimeout(() => toast.classList.add('show'), 100);

    // Hide and remove toast
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => container.removeChild(toast), 300);
    }, 3000);
}

// Save shift name

// Save hall name

// Load and display all employees
function loadEmployees() {
    const table = document.getElementById('employeesTable');
    if (!table) return;
    table.innerHTML = '<div style="padding: 2rem; text-align: center;"><div class="loading"></div> Loading...</div>';
    fetch('admin-api.php?action=get_employees')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allEmployees = data.employees;
                renderEmployeesTable(getFilteredEmployees());
                updateEmployeeSummary();
            } else {
                table.innerHTML = '<div style="padding:2rem;text-align:center;color:#ef4444;">Error loading employees: ' + data.message + '</div>';
            }
        })
        .catch(() => {
            table.innerHTML = '<div style="padding:2rem;text-align:center;color:#ef4444;">Error loading employees</div>';
        });
}

// Update employee summary statistics
function updateEmployeeSummary() {
    if (!allEmployees || allEmployees.length === 0) return;

    const total = allEmployees.length;
    const active = allEmployees.filter(emp => emp.is_active == 1).length;
    const inactive = total - active;
    const withRegistration = allEmployees.filter(emp => emp.has_active_registration == 1).length;

    document.getElementById('totalEmployees').textContent = total;
    document.getElementById('activeEmployees').textContent = active;
    document.getElementById('inactiveEmployees').textContent = inactive;
    document.getElementById('employeesWithRegistration').textContent = withRegistration;
}

// Get filtered employees based on tab and search
function getFilteredEmployees() {
    const searchInput = document.getElementById('employeeSearchInput');

    // First filter by active/inactive status
    let filtered = allEmployees.filter(emp => showActiveEmployees ? emp.is_active == 1 : emp.is_active == 0);

    // Then filter by registration status
    if (registrationFilter === 'registered') {
        filtered = filtered.filter(emp => emp.has_active_registration == 1);
    } else if (registrationFilter === 'not_registered') {
        filtered = filtered.filter(emp => emp.has_active_registration != 1);
    }
    // 'all' doesn't need additional filtering

    // Finally filter by search input
    if (searchInput && searchInput.value.trim()) {
        const searchValue = searchInput.value.trim().toLowerCase();
        filtered = filtered.filter(emp =>
            emp.emp_number && emp.emp_number.toLowerCase().includes(searchValue)
        );
    }
    return filtered;
}

// Render employee table
function renderEmployeesTable(employees) {
    let html = `<table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="font-weight:600;color:var(--secondary-color);background:var(--hover);">
                <th style="padding:0.75rem 0.5rem;">Employee #</th>
                <th>Name</th>
                <th>Shift</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>`;
    employees.forEach(emp => {
        const statusBadge = emp.is_active == 1 ?
            '<span class="status-badge" style="background: #22c55e; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">Active</span>' :
            '<span class="status-badge" style="background: #ef4444; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">Inactive</span>';

        const hasRegistration = emp.has_active_registration ?
            '<span style="color: #f59e0b; font-size: 0.75rem; margin-left: 0.5rem; cursor: help;" title="This employee has an active registration. Deactivating them will free their seat(s)."><i class="fas fa-clipboard-list ui-icon" aria-hidden="true"></i> Has Registration</span>' : '';

        html += `<tr style="border-bottom:1px solid var(--border);">
            <td style="padding:0.75rem 0.5rem;">${escapeHtml(emp.emp_number)}</td>
            <td>${escapeHtml(emp.full_name)}${hasRegistration}</td>
            <td>${escapeHtml(emp.shift_name || 'N/A')}</td>
            <td>${statusBadge}</td>
            <td>
                <button class="btn btn-secondary btn-sm" onclick="openEditEmployeeModal(${Number(emp.id)})">Edit</button>
                <button class="btn btn-warning btn-sm" onclick="toggleEmployeeStatus(${Number(emp.id)}, ${Number(emp.is_active)})">${emp.is_active ? 'Deactivate' : 'Activate'}</button>
                ${emp.is_active == 0 ? `<button class="btn btn-danger btn-sm" onclick="deleteEmployeeAndCleanup(${Number(emp.id)})">Delete</button>` : ''}
            </td>
        </tr>`;
    });
    html += `</tbody></table>`;
    document.getElementById('employeesTable').innerHTML = html;
}

// Search/filter employees by employee number
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('employeeSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            renderEmployeesTable(getFilteredEmployees());
        });
    }

    // Tab switching logic for employees
    const tabActive = document.getElementById('tabActiveEmployees');
    const tabDeactivated = document.getElementById('tabDeactivatedEmployees');
    if (tabActive && tabDeactivated) {
        tabActive.addEventListener('click', function() {
            showActiveEmployees = true;
            tabActive.classList.add('active');
            tabDeactivated.classList.remove('active');
            renderEmployeesTable(getFilteredEmployees());
        });
        tabDeactivated.addEventListener('click', function() {
            showActiveEmployees = false;
            tabDeactivated.classList.add('active');
            tabActive.classList.remove('active');
            renderEmployeesTable(getFilteredEmployees());
        });
    }

    // Registration filter logic
    const filterAll = document.getElementById('filterAll');
    const filterRegistered = document.getElementById('filterRegistered');
    const filterNotRegistered = document.getElementById('filterNotRegistered');

    if (filterAll && filterRegistered && filterNotRegistered) {
        filterAll.addEventListener('click', function() {
            registrationFilter = 'all';
            filterAll.classList.add('active');
            filterRegistered.classList.remove('active');
            filterNotRegistered.classList.remove('active');
            renderEmployeesTable(getFilteredEmployees());
        });

        filterRegistered.addEventListener('click', function() {
            registrationFilter = 'registered';
            filterRegistered.classList.add('active');
            filterAll.classList.remove('active');
            filterNotRegistered.classList.remove('active');
            renderEmployeesTable(getFilteredEmployees());
        });

        filterNotRegistered.addEventListener('click', function() {
            registrationFilter = 'not_registered';
            filterNotRegistered.classList.add('active');
            filterAll.classList.remove('active');
            filterRegistered.classList.remove('active');
            renderEmployeesTable(getFilteredEmployees());
        });
    }
});

// Load employees on page load if on employees tab
document.addEventListener('DOMContentLoaded', function() {
    const currentTab = window.MovieNightPage.currentTab;
    if (currentTab === 'employees') {
        loadEmployees();
    }
});

// Toggle employee status function (replaces separate activate/deactivate functions)
function toggleEmployeeStatus(empId, currentStatus) {
    const action = currentStatus == 1 ? 'deactivate' : 'activate';
    const employee = allEmployees.find(emp => emp.id == empId);
    let confirmMessage;

    if (currentStatus == 1) {
        // Deactivating - check if they have active registration
        confirmMessage = 'Are you sure you want to deactivate this employee?\n\n' +
            'Employee: ' + (employee ? employee.full_name : 'Unknown') + '\n' +
            'Employee #: ' + (employee ? employee.emp_number : 'Unknown') + '\n\n' +
            'This action will:\n' +
            '• Deactivate the employee\n' +
            '• Free their seat(s) if they have an active registration\n' +
            '• Cancel their current registration\n\n' +
            'This action cannot be undone. Continue?';
    } else {
        // Activating
        confirmMessage = 'Are you sure you want to activate this employee?\n\n' +
            'Employee: ' + (employee ? employee.full_name : 'Unknown') + '\n' +
            'Employee #: ' + (employee ? employee.emp_number : 'Unknown') + '\n\n' +
            'This will allow them to register for the event again.';
    }

    if (!confirm(confirmMessage)) return;

    const formData = new FormData();
    formData.append('action', 'toggle_employee_status');
    formData.append('emp_id', empId);
    formData.append('current_status', currentStatus);
    formData.append('admin_csrf_token', document.querySelector('meta[name=admin-csrf-token]').content);

    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            loadEmployees();
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(() => showToast('Error updating employee status', 'error'));
}

function openEditEmployeeModal(empId) {
    const emp = allEmployees.find(e => e.id == empId);
    if (!emp) return;
    document.getElementById('edit_employee_id').value = emp.id;
    document.getElementById('edit_emp_number').value = emp.emp_number;
    document.getElementById('edit_full_name').value = emp.full_name;
    document.getElementById('edit_shift_id').value = emp.shift_id || '';
    document.getElementById('editEmployeeModal').style.display = 'flex';
}

function closeEditEmployeeModal() {
    document.getElementById('editEmployeeModal').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editEmployeeForm');
    if (form) {
        form.onsubmit = function(e) {
            e.preventDefault();
            const id = document.getElementById('edit_employee_id').value;
            const emp_number = document.getElementById('edit_emp_number').value.trim();
            const full_name = document.getElementById('edit_full_name').value.trim();
            const shift_id = document.getElementById('edit_shift_id').value;
            if (!emp_number || !full_name || !shift_id) {
                showToast('Please fill in all fields', 'error');
                return;
            }
            const adminCsrfToken = document.querySelector('meta[name=admin-csrf-token]').content;
            fetch('admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=update_employee&id=${id}&emp_number=${encodeURIComponent(emp_number)}&full_name=${encodeURIComponent(full_name)}&shift_id=${encodeURIComponent(shift_id)}&admin_csrf_token=${adminCsrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Employee updated successfully', 'success');
                    closeEditEmployeeModal();
                    loadEmployees();
                } else {
                    showToast('Error updating employee: ' + data.message, 'error');
                }
            })
            .catch(() => showToast('Error updating employee', 'error'));
        }
    }
});

// Add this new function after renderEmployeesTable
function deleteEmployeeAndCleanup(empId) {
    if (!confirm('Are you sure you want to permanently delete this employee?\n\nThis will remove all their registrations and free any seats they held. This action cannot be undone.')) {
        return;
    }
    const formData = new FormData();
    formData.append('action', 'delete_employee_cleanup');
    formData.append('emp_id', empId);
    formData.append('admin_csrf_token', document.querySelector('meta[name=admin-csrf-token]').content);
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Employee deleted successfully', 'success');
            loadEmployees();
        } else {
            showToast('Error deleting employee: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showToast('Error deleting employee', 'error');
    });
}
