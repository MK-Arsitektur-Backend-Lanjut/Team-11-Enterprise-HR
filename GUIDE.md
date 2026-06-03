# 📘 Enterprise HR API — Postman Guide

Dokumentasi lengkap untuk menjalankan dan menguji semua endpoint API pada sistem **Enterprise HR** menggunakan Postman.

---

## 📋 Daftar Isi

- [Setup Awal](#-setup-awal)
- [Daftar Semua URL Endpoint](#-daftar-semua-url-endpoint)
- [Autentikasi (JWT)](#-autentikasi-jwt)
- [Module 1 — Auth](#-module-1--auth)
- [Module 2 — Employee Management](#-module-2--employee-management)
- [Module 3 — Leave Request & Approval Workflow](#-module-3--leave-request--approval-workflow)
- [Module 4 — Attendance & Reporting](#-module-4--attendance--reporting)
- [Module 5 — Leave Data (Attendance Module)](#-module-5--leave-data-attendance-module)
- [Hierarki Organisasi](#-hierarki-organisasi)
- [Skenario Testing End-to-End](#-skenario-testing-end-to-end)

---

## 🗂 Daftar Semua URL Endpoint

> Base URL: `http://127.0.0.1:8003/api`

### 🔓 Public (Tanpa Token)

| No | Method   | URL                  | Deskripsi                |
|----|----------|----------------------|--------------------------|
| 1  | `POST`   | `/api/register`      | Register karyawan baru   |
| 2  | `POST`   | `/api/login`         | Login & dapatkan JWT     |

### 🔑 Auth

| No | Method   | URL                  | Deskripsi                |
|----|----------|----------------------|--------------------------|
| 3  | `GET`    | `/api/me`            | Get current user profile |
| 4  | `POST`   | `/api/logout`        | Logout (invalidate JWT)  |
| 5  | `POST`   | `/api/refresh`       | Refresh JWT token        |

### 👥 Employee Management

| No | Method   | URL                                    | Deskripsi                      |
|----|----------|----------------------------------------|--------------------------------|
| 6  | `GET`    | `/api/employees`                       | List semua karyawan (paginated)|
| 7  | `GET`    | `/api/employees/statistics`            | Statistik karyawan             |
| 8  | `POST`   | `/api/employees`                       | Tambah karyawan baru           |
| 9  | `GET`    | `/api/employees/{id}`                  | Detail karyawan by ID          |
| 10 | `PUT`    | `/api/employees/{id}`                  | Update data karyawan           |
| 11 | `DELETE` | `/api/employees/{id}`                  | Hapus karyawan                 |
| 12 | `GET`    | `/api/employees/{id}/subordinates`     | Lihat bawahan langsung         |
| 13 | `GET`    | `/api/employees/{id}/hierarchy`        | Lihat hierarki (atasan + bawahan)|
| 14 | `PUT`    | `/api/employees/{id}/leave-balance`    | Update saldo cuti karyawan     |

### 📝 Leave Request

| No | Method   | URL                            | Deskripsi                        |
|----|----------|---------------------------------|----------------------------------|
| 15 | `POST`   | `/api/leaves`                  | Submit pengajuan cuti            |
| 16 | `GET`    | `/api/leaves/my-requests`      | Riwayat cuti saya + saldo       |
| 17 | `GET`    | `/api/leaves/subordinates`     | Cuti bawahan (untuk Manager)     |

### ✅ Approval Workflow

| No | Method   | URL                                          | Deskripsi                        |
|----|----------|----------------------------------------------|----------------------------------|
| 18 | `GET`    | `/api/approvals/pending`                     | Approval yang menunggu saya      |
| 19 | `POST`   | `/api/approvals/level-1/{leave_request_id}`  | Approve/Reject Level 1 (Manager) |
| 20 | `POST`   | `/api/approvals/level-2/{leave_request_id}`  | Approve/Reject Level 2 (Director)|

### ⏰ Attendance (v1)

| No | Method   | URL                                    | Deskripsi                       |
|----|----------|----------------------------------------|---------------------------------|
| 21 | `POST`   | `/api/v1/attendance/clock-in`          | Clock in hari ini               |
| 22 | `POST`   | `/api/v1/attendance/clock-out`         | Clock out hari ini              |
| 23 | `GET`    | `/api/v1/attendance/me`                | Kehadiran saya                  |
| 24 | `GET`    | `/api/v1/attendance/report`            | Report kehadiran (HR: semua)    |
| 25 | `GET`    | `/api/v1/attendance/report/export`     | Export report ke CSV            |

### 📊 Leave Data (v1)

| No | Method   | URL                                    | Deskripsi                       |
|----|----------|----------------------------------------|---------------------------------|
| 26 | `GET`    | `/api/v1/leaves/me`                    | Data cuti saya                  |
| 27 | `GET`    | `/api/v1/leaves`                       | List semua data cuti            |
| 28 | `GET`    | `/api/v1/leaves/{id}`                  | Detail cuti by ID               |
| 29 | `GET`    | `/api/v1/leaves/export/payroll`        | Export cuti untuk payroll       |
| 30 | `POST`   | `/api/v1/leaves/sync`                  | Sync data cuti dari approval    |

> **Total: 30 endpoint** — 2 public, 28 protected (memerlukan JWT token)

---

## 🚀 Setup Awal

### 1. Environment Variables di Postman

Buat environment baru di Postman dengan variabel berikut:

| Variable       | Value                          |
|----------------|--------------------------------|
| `base_url`     | `http://127.0.0.1:8003/api`   |
| `token`        | *(kosongkan, akan diisi otomatis)* |

### 2. Jalankan Server

```bash
php artisan serve --port=8003
```

### 3. Jalankan Migration & Seeder

```bash
php artisan migrate:fresh --seed
```

> **Seeder** akan membuat: 1 CEO, 10 Director, 50 Manager, dan 4939 Staff.

### 4. Akun Default (dari Seeder)

| Role     | Email                          | Password      | Leave Balance |
|----------|--------------------------------|---------------|---------------|
| CEO      | `ceo@enterprise.com`           | `password123` | 30            |
| Director | `director.hr@enterprise.com`   | `password123` | 25            |
| Manager  | `manager1.hr@enterprise.com`   | `password123` | 20            |
| Staff    | `staff1@enterprise.com`        | `password123` | 10–15         |

---

## 🔐 Autentikasi (JWT)

Semua endpoint (kecuali `POST /register` dan `POST /login`) memerlukan JWT token.

### Setup di Postman

1. Setelah **Login** atau **Register**, copy nilai `access_token` dari response.
2. Di tab **Authorization** pada setiap request:
   - Type: **Bearer Token**
   - Token: `{{token}}`
3. Atau tambahkan header secara manual:
   ```
   Authorization: Bearer {{token}}
   ```

### Auto-Set Token (Postman Script)

Tambahkan script berikut di tab **Tests** pada request Login/Register untuk otomatis menyimpan token:

```javascript
var jsonData = pm.response.json();
if (jsonData.access_token) {
    pm.environment.set("token", jsonData.access_token);
}
```

---

## 🔑 Module 1 — Auth

### 1.1 Register

Mendaftarkan karyawan baru dan langsung mendapatkan JWT token.

```
POST {{base_url}}/register
```

**Headers:**
```
Content-Type: application/json
```

**Body (JSON):**
```json
{
    "name": "Budi Santoso",
    "email": "budi@enterprise.com",
    "password": "password123",
    "password_confirmation": "password123",
    "position": "Staff",
    "department": "IT",
    "manager_id": 12
}
```

> `manager_id` opsional. Jika diisi, harus merujuk ke ID employee yang sudah ada.
> `leave_balance` default `0` jika tidak diisi.

**Response (201):**
```json
{
    "message": "Employee registered successfully",
    "employee": {
        "id": 5001,
        "name": "Budi Santoso",
        "email": "budi@enterprise.com",
        "position": "Staff",
        "department": "IT",
        "leave_balance": 0,
        "manager_id": 12
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOi..."
}
```

---

### 1.2 Login

```
POST {{base_url}}/login
```

**Body (JSON):**
```json
{
    "email": "ceo@enterprise.com",
    "password": "password123"
}
```

**Response (200):**
```json
{
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOi...",
    "token_type": "bearer",
    "expires_in": 3600
}
```

---

### 1.3 Get Current User

```
GET {{base_url}}/me
```

**Headers:**
```
Authorization: Bearer {{token}}
```

**Response (200):**
```json
{
    "id": 1,
    "name": "John Doe (CEO)",
    "email": "ceo@enterprise.com",
    "position": "CEO",
    "department": "Executive",
    "leave_balance": 30,
    "manager_id": null,
    "manager": null,
    "subordinates": [
        {
            "id": 2,
            "name": "Director HR",
            "position": "Director",
            "department": "HR"
        }
    ]
}
```

---

### 1.4 Logout

```
POST {{base_url}}/logout
```

**Headers:**
```
Authorization: Bearer {{token}}
```

**Response (200):**
```json
{
    "message": "Successfully logged out"
}
```

---

### 1.5 Refresh Token

```
POST {{base_url}}/refresh
```

**Headers:**
```
Authorization: Bearer {{token}}
```

**Response (200):**
```json
{
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOi...",
    "token_type": "bearer",
    "expires_in": 3600
}
```

---

## 👥 Module 2 — Employee Management

> Semua endpoint memerlukan `Authorization: Bearer {{token}}`

### 2.1 Get All Employees (Paginated)

```
GET {{base_url}}/employees
```

**Query Parameters (opsional):**

| Parameter    | Type   | Deskripsi                              |
|-------------|--------|----------------------------------------|
| `per_page`  | int    | Jumlah data per halaman (default: 15)  |
| `department`| string | Filter berdasarkan departemen          |
| `position`  | string | Filter berdasarkan posisi              |
| `search`    | string | Cari berdasarkan nama atau email       |

**Contoh:**
```
GET {{base_url}}/employees?department=HR&position=Manager&per_page=10
GET {{base_url}}/employees?search=staff
```

**Response (200):**
```json
{
    "current_page": 1,
    "data": [
        {
            "id": 12,
            "name": "Manager 1 - HR",
            "email": "manager1.hr@enterprise.com",
            "position": "Manager",
            "department": "HR",
            "leave_balance": 20,
            "manager_id": 2,
            "manager": {
                "id": 2,
                "name": "Director HR",
                "position": "Director"
            }
        }
    ],
    "last_page": 5,
    "per_page": 10,
    "total": 50
}
```

---

### 2.2 Get Employee by ID

```
GET {{base_url}}/employees/1
```

**Response (200):**
```json
{
    "id": 1,
    "name": "John Doe (CEO)",
    "email": "ceo@enterprise.com",
    "position": "CEO",
    "department": "Executive",
    "leave_balance": 30,
    "manager_id": null,
    "manager": null,
    "subordinates": [
        {
            "id": 2,
            "name": "Director HR",
            "position": "Director",
            "department": "HR"
        }
    ]
}
```

---

### 2.3 Create Employee

```
POST {{base_url}}/employees
```

**Body (JSON):**
```json
{
    "name": "Andi Pratama",
    "email": "andi@enterprise.com",
    "password": "password123",
    "position": "Staff",
    "department": "IT",
    "leave_balance": 12,
    "manager_id": 12
}
```

> `leave_balance` opsional (default: 15).
> `manager_id` opsional.

**Response (201):**
```json
{
    "message": "Employee created successfully",
    "data": {
        "id": 5001,
        "name": "Andi Pratama",
        "email": "andi@enterprise.com",
        "position": "Staff",
        "department": "IT",
        "leave_balance": 12,
        "manager_id": 12
    }
}
```

---

### 2.4 Update Employee

```
PUT {{base_url}}/employees/5001
```

**Body (JSON):** *(semua field opsional)*
```json
{
    "name": "Andi Pratama Updated",
    "position": "Manager",
    "department": "Engineering",
    "leave_balance": 20
}
```

**Response (200):**
```json
{
    "message": "Employee updated successfully",
    "data": {
        "id": 5001,
        "name": "Andi Pratama Updated",
        "position": "Manager",
        "department": "Engineering",
        "leave_balance": 20
    }
}
```

---

### 2.5 Delete Employee

```
DELETE {{base_url}}/employees/5001
```

**Response (200):**
```json
{
    "message": "Employee deleted successfully"
}
```

---

### 2.6 Get Employee Subordinates

```
GET {{base_url}}/employees/2/subordinates
```

**Response (200):**
```json
{
    "manager": {
        "id": 2,
        "name": "Director HR",
        "position": "Director"
    },
    "subordinates": [
        {
            "id": 12,
            "name": "Manager 1 - HR",
            "email": "manager1.hr@enterprise.com",
            "position": "Manager",
            "department": "HR"
        }
    ],
    "count": 5
}
```

---

### 2.7 Get Employee Hierarchy

Menampilkan employee beserta manager dan subordinates-nya.

```
GET {{base_url}}/employees/12/hierarchy
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "employee": {
            "id": 12,
            "name": "Manager 1 - HR",
            "position": "Manager",
            "department": "HR",
            "manager_id": 2
        },
        "manager": {
            "id": 2,
            "name": "Director HR",
            "position": "Director"
        },
        "subordinates": [
            {
                "id": 62,
                "name": "Staff Member 1",
                "position": "Staff"
            }
        ]
    }
}
```

---

### 2.8 Get Employee Statistics

```
GET {{base_url}}/employees/statistics
```

**Response (200):**
```json
{
    "total_employees": 5000,
    "by_position": [
        { "position": "CEO", "count": 1 },
        { "position": "Director", "count": 10 },
        { "position": "Manager", "count": 50 },
        { "position": "Staff", "count": 4939 }
    ],
    "by_department": [
        { "department": "IT", "count": 500 },
        { "department": "HR", "count": 498 }
    ],
    "average_leave_balance": 14.5
}
```

---

### 2.9 Update Leave Balance

Endpoint khusus untuk HR mengubah saldo cuti karyawan.

```
PUT {{base_url}}/employees/62/leave-balance
```

**Body (JSON):**
```json
{
    "leave_balance": 20
}
```

**Response (200):**
```json
{
    "message": "Leave balance updated successfully",
    "data": {
        "id": 62,
        "name": "Staff Member 1",
        "leave_balance": 20
    }
}
```

---

## 📝 Module 3 — Leave Request & Approval Workflow

### Alur Pengajuan Cuti

```
Staff submit leave → Manager (Level 1) approve/reject → Director (Level 2) approve/reject → Saldo dipotong
```

**Aturan Penting:**
- Karyawan **tidak bisa** submit cuti jika masih ada request berstatus `pending`
- Saldo cuti harus **mencukupi** untuk jumlah hari yang diajukan
- **CEO** → otomatis approved (tanpa approval chain)
- **Staff tanpa manager** → otomatis approved
- **Staff biasa** (punya Manager) → butuh approval Level 1
- **Manager** (punya Director di atasnya) → butuh approval Level 1 + Level 2
- Jika Level 1 **reject**, maka Level 2 otomatis **auto-rejected**
- Saldo cuti **dipotong** setelah semua level approve

---

### 3.1 Submit Leave Request

> Login sebagai karyawan yang ingin cuti.

```
POST {{base_url}}/leaves
```

**Body (JSON):**
```json
{
    "start_date": "2026-06-10",
    "end_date": "2026-06-12",
    "reason": "Liburan keluarga",
    "type": "annual"
}
```

**Response — Sukses (201):**
```json
{
    "success": true,
    "message": "Leave request submitted successfully.",
    "data": {
        "id": 1,
        "employee_id": 62,
        "start_date": "2026-06-10",
        "end_date": "2026-06-12",
        "reason": "Liburan keluarga",
        "type": "annual",
        "status": "pending",
        "leaves_balances": 12,
        "employee": {
            "id": 62,
            "name": "Staff Member 1"
        },
        "approvals": [
            {
                "id": 1,
                "leave_request_id": 1,
                "approver_id": 12,
                "status": "pending",
                "approval_level": 1,
                "approver": {
                    "id": 12,
                    "name": "Manager 1 - HR"
                }
            }
        ]
    }
}
```

**Response — Saldo Tidak Cukup (400):**
```json
{
    "success": false,
    "message": "Pengajuan cuti ditolak. Saldo cuti (3) tidak mencukupi untuk 5 hari.",
    "leaves_balances": 3
}
```

**Response — Masih Ada Pending (400):**
```json
{
    "success": false,
    "message": "Pengajuan cuti ditolak. Anda masih memiliki pengajuan cuti yang berstatus pending.",
    "leaves_balances": 12
}
```

---

### 3.2 Get My Leave Requests

Melihat semua riwayat pengajuan cuti beserta saldo saat ini.

```
GET {{base_url}}/leaves/my-requests
```

**Response (200):**
```json
{
    "success": true,
    "leaves_balances": 12,
    "data": [
        {
            "id": 1,
            "employee_id": 62,
            "start_date": "2026-06-10",
            "end_date": "2026-06-12",
            "reason": "Liburan keluarga",
            "type": "annual",
            "status": "pending",
            "employee": { "id": 62, "name": "Staff Member 1" },
            "approvals": [
                {
                    "approver_id": 12,
                    "status": "pending",
                    "approval_level": 1,
                    "approver": { "id": 12, "name": "Manager 1 - HR" }
                }
            ]
        }
    ]
}
```

---

### 3.3 Get Subordinate Leave Requests

> Login sebagai Manager/Director untuk melihat cuti bawahan.

```
GET {{base_url}}/leaves/subordinates
```

**Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "employee_id": 62,
            "status": "pending",
            "employee": { "id": 62, "name": "Staff Member 1" },
            "approvals": [...]
        }
    ]
}
```

---

### 3.4 Get Pending Approvals

> Login sebagai Manager/Director yang memiliki approval yang harus diproses.

```
GET {{base_url}}/approvals/pending
```

**Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "leave_request_id": 1,
            "approver_id": 12,
            "status": "pending",
            "approval_level": 1,
            "leave_request": {
                "id": 1,
                "employee_id": 62,
                "start_date": "2026-06-10",
                "end_date": "2026-06-12",
                "status": "pending",
                "employee": { "id": 62, "name": "Staff Member 1" }
            },
            "approver": { "id": 12, "name": "Manager 1 - HR" }
        }
    ]
}
```

---

### 3.5 Approve/Reject Level 1

> Login sebagai **Manager** (direct manager dari pemohon).

```
POST {{base_url}}/approvals/level-1/{leave_request_id}
```

**Body — Approve:**
```json
{
    "status": "approved",
    "notes": "Disetujui, selamat berlibur!"
}
```

**Body — Reject:**
```json
{
    "status": "rejected",
    "notes": "Tidak bisa cuti pada tanggal tersebut, sedang ada project deadline."
}
```

**Response — Approved (200):**
```json
{
    "success": true,
    "message": "Level 1 leave request processed successfully.",
    "data": {
        "request": {
            "id": 1,
            "status": "pending",
            "employee": { "id": 62, "name": "Staff Member 1" },
            "approvals": [
                {
                    "approval_level": 1,
                    "status": "approved",
                    "notes": "Disetujui, selamat berlibur!",
                    "approver": { "id": 12, "name": "Manager 1 - HR" }
                },
                {
                    "approval_level": 2,
                    "status": "pending",
                    "approver": { "id": 2, "name": "Director HR" }
                }
            ]
        }
    }
}
```

> Jika pemohon adalah **Staff biasa** (manager-nya tidak punya manager lagi), setelah Level 1 approve, request **langsung approved** dan saldo dipotong.

---

### 3.6 Approve/Reject Level 2

> Login sebagai **Director** (manager dari manager pemohon).

```
POST {{base_url}}/approvals/level-2/{leave_request_id}
```

**Body:**
```json
{
    "status": "approved",
    "notes": "Approved by Director"
}
```

**Response — Final Approved (200):**
```json
{
    "success": true,
    "message": "Level 2 leave request processed successfully.",
    "data": {
        "request": {
            "id": 1,
            "status": "approved",
            "employee": {
                "id": 62,
                "name": "Staff Member 1",
                "leave_balance": 9
            },
            "approvals": [
                {
                    "approval_level": 1,
                    "status": "approved",
                    "approver": { "id": 12, "name": "Manager 1 - HR" }
                },
                {
                    "approval_level": 2,
                    "status": "approved",
                    "approver": { "id": 2, "name": "Director HR" }
                }
            ]
        }
    }
}
```

> Setelah Level 2 approve, `leave_balance` karyawan otomatis dipotong. Pada contoh di atas: 12 - 3 hari = **9**.

---

## ⏰ Module 4 — Attendance & Reporting

> Semua endpoint menggunakan prefix `/api/v1` dan memerlukan `Authorization: Bearer {{token}}`

### 4.1 Clock In

```
POST {{base_url}}/v1/attendance/clock-in
```

> Tidak perlu body. Otomatis menggunakan waktu saat ini.

**Response — Sukses (200):**
```json
{
    "success": true,
    "message": "Clock-in successful",
    "data": {
        "id": 1,
        "employee_id": 62,
        "date": "2026-06-04",
        "clock_in": "08:30:00",
        "clock_out": null,
        "status": "present"
    }
}
```

**Response — Sudah Clock In (400):**
```json
{
    "success": false,
    "message": "Already clocked in today"
}
```

---

### 4.2 Clock Out

```
POST {{base_url}}/v1/attendance/clock-out
```

**Response — Sukses (200):**
```json
{
    "success": true,
    "message": "Clock-out successful",
    "data": {
        "id": 1,
        "employee_id": 62,
        "date": "2026-06-04",
        "clock_in": "08:30:00",
        "clock_out": "17:00:00",
        "work_hours": 8.5,
        "status": "present"
    }
}
```

---

### 4.3 My Attendance

Melihat riwayat kehadiran sendiri.

```
GET {{base_url}}/v1/attendance/me
```

**Query Parameters (opsional):**

| Parameter    | Type   | Deskripsi                     |
|-------------|--------|-------------------------------|
| `start_date`| date   | Tanggal mulai (YYYY-MM-DD)   |
| `end_date`  | date   | Tanggal akhir (YYYY-MM-DD)   |
| `month`     | int    | Bulan (1-12)                  |
| `year`      | int    | Tahun (min: 2000)             |

**Contoh:**
```
GET {{base_url}}/v1/attendance/me?month=6&year=2026
GET {{base_url}}/v1/attendance/me?start_date=2026-06-01&end_date=2026-06-30
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "employee_id": 62,
        "total_days": 20,
        "present_days": 18,
        "absent_days": 2,
        "records": [
            {
                "date": "2026-06-04",
                "clock_in": "08:30:00",
                "clock_out": "17:00:00",
                "work_hours": 8.5,
                "status": "present"
            }
        ]
    }
}
```

---

### 4.4 Attendance Report

> HR department dapat melihat report seluruh karyawan. Non-HR hanya bisa melihat datanya sendiri.

```
GET {{base_url}}/v1/attendance/report
```

**Query Parameters (opsional):**

| Parameter      | Type   | Deskripsi                              |
|---------------|--------|----------------------------------------|
| `employee_id` | int    | ID karyawan (khusus HR)                |
| `start_date`  | date   | Tanggal mulai                          |
| `end_date`    | date   | Tanggal akhir                          |
| `month`       | int    | Bulan (1-12)                           |
| `year`        | int    | Tahun                                  |

**Contoh — HR melihat semua:**
```
GET {{base_url}}/v1/attendance/report?month=6&year=2026
```

**Contoh — HR melihat karyawan tertentu:**
```
GET {{base_url}}/v1/attendance/report?employee_id=62&month=6&year=2026
```

---

### 4.5 Export Attendance Report (CSV)

> Mengunduh report dalam format CSV. Hak akses sama dengan endpoint report.

```
GET {{base_url}}/v1/attendance/report/export
```

**Query Parameters:** Sama dengan endpoint report di atas.

**Contoh:**
```
GET {{base_url}}/v1/attendance/report/export?month=6&year=2026
```

> **Postman Tip:** Klik **Send and Download** untuk mengunduh file CSV.

**Response:** File CSV didownload langsung (`attendance_report_2026-06-01_to_2026-06-30.csv`).

---

## 📊 Module 5 — Leave Data (Attendance Module)

> Endpoint untuk modul attendance yang mengakses data cuti (sync dari approval module).

### 5.1 Get My Leaves

```
GET {{base_url}}/v1/leaves/me
```

---

### 5.2 Get All Leaves

```
GET {{base_url}}/v1/leaves
```

---

### 5.3 Get Leave by ID

```
GET {{base_url}}/v1/leaves/{id}
```

---

### 5.4 Export Leaves for Payroll

```
GET {{base_url}}/v1/leaves/export/payroll
```

**Query Parameters:**

| Parameter    | Type | Deskripsi               |
|-------------|------|-------------------------|
| `start_date`| date | Tanggal mulai (wajib)   |
| `end_date`  | date | Tanggal akhir (wajib)   |

**Contoh:**
```
GET {{base_url}}/v1/leaves/export/payroll?start_date=2026-06-01&end_date=2026-06-30
```

---

### 5.5 Sync Leaves from Approval Service

Sinkronisasi data cuti dari Approval module ke database lokal.

```
POST {{base_url}}/v1/leaves/sync
```

---

## 🏢 Hierarki Organisasi

Sistem ini menggunakan hierarki 4 level:

```
CEO (John Doe)
  └── Director (per Department: HR, IT, Finance, dll.)
        └── Manager (5 per Department)
              └── Staff (acak per Manager)
```

### Alur Approval berdasarkan Posisi:

| Posisi Pemohon | Level 1 (Approver) | Level 2 (Approver) | Keterangan                  |
|---------------|--------------------|--------------------|------------------------------|
| **Staff**     | Manager            | Director           | Butuh 2 level approval       |
| **Manager**   | Director           | CEO                | Butuh 2 level approval       |
| **Director**  | CEO                | —                  | Butuh 1 level approval       |
| **CEO**       | —                  | —                  | Auto-approved                |

---

## 🧪 Skenario Testing End-to-End

### Skenario 1: Staff Mengajukan Cuti (Full Approval Chain)

```
Langkah 1: Login sebagai Staff
POST /api/login → { "email": "staff1@enterprise.com", "password": "password123" }

Langkah 2: Submit Leave Request
POST /api/leaves → { "start_date": "2026-07-01", "end_date": "2026-07-03", "reason": "Mudik", "type": "annual" }
→ Catat leave_request_id dari response

Langkah 3: Login sebagai Manager (direct manager staff tersebut)
POST /api/login → { "email": "manager1.hr@enterprise.com", "password": "password123" }

Langkah 4: Lihat Pending Approvals
GET /api/approvals/pending

Langkah 5: Approve Level 1
POST /api/approvals/level-1/{leave_request_id} → { "status": "approved", "notes": "OK" }

Langkah 6: Login sebagai Director
POST /api/login → { "email": "director.hr@enterprise.com", "password": "password123" }

Langkah 7: Approve Level 2
POST /api/approvals/level-2/{leave_request_id} → { "status": "approved", "notes": "Approved" }
→ Leave balance staff otomatis berkurang 3 hari

Langkah 8: Verifikasi (login kembali sebagai Staff)
GET /api/leaves/my-requests → cek status "approved" dan leaves_balances berkurang
```

---

### Skenario 2: Level 1 Reject (Auto-Reject Level 2)

```
Langkah 1-2: Sama seperti Skenario 1

Langkah 3: Login sebagai Manager
Langkah 4: Reject Level 1
POST /api/approvals/level-1/{leave_request_id} → { "status": "rejected", "notes": "Timing tidak tepat" }
→ Level 2 otomatis auto-rejected
→ Leave balance TIDAK berubah
```

---

### Skenario 3: CEO Submit Leave (Auto-Approve)

```
Langkah 1: Login sebagai CEO
POST /api/login → { "email": "ceo@enterprise.com", "password": "password123" }

Langkah 2: Submit Leave Request
POST /api/leaves → { "start_date": "2026-07-10", "end_date": "2026-07-11", "reason": "Personal", "type": "annual" }
→ Status langsung "approved" tanpa approval chain
```

---

### Skenario 4: Attendance Flow

```
Langkah 1: Login sebagai karyawan manapun

Langkah 2: Clock In (pagi)
POST /api/v1/attendance/clock-in

Langkah 3: Clock Out (sore)
POST /api/v1/attendance/clock-out

Langkah 4: Lihat Kehadiran
GET /api/v1/attendance/me?month=6&year=2026

Langkah 5: (HR only) Export Report
GET /api/v1/attendance/report/export?month=6&year=2026
```

---

### Skenario 5: HR Update Leave Balance

```
Langkah 1: Login sebagai HR / Admin

Langkah 2: Update saldo cuti karyawan
PUT /api/employees/62/leave-balance → { "leave_balance": 20 }

Langkah 3: Verifikasi
GET /api/employees/62 → cek leave_balance = 20
```

---

## ❌ Error Responses

| Status Code | Deskripsi                                |
|-------------|------------------------------------------|
| `400`       | Validation error / Business rule violation |
| `401`       | Unauthorized (token missing/invalid)     |
| `404`       | Resource not found                       |
| `422`       | Validation failed (field errors)         |
| `500`       | Server error                             |

**Contoh Error 401:**
```json
{
    "message": "Unauthenticated."
}
```

**Contoh Error 422:**
```json
{
    "message": "Validation failed",
    "errors": {
        "email": ["The email has already been taken."],
        "password": ["The password field must be at least 6 characters."]
    }
}
```

---

## 📌 Tips Postman

1. **Gunakan Collection** — Buat satu collection per module (Auth, Employee, Leave, Approval, Attendance).
2. **Gunakan Environment** — Simpan `base_url` dan `token` sebagai variabel environment agar mudah diganti.
3. **Auto-set token** — Gunakan script di tab Tests pada request Login untuk otomatis menyimpan token.
4. **Collection Variables** — Simpan `leave_request_id` dari response agar bisa dipakai di request approval.
5. **Send and Download** — Gunakan fitur ini untuk endpoint export CSV.
