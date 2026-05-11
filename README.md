# PWA-HR

PWA-HR is a lightweight HRIS web application built with **native PHP** and **MySQL**. It covers essential HR workflows such as camera-based attendance, payroll management, reimbursements, internal chat, employee data management, and employee payslips.

The application is designed as a **Progressive Web App (PWA)**, so it can be accessed from desktop and mobile browsers and installed to supported devices.

## Preview

> Screenshots can be placed in the `/screenshots` folder.

| Login | Admin Dashboard | Employee Attendance |
| --- | --- | --- |
| `screenshots/login-page.png` | `screenshots/dashboard-karyawan.png` | `screenshots/absensi-page.png` |

| Payroll | Reimburse | Internal Chat |
| --- | --- | --- |
| `screenshots/payroll.png` | `screenshots/reimburse.png` | `screenshots/chat-page.png` |

## Main Features

- Role-based login for Admin, HRD, and Employee users.
- Admin and employee dashboards.
- Employee management with department, position, shift, and contract data.
- Camera attendance with GPS validation.
- Attendance history and monthly recap.
- Manual payroll management controlled by Admin/HRD.
- Employee payslip view.
- Reimbursement submission and approval.
- Leave and overtime workflows.
- Internal HR chat.
- Notification page for employees.
- Responsive UI for desktop and mobile.
- PWA support with manifest, service worker, install prompt, and offline page.

## User Roles

### Admin

- Manage employees.
- Manage departments and shifts.
- View attendance recap.
- Approve or reject leave, overtime, and reimbursements.
- Manage payroll and payslips.
- Access internal chat.

### HRD

- Access HR operational pages.
- Manage employee-related workflows.
- Manage payroll and reimbursement approvals.
- Communicate with employees through chat.

### Karyawan

- Access employee dashboard.
- Submit camera attendance.
- Submit leave, overtime, and reimbursement requests.
- View personal payslips.
- Update profile.
- Use internal chat and notifications.

## Tech Stack

| Area | Technology |
| --- | --- |
| Backend | Native PHP |
| Database | MySQL / MariaDB |
| Frontend | HTML, CSS, Vanilla JavaScript |
| Database Driver | MySQLi |
| PWA | Web App Manifest, Service Worker, Cache API |
| Attendance | HTML5 Camera API, Geolocation API |
| Styling | Custom CSS |
| Local Server | XAMPP / Laragon |

## Important Folder Structure

```text
pwa-hr/
|-- api/
|   `-- chat.php
|-- assets/
|   |-- css/
|   |   `-- app.css
|   |-- icons/
|   |-- js/
|   |   `-- app.js
|   `-- uploads/
|       `-- absensi/
|-- config/
|   `-- database.php
|-- includes/
|   |-- header.php
|   `-- footer.php
|-- pages/
|   |-- admin/
|   |   |-- dashboard.php
|   |   |-- karyawan.php
|   |   |-- absensi.php
|   |   |-- penggajian.php
|   |   |-- reimburse.php
|   |   |-- chat.php
|   |   |-- departemen.php
|   |   `-- shift.php
|   `-- karyawan/
|       |-- dashboard.php
|       |-- absensi.php
|       |-- slip_gaji.php
|       |-- reimburse.php
|       |-- chat.php
|       `-- profil.php
|-- screenshots/
|-- database.sql
|-- alter_tables.sql
|-- index.php
|-- login.php
|-- logout.php
|-- manifest.json
|-- offline.html
`-- sw.js
```

## Installation

### XAMPP

1. Clone this repository into the XAMPP `htdocs` directory.

```bash
cd C:\xampp\htdocs
git clone https://github.com/imaddudinghozali/pwa-hr.git
```

2. Start **Apache** and **MySQL** from XAMPP Control Panel.

3. Open phpMyAdmin.

```text
http://localhost/phpmyadmin
```

4. Import [database.sql](database.sql).

5. If needed, apply [alter_tables.sql](alter_tables.sql) after the main database import.

6. Configure database credentials in [config/database.php](config/database.php).

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'simk_pha');
```

7. Open the application.

```text
http://localhost/pwa-hr/
```

### Laragon

1. Clone this repository into the Laragon `www` directory.

```bash
cd C:\laragon\www
git clone https://github.com/imaddudinghozali/pwa-hr.git
```

2. Start **Apache/Nginx** and **MySQL** from Laragon.

3. Import [database.sql](database.sql) through phpMyAdmin, Adminer, or another MySQL client.

4. Configure [config/database.php](config/database.php).

5. Open the project from the generated local URL or:

```text
http://localhost/pwa-hr/
```

## Demo Login

The database seed contains demo user identities for testing:

| Role | NIP | Email |
| --- | --- | --- |
| Admin | `ADM001` | `admin@pestahijau.co.id` |
| HRD | `HRD001` | `sari.dewi@pestahijau.co.id` |
| Karyawan | `EMP001` | `andi.k@pestahijau.co.id` |
| Karyawan | `EMP002` | `budi.s@pestahijau.co.id` |
| Karyawan | `EMP003` | `rina.m@pestahijau.co.id` |

For deployment-ready usage, passwords should be configured securely by the administrator before publishing the application.

## Feature Overview

### Camera Attendance + GPS

Employees can submit attendance using the device camera. The attendance flow validates GPS location against the assigned office or department radius, requires a valid JPEG photo, and stores attendance records with timestamp, distance, and device information.

### Payroll

Payroll is controlled manually by Admin/HRD. The system calculates the final total from HR-controlled components such as base salary, allowances, bonus, overtime amount, deductions, and notes.

### Reimburse

Employees can submit reimbursement requests with category, amount, description, and proof attachment. Admin/HRD can review, approve, reject, and add approval notes.

### Internal Chat

The application includes a simple internal chat module for communication between HR/Admin and employees.

### Payslip

Employees can view their own payslips, while Admin/HRD can manage payroll records and view payslip details.

## Responsive and PWA Support

PWA-HR includes:

- Responsive layout for desktop, tablet, and mobile.
- Web App Manifest.
- Service Worker.
- Offline page.
- Install prompt support.
- Mobile-friendly navigation and tables.

For production, HTTPS is recommended so camera, geolocation, and PWA behavior work reliably.

## Screenshots

Add project screenshots to:

```text
screenshots/
```

Recommended files:

- `screenshots/login-page.png`
- `screenshots/dashboard-karyawan.png`
- `screenshots/employee-dashboard.png`
- `screenshots/absensi-page.png`
- `screenshots/payroll.png`
- `screenshots/reimburse.png`
- `screenshots/chat-page.png`

## Roadmap V2

- Push notification.
- Android/iOS APK build.
- Excel import for employee and payroll data.
- More advanced multi-branch support.
- More detailed audit log.
- Improved reporting and analytics.
- Additional payroll components and approval workflow.

## Security Notes

- Use HTTPS in production.
- Change all default/demo credentials before public deployment.
- Restrict upload directory execution.
- Keep database credentials outside public repositories for production.
- Add CSRF protection before exposing write actions to public networks.
- Backup the database regularly.
- Review server permissions for upload and cache folders.

## License

This project is provided for portfolio and educational purposes. Add a license file before using it for commercial or production distribution.

## Author

**Imaddudin Ghozali**

- GitHub: [imaddudinghozali](https://github.com/imaddudinghozali)
