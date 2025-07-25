# 🎛️ Admin Panel Guide

## Overview
This document explains the features and usage of the admin panel for the Movie Night Registration System.

---

## 1. Admin Users Management
- **Purpose:** Manage admin accounts (add, edit, delete).
- **Who can access:** Only users with the `admin` role.
- **Features:**
  - Add new admins (username, password, role, active status)
  - Edit existing admins (change username, role, status, password)
  - Delete admins (cannot delete your own account)
- **Security:** All actions require CSRF tokens and are logged. Passwords are hashed with bcrypt.

---

## 2. Employee Settings
- **Purpose:** Manage employee records and their shifts.
- **Features:**
  - Add new employees
  - Edit employee details
  - Deactivate/reactivate employees (deactivation frees up seats)
  - Delete employees (must be deactivated first)
- **Notes:** Deactivating an employee cancels their registration and frees their seats.

---

## 3. Event Settings
- **Purpose:** Configure event details (movie name, time, venue, etc.).
- **Features:** Update each setting individually.
- **Notes:** Some settings are public, others are for admin use only.

---

## 4. Export
- **Purpose:** Export registration and attendance data.
- **Features:** Download CSV files for reporting or backup.

---

## 5. Security Features
- **Authentication:** Only admins can access the admin panel.
- **CSRF Protection:** All forms/actions require a valid CSRF token.
- **Session Security:** Secure cookies, session regeneration, and timeouts.
- **Password Hashing:** All passwords are stored using bcrypt.
- **Rate Limiting:** Prevents brute force and abuse.
- **Self-Delete Protection:** Admins cannot delete their own account.

---

## 6. Troubleshooting & FAQ
- **Forgot password:** Contact another admin to reset.
- **Cannot delete admin:** You cannot delete your own account.
- **Database errors:** Check logs in `/logs/php_errors.log`.
- **Registration issues:** Ensure employee is active and not already registered.

---

## 7. Contact
For further help, contact the system maintainer or IT support. 