# 🚆 Railway Reservation System (RRS)

A **full-featured Railway Reservation System** built with **PHP, Oracle 21c, and Python integration**, supporting real-time ticket booking, concurrency control, and administrative monitoring.

---

## 📌 Overview

The Railway Reservation System simulates a real-world railway booking platform, offering:

* Passenger ticket booking and management
* Clerk-assisted booking and seat allocation
* Admin dashboard for system monitoring and staff management
* Concurrency control via **Two-Phase Locking (2PL)**
* Integration with a Python module (**MIQSM**) for queue and request handling
* Oracle 21c database backend

---

## ✨ Features (Updated for v1.1)

### 👤 Passenger Module

* 🔍 Live train search (no page reload)
* 🎟️ Ticket booking with seat selection
* 📜 Booking history (now shows base fare and passenger summary)
* ❌ Cancellation requests
* 🆘 Help center
* 🏷️ **Passenger-wise concession flow**:
  - Supports age and military status per passenger
  - Applies concession rules before class selection
  - Shows per-passenger fare breakdown
  - Prevents selecting more/fewer seats than passenger count

### 🧑‍💼 Clerk Module

* 📊 Dashboard with real-time stats
* 🧾 Manual booking entry
* 👀 Live booking monitor
* 📥 View and manage requests
* 🔓 Seat release functionality
* 🚪 Secure login/logout system

### 🛠️ Core System Updates in v1.1

* TwoPL booking now fully integrates **manifest-based seat allocation**
* **Shared discount and pricing logic** centralized in `TwoPL/train_data.php`
* Booking server-side validation ensures seat-count matches passenger manifest
* Booking history now includes **concession breakdown**

---

## 🧱 Tech Stack

| Layer         | Technology                         |
| ------------- | ---------------------------------- |
| Frontend      | HTML, CSS, JavaScript              |
| Backend       | PHP                                |
| Database      | Oracle 21c                         |
| Concurrency   | 2PL (Two-Phase Locking)            |
| Python Module | MIQSM (Queue + Request Simulation) |

---

## 📂 Project Structure
```bash
RailwaySystem/
│
├─ MIQSM_Module/          # Core modules & web interface
│   ├─ config/
│   ├─ models/
│   ├─ modules/
│   ├─ utils/
│   ├─ templates/
│   └─ main.py
│
├─ TwoPL/                 # Two-phase locking modules
├─ staff/                 # Clerk/Admin PHP scripts
├── *.php                 # Core backend files
├── style.css             # Styling
└─ README.md
```
---

## 🚀 Getting Started

### 1️⃣ Clone the repository

```bash
git clone https://github.com/TheSunGodNikaa/RailwaySystem.git
cd RailwaySystem
```
## Setup PHP Server (XAMPP)

Place the project in:
```bash
C:\xampp\htdocs\
```
Start Apache
System uses Oracle 21c as the database

## Run the PHP Project

Open your browser:
```bash
http://localhost/railway_auth
```
## Run Python Module 
```bash
cd MIQSM_Module
pip install -r requirements.txt
python web_interface.py
```

## 🔐 Authentication Roles

| Role      | Access                         |
| --------- | ------------------------------ |
| Passenger | Booking, history, cancellation |
| Clerk     | Booking management, monitoring |
| Admin     | Full system control            |

---

## 🧠 Key Concepts Implemented

* Two-Phase Locking (2PL)
* Concurrency control for seat allocation
* Real-time UI updates without page reload
* Role-based authentication
* Queue simulation using Python

---

## 🛠️ Future Improvements

* 💳 Payment gateway integration
* 📱 Mobile app version
* 🌐 API-based architecture
* 🔔 Notifications system
* 📊 Advanced analytics dashboard

---

## 👨‍💻 Author

**Aditya Ramachandran** – [GitHub Profile](https://github.com/TheSunGodNikaa)

---

## 📄 License

This project is for **educational purposes only**.
