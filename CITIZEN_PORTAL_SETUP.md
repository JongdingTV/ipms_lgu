# Citizen Portal - Implementation Guide

## Overview

The Citizen Portal is a modern, responsive public engagement platform for your Infrastructure Project Management System (IPMS). It enables citizens to view project transparency, track project status, submit feedback, and track complaint resolution.

## Features

### 1. **Public Landing Page** 
- Modern, responsive design
- Project information and transparency messaging
- Call-to-action for login/registration
- Feature highlights for public engagement

**Location:** `/landing.php`

### 2. **Strict Identity Verification System**
- Mandatory government-issued ID verification
- Photo upload for ID documents
- Personal information collection (name, location, birthdate, etc.)
- Prevents dummy and troll accounts
- Admin verification workflow ready

**Location:** `/citizen/register.php`

**Required Fields:**
- First Name, Last Name, Middle Name
- Date of Birth (18+ required)
- Gender & Civil Status
- Complete Address (Street, Barangay, City, Province, Postal Code)
- Government ID Type (National ID, Passport, Driver License, etc.)
- ID Number (unique per citizen)
- ID Photo Upload (required for verification)
- Email & Phone Number

### 3. **Citizen Login Portal**
- Secure authentication
- Email or username login
- Role-based access control
- Session management
- Login attempt tracking

**Location:** `/citizen/login.php`

### 4. **Citizen Dashboard**
Modern dashboard with the following sections:

#### KPI Cards
- Active Projects Count
- Completed Projects Count  
- Delayed Projects Count
- Personal Submissions Count

#### Dashboard Pages

**1. Dashboard (Home)**
- Overview of system statistics
- Recent projects in your area
- Your recent feedback submissions

**2. Public Projects**
- Browse all public infrastructure projects
- Filter by status (Planning, Active, Delayed, On Hold, Completed, Cancelled)
- Search projects by name, location, or description
- View project budget, timeline, and progress

**3. Project Status Tracking**
- Detailed project status and progress
- Milestone tracking
- Budget vs. actual expenses
- Project completion estimates

**4. Submit Feedback/Complaint**
- User-friendly feedback form
- Category selection (Complaint, Suggestion, Inquiry)
- Priority levels (Low, Medium, High, Urgent)
- Project-specific feedback
- Real-time submission

**5. Track Complaints**
- View all personal submissions
- Track complaint status (Open, In Progress, Resolved, Closed)
- View submission history
- See resolution timelines

**6. Transparency Dashboard**
- Total budget allocation
- Total expenses incurred
- Budget remaining
- On-time project completion rates
- Expense breakdown by project and category
- Financial transparency visualizations

**Location:** `/citizen/dashboard.php`

## Database Schema

### Citizens Table
New table created to store citizen identity information:

```sql
CREATE TABLE citizens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    email VARCHAR(180) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    civil_status ENUM('Single', 'Married', 'Divorced', 'Widowed', 'Separated') NOT NULL,
    address TEXT NOT NULL,
    barangay VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    postal_code VARCHAR(10),
    id_type VARCHAR(50) NOT NULL,
    id_number VARCHAR(100) NOT NULL UNIQUE,
    id_photo_path VARCHAR(255),
    verification_status ENUM('unverified', 'verified', 'rejected') DEFAULT 'unverified',
    verified_by INT NULL,
    verified_at DATETIME NULL,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Updated Feedback Table
The feedback table was updated to link citizen records:

```sql
CREATE TABLE feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT,
    citizen_id INT,
    citizen_name VARCHAR(120),
    message TEXT NOT NULL,
    category ENUM('complaint','suggestion','inquiry') DEFAULT 'complaint',
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE SET NULL
);
```

## File Structure

```
citizen/
├── dashboard.php              # Main citizen dashboard
├── login.php                  # Citizen login page
├── register.php               # Citizen registration (strict validation)
├── sidebar.php                # Navigation sidebar
├── assets/
│   ├── css/
│   │   └── citizen.css        # Citizen portal styling
│   └── js/
│       └── citizen.js         # Client-side functionality
├── api/
│   ├── dashboard.php          # Dashboard statistics API
│   ├── projects.php           # Projects list API
│   ├── project-status.php     # Detailed project status API
│   ├── my-feedback.php        # User feedback history API
│   ├── submit-feedback.php    # Feedback submission API
│   └── transparency.php       # Transparency dashboard API
└── includes/
    └── scope.php              # Citizen portal scope & permissions
```

## API Endpoints

All endpoints require citizen login authentication.

### Dashboard API
**GET** `/citizen/api/dashboard.php`
- Returns KPI statistics and recent data
- Response: JSON with stats, recent_projects, recent_feedback

### Projects API
**GET** `/citizen/api/projects.php?search=X&status=Y&all=1`
- Filters and searches projects
- Query params: search, status, all (return all including cancelled)
- Response: JSON with projects array

### Project Status API
**GET** `/citizen/api/project-status.php`
- Returns detailed project status with milestones
- Response: JSON with projects array including milestone data

### My Feedback API
**GET** `/citizen/api/my-feedback.php`
- Returns citizen's feedback submissions
- Response: JSON with feedback array

### Submit Feedback API
**POST** `/citizen/api/submit-feedback.php`
- POST Data: project_id, category, priority, message
- Response: JSON with success status

### Transparency API
**GET** `/citizen/api/transparency.php`
- Returns budget and expense transparency data
- Response: JSON with stats and expenses

## Security Features

1. **Strict Registration Validation**
   - Minimum 18 years old
   - Strong password requirements (8+ chars, upper, lower, number, special char)
   - Unique email and username validation
   - Government ID verification

2. **Identity Verification**
   - Government-issued ID required
   - Photo upload with file type validation
   - Admin approval workflow ready
   - Prevents multiple accounts per ID number

3. **Authentication & Session Management**
   - Secure password hashing (bcrypt)
   - Session timeout (30 minutes default)
   - Login attempt tracking
   - Account lockout after failed attempts
   - CSRF token generation
   - User agent validation

4. **Data Access Control**
   - Role-based access (citizen role only)
   - Read-only access to projects
   - Can only see own feedback submissions
   - No access to contractor/engineer portals

## Styling & UI

### Color Scheme
- Primary: `#667eea` (Purple-blue)
- Primary Dark: `#764ba2` (Deep purple)
- Success: `#27ae60` (Green)
- Warning: `#f39c12` (Orange)
- Danger: `#e74c3c` (Red)

### Responsive Design
- Mobile-first approach
- Breakpoints: 768px, 1024px
- Sidebar collapses on mobile
- Touch-friendly interface
- Accessible navigation

## User Flow

1. **Landing Page** → User clicks "Login" or "Register"
2. **Registration** → Strict validation with ID photo upload
3. **Verification** → Admin verifies identity (manual process)
4. **Activation** → Account activated after verification
5. **Login** → Citizens log in with verified account
6. **Dashboard** → View projects and submission history
7. **Interact** → Browse projects, submit feedback, track complaints
8. **Transparency** → View budget and expense information

## Testing Credentials

After database migration, you can use:
- **Username:** citizen
- **Email:** citizen@ipms.local
- **Password:** admin123

## Setup Instructions

1. **Run Database Migration**
   ```bash
   mysql -u root lgu_infrastructure < database.sql
   ```

2. **Create Citizen ID Upload Directory**
   ```bash
   mkdir -p assets/img/citizen-ids
   chmod 755 assets/img/citizen-ids
   ```

3. **Access the System**
   - Landing Page: `http://localhost/ipms.lgu/`
   - Login: `http://localhost/ipms.lgu/citizen/login.php`
   - Register: `http://localhost/ipms.lgu/citizen/register.php`

4. **First Admin Visit**
   - Access admin panel to review and approve pending citizen registrations
   - Feature location: (To be implemented in admin panel)

## Customization

### Modify Required ID Types
Edit `register.php` line ~250:
```php
$idTypes = [
    'National ID' => 'National ID (PhilID)',
    'Passport' => 'Passport',
    // Add or remove as needed
];
```

### Adjust Password Requirements
Edit `register.php` validation section to modify:
- Minimum length
- Character requirements
- Special characters required

### Change Color Scheme
Edit `citizen/assets/css/citizen.css` `:root` section

## Future Enhancements

1. Admin verification panel for citizen approvals
2. Citizen notifications (email/SMS on project updates)
3. Project location mapping with GIS integration
4. Document uploads (supporting documents for complaints)
5. Two-factor authentication
6. Mobile app integration
7. QR code for quick feedback submission
8. API rate limiting
9. Export feedback reports
10. Video project progress tracking

## Support & Troubleshooting

### Common Issues

**Registration failing:**
- Check file upload directory permissions
- Verify MySQL connection
- Check validation error messages in console

**API endpoints returning errors:**
- Verify citizen profile exists in database
- Check authentication session is active
- Review browser console for AJAX errors

**Login issues:**
- Clear browser cookies
- Check database for user record
- Verify password hash is correct

## Notes

- All citizen data is encrypted and securely stored
- GDPR compliance recommended for European deployments
- Regular backups of citizen ID photos recommended
- Consider image compression for large-scale deployments
- Admin verification workflow reduces spam significantly
