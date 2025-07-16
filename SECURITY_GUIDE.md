# Security Guide - Western Digital Movie Night Registration System

## 🔒 Security Overview

This document outlines the security measures implemented for the Western Digital Movie Night registration system, designed for corporate use.

## 🛡️ Security Features Implemented

### 1. **Authentication & Authorization**
- ✅ Database-based admin authentication (no hardcoded passwords)
- ✅ Password hashing with bcrypt
- ✅ Session management with secure configuration
- ✅ Role-based access control (admin, manager, viewer)
- ✅ Login attempt tracking and account lockout
- ✅ Session timeout enforcement

### 2. **Data Protection**
- ✅ SQL injection prevention (PDO prepared statements)
- ✅ XSS protection (input sanitization)
- ✅ CSRF protection (tokens)
- ✅ Input validation and sanitization
- ✅ Employee data verification

### 3. **Infrastructure Security**
- ✅ HTTPS enforcement (HSTS)
- ✅ Security headers (CSP, X-Frame-Options, etc.)
- ✅ Rate limiting on API endpoints
- ✅ IP-based access logging
- ✅ Error handling without information disclosure

### 4. **Audit & Monitoring**
- ✅ Security event logging
- ✅ Admin activity tracking
- ✅ Failed login attempt monitoring
- ✅ Registration activity logging

## 🚨 Critical Security Requirements

### **Before Production Deployment:**

1. **Change Default Passwords**
   ```sql
   -- Run this immediately after setup
   UPDATE admin_users SET password_hash = '$2y$10$NEW_HASH_HERE' WHERE username = 'admin';
   UPDATE admin_users SET password_hash = '$2y$10$NEW_HASH_HERE' WHERE username = 'manager';
   ```

2. **Enable HTTPS**
   - Configure SSL certificate
   - Force HTTPS redirects
   - Enable HSTS

3. **Database Security**
   - Use strong database passwords
   - Limit database user permissions
   - Enable database logging

4. **Server Security**
   - Keep PHP and server software updated
   - Configure firewall rules
   - Enable server logging

## 🔧 Security Configuration

### **Environment Variables (Recommended)**
Create a `.env` file for sensitive configuration:

```env
# Database
DB_HOST=localhost
DB_NAME=movie_night_db
DB_USER=secure_user
DB_PASS=strong_password_here

# Admin Credentials (change these!)
ADMIN_USERNAME=wd_admin
ADMIN_PASSWORD=StrongPassword123!

# Security
ENCRYPTION_KEY=your_32_character_key_here
SESSION_SECRET=your_session_secret_here
```

### **PHP Security Settings**
Add to your `php.ini`:

```ini
# Security settings
expose_php = Off
display_errors = Off
log_errors = On
error_log = /path/to/secure/error.log

# Session security
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = "Strict"
session.use_strict_mode = 1
```

## 📊 Security Monitoring

### **Key Metrics to Monitor:**

1. **Failed Login Attempts**
   ```sql
   SELECT COUNT(*) FROM login_attempts 
   WHERE success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR);
   ```

2. **Suspicious IP Activity**
   ```sql
   SELECT ip_address, COUNT(*) as attempts 
   FROM security_audit_log 
   WHERE risk_level IN ('high', 'critical') 
   GROUP BY ip_address;
   ```

3. **Admin Activity**
   ```sql
   SELECT admin_user, action, created_at 
   FROM admin_activity_log 
   ORDER BY created_at DESC LIMIT 50;
   ```

## 🚨 Incident Response

### **Security Breach Response:**

1. **Immediate Actions:**
   - Disable admin access
   - Review security logs
   - Change all passwords
   - Check for data compromise

2. **Investigation:**
   - Analyze security_audit_log
   - Check admin_activity_log
   - Review server logs
   - Identify attack vector

3. **Recovery:**
   - Restore from backup if needed
   - Implement additional security measures
   - Update security documentation
   - Notify stakeholders

## 🔐 Password Policy

### **Admin Password Requirements:**
- Minimum 12 characters
- Mix of uppercase, lowercase, numbers, symbols
- No common words or patterns
- Change every 90 days

### **Employee Number Security:**
- Employee numbers are internal identifiers
- Not used for external authentication
- Validated against employees table
- Logged for audit purposes

## 📋 Security Checklist

### **Pre-Deployment:**
- [ ] Change default admin passwords
- [ ] Enable HTTPS
- [ ] Configure security headers
- [ ] Set up monitoring
- [ ] Test all security features
- [ ] Review access controls

### **Post-Deployment:**
- [ ] Monitor security logs daily
- [ ] Review failed login attempts
- [ ] Check for suspicious activity
- [ ] Update security patches
- [ ] Backup data regularly
- [ ] Test incident response procedures

## 🆘 Emergency Contacts

### **Security Issues:**
- **IT Security Team**: security@wd.com
- **System Administrator**: admin@wd.com
- **Emergency Hotline**: +1-XXX-XXX-XXXX

### **Escalation Process:**
1. Immediate containment
2. Security team notification
3. Management escalation
4. External notification if required

## 📚 Additional Resources

- [OWASP Security Guidelines](https://owasp.org/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [Western Digital Security Policy](internal-link)
- [Incident Response Procedures](internal-link)

---

**Last Updated:** January 2025  
**Security Level:** Corporate Grade  
**Compliance:** Western Digital Security Standards 