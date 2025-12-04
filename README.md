# 🏥 Medicare Portal - Appointment Booking System

Welcome to the **Medicare Portal**!
A secure and robust full-stack web application that connects patients with doctors. It streamlines the entire process of scheduling, confirming, and managing medical appointments using automated logic and secure authentication.

![image alt](https://github.com/WahidurRahman77/Clinic-Appointment-Booking-System/blob/531da81b41ca14cef69ebd68437d5bc13b83ac95/Pictures/readmepic.png)

---

## 🚀 Overview

This system digitizes clinic operations by offering distinct dashboards for **Doctors** and **Patients**. It features an automated time-slot generator for doctors and a conflict-free booking system for patients. Built with **Core PHP** and **MySQL**, it is optimized to run seamlessly on a local **XAMPP** environment.

---

## 🎯 Key Features

- **👥 Role-Based Access:** Distinct secure login/signup flows for **Patients** and **Doctors**.
- **📅 Dynamic Schedule Management:** Doctors can input start/end times, and the system automatically generates 15-minute appointment slots.
- **✅ Appointment Workflow:**
    - Patients can filter doctors by specialty and request appointments.
    - Doctors can **Confirm** or **Cancel** requests via their dashboard.
    - Real-time status updates (Pending, Confirmed, Canceled).
- **🔒 Security & Recovery:**
    - Secure password hashing (`password_hash`).
    - **Forgot Password System:** Integrated **Brevo (Sendinblue) API** to send real 6-digit OTPs via email for password resets.
- **🚫 Concurrency Handling:** Prevents double-booking by locking slots immediately upon request.
- **📱 Responsive UI:** Custom CSS design with FontAwesome icons and AOS (Animate On Scroll) animations.

---

## 🛠️ Technologies

- **Frontend:** HTML5, CSS3 (Custom), JavaScript, FontAwesome, AOS Library.
- **Backend:** Native PHP (7.4+)
- **Database:** MySQL (Relational DB with Transactions)
- **API Integration:** Brevo (formerly Sendinblue) for SMTP Email logic.
- **Web Server:** Apache (via XAMPP)

---

## ⚡ Installation Guide (XAMPP)

1. **Download & Install [XAMPP](https://www.apachefriends.org/index.html)**
2. **Start Apache & MySQL** from the XAMPP Control Panel.
3. **Database Setup:**
   - Open `phpMyAdmin` (`http://localhost/phpmyadmin`)
   - Create a new database named **`portal_db`** (Important: Must match `login.php` config).
   - Import the provided SQL file (if available) or create tables for `users`, `appointment_slots`, `appointments`, and `password_resets`.
4. **Configure API (Optional):**
   - To make the "Forgot Password" feature work, open `forgot_password.php`.
   - Replace the `$api_key` variable with your own **Brevo/Sendinblue API Key**.
5. **Place Files:**
   - Copy all project files into a folder in your `htdocs` directory:
     ```
     C:\xampp\htdocs\Medicare-Portal
     ```
6. **Run the Application:**
   - Visit: `http://localhost/Medicare-Portal/Home Page.html`

---

## 📁 Project Structure

```plaintext
Medicare-Portal/
├── Home Page.html        # Landing page with animations
├── login.php             # Universal login (Doctor/Patient)
├── signup.php            # Registration logic
├── doctor_dashboard.php  # Doctor: Create slots & view status
├── patient_dashboard.php # Patient: Book slots & view history
├── manage_slots.php      # Doctor: Delete slots logic
├── view_requests.php     # Doctor: Approve/Reject appointments
├── forgot_password.php   # API logic for sending OTP
├── verify_otp.php        # Validate email OTP
├── reset_password.php    # Set new password
└── logout.php            # Session destruction
```

---

## ⚠️ SQL Compatibility

> **Important:**  
> This system is designed and fully tested for **MySQL on XAMPP**.  
> ⚡ Not guaranteed to work on other database servers (e.g., PostgreSQL, MSSQL).
> [Brevo](https://www.brevo.com/) for Email API services(forgot and reset password OTP).

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
- [Brevo](https://www.brevo.com/) for Email API services.
- The PHP community

---

> _Designed for simplicity. Built for clinics. Powered by Wahidur Rahman._
