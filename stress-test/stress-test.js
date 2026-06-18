import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

// ============================================================
//  STRESS TEST — Modul Approval Workflow
// ============================================================
//  Tool     : k6 (https://k6.io)
//  Target   : 2 Endpoint API Approval Workflow (My Requests & Submit Leave)
//  Indikator: Response Time, Throughput, Error Rate,
//             Concurrent Users, Resource Utilization
// ============================================================

// ============================================================
// 1. KONFIGURASI — Sesuaikan sebelum menjalankan
// ============================================================
//  Jalankan dengan:
//    k6 run --env BASE_URL=http://localhost stress-test.js
//
//  Atau override user:
//    k6 run --env BASE_URL=http://localhost \
//           --env USER_EMAIL=manager1.hr@enterprise.com \
//           --env USER_PASSWORD=password123 \
//           stress-test.js
// ============================================================

const BASE_URL    = __ENV.BASE_URL    || 'http://localhost';
const USER_EMAIL  = __ENV.USER_EMAIL  || 'manager1.hr@enterprise.com';
const USER_PASSWORD = __ENV.USER_PASSWORD || 'password123';

// ============================================================
// 2. CUSTOM METRICS — Untuk mengukur setiap indikator
// ============================================================

// --- Indikator 1: Response Time (per endpoint, dalam ms) ---
const rtMyRequests   = new Trend('rt_get_my_requests', true);
const rtSubmitLeave  = new Trend('rt_post_submit_leave', true);

// --- Indikator 3: Error Rate ---
// Hanya HTTP 5xx (server error) yang dihitung sebagai error.
// HTTP 4xx (misal "sudah ada pending") adalah business logic, BUKAN error server.
const errorRate = new Rate('error_rate');
const serverErrors = new Counter('server_errors_5xx');

// --- Indikator 2: Throughput (dilacak otomatis oleh k6 sebagai http_reqs) ---
// Kita tambah counter manual per-endpoint untuk detail
const reqMyRequests   = new Counter('throughput_my_requests');
const reqSubmitLeave  = new Counter('throughput_submit_leave');

// ============================================================
// 3. OPSI TEST — Skenario beban & batas kelulusan
// ============================================================
//
//  Indikator 4 (Concurrent Users) diatur melalui stages:
//  - target = jumlah Virtual Users (VU) yang aktif bersamaan
//  - Setiap VU = 1 pengguna yang mengakses API secara bersamaan
//
//  Alur beban:
//
//  VU
//  300 |                              ┌──────────┐
//      |                              │ TAHAP 3  │
//  150 |              ┌──────────┐    │  STRESS  │
//      |              │ TAHAP 2  │    │          │
//   50 |  ┌──────────┐│   PEAK   │    │          │
//      |  │ TAHAP 1  ││          │    │          │
//    0 |──┘  NORMAL  └┘          └────┘          └──→ waktu
//      0    1m   3m  4m    6m   7m   9m  10m  11m
//
export const options = {
    stages: [
        // ===== TAHAP 1: NORMAL LOAD (50 users) =====
        { duration: '30s', target: 50 },    // Naikkan perlahan ke 50 users
        { duration: '2m',  target: 50 },    // Tahan 50 users selama 2 menit

        // ===== TAHAP 2: PEAK LOAD (150 users) =====
        { duration: '30s', target: 150 },   // Naikkan ke 150 users
        { duration: '2m',  target: 150 },   // Tahan 150 users selama 2 menit

        // ===== TAHAP 3: STRESS TEST (300 users) =====
        { duration: '30s', target: 300 },   // Naikkan ke 300 users
        { duration: '2m',  target: 300 },   // Tahan 300 users selama 2 menit

        // ===== RECOVERY =====
        { duration: '30s', target: 0 },     // Turunkan ke 0 (amati pemulihan)
    ],

    // Batas kelulusan (thresholds):
    // Jika melewati batas → k6 menandai FAIL (❌)
    thresholds: {
        // --- Response Time ---
        'http_req_duration':      ['p(95)<1500', 'p(99)<3000'],  // Global: 95% < 1.5s, 99% < 3s
        'rt_get_my_requests':     ['p(95)<500',  'avg<200'],     // my-requests: 95% < 500ms
        'rt_post_submit_leave':   ['p(95)<1000', 'avg<500'],     // submit: 95% < 1s

        // --- Error Rate ---
        'error_rate': ['rate<0.05'],  // Error rate harus < 5%

        // --- Throughput ---
        'http_reqs': ['rate>10'],     // Minimal 10 request/detik secara keseluruhan
    },
};

// ============================================================
// 4. SETUP — Login untuk mendapatkan JWT Token
// ============================================================
//  Fungsi ini berjalan SEKALI di awal sebelum test dimulai.
//  Token yang didapat akan dipakai oleh semua Virtual Users.
//
export function setup() {
    console.log(`\n🔧 Konfigurasi:`);
    console.log(`   Base URL : ${BASE_URL}`);
    console.log(`   User     : ${USER_EMAIL}`);
    console.log(`   Stages   : 50 → 150 → 300 VUs\n`);

    // Kirim request login
    const loginRes = http.post(
        `${BASE_URL}/api/login`,
        JSON.stringify({
            email: USER_EMAIL,
            password: USER_PASSWORD,
        }),
        {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        }
    );

    // Cek apakah login berhasil
    const loginOk = check(loginRes, {
        'login status is 200': (r) => r.status === 200,
        'login returns token': (r) => r.json('access_token') !== undefined,
    });

    if (!loginOk) {
        console.error(`❌ LOGIN GAGAL! Status: ${loginRes.status}`);
        console.error(`   Response: ${loginRes.body}`);
        console.error(`   Pastikan:`);
        console.error(`   1. Server berjalan di ${BASE_URL}`);
        console.error(`   2. Email & password benar`);
        console.error(`   3. Sudah menjalankan: sail artisan db:seed`);
        throw new Error('Login gagal. Test dibatalkan.');
    }

    const token = loginRes.json('access_token');
    console.log(`✅ Login berhasil! Token: ${token.substring(0, 30)}...`);

    return { token: token };
}

// ============================================================
// 5. SKENARIO UTAMA — Dijalankan oleh setiap Virtual User
// ============================================================
//  Fungsi ini dijalankan BERULANG oleh setiap VU selama test.
//  Distribusi request: 100% GET my-requests dan 100% POST submit-leave
//
export default function (data) {
    const headers = {
        Authorization: `Bearer ${data.token}`,
        'Content-Type': 'application/json',
        Accept: 'application/json',
    };

    // Eksekusi kedua endpoint (100% - 100%)
    testMyRequests(headers);
    testSubmitLeave(headers);

    // Think time: simulasi jeda user sebelum aksi berikutnya (1-3 detik)
    sleep(Math.random() * 2 + 1);
}

// ============================================================
// 6. FUNGSI TEST PER-ENDPOINT
// ============================================================

// ----- GET /api/leaves/my-requests -----
// Endpoint paling sering diakses: karyawan cek riwayat & status cuti
function testMyRequests(headers) {
    group('GET /api/leaves/my-requests', () => {
        const res = http.get(`${BASE_URL}/api/leaves/my-requests`, {
            headers,
            tags: { endpoint: 'my_requests' },
        });

        // Catat metrik
        rtMyRequests.add(res.timings.duration);
        reqMyRequests.add(1);
        errorRate.add(res.status >= 500);
        if (res.status >= 500) serverErrors.add(1);

        // Validasi response
        check(res, {
            '[my-requests] status 200': (r) => r.status === 200,
            '[my-requests] has data':   (r) => r.json('success') === true,
        });
    });
}

// ----- POST /api/leaves -----
// Karyawan submit pengajuan cuti baru
// Catatan: Jika user sudah punya pending leave, API mengembalikan 400
//          Ini BUKAN error server, melainkan business logic yang benar.
function testSubmitLeave(headers) {
    group('POST /api/leaves', () => {
        // Generate tanggal acak di masa depan agar unik
        const month = Math.floor(Math.random() * 6) + 7; // Jul-Dec
        const day   = Math.floor(Math.random() * 20) + 1; // 1-20
        const startDate = `2026-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const endDay    = Math.min(day + Math.floor(Math.random() * 2) + 1, 28);
        const endDate   = `2026-${String(month).padStart(2, '0')}-${String(endDay).padStart(2, '0')}`;

        const payload = JSON.stringify({
            start_date: startDate,
            end_date:   endDate,
            reason:     `Stress test - VU ${__VU} Iter ${__ITER}`,
            type:       'annual',
        });

        const res = http.post(`${BASE_URL}/api/leaves`, payload, {
            headers,
            tags: { endpoint: 'submit_leave' },
        });

        rtSubmitLeave.add(res.timings.duration);
        reqSubmitLeave.add(1);

        // 201 = berhasil, 400 = ditolak (sudah ada pending / saldo kurang)
        // HANYA 5xx yang dianggap error
        errorRate.add(res.status >= 500);
        if (res.status >= 500) serverErrors.add(1);

        check(res, {
            '[submit] status 201 or 400': (r) => r.status === 201 || r.status === 400,
        });
    });
}

// ============================================================
// 7. TEARDOWN — Berjalan SEKALI setelah test selesai
// ============================================================
export function teardown(data) {
    console.log('\n✅ Stress test selesai!');
    console.log('📊 Lihat ringkasan indikator di atas.');
    console.log('📁 Untuk output JSON: k6 run --out json=results.json stress-test.js');
    console.log('');
}
