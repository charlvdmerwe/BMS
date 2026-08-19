# BMS — Business Management & Booking System

BMS is a lightweight, configurable **PHP/MySQL business booking system** designed for small service-based businesses.

It provides two main interfaces:

- A customer-facing website where clients can view services and book appointments.
- A business dashboard for viewing, adding, completing, deleting, filtering, and monitoring bookings.

Business-specific settings such as the business name, contact details, services, prices, opening days, booking slots, and database configuration are centralized in `config.php`.

## Features

### Customer Website

- Responsive landing page
- Business information and contact details
- About section
- Configurable service catalogue
- Configurable service pricing
- Online appointment booking
- Date-based appointment availability
- Configurable appointment time slots
- Automatically hides/removes booked time slots
- Prevents invalid booking submissions
- Customer name and phone number collection
- Optional booking notes
- Booking confirmation interface
- Phone-call links
- WhatsApp integration
- Embedded Google Maps location
- Responsive mobile navigation
- Reduced-motion accessibility support
- Responsive design for desktop, tablet, and mobile

### Business Dashboard

The dashboard provides a centralized interface for managing the business's appointments.

- Dashboard overview
- Booking statistics
- Booking list/table
- Date filtering
- Service filtering
- Add new bookings manually
- Support for bookings created by staff
- View booking information
- Mark bookings as completed
- Delete bookings
- Contact customers through WhatsApp
- Live booking updates/polling
- Appointment availability checking
- Monthly booking statistics
- Monthly revenue statistics
- Completed booking statistics
- Service performance statistics
- Service revenue breakdown
- Best-performing service information
- Business information/settings panel
- Modal-based booking forms
- Toast notifications
- Responsive dashboard layout

## Tech Stack

| Technology | Purpose |
|---|---|
| PHP | Server-side application logic |
| MySQL / MariaDB | Database |
| HTML5 | Application structure |
| CSS3 | Responsive UI and styling |
| JavaScript | AJAX, dynamic UI and dashboard functionality |
| Google Fonts / Inter | Typography |
| WhatsApp | Customer communication |

BMS does not require a PHP framework, Node.js, npm, Composer, or a frontend build system.

## Project Structure

```text
BMS/
├── config.php
├── index.php
├── dashboard.php
└── README.md
```

### `config.php`

Contains the application's deployment-specific configuration:

- Database connection
- Database/table name
- Business name
- Business tagline
- Business description
- Address
- Phone numbers
- Opening days
- Opening hours
- Closed days
- Services and prices
- Available appointment slots

The intention is that a new business deployment can be created by changing `config.php` rather than modifying the main application files.

### `index.php`

The public customer-facing website.

Responsibilities include:

- Rendering the business website
- Displaying services
- Displaying business information
- Rendering the booking form
- Checking appointment availability
- Creating customer bookings
- Displaying booking confirmation
- Generating phone and WhatsApp links
- Displaying the business location

### `dashboard.php`

The internal business management interface.

Responsibilities include:

- Loading bookings
- Displaying booking statistics
- Filtering bookings
- Adding bookings
- Completing bookings
- Deleting bookings
- Checking appointment availability
- Refreshing booking data
- Displaying service statistics
- Calculating revenue
- Providing customer contact actions

---

# Architecture

![BMS Architecture](docMedia/architecture.png)

---

# Booking Flow

## Customer Booking

1. Customer opens the public website.
2. Customer selects a service.
3. Customer selects a date.
4. BMS queries the database for bookings on that date.
5. Existing appointment times are identified as booked.
6. Available time slots are displayed.
7. Customer selects a time.
8. Customer enters their name and phone number.
9. Customer can optionally add notes.
10. The booking is submitted through AJAX.
11. The booking is stored in MySQL.
12. The customer receives an on-page confirmation.

The booking endpoint validates required fields and uses a prepared MySQL statement when inserting the booking.

## Availability

Availability is requested through:

```text
GET index.php?action=getSlots&date=YYYY-MM-DD
```

The endpoint returns:

- Requested date
- Booked slots
- Whether the day is fully booked
- Available slots

Example:

```json
{
  "success": true,
  "date": "2026-08-20",
  "bookedSlots": [
    "10:00",
    "14:00"
  ],
  "isFullyBooked": false,
  "availableSlots": [
    "09:00",
    "11:00",
    "12:00",
    "13:00",
    "15:00",
    "16:00",
    "17:00"
  ]
}
```

---

# Dashboard

The dashboard loads the booking records from the database and converts them into a frontend-friendly representation.

Each booking contains information such as:

```text
Booking ID
Customer name
Phone number
Service
Date
Time
Price
Completion status
Notes
```

Bookings can have the following application statuses:

```text
booked
complete
```

## Dashboard Actions

### Add Booking

Staff can create a booking directly from the dashboard.

This is useful for:

- Walk-in customers
- Phone bookings
- Bookings made outside the public website

Before creating a manual booking, the dashboard checks the selected date and time against existing bookings.

### Complete Booking

A booking can be marked as completed.

This updates the `Complete` field in the database.

### Delete Booking

A booking can be permanently removed from the database.

### Live Updates

The dashboard can periodically retrieve booking information so that the interface stays synchronized with the database without requiring a full page refresh.

---

# Database

BMS uses MySQL/MariaDB.

The default configuration expects:

```text
Database: dbBMS
Table:    tblbookings
```

## Booking Table

The application expects the following fields:

| Field | Description |
|---|---|
| `BookingID` | Unique booking identifier |
| `Name` | Customer first name |
| `Surname` | Customer surname |
| `ContactNum` | Customer phone number |
| `Date` | Appointment date |
| `Time` | Appointment time |
| `Price` | Service price |
| `Complete` | Booking completion flag |
| `ExtraInfo` | Service and customer notes |

## Example Database Schema

```sql
CREATE DATABASE dbBMS;

USE dbBMS;

CREATE TABLE tblbookings (
    BookingID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(100) NOT NULL,
    Surname VARCHAR(100) DEFAULT '',
    ContactNum VARCHAR(50) NOT NULL,
    Date DATE NOT NULL,
    Time TIME NOT NULL,
    Price DECIMAL(10,2) NOT NULL DEFAULT 0,
    Complete TINYINT(1) NOT NULL DEFAULT 0,
    ExtraInfo TEXT
);
```

---

# Installation

## Requirements

A server running BMS should provide:

- PHP 7.4 or newer
- MySQL 5.7+ or MariaDB
- PHP MySQLi extension
- Apache, Nginx, or another PHP-compatible web server

For local development, BMS can be run using:

- XAMPP
- WAMP
- Laragon
- Apache + PHP + MySQL
- Nginx + PHP-FPM + MySQL

## 1. Clone the Repository

```bash
git clone https://github.com/charlvdmerwe/BMS.git
cd BMS
```

## 2. Create the Database

Create the database and booking table using the SQL schema above.

## 3. Configure the Database

Open:

```text
config.php
```

Configure:

```php
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'dbBMS';
$dbTable = 'tblbookings';
```

Use a dedicated database user rather than `root` for production.

## 4. Configure the Business

Update the business information in `config.php`:

```php
$businessName = 'Your Business Name';
$businessTagline = 'Book an appointment online in a few clicks.';
$businessBlurb = 'Tell customers about your business here.';
$businessAddress = '123 Main Street, Your City';
$businessPhone = '000 000 0000';
$businessPhoneIntl = '00000000000';

$businessOpenDays = 'Mon – Fri';
$businessHours = '09:00 – 17:00';
```

## 5. Configure Closed Days

The application uses PHP's `date('w')` numbering:

```text
0 = Sunday
1 = Monday
2 = Tuesday
3 = Wednesday
4 = Thursday
5 = Friday
6 = Saturday
```

For example:

```php
$businessClosedDays = [0, 6];
```

This disables Sunday and Saturday in the booking calendar.

## 6. Configure Services

Services and their prices are configured as an associative array:

```php
$services = [
    'Service One'   => 100,
    'Service Two'   => 150,
    'Service Three' => 200,
    'Service Four'  => 250,
];
```

The service selected by a customer is used to determine the booking price.

## 7. Configure Appointment Slots

Available times are defined in:

```php
$timeSlots = [
    '09:00',
    '10:00',
    '11:00',
    '12:00',
    '13:00',
    '14:00',
    '15:00',
    '16:00',
    '17:00'
];
```

These slots are used by both the customer booking interface and the dashboard.

## 8. Start the Server

For XAMPP, place the project inside:

```text
C:\xampp\htdocs\BMS\
```

Start:

```text
Apache
MySQL
```

Then open:

```text
http://localhost/BMS/
```

Customer website:

```text
http://localhost/BMS/index.php
```

Dashboard:

```text
http://localhost/BMS/dashboard.php
```

---

# Configuration Reference

| Variable | Description |
|---|---|
| `$dbHost` | MySQL hostname |
| `$dbUser` | MySQL username |
| `$dbPass` | MySQL password |
| `$dbName` | Database name |
| `$dbTable` | Booking table |
| `$businessName` | Business display name |
| `$businessTagline` | Main website tagline |
| `$businessBlurb` | Business description |
| `$businessAddress` | Business address |
| `$businessPhone` | Display phone number |
| `$businessPhoneIntl` | International phone number used for WhatsApp |
| `$businessOpenDays` | Displayed opening days |
| `$businessHours` | Displayed opening hours |
| `$businessClosedDays` | Calendar-disabled days |
| `$services` | Services and prices |
| `$timeSlots` | Available appointment times |

---

# AJAX Endpoints

BMS uses AJAX requests to make the booking and dashboard interfaces interactive without requiring a complete page reload.

## Customer Booking

```text
POST index.php
```

Required fields include:

```text
ajax
name
phone
service
date
time
notes
```

A successful request returns:

```json
{
  "success": true
}
```

## Check Availability

```text
GET index.php?action=getSlots&date=YYYY-MM-DD
```

Returns booked and available appointment slots.

## Dashboard Booking List

```text
GET dashboard.php?ajax=1&action=list
```

Returns the current bookings as JSON.

## Dashboard Actions

The dashboard supports POST actions including:

```text
add
complete
delete
```

The `add` action creates a new booking.

The `complete` action marks an existing booking as completed.

The `delete` action removes an existing booking.

---

# Security

BMS is currently a lightweight application/template and should be hardened before production deployment.

## Dashboard Authentication

The current dashboard does **not** include a dedicated login/authentication layer.

Before exposing `dashboard.php` to the public internet, add authentication and authorization.

Possible approaches include:

- PHP sessions with password authentication
- Server-level authentication
- Integration with an existing identity provider
- Role-based access control

## Database Credentials

Do not commit real production credentials to a public repository.

For production, consider:

- Environment variables
- A server-only configuration file
- Secret management provided by the hosting environment

## Database User

Avoid using:

```text
root
```

in production.

Create a dedicated database user with only the permissions BMS requires.

## HTTPS

Use HTTPS in production because the application handles customer contact information.

## CSRF Protection

The AJAX endpoints should ideally include CSRF protection before being exposed publicly.

## Input Validation

The application performs basic required-field validation and uses prepared statements for database operations.

For production, additional validation should be considered for:

- Phone numbers
- Dates
- Times
- Service names
- Notes
- Request frequency

## Booking Race Conditions

The availability check occurs before the booking is created. For a production system with multiple simultaneous users, the database should also enforce booking uniqueness or use a transaction/locking strategy to prevent two users from successfully booking the same slot at exactly the same time.

---

# Customization

The project is designed around a configuration-first deployment model.

For a new business, the primary configuration workflow is:

```text
1. Create database
        ↓
2. Configure config.php
        ↓
3. Add services and prices
        ↓
4. Configure opening hours
        ↓
5. Configure appointment slots
        ↓
6. Deploy PHP application
        ↓
7. Test customer booking
        ↓
8. Test dashboard
```

The main application files should generally not need to be changed for a standard business deployment.

---

# Current Limitations

- No built-in dashboard authentication
- No role-based permissions
- No online payment processing
- No automated email confirmations
- No automated SMS notifications
- No automated WhatsApp notifications
- Services are configured in PHP rather than managed through the dashboard
- Business settings are configured in `config.php`
- No customer account system
- No customer self-service cancellation/rescheduling
- No built-in calendar integration
- No database-level unique constraint for appointment slots
- No dedicated API layer
- No automated backup system

---

# Future Improvements

Potential future features include:

- Admin login
- Multiple dashboard users
- Role-based permissions
- Customer accounts
- Email booking confirmations
- SMS notifications
- WhatsApp notifications
- Appointment reminders
- Online payments
- Customer cancellation/rescheduling
- Google Calendar integration
- Outlook Calendar integration
- Database-driven services
- Database-driven business settings
- Recurring appointments
- Staff/member scheduling
- Multiple businesses/tenants
- Stronger CSRF protection
- Rate limiting
- Audit logs
- Automated database backups
- REST API
- Docker deployment
- Environment-based configuration
- Automated testing
- CI/CD

---

# Deployment Checklist

Before deploying BMS publicly:

- [ ] Create production database
- [ ] Create dedicated database user
- [ ] Configure production database credentials
- [ ] Configure business information
- [ ] Configure services and prices
- [ ] Configure opening hours
- [ ] Configure appointment slots
- [ ] Enable HTTPS
- [ ] Protect `dashboard.php`
- [ ] Add CSRF protection
- [ ] Validate all production inputs
- [ ] Test customer bookings
- [ ] Test duplicate-slot handling
- [ ] Test dashboard booking creation
- [ ] Test booking completion
- [ ] Test booking deletion
- [ ] Test WhatsApp links
- [ ] Test mobile layout
- [ ] Configure database backups
- [ ] Remove development credentials

---

# Author

**Charl van der Merwe**

GitHub: [@charlvdmerwe](https://github.com/charlvdmerwe)

Repository: https://github.com/charlvdmerwe/BMS

---

Built with PHP, MySQL, HTML, CSS and JavaScript.
