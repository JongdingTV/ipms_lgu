# Citizen Portal - Admin Management Guide

## Overview

This guide explains how administrators can manage citizen registrations, verify identities, and oversee citizen portal activity.

## Database Queries for Management

### View All Pending Citizen Registrations

```sql
SELECT 
    u.id,
    u.username,
    u.email,
    c.first_name,
    c.last_name,
    c.id_type,
    c.id_number,
    c.id_photo_path,
    c.created_at,
    c.verification_status
FROM users u
JOIN citizens c ON u.id = c.user_id
WHERE c.verification_status = 'unverified'
ORDER BY c.created_at ASC;
```

### View Verified Citizens

```sql
SELECT 
    u.id,
    u.username,
    u.email,
    c.first_name,
    c.last_name,
    c.email as citizen_email,
    c.phone,
    c.barangay,
    c.city,
    c.verified_at
FROM users u
JOIN citizens c ON u.id = c.user_id
WHERE c.verification_status = 'verified'
ORDER BY c.verified_at DESC;
```

### View Rejected Registrations

```sql
SELECT 
    u.id,
    u.username,
    u.email,
    c.first_name,
    c.last_name,
    c.rejection_reason,
    c.verified_at
FROM users u
JOIN citizens c ON u.id = c.user_id
WHERE c.verification_status = 'rejected'
ORDER BY c.verified_at DESC;
```

## Manual Verification Workflow

### Step 1: Review Pending Applications

Admin panel should display:
- Citizen's personal information
- ID photo (zoomable view)
- ID details (Type, Number)
- Address information
- Email & Phone number
- Account creation date
- Current verification status

### Step 2: Verify Identity

Admin should check:
- [ ] ID photo is clear and legible
- [ ] ID number is formatted correctly
- [ ] Name matches application
- [ ] DOB is valid (18+ years old)
- [ ] Address is complete and valid
- [ ] No duplicate ID numbers in system
- [ ] ID type is government-issued

### Step 3: Approve or Reject

**To Approve:**
```sql
UPDATE citizens 
SET verification_status = 'verified', 
    verified_by = ?,
    verified_at = NOW()
WHERE id = ?;
```

**To Reject:**
```sql
UPDATE citizens 
SET verification_status = 'rejected', 
    verified_by = ?,
    verified_at = NOW(),
    rejection_reason = ?
WHERE id = ?;
```

## Feedback Management

### View All Citizen Feedback

```sql
SELECT 
    f.id,
    f.created_at,
    c.first_name,
    c.last_name,
    p.name as project_name,
    f.category,
    f.priority,
    f.message,
    f.status
FROM feedback f
JOIN citizens c ON f.citizen_id = c.id
LEFT JOIN projects p ON f.project_id = p.id
ORDER BY f.created_at DESC;
```

### Filter Urgent Feedback

```sql
SELECT 
    f.id,
    c.first_name,
    c.last_name,
    p.name as project_name,
    f.message,
    f.priority,
    f.status,
    f.created_at
FROM feedback f
JOIN citizens c ON f.citizen_id = c.id
LEFT JOIN projects p ON f.project_id = p.id
WHERE f.priority IN ('urgent', 'high')
  AND f.status != 'resolved'
ORDER BY f.created_at DESC;
```

## Citizen Activity Monitoring

### Active Citizens This Week

```sql
SELECT 
    u.username,
    c.first_name,
    c.last_name,
    COUNT(f.id) as feedback_count,
    MAX(u.last_login) as last_active
FROM users u
JOIN citizens c ON u.id = c.user_id
LEFT JOIN feedback f ON c.id = f.citizen_id
WHERE c.verification_status = 'verified'
  AND u.last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY u.id
ORDER BY u.last_login DESC;
```

## ID Photo Management

### Locate ID Photos

```bash
ls -la /ipms.lgu/assets/img/citizen-ids/
```

### Backup ID Photos

```bash
tar -czf citizen-ids-backup-$(date +%Y%m%d).tar.gz /ipms.lgu/assets/img/citizen-ids/
```
