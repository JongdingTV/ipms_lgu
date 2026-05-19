# Citizen Portal - Quick Start Guide

## 🚀 What's New

A complete, modern citizen portal has been added to your IPMS system with:

- **Modern, responsive landing page** with project transparency messaging
- **Strict identity verification** (government ID required)
- **Secure citizen registration** with robust validation
- **Comprehensive citizen dashboard** with multiple views
- **Real-time project transparency** (budget, expenses, progress)
- **Feedback & complaint tracking system**
- **Beautiful, mobile-friendly UI** with smooth animations

## 📋 Quick Access

| Component | URL |
|-----------|-----|
| **Landing Page** | http://localhost/ipms.lgu/ |
| **Citizen Login** | http://localhost/ipms.lgu/citizen/login.php |
| **Citizen Register** | http://localhost/ipms.lgu/citizen/register.php |
| **Citizen Dashboard** | http://localhost/ipms.lgu/citizen/dashboard.php |
| **Setup Guide** | `/CITIZEN_PORTAL_SETUP.md` |
| **Admin Guide** | `/CITIZEN_ADMIN_GUIDE.md` |

## 🎯 Use Flow

```
Start
  ↓
Landing Page (/landing.php)
  ├─ New User? → Register (/citizen/register.php)
  │   ├─ Enter personal info
  │   ├─ Upload government ID
  │   ├─ Create account
  │   └─ Account pending verification
  │
  └─ Existing User? → Login (/citizen/login.php)
       ↓
    Dashboard (/citizen/dashboard.php)
       ├─ View overview & recent projects
       ├─ View public projects list & details
       ├─ Track project status & milestones
       ├─ Submit feedback/complaints
       ├─ Track complaint resolution
       └─ View transparency (budgets, expenses)
```

## 📊 Dashboard Features

### KPI Cards
- Active Projects
- Completed Projects
- Delayed Projects
- Your Submissions

### Navigation Menu
1. **Dashboard** - Overview & recent activity
2. **Public Projects** - Browse all projects with filters
3. **Project Status** - Detailed tracking of project progress
4. **Submit Feedback** - Create complaints/suggestions
5. **Track Complaints** - Monitor your submissions
6. **Transparency Dashboard** - View budget & expenses

## 🔒 Registration Requirements

Citizens must provide:
- ✓ Full legal name (First, Last, Middle)
- ✓ Date of birth (18+ years old)
- ✓ Gender & Civil Status
- ✓ Complete address details
- ✓ Government-issued ID (National ID, Passport, Driver License, etc.)
- ✓ Valid ID number
- ✓ Clear photo of government ID
- ✓ Email address & phone number
- ✓ Strong password (8+ chars, upper, lower, number, special)

## 🎨 Styling & Theme

The portal uses a modern gradient color scheme:
- Primary: Purple-blue (#667eea)
- Secondary: Deep purple (#764ba2)
- Mobile responsive with touch-friendly interface
- Smooth animations and transitions

## 📦 Files Created

### Dashboard & Navigation
- `/citizen/dashboard.php` - Main dashboard
- `/citizen/sidebar.php` - Navigation sidebar
- `/landing.php` - Public landing page
- `/citizen/login.php` - Citizen login page
- `/citizen/register.php` - Citizen registration (strict validation)

### Styling & JavaScript
- `/citizen/assets/css/citizen.css` - Complete styling
- `/citizen/assets/js/citizen.js` - Client-side functionality

### API Endpoints
- `/citizen/api/dashboard.php` - KPI statistics
- `/citizen/api/projects.php` - Project listing & search
- `/citizen/api/project-status.php` - Detailed project tracking
- `/citizen/api/my-feedback.php` - User feedback history
- `/citizen/api/submit-feedback.php` - Feedback submission
- `/citizen/api/transparency.php` - Budget & expense data

### Documentation
- `/CITIZEN_PORTAL_SETUP.md` - Complete setup guide
- `/CITIZEN_ADMIN_GUIDE.md` - Admin management guide
- `/citizen/includes/scope.php` - Portal capabilities

### Database
- Updated `database.sql` with:
  - `citizens` table (identity verification)
  - Updated `feedback` table (linked to citizens)

## 🔧 Database Changes

```sql
-- New Table
CREATE TABLE citizens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    first_name, last_name, middle_name,
    email, phone,
    date_of_birth, gender, civil_status,
    address, barangay, city, province, postal_code,
    id_type, id_number, id_photo_path,
    verification_status (unverified|verified|rejected),
    verified_by, verified_at, rejection_reason,
    created_at, updated_at
);

-- Updated Table
ALTER TABLE feedback ADD COLUMN citizen_id INT;
ALTER TABLE feedback ADD FOREIGN KEY (citizen_id) REFERENCES citizens(id);
```

## ✅ Next Steps

1. **Run Database Migration**
   ```bash
   mysql -u root lgu_infrastructure < database.sql
   ```

2. **Create Upload Directory**
   ```bash
   mkdir -p assets/img/citizen-ids
   chmod 755 assets/img/citizen-ids
   ```

3. **Test the Portal**
   - Go to http://localhost/ipms.lgu/
   - Click "Login" or "Register"
   - Try the registration flow

4. **Set Up Admin Verification**
   - Implement admin panel for verifying citizens
   - Refer to `/CITIZEN_ADMIN_GUIDE.md` for database queries

## 🎓 Testing Accounts

After database migration, test with:
- **Username:** citizen
- **Email:** citizen@ipms.local
- **Password:** admin123

*Note: This test account has status 'unverified' and needs admin approval to access the full dashboard.*

## 🔐 Security Features Implemented

✓ Strict 18+ age validation
✓ Strong password requirements
✓ Government ID verification system
✓ Unique email & ID number validation
✓ Photo upload with file validation
✓ Role-based access control
✓ Session management & timeout
✓ Login attempt tracking
✓ CSRF token generation
✓ Password hashing with bcrypt
✓ Read-only access to projects

## 🎯 Features by Role

### Citizens Can:
- ✓ Register with strict identity verification
- ✓ View all public projects
- ✓ Filter & search projects
- ✓ Track project progress
- ✓ Submit feedback/complaints
- ✓ Track complaint resolution
- ✓ View budget & expense transparency
- ✓ Manage own profile
- ✓ View own submissions only

### Citizens Cannot:
- ✗ Create or edit projects
- ✗ Access contractor/engineer data
- ✗ View other citizens' feedback
- ✗ Modify project information
- ✗ Access payment data
- ✗ Manage system users

## 📱 Responsive Design

Works great on all devices:
- Desktop (1024px+)
- Tablet (768px - 1024px)
- Mobile (< 768px)

## 🆘 Troubleshooting

**Citizen can't register:**
- Check file upload permissions
- Ensure MySQL is running
- Verify database schema updated

**Login not working:**
- Clear browser cookies
- Check citizens table for unverified accounts
- Verify password hash

**Pages not loading:**
- Check file paths are correct
- Verify `.php` file syntax
- Check browser console for errors

## 📞 Support Resources

- Setup Guide: `/CITIZEN_PORTAL_SETUP.md`
- Admin Guide: `/CITIZEN_ADMIN_GUIDE.md`
- Portal Scope: `/citizen/includes/scope.php`
- Database Schema: `/database.sql`

## 🚀 Future Enhancements

Consider implementing:
- Admin verification dashboard
- Email/SMS notifications
- Geographic project mapping
- Document uploads for complaints
- Two-factor authentication
- Mobile app integration
- QR code feedback submission
- Citizen feedback reports
- Video project progress updates
- Project timeline interactive map

---

**Happy deploying! Your citizens will love the transparency! 🎉**
