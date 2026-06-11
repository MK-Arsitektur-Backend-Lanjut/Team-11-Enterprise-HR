# Dokumentasi Modul: Employee & Organization

Dokumen ini menjelaskan struktur, alur kerja, arsitektur, serta detail endpoint dari Modul Employee & Organization pada sistem Enterprise HR.

## 1. Ringkasan Modul (Overview)
Modul **Employee** merupakan inti dari sistem sumber daya manusia perusahaan. Modul ini bertanggung jawab untuk:
- Mengelola data profil karyawan (CRUD).
- Menangani hierarki organisasi yang kompleks (*manager - subordinates*).
- Menyediakan data statistik agregasi untuk *dashboard* HR (berdasarkan jabatan dan departemen).
- Mengatur *Leave Balance* (kuota cuti) tahunan karyawan.

Karena struktur perusahaannya mensyaratkan data dalam jumlah sangat besar (5.000+ karyawan dengan hierarki yang dalam), performa pemanggilan data (Read) menjadi prioritas utama.

---

## 2. Arsitektur & Pola Desain (Design Pattern)
Modul ini mengadopsi pola **Service-Repository Pattern** untuk memisahkan tanggung jawab (Seperation of Concerns) secara tegas:

1. **`EmployeeController`**: Lapisan terluar. Hanya bertugas menerima *HTTP Request*, melakukan validasi input dari pengguna, meneruskannya ke Service, lalu mengembalikan *HTTP Response* berformat JSON.
2. **`EmployeeService`**: Lapisan logika bisnis (Business Logic). Bertugas mengolah data, menghitung kalkulasi cuti, serta mengatur urutan pengerjaan proses sebelum menyimpannya ke *database*.
3. **`EmployeeRepository`**: Lapisan manipulasi *database* (Data Access Layer). Bertugas melakukan kueri kompleks menggunakan Eloquent ORM (misal: penarikan *statistics*, pencarian *like*, dan paginasi data).

---

## 3. Struktur Database & Optimasi (Indexing)
Semua data pegawai disimpan di dalam tabel `employees`.

### Kolom Penting:
- `id`: *Primary Key*
- `name`, `email`, `phone`: Data personal
- `department`, `position`: Jabatan struktural
- `manager_id`: *Self-referencing Foreign Key* ke `employees(id)`. Digunakan untuk membangun pohon hierarki perusahaan.
- `leave_balance`: Integer untuk menyimpan sisa cuti.

### Database Indexing untuk Performa
Untuk menangani masalah beban kueri (*bottleneck*) ketika data mencapai ribuan, beberapa bentuk **Index** telah diterapkan melalui migrasi:
- **B-Tree Indexes**: Ditambahkan pada kolom `manager_id`, `department`, dan `position` untuk mempercepat proses filter dan kalkulasi statistik secara dramatis tanpa *Full Table Scan*.
- **FULLTEXT Index**: Ditambahkan pada kolom kombinasi `name` dan `email` untuk mengakomodasi pencarian teks parsial dengan *wildcard*.

---

## 4. Daftar Endpoint API (API Reference)

Semua endpoint dilindungi oleh sistem autentikasi (`auth:api`), sehingga *client* wajib menyertakan `Bearer Token`.

### A. Employee CRUD & List
| Method | Endpoint | Deskripsi |
|---|---|---|
| **GET** | `/api/employees` | Mengambil daftar paginasi karyawan. Mendukung filter via *query string* (misal: `?search=John&department=IT`). |
| **POST** | `/api/employees` | Menambahkan karyawan baru (digunakan oleh Admin HR). |
| **GET** | `/api/employees/{id}` | Mengambil detail profil spesifik dari seorang karyawan. |
| **PUT** | `/api/employees/{id}` | Memperbarui data profil karyawan yang sudah ada. |
| **DELETE** | `/api/employees/{id}` | Menghapus (atau *soft delete*) data karyawan. |

### B. Organisasi & Hierarki
| Method | Endpoint | Deskripsi |
|---|---|---|
| **GET** | `/api/employees/{id}/subordinates` | Menampilkan seluruh bawahan (*subordinates*) langsung dari seorang manajer. |
| **GET** | `/api/employees/{id}/hierarchy` | Menampilkan pohon hierarki lengkap (menampilkan siapa atasannya, dan siapa saja bawahannya). |

### C. HR Dashboard & Manajerial
| Method | Endpoint | Deskripsi |
|---|---|---|
| **GET** | `/api/employees/statistics` | Menghasilkan ringkasan agregasi data: total karyawan, pengelompokan jumlah berdasar `department`, dan berdasar `position`. Sangat berat jika tanpa indeks database. |
| **PUT** | `/api/employees/{id}/leave-balance` | Secara spesifik memperbarui sisa kuota cuti seorang karyawan. |

---

## 5. Pengujian & Performa (Stress Testing)
Modul ini telah teruji kinerjanya menggunakan alat penguji ketahanan **Grafana k6**. Skenario simulasi ditekankan pada skenario "HR Dashboard & Managerial Check":

- **Alat Uji**: `stress-test/employee-stress-test.js`
- **Skenario Target**: Beban lalu lintas secara serentak (*Concurrent*) dengan pola: 50 -> 150 -> 300 *Virtual Users* (VUs) secara simultan.
- **Hasil Parameter**: Skrip pengetesan menunjukkan tingkat keberhasilan tinggi dengan parameter **0.00% Error Rate** pada penggunaan *traffic* yang ekstrem setelah *database indexing* diimplementasikan.
