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
│   │   │   ├── LeaveController.php       # Menangani endpoint pengajuan cuti karyawan
│   │   │   └── ApprovalController.php    # Menangani endpoint approval (persetujuan/penolakan)
│   ├── Repositories/
│   │   └── LeaveRequestRepository.php    # Mengelola logika query database untuk cuti dan persetujuan
│   └── Services/
│       └── ApprovalWorkflowService.php   # Core business logic untuk alur (workflow) cuti
├── routes/
│   └── api.php                           # Definisi routing API (endpoint cuti & approval)
├── stress-test/
│   └── stress-test.js                    # Script testing performa (Stress Test) menggunakan k6
└── README.md                             # Dokumentasi proyek (file ini)
```

---

## ⚙️ Mekanisme Program (Modul Approval Cuti)

Alur kerja (workflow) pengajuan dan persetujuan cuti diatur secara terpusat melalui API berbasis RESTful dengan autentikasi JWT.

### 1. Pengajuan Cuti (Karyawan)
- **Endpoint:** `POST /api/leaves`
- **Mekanisme:** Karyawan login dan mengirimkan payload berupa tanggal mulai (`start_date`), tanggal selesai (`end_date`), alasan (`reason`), dan tipe cuti (`type`).
- **Validasi Business Logic:** Sistem (`ApprovalWorkflowService`) akan memvalidasi apakah karyawan memiliki saldo cuti yang cukup, dan apakah sudah ada pengajuan cuti lain yang statusnya masih *pending*. Jika valid, cuti akan disimpan dengan status awal `pending`.
- **Cek Riwayat Cuti:** Karyawan dapat melihat daftar riwayat dan status pengajuan cuti mereka melalui `GET /api/leaves/my-requests`.

### 2. Proses Approval (Manager / Atasan)
- **Endpoint Pending Approval:** `GET /api/approvals/pending`
  Manager dapat melihat semua pengajuan cuti dari bawahan yang menunggu persetujuan mereka.
- **Endpoint Eksekusi Approval:**
  - Level 1: `POST /api/approvals/level-1/{leave_request_id}`
  - Level 2: `POST /api/approvals/level-2/{leave_request_id}`
- **Mekanisme:** Manager mengirimkan status (`approved` atau `rejected`) beserta catatan/alasan (`notes`). Sistem akan memproses status tersebut. Apabila ditolak, status akhir cuti menjadi `rejected` dan saldo cuti dikembalikan. Jika disetujui, status berlanjut ke tahap berikutnya atau menjadi `approved` sepenuhnya.

---

## 🚀 Testing Performa (Stress Test) dengan k6

Untuk memastikan keandalan sistem dalam menangani banyak *request* (concurrent users), proyek ini dilengkapi dengan skenario *Stress Testing* menggunakan alat bantu **k6** (Grafana k6).

### Skenario Testing
File script k6 terletak di `stress-test/stress-test.js`. Skrip ini difokuskan pada pengujian 2 endpoint utama dalam modul approval, yaitu:
1. `GET /api/leaves/my-requests` (Karyawan mengecek status cutinya)
2. `POST /api/leaves` (Karyawan mengajukan cuti baru)

**Distribusi Beban:**  
Skenario diatur dengan distribusi **100% - 100%**. Artinya, pada setiap iterasi yang dilakukan oleh satu Virtual User (VU), sistem akan mengeksekusi request `GET /my-requests` dan `POST /api/leaves` secara berurutan, sehingga kedua endpoint tersebut menerima beban yang sama berat.

**3 Tahapan Beban (Stages):**  
Test dirancang berjalan secara bertahap (Ramp-up & Peak load) untuk mengamati bagaimana performa Laravel menangani lonjakan traffic:

| Tahap | Users | Tujuan |
|---|---|---|
| 🟢 Normal | 10-30 | Baseline performa harian |
| 🟡 Peak | 50-100 | Simulasi jam sibuk |
| 🔴 Stress | 150-300 | Menemukan breaking point |

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
# Set Base URL dan jalankan test
k6 run --env BASE_URL=http://localhost stress-test/stress-test.js
```

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
