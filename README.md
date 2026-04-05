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

## ✨ Features

### 👤 Passenger Module

* 🔍 Live train search (no page reload)
* 🎟️ Ticket booking with seat selection
* 📜 Booking history tracking
* ❌ Cancellation requests
* 🆘 Help Center support

---

### 🧑‍💼 Clerk Module

* 📊 Dashboard with real-time stats
* 🧾 Manual booking entry
* 👀 Live booking monitor
* 📥 View and manage passenger requests
* 🔓 Seat release functionality
* 🚪 Secure login/logout system

---

### 🛠️ Admin Module

* 📈 System overview dashboard
* 🚆 Fleet management (sort by busiest trains)
* 👨‍💻 Staff management (track active clerks)
* 🔗 Integration with **MIQSM Python module**
* 🔐 Role-based authentication and access control

---

### ⚙️ Core System Features

* 🔒 **Two-Phase Locking (2PL)** for concurrency control
* 💾 Oracle 21c database integration
* 🔄 Real-time updates and state handling
* 🧠 Python-based queue and request simulation

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
