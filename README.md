# 🏢 Team 11 Enterprise HR - Modul Approval Cuti

Selamat datang di repositori **Team 11 Enterprise HR**! Proyek ini merupakan sistem Human Resources Management (HRM) berbasis Laravel yang berfokus pada **Modul Approval Cuti (Leave Approval Workflow)**. Sistem ini memungkinkan karyawan untuk mengajukan cuti dan memungkinkan atasan (Manager) untuk melakukan *approval* (persetujuan) secara bertingkat.

---

## 📂 Struktur Folder Utama

Proyek ini menggunakan struktur standar Laravel dengan beberapa penyesuaian untuk mengimplementasikan pola desain **Service-Repository Pattern**. Berikut adalah direktori penting yang berkaitan dengan modul approval:

```text
Team-11-Enterprise-HR/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php         # Menangani endpoint autentikasi (login, register, JWT)
│   │   │   ├── EmployeeController.php     # Menangani endpoint CRUD karyawan & hierarki organisasi
│   │   │   ├── LeaveController.php        # Menangani endpoint pengajuan cuti karyawan
│   │   │   ├── ApprovalController.php     # Menangani endpoint approval (persetujuan/penolakan)
│   │   │   └── AttendanceController.php   # Menangani endpoint kehadiran & laporan absensi
│   ├── Repositories/
│   │   └── LeaveRequestRepository.php     # Mengelola logika query database untuk cuti dan persetujuan
│   └── Services/
│       └── ApprovalWorkflowService.php    # Core business logic untuk alur (workflow) cuti
├── routes/
│   └── api.php                            # Definisi routing API (semua endpoint modul)
├── stress-test/
│   ├── stress-test.js                     # Stress test modul Approval Workflow (Leave Submit & My Requests)
│   ├── employee-stress-test.js            # Stress test modul Employee & Organisasi (List, Hierarki, Statistik)
│   ├── attendance-stress-test.js          # Stress test modul Attendance & Reporting (Clock-in/out, Laporan)
│   └── auth-stress-test.js                # Stress test modul Authentication (Login, Me, Refresh, Logout)
└── README.md                              # Dokumentasi proyek (file ini)
```

---

## ⚙️ Mekanisme Program

Seluruh fitur sistem HR diakses melalui API berbasis RESTful dengan autentikasi **JWT (JSON Web Token)**. Berikut adalah mekanisme kerja setiap modul:

### 1. Autentikasi (Login & Logout)

Sistem autentikasi menggunakan JWT yang dikelola oleh `AuthController` dan `AuthService`. Setiap endpoint (kecuali login & register) memerlukan token JWT yang valid.

- **Register:** `POST /api/register`
  Karyawan baru mendaftar dengan mengirimkan data profil (`name`, `email`, `password`, `position`, `department`, `manager_id`). Sistem akan memvalidasi input, membuat akun karyawan, dan langsung mengembalikan JWT token sehingga user bisa mengakses API tanpa login ulang.

- **Login:** `POST /api/login`
  Karyawan mengirimkan `email` dan `password`. Sistem melakukan verifikasi kredensial menggunakan *bcrypt hash comparison*. Jika valid, server menghasilkan JWT token berisi identitas user dengan masa berlaku tertentu (*TTL*). Token ini wajib disertakan di header `Authorization: Bearer {token}` untuk setiap request selanjutnya.

- **Profil User:** `GET /api/me`
  Mengambil data profil karyawan yang sedang login beserta relasi `manager` (atasan) dan `subordinates` (bawahan langsung). Endpoint ini dipanggil oleh frontend setiap kali halaman dimuat untuk menampilkan identitas user.

- **Refresh Token:** `POST /api/refresh`
  Memperbarui JWT token yang hampir expired tanpa harus login ulang. Frontend memanggil endpoint ini secara berkala untuk menjaga sesi tetap aktif.

- **Logout:** `POST /api/logout`
  Menginvalidasi (*blacklist*) JWT token yang sedang aktif. Setelah logout, token tersebut tidak bisa digunakan lagi meskipun belum expired.

**Alur Autentikasi:**
```
┌─────────┐    POST /login     ┌──────────┐    JWT Token     ┌─────────────┐
│  Client  │ ───────────────── │  Server  │ ──────────────── │   Client    │
│          │  email + password │ (bcrypt) │  access_token   │ (simpan di  │
│          │                   │          │  + token_type   │  localStorage│
└─────────┘                    └──────────┘  + expires_in   └─────────────┘
                                                                    │
                                                   Sertakan di setiap request:
                                                   Authorization: Bearer {token}
```

### 2. Kehadiran (Clock-in & Clock-out)

Modul kehadiran dikelola oleh `AttendanceController` dan `AttendanceService`. Sistem mencatat jam masuk dan jam keluar karyawan secara harian.

- **Clock-in:** `POST /api/v1/attendance/clock-in`
  Karyawan melakukan absen masuk. Sistem mencatat waktu clock-in pada tanggal hari ini. Jika karyawan sudah melakukan clock-in pada hari yang sama, API akan mengembalikan status `400` (duplikasi dicegah oleh business logic).

- **Clock-out:** `POST /api/v1/attendance/clock-out`
  Karyawan melakukan absen pulang. Sistem mencatat waktu clock-out dan menghitung durasi kerja. Jika karyawan belum clock-in atau sudah clock-out sebelumnya pada hari tersebut, API mengembalikan status `400`.

- **Riwayat Kehadiran:** `GET /api/v1/attendance/me`
  Karyawan melihat riwayat kehadiran pribadi. Mendukung filter berdasarkan `month` & `year`, atau rentang tanggal (`start_date` & `end_date`). Default menampilkan data bulan berjalan.

- **Laporan Kehadiran:** `GET /api/v1/attendance/report`
  HR dapat melihat laporan kehadiran seluruh karyawan, sedangkan karyawan biasa hanya bisa melihat miliknya sendiri. Mendukung filter per `employee_id`, `month`/`year`, dan rentang tanggal.

- **Export Laporan (CSV):** `GET /api/v1/attendance/report/export`
  HR mengekspor laporan kehadiran ke format CSV untuk keperluan rekapitulasi atau integrasi dengan sistem penggajian. Mendukung filter yang sama dengan endpoint report.

**Alur Kehadiran Harian:**
```
  Pagi (Masuk Kerja)              Sore (Pulang Kerja)
┌──────────────────┐           ┌──────────────────┐
│ POST /clock-in   │           │ POST /clock-out  │
│                  │           │                  │
│ ✅ Catat waktu   │ ───────── │ ✅ Catat waktu   │
│    masuk hari ini│  Bekerja  │    keluar        │
│                  │           │ ✅ Hitung durasi  │
│ ❌ 400 jika sudah│           │ ❌ 400 jika belum │
│    clock-in      │           │    clock-in      │
└──────────────────┘           └──────────────────┘
                                        │
                               GET /attendance/me
                               📊 Lihat riwayat kehadiran
```

### 3. Pengajuan Cuti (Karyawan)
- **Endpoint:** `POST /api/leaves`
- **Mekanisme:** Karyawan login dan mengirimkan payload berupa tanggal mulai (`start_date`), tanggal selesai (`end_date`), alasan (`reason`), dan tipe cuti (`type`).
- **Validasi Business Logic:** Sistem (`ApprovalWorkflowService`) akan memvalidasi apakah karyawan memiliki saldo cuti yang cukup, dan apakah sudah ada pengajuan cuti lain yang statusnya masih *pending*. Jika valid, cuti akan disimpan dengan status awal `pending`.
- **Cek Riwayat Cuti:** Karyawan dapat melihat daftar riwayat dan status pengajuan cuti mereka melalui `GET /api/leaves/my-requests`.

### 4. Proses Approval (Manager / Atasan)
- **Endpoint Pending Approval:** `GET /api/approvals/pending`
  Manager dapat melihat semua pengajuan cuti dari bawahan yang menunggu persetujuan mereka.
- **Endpoint Eksekusi Approval:**
  - Level 1: `POST /api/approvals/level-1/{leave_request_id}`
  - Level 2: `POST /api/approvals/level-2/{leave_request_id}`
- **Mekanisme:** Manager mengirimkan status (`approved` atau `rejected`) beserta catatan/alasan (`notes`). Sistem akan memproses status tersebut. Apabila ditolak, status akhir cuti menjadi `rejected` dan saldo cuti dikembalikan. Jika disetujui, status berlanjut ke tahap berikutnya atau menjadi `approved` sepenuhnya.

---

## 🚀 Testing Performa (Stress Test) dengan k6

Untuk memastikan keandalan sistem dalam menangani banyak *request* (concurrent users), proyek ini dilengkapi dengan **4 skenario Stress Testing** yang mencakup seluruh modul API menggunakan alat bantu **k6** (Grafana k6).

### Daftar Skenario Stress Test

| No | File Script | Modul | Endpoint yang Diuji | Distribusi Beban |
|---|---|---|---|---|
| 1 | `stress-test.js` | Approval Workflow | `GET /api/leaves/my-requests`, `POST /api/leaves` | 100% - 100% (berurutan) |
| 2 | `employee-stress-test.js` | Employee & Organisasi | `GET /api/employees`, `GET /api/employees/{id}/subordinates`, `GET /api/employees/statistics`, `GET /api/employees/{id}/hierarchy` | 35% / 30% / 25% / 10% |
| 3 | `attendance-stress-test.js` | Attendance & Reporting | `POST /api/v1/attendance/clock-in`, `POST /api/v1/attendance/clock-out`, `GET /api/v1/attendance/me`, `GET /api/v1/attendance/report`, `GET /api/v1/attendance/report/export` | 25% / 25% / 25% / 15% / 10% |
| 4 | `auth-stress-test.js` | Authentication | `POST /api/login`, `GET /api/me`, `POST /api/refresh`, `POST /api/logout` | 30% / 35% / 20% / 15% |

### 3 Tahapan Beban (Stages)
Semua skenario stress test menggunakan pola beban yang sama — bertahap (*Ramp-up & Peak load*) untuk mengamati bagaimana performa Laravel menangani lonjakan traffic:

| Tahap | Users | Tujuan |
|---|---|---|
| 🟢 Normal | 50 | Baseline performa harian |
| 🟡 Peak | 150 | Simulasi jam sibuk |
| 🔴 Stress | 300 | Menemukan breaking point |

### 7 Indikator Pengukuran & Alasan
K6 akan memantau metrik performa secara *real-time* dan mencocokkannya dengan kriteria kelulusan. Berikut adalah indikator utama beserta alasannya:

| No | Indikator | Mengapa Dipilih (Singkat) |
|---|---|---|
| 1 | **Response Time** | Bukti paling langsung bahwa indexing & cache bekerja |
| 2 | **Throughput (req/s)** | Mengukur kapasitas total — query ringan = lebih banyak request terlayani |
| 3 | **Error Rate** | Memastikan sistem stabil, bukan hanya cepat |
| 4 | **P95 / P99** | Rata-rata bisa menipu — P99 mengungkap user yang "sial" (cache miss, slow query) |
| 5 | **Concurrent Users** | Konteks perusahaan besar — berapa user bersamaan sebelum sistem degradasi? |
| 6 | **CPU & Memory** | Membuktikan efisiensi resource dari select optimization & indexing |
| 7 | **Redis Hit Rate** | Bukti spesifik bahwa Redis cache berfungsi efektif |

*(Batas kelulusan tetap mengacu pada `p95` < 500ms-1s untuk endpoint spesifik, dan *Error Rate* HTTP 5xx di bawah 5%).*

### Cara Menjalankan k6 Test
Pastikan k6 sudah terinstall di perangkat Anda. Lalu jalankan perintah berikut di terminal:
```bash
# 1. Stress test modul Approval Workflow (Leave)
k6 run --env BASE_URL=http://localhost stress-test/stress-test.js

# 2. Stress test modul Employee & Organisasi
k6 run --env BASE_URL=http://localhost stress-test/employee-stress-test.js

# 3. Stress test modul Attendance & Reporting
k6 run --env BASE_URL=http://localhost stress-test/attendance-stress-test.js

# 4. Stress test modul Authentication
k6 run --env BASE_URL=http://localhost stress-test/auth-stress-test.js
```

> **Tips:** Untuk menyimpan hasil test dalam format JSON, tambahkan flag `--out json=results.json` pada setiap perintah di atas.

---

## ⚡ Optimasi Database & Performa

Sistem ini telah melalui serangkaian proses optimalisasi pada tingkat database dan *caching* untuk memastikan performa yang sangat cepat, khususnya dalam menangani volume data yang besar (misal: lebih dari 100.000 *records*). 

Berikut adalah metrik peningkatan performa berdasarkan hasil perbaikan:

| No | Optimalisasi | Endpoint Terdampak | Sebelum | Sesudah | Peningkatan |
|---|---|---|---|---|---|
| 1 | **Index `leave_requests`** <br> `(employee_id, created_at, start_date+end_date)` | Submit cuti, Riwayat cuti, Cuti bawahan | ~200-500ms (100K) | ~1-5ms | ⬆ ~100x lipat |
| 2 | **Index `leave_approvals`** <br> `(approver_id, created_at)` | Proses approval, Daftar pending | ~200-500ms (100K) | ~1-5ms | ⬆ ~100x lipat |
| 3 | **Redis Cache** | Semua endpoint baca `leave_balance` | ~10-30ms | ~0.5-2ms | ⬆ ~5-15x lipat |
| 4 | **N+1 Query Fix** (Eager Loading) | Semua endpoint list | ~150-300ms (10 rec) | ~30-50ms | ⬆ ~3-6x lipat |
| 5 | **Select Optimization** | Semua endpoint baca Employee | ~400 bytes/row | ~24 bytes/row | ⬆ Transfer ~94% lebih kecil |
| 6 | **Event Queue (Redis)** | Approval endpoints | +2-5 detik (produksi) | +0ms (Async) | ⬆ Instan |

### Rincian Penjelasan Optimasi:

- **Indexing pada Database:** Penambahan *composite index* pada tabel `leave_requests` dan `leave_approvals` memangkas waktu pencarian secara drastis. Pencarian riwayat cuti maupun daftar persetujuan yang sebelumnya harus melakukan *Full Table Scan* hingga memakan waktu setengah detik pada 100.000 data, kini langsung meloncat ke data terkait melalui struktur *B-Tree* hanya dalam 1-5 milidetik.
- **Redis Cache:** Penggunaan mekanisme *caching* dengan Redis untuk menyimpan sisa saldo cuti (`leave_balance`). Ini menghindari eksekusi kueri berulang yang berat ke database utama. Response API menjadi jauh lebih cepat karena data ditarik langsung dari RAM (Memory).
- **N+1 Query Fix:** Pendekatan *Eager Loading* diimplementasikan untuk mencegah masalah klasik N+1 saat memuat relasi (misalnya memuat profil *User* atau Atasan pada sebuah daftar cuti). Banyak *query* kecil yang membebani database digabungkan menjadi 1 atau 2 *query* saja.
- **Select Optimization:** Menerapkan penyeleksian kolom secara eksplisit (seperti `SELECT id, name`) ketimbang mengambil semua isi tabel (`SELECT *`). Hal ini mampu menurunkan ukuran *payload* dari database hingga 94%, mempercepat transfer *network* dan konsumsi *memory* di server.
- **Event Queue (Redis):** Proses berat pasca-*approval*, seperti mengirim notifikasi email atau menembak *webhook* eksternal, dilempar ke sistem *Background Jobs* / Antrean (Queue). Alhasil, tidak ada *blocking* waktu bagi user; respons API diberikan secara instan, sementara proses dilanjutkan di balik layar.
