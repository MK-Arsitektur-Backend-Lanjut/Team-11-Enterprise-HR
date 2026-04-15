<p align="center">
  <h1 align="center">🏢 Team 11 - Enterprise HR (Approval Module)</h1>
</p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## 📖 Tentang Project

Project ini adalah **Leave Approval Module** (Modul Persetujuan Cuti) yang merupakan bagian dari Sistem HR Enterprise terintegrasi (Team 11). Dibangun menggunakan **Laravel**, modul ini menangani seluruh siklus pengajuan cuti karyawan, mulai dari permohonan awal, proses multi-level approval (HRD, Manajer, dsb), hingga ke pengiriman notifikasi ketika status persetujuan telah diperbarui.

### ✨ Fitur Utama

- **Pengajuan Cuti (Leave Request)**: Karyawan dapat membuat dan melacak status permohonan cuti.
- **Alur Persetujuan (Approval Workflow)**: Mendukung persetujuan bertingkat untuk permohonan cuti yang diatur melalui service independen.
- **Event-Driven Notifications**: Menggunakan implementasi Event-Listener Laravel untuk proses notifikasi di latar belakang yang lebih efisien.
- **Repository & Service Pattern**: Kode terstruktur rapi untuk menjaga logika bisnis terpisah dari Controller, sehingga memudahkan _maintenance_ jangka panjang.

---

## 🧩 Struktur Modul Approval

Modul inti sistem terletak pada direktori berikut:

- **Models**: `LeaveRequest` (Permohonan cuti) dan `LeaveApproval` (Tabel approval/persetujuan setiap entitas/manager terkait).
- **Controllers**: Endpoint API & Web yang diekspos melalui `LeaveController` & `ApprovalController`.
- **Services**: `ApprovalWorkflowService` bertanggungjawab memanajemen workflow persetujuan secara mandiri.
- **Repositories**: Akses data ke _database_ diabstraksikan melalui `LeaveRequestRepository`.
- **Events & Listeners**: Event `LeaveRequestStatusUpdated` dipicu ketika ada perubahan, lalu didengarkan oleh listener `SendLeaveNotification` untuk melog status atau mengirim email/WhatsApp (notifikasi asinkron).

---

## 🚀 Panduan Instalasi (Docker & Laravel Sail)

Project ini siap dijalankan secara seragam _cross-platform_ menggunakan [Laravel Sail](https://laravel.com/docs/sail). Anda tidak perlu repot menginstall PHP, Nginx, MySQL, atau Redis secara manual di komputer Anda, cukup mengandalkan **Docker**!

### Prasyarat

1. **Docker Desktop** terinstall dan berjalan di mesin Anda (disarankan pengaturan [WSL2 backend](https://docs.docker.com/desktop/windows/wsl/) untuk Windows).
2. Git untuk _version control_.

### Langkah-langkah Instalasi

**1. Clone Repository**  
Buka terminal dan jalankan:

```bash
git clone https://github.com/your-username/Team-11-Enterprise-HR.git
cd Team-11-Enterprise-HR
```

**2. Copy File Environment**  
Sesuaikan konfigurasi environment aplikasi:

```bash
cp .env.example .env
```

_(Catatan: pastikan `.env` Anda menggunakan kredensial default bawaan sail, misalnya DB_HOST=mysql, DB_USERNAME=sail)_

**3. Install Dependensi (Composer via Sail)**  
Jika Anda tidak memiliki Composer/PHP di mesin lokal, jalankan perintah instalasi via _small docker container_ berikut:

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php83-composer:latest \
    composer install --ignore-platform-reqs
```

> Atau, jika Anda **sudah** memiliki composer di lokal, cukup jalankan: `composer install`.

**4. Jalankan Laravel Sail (Container)**  
Aplikasi dan database akan segera dibangun dan dijalankan di latar belakang:

```bash
./vendor/bin/sail up -d
```

_(Disarankan untuk membuat alias: `alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'` pada bashrc/zshrc Anda untuk menyingkat perintah menjadi `sail` saja)._

**5. Generate App Key & Database Migrations**  
Setelah semua service docker berlabel _started/running_, generate App key dan migrasi skema tabelnya (termasuk dummy data):

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

🎉 **Selesai!** Aplikasi kini dapat diakses secara lokal di browser Anda pada **[http://localhost](http://localhost)**.

---

## 🛑 Perintah Berguna Lainnya via Sail

Gunakan baris perintah `./vendor/bin/sail` (atau `sail` jika sudah dialias) sebagai pengingat Anda berinteraksi dengan Laravel dalam Docker.

- **Tinker (Console Aplikasi)**:
    ```bash
    ./vendor/bin/sail tinker
    ```
- **Melihat Log Aplikasi**:
    ```bash
    ./vendor/bin/sail logs -f
    ```
- **Mematikan Service / Container**:
    ```bash
    ./vendor/bin/sail down
    ```
    *(Tambahkan `-v` jika Anda ingin mereset *volume* database).*

---

<p align="center">
<i>Dibangun oleh Team 11 Enterprise HR Developers</i>
</p>
