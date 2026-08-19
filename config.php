<?php
/**
 * Template configuration.
 * Edit these values for each new business deployment — nothing else in
 * index.php or dashboard.php should need to change.
 */

// Database
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'dbBMS';
$dbTable = 'tblbookings';

// Business details
$businessName = 'Your Business Name';
$businessTagline = 'Book an appointment online in a few clicks.';
$businessBlurb = 'Tell customers a little about your business here — what you offer, what makes you different, and why they should book with you.';
$businessAddress = '123 Main Street, Your City';
$businessPhone = '000 000 0000';
$businessPhoneIntl = '00000000000'; // digits only, with country code — used for WhatsApp links
$businessOpenDays = 'Mon – Fri';
$businessHours = '09:00 – 17:00';
$businessClosedDays = [0, 6]; // 0 = Sunday ... 6 = Saturday, used to grey out the calendar

// Services offered and their prices
$services = [
    'Service One'   => 100,
    'Service Two'   => 150,
    'Service Three' => 200,
    'Service Four'  => 250,
    'Service Five'  => 300,
    'Service Six'   => 350,
];

// Available booking time slots
$timeSlots = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
