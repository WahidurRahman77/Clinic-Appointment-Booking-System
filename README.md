# 🏥 Clinic Appointment Booking System

Welcome to the **Clinic Appointment Booking System**!  
A powerful, easy-to-use web application for clinics that streamlines doctor schedules, patient registration, and appointment management.

---

## 🚀 Overview

This system enables clinics of all sizes to digitize their operations—patients can register, book appointments, and doctors can manage their schedules efficiently. Built with PHP and MySQL, designed to run seamlessly on **XAMPP**.

---

## 🎯 Features

- **User Registration:** Secure sign-up and login for patients and admins.
- **Doctor Management:** Add, edit, and remove doctors; assign specializations.
- **Booking Appointments:** Patients easily book, view, or cancel appointments.
- **Admin Dashboard:** Comprehensive management of appointments, doctors, and patients.
- **Notification System:** Receive alerts for appointment confirmations and cancellations.
- **Responsive Design:** User-friendly interface across all devices.
- **Secure Authentication:** Password encryption and session protection.

---

## 🛠️ Technologies

- **Frontend:** HTML5, CSS3, JavaScript, Bootstrap 5  
- **Backend:** PHP 7+
- **Database:** MySQL (**XAMPP Only**)
- **Web Server:** Apache (via XAMPP)

---

## ⚡ Installation Guide (XAMPP)

1. **Download & Install [XAMPP](https://www.apachefriends.org/index.html)**
2. **Start Apache & MySQL** from the XAMPP Control Panel.
3. **Import the Database:**
   - Open `phpMyAdmin` (`http://localhost/phpmyadmin`)
   - Create a database, e.g., `clinic_appointment`
   - Import `clinic_appointment.sql` from the repository.
4. **Configure Database Connection:**
   - Update your PHP DB config:
     ```php
     $conn = mysqli_connect("localhost", "root", "", "clinic_appointment");
     ```
5. **Place Files:**
   - Copy all files into your XAMPP `htdocs` directory:
     ```
     C:\xampp\htdocs\Clinic-Appointment-Booking-System
     ```
6. **Run the Application:**
   - Visit: `http://localhost/Clinic-Appointment-Booking-System/`

---

## 📁 Project Structure

```plaintext
Clinic-Appointment-Booking-System/
├── assets/                # CSS, JS, images
├── config/                # DB connection files
├── admin/                 # Admin panel
├── doctor/                # Doctor pages
├── patient/               # Patient dashboard
├── appointment/           # Booking management
├── clinic_appointment.sql # Database dump
├── index.php              # Entry point
└── README.md
```

---

## ⚠️ SQL Compatibility

> **Important:**  
> This system is designed and fully tested for **MySQL on XAMPP**.  
> ⚡ Not guaranteed to work on other database servers (e.g., PostgreSQL, MSSQL).

---

## 👩‍⚕️ Usage

- **Patients:** Register and log in to book appointments.
- **Admins:** Sign in to manage doctors, appointments, and patient records.
- **Doctors:** View and manage their appointments and schedules.

---

## 🤝 Contributing

Contributions are welcome!  
- Fork the repo, make changes, and submit a pull request.
- Ensure compatibility with **XAMPP/MySQL** for database changes.

---

## 📜 License

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

This project is open-sourced under the [MIT License](LICENSE).

> Please review the LICENSE file for additional terms and contact information.

---

## 🙏 Acknowledgments

- [Bootstrap](https://getbootstrap.com/)
- [XAMPP](https://www.apachefriends.org/index.html)
- The PHP community

---

> _Designed for simplicity. Built for clinics. Powered by Wahidur Rahman._
