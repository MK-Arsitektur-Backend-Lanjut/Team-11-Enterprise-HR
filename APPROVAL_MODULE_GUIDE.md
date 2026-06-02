# 📚 Panduan Approval Module (Monolith Architecture)

## 🎯 Overview

Approval Module telah dikonversi dari **Microservice** menjadi **Monolith** dan terintegrasi penuh dengan Employee Module dalam satu aplikasi Laravel.

### ✅ Perubahan Utama:
- ❌ **Sebelum:** HTTP calls ke API Employee Module terpisah
- ✅ **Sekarang:** Direct database access menggunakan Eloquent ORM
- ✅ Menggunakan JWT Authentication yang sama (`auth:api`)
- ✅ Semua endpoint dalam satu aplikasi

---

## 🗂️ Struktur Database

### **Tabel: `employees`**
Tabel utama untuk data karyawan (sudah ada)

### **Tabel: `leave_requests`**
Menyimpan pengajuan cuti karyawan
```sql
- id
- employee_id (FK ke employees)
- start_date
- end_date
- reason
- type
- status (pending/approved/rejected)
- timestamps
```

### **Tabel: `leave_approvals`**
Menyimpan history approval dari manager
```sql
- id
- leave_request_id (FK ke leave_requests)
- approver_id (FK ke employees)
- status (pending/approved/rejected)
- notes
- approval_level (1 atau 2)
- timestamps
```

---

## 🔐 Authentication

Semua endpoint menggunakan **JWT Bearer Token** yang sama dengan Employee Module.

**Login terlebih dahulu:**
```http
POST /api/login
Content-Type: application/json

{
    "email": "staff1@enterprise.com",
    "password": "password123"
}
```

**Response:**
```json
{
    "access_token": "eyJ0eXAiOiJKV1Qi...",
    "token_type": "bearer",
    "expires_in": 3600
}
```

**Gunakan token di semua request:**
```
Authorization: Bearer {your_token}
```

---

## 📋 API Endpoints

### **1️⃣ LEAVE REQUEST - Pengajuan Cuti**

#### **A. Submit Leave Request**
Karyawan mengajukan cuti

**Endpoint:** `POST /api/leaves`

**Headers:**
- `Authorization: Bearer {token}`
- `Content-Type: application/json`
- `Accept: application/json`

**Body:**
```json
{
    "start_date": "2026-06-10",
    "end_date": "2026-06-12",
    "reason": "Liburan keluarga",
    "type": "annual"
}
```

**Response Success (201):**
```json
{
    "success": true,
    "message": "Leave request submitted successfully.",
    "data": {
        "id": 1,
        "employee_id": 100,
        "start_date": "2026-06-10",
        "end_date": "2026-06-12",
        "reason": "Liburan keluarga",
        "type": "annual",
        "status": "pending",
        "leaves_balances": 12,
        "employee": {
            "id": 100,
            "name": "Staff Member 1",
            "email": "staff1@enterprise.com"
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
                    "name": "Manager 1 - IT"
                }
            }
        ]
    }
}
```

**Response Error - Saldo Tidak Cukup (400):**
```json
{
    "success": false,
    "message": "Pengajuan cuti ditolak. Saldo cuti (5) tidak mencukupi untuk 7 hari.",
    "leaves_balances": 5
}
```

**Response Error - Masih Ada Pending (400):**
```json
{
    "success": false,
    "message": "Pengajuan cuti ditolak. Anda masih memiliki pengajuan cuti yang berstatus pending.",
    "leaves_balances": 15
}
```

---

#### **B. Get My Leave Requests**
Melihat riwayat pengajuan cuti sendiri

**Endpoint:** `GET /api/leaves/my-requests`

**Headers:**
- `Authorization: Bearer {token}`
- `Accept: application/json`

**Response (200):**
```json
{
    "success": true,
    "leaves_balances": 12,
    "data": [
        {
            "id": 1,
            "employee_id": 100,
            "start_date": "2026-06-10",
            "end_date": "2026-06-12",
            "reason": "Liburan keluarga",
            "type": "annual",
            "status": "pending",
            "employee": {
                "id": 100,
                "name": "Staff Member 1"
            },
            "approvals": [
                {
                    "id": 1,
                    "approver_id": 12,
                    "status": "pending",
                    "approval_level": 1,
                    "approver": {
                        "id": 12,
                        "name": "Manager 1 - IT"
                    }
                }
            ]
        }
    ]
}
```

---

#### **C. Get Subordinates' Leave Requests**
Manager melihat pengajuan cuti dari bawahan

**Endpoint:** `GET /api/leaves/subordinates`

**Headers:**
- `Authorization: Bearer {token}`
- `Accept: application/json`

**Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "employee_id": 100,
            "start_date": "2026-06-10",
            "end_date": "2026-06-12",
            "reason": "Liburan keluarga",
            "type": "annual",
            "status": "pending",
            "employee": {
                "id": 100,
                "name": "Staff Member 1",
                "position": "Staff",
                "department": "IT"
            },
            "approvals": [...]
        }
    ]
}
```

---

### **2️⃣ APPROVAL - Persetujuan Cuti**

#### **A. Get Pending Approvals**
Manager melihat list cuti yang menunggu approval

**Endpoint:** `GET /api/approvals/pending`

**Headers:**
- `Authorization: Bearer {token}`
- `Accept: application/json`

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
            "notes": null,
            "approval_level": 1,
            "approver": {
                "id": 12,
                "name": "Manager 1 - IT",
                "position": "Manager"
            },
            "leave_request": {
                "id": 1,
                "employee_id": 100,
                "start_date": "2026-06-10",
                "end_date": "2026-06-12",
                "reason": "Liburan keluarga",
                "type": "annual",
                "status": "pending",
                "employee": {
                    "id": 100,
                    "name": "Staff Member 1",
                    "position": "Staff"
                }
            }
        }
    ]
}
```

---

#### **B. Approve/Reject Level 1**
Manager Level 1 menyetujui atau menolak cuti

**Endpoint:** `POST /api/approvals/level-1/{leave_request_id}`

**Headers:**
- `Authorization: Bearer {token}`
- `Content-Type: application/json`
- `Accept: application/json`

**Body (Approve):**
```json
{
    "status": "approved",
    "notes": "Disetujui, selamat berlibur"
}
```

**Body (Reject):**
```json
{
    "status": "rejected",
    "notes": "Ditolak karena periode sibuk"
}
```

**Response Success (200):**
```json
{
    "success": true,
    "message": "Level 1 leave request processed successfully.",
    "data": {
        "request": {
            "id": 1,
            "employee_id": 100,
            "status": "approved",
            "employee": {...},
            "approvals": [...]
        }
    }
}
```

---

#### **C. Approve/Reject Level 2**
Manager Level 2 (Director/CEO) menyetujui atau menolak cuti

**Endpoint:** `POST /api/approvals/level-2/{leave_request_id}`

**Headers:**
- `Authorization: Bearer {token}`
- `Content-Type: application/json`
- `Accept: application/json`

**Body:**
```json
{
    "status": "approved",
    "notes": "Approved by CEO"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Level 2 leave request processed successfully.",
    "data": {
        "request": {
            "id": 1,
            "status": "approved",
            ...
        }
    }
}
```

---

## 🔄 Workflow Approval

### **Scenario 1: Staff Mengajukan Cuti**
1. **Staff** submit cuti → Status: `pending`
2. System otomatis buat approval record untuk **Manager** (Level 1)
3. **Manager** approve → Status: `approved` ✅ (Selesai, saldo dipotong)

### **Scenario 2: Manager Mengajukan Cuti**
1. **Manager** submit cuti → Status: `pending`
2. System buat 2 approval record:
   - **Director** (Level 1)
   - **CEO** (Level 2)
3. **Director** approve → Status masih: `pending` (menunggu Level 2)
4. **CEO** approve → Status: `approved` ✅ (Selesai, saldo dipotong)

### **Scenario 3: Director Mengajukan Cuti**
1. **Director** submit cuti → Status: `pending`
2. System buat approval record untuk **CEO** (Level 1)
3. **CEO** approve → Status: `approved` ✅

### **Scenario 4: CEO Mengajukan Cuti**
1. **CEO** submit cuti → **Auto-Approved** ✅ (Tidak butuh approval)

### **Scenario 5: Level 1 Reject**
1. Manager submit cuti
2. Director (Level 1) **reject** → Status: `rejected` ❌
3. CEO (Level 2) otomatis mendapat record `rejected` (tidak perlu approve/reject lagi)

---

## 🧪 Testing Flow di Postman

### **Test 1: Staff Mengajukan Cuti**

```bash
# 1. Login sebagai Staff
POST /api/login
{
    "email": "staff1@enterprise.com",
    "password": "password123"
}
# Simpan token

# 2. Submit cuti
POST /api/leaves
Authorization: Bearer {token}
{
    "start_date": "2026-06-10",
    "end_date": "2026-06-12",
    "reason": "Liburan",
    "type": "annual"
}

# 3. Cek riwayat cuti sendiri
GET /api/leaves/my-requests
Authorization: Bearer {token}
```

### **Test 2: Manager Approve Cuti Staff**

```bash
# 1. Login sebagai Manager
POST /api/login
{
    "email": "manager1.it@enterprise.com",
    "password": "password123"
}
# Simpan token manager

# 2. Lihat pending approvals
GET /api/approvals/pending
Authorization: Bearer {manager_token}

# 3. Approve cuti (gunakan leave_request_id dari response step 2)
POST /api/approvals/level-1/1
Authorization: Bearer {manager_token}
{
    "status": "approved",
    "notes": "Disetujui"
}
```

### **Test 3: Manager Mengajukan Cuti (Butuh 2 Level)**

```bash
# 1. Login sebagai Manager
POST /api/login
{
    "email": "manager1.it@enterprise.com",
    "password": "password123"
}

# 2. Submit cuti
POST /api/leaves
Authorization: Bearer {token}
{
    "start_date": "2026-07-01",
    "end_date": "2026-07-05",
    "reason": "Cuti tahunan",
    "type": "annual"
}

# 3. Login sebagai Director (atasan manager)
POST /api/login
{
    "email": "director.it@enterprise.com",
    "password": "password123"
}

# 4. Director approve Level 1
POST /api/approvals/level-1/2
Authorization: Bearer {director_token}
{
    "status": "approved",
    "notes": "OK"
}

# 5. Login sebagai CEO
POST /api/login
{
    "email": "ceo@enterprise.com",
    "password": "password123"
}

# 6. CEO approve Level 2
POST /api/approvals/level-2/2
Authorization: Bearer {ceo_token}
{
    "status": "approved",
    "notes": "Approved by CEO"
}
```

---

## 📊 Business Rules

### ✅ **Validasi Submit Cuti:**
1. Employee harus login (JWT valid)
2. Saldo cuti harus mencukupi (jumlah hari yang diminta)
3. Tidak boleh ada pengajuan cuti lain yang statusnya `pending`
4. `end_date` harus >= `start_date`

### ✅ **Approval Level:**
- **Staff** → Butuh approval dari **Manager** (Level 1 saja)
- **Manager** → Butuh approval dari **Director** (Level 1) + **CEO** (Level 2)
- **Director** → Butuh approval dari **CEO** (Level 1 saja)
- **CEO** → **Auto-approved** (tidak butuh approval)

### ✅ **Rejection Rules:**
- Jika Level 1 reject, status langsung `rejected`
- Jika Manager mengajukan cuti dan Level 1 reject, maka Level 2 otomatis dapat record `rejected` (tidak perlu action)

### ✅ **Pemotongan Saldo:**
- Saldo cuti **HANYA dipotong** saat status menjadi `approved`
- Dihitung dari `start_date` sampai `end_date` (inclusive)
- Saldo dipotong dari tabel `employees.leave_balance`

---

## 🆚 Perbedaan Microservice vs Monolith

| Aspek | Microservice (Sebelum) | Monolith (Sekarang) |
|-------|------------------------|---------------------|
| **Komunikasi** | HTTP API calls | Direct database/Eloquent |
| **Authentication** | Custom middleware + token passing | JWT `auth:api` middleware |
| **Employee Data** | `Http::get()` ke Employee API | `Employee::find()` |
| **Leave Balance Update** | `Http::put()` ke Employee API | `$employee->save()` |
| **Hierarchy** | `fetchEmployeeHierarchy()` via HTTP | `$employee->manager`, `$employee->subordinates` |
| **Performance** | Slower (network latency) | Faster (in-process) |
| **Deployment** | Separate services | Single application |
| **Complexity** | High (network, service discovery) | Low (single codebase) |

---

## 🚀 Cara Menjalankan

```bash
# 1. Install dependencies
composer install

# 2. Setup database
php artisan migrate

# 3. Seed employees
php artisan db:seed --class=EmployeeSeeder

# 4. Generate JWT secret (jika belum)
php artisan jwt:secret

# 5. Jalankan server
php artisan serve
```

---

## 📝 Notes

- Semua endpoint approval sudah menggunakan JWT authentication yang sama
- Tidak ada lagi `VerifyMicroserviceToken` middleware
- Employee ID diambil dari `auth('api')->user()->id`
- Eager loading digunakan untuk performa (`->load(['employee', 'approvals.approver'])`)
- Event `LeaveRequestStatusUpdated` tetap berfungsi untuk notifikasi

---

## 🐛 Troubleshooting

### Error: "Unauthenticated"
- Pastikan token JWT valid
- Format header: `Authorization: Bearer {token}`
- Token bisa expire, lakukan login ulang

### Error: "Employee not found"
- Pastikan sudah menjalankan seeder
- Cek apakah employee_id ada di database

### Error: "Approval level mismatch"
- Pastikan menggunakan endpoint yang benar (level-1 atau level-2)
- Cek approval_level di database untuk leave request tersebut

---

**✅ Konversi ke Monolith Selesai!**  
Semua module (Employee, Leave, Approval) sekarang terintegrasi dalam satu aplikasi Laravel.
