# IPMS Deployment Guide

This project can run in two environments using the same codebase:

- Local testing: XAMPP at `http://localhost/ipms.lgu`
- Web deployment: CyberPanel/InDevFinite hosting at your domain or subfolder

The environment is controlled by the `.env` file.

## 1. Local XAMPP Setup

Keep this `.env` in the project root for local work:

```env
APP_ENV=local
APP_BASE_PATH=/ipms.lgu

DB_HOST=localhost
DB_NAME=lgu_infrastructure
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
```

Local URL:

```text
http://localhost/ipms.lgu
```

## 2. Production Hosting Setup

On CyberPanel/InDevFinite, create a new MySQL database and database user.

Then create a production `.env` in the uploaded project root:

```env
APP_ENV=production
APP_BASE_PATH=/

DB_HOST=localhost
DB_NAME=your_hosting_database_name
DB_USER=your_hosting_database_user
DB_PASS=your_hosting_database_password
DB_CHARSET=utf8mb4
```

Use `APP_BASE_PATH=/` when the app is uploaded to the website root:

```text
https://yourdomain.com/
```

Use a subfolder base path if uploaded into a subfolder:

```env
APP_BASE_PATH=/ipms
```

Example:

```text
https://yourdomain.com/ipms/
```

## 3. Files To Upload

Upload the project files to the hosting document root.

Usually CyberPanel paths look similar to:

```text
/home/example.com/public_html/
```

Upload these folders/files:

```text
admin/
api/
assets/
auth/
bac/
citizen/
contractor/
database/
engineer/
includes/
public/
superadmin/
uploads/
index.php
landing.php
.htaccess
.env
```

Do not upload local-only temporary files.

## 4. Database Import

For a fresh production database, import:

```text
database.sql
```

Then apply newer migrations if needed:

```text
database/migrations/connect_role_workflow_tables.sql
database/migrations/seed_engineer_contractor_accounts.sql
```

Important: if your hosting database name is not `lgu_infrastructure`, remove or edit `USE lgu_infrastructure;` in SQL files before importing through phpMyAdmin.

## 5. Folder Permissions

The `uploads/` folder must be writable by PHP.

Recommended permission:

```text
755 or 775
```

If uploads fail, adjust the folder permission from CyberPanel File Manager.

## 6. Test Accounts

Admin:

```text
username: admin
password: admin123
```

BAC:

```text
username: bac
password: bac123
```

Engineers:

```text
engineer / engineer123
engineer_roads / engineer123
engineer_structural / engineer123
engineer_site / engineer123
engineer_electrical / engineer123
```

Contractors:

```text
contractor / contractor123
contractor_abc / contractor123
contractor_xyz / contractor123
contractor_delta / contractor123
contractor_omega / contractor123
```

## 7. Testing After Upload

Open:

```text
https://yourdomain.com/api/test.php
```

Expected result:

```json
{
  "status": "ok",
  "message": "Database connection successful"
}
```

After confirming the test works, it is safer to disable or remove `api/test.php` for production.

## 8. How Local And Web Work Together

Local development:

```text
APP_ENV=local
APP_BASE_PATH=/ipms.lgu
DB_NAME=lgu_infrastructure
```

Production deployment:

```text
APP_ENV=production
APP_BASE_PATH=/
DB_NAME=hosting_database_name
```

The PHP code reads `.env`, so the same code can run in both places without manually editing `includes/config.php`.

## 9. Recommended Deployment Workflow

1. Edit and test code locally in XAMPP.
2. Export or migrate database changes.
3. Upload changed PHP/JS/CSS files to hosting.
4. Apply new SQL migrations on production.
5. Test login by role.
6. Test the main flow:
   - Admin creates/approves project
   - BAC posts bidding and recommends award
   - Contract is created
   - Contractor submits report/payment request
   - Engineer inspects/reviews
   - Admin approves payment
