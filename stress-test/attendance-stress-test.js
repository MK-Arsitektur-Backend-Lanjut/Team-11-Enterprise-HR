import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

// ============================================================
//  STRESS TEST — Modul Attendance & Reporting
// ============================================================
//  Target   : 5 Endpoint API Utama Modul Attendance
//  Fokus    : Clock-in/out, Laporan Kehadiran, Export CSV
// ============================================================

const BASE_URL      = __ENV.BASE_URL      || 'http://localhost';
const USER_EMAIL    = __ENV.USER_EMAIL    || 'manager1.hr@enterprise.com';
const USER_PASSWORD = __ENV.USER_PASSWORD || 'password123';

// ============================================================
//  CUSTOM METRICS
// ============================================================

// --- Response Time (per endpoint, dalam ms) ---
const rtClockIn       = new Trend('rt_post_clock_in', true);
const rtClockOut      = new Trend('rt_post_clock_out', true);
const rtMyAttendance  = new Trend('rt_get_my_attendance', true);
const rtReport        = new Trend('rt_get_report', true);
const rtExport        = new Trend('rt_get_export', true);

// --- Error Rate ---
const errorRate    = new Rate('error_rate');
const serverErrors = new Counter('server_errors_5xx');

// --- Throughput (per endpoint) ---
const reqClockIn      = new Counter('throughput_clock_in');
const reqClockOut     = new Counter('throughput_clock_out');
const reqMyAttendance = new Counter('throughput_my_attendance');
const reqReport       = new Counter('throughput_report');
const reqExport       = new Counter('throughput_export');

// ============================================================
//  OPSI TEST & SKENARIO BEBAN
// ============================================================
//
//  Distribusi beban:
//    25% Clock-in   — Aksi paling umum di pagi hari
//    25% Clock-out  — Aksi paling umum di sore hari
//    25% My Attendance — Karyawan cek kehadiran sendiri
//    15% Report     — HR/Manager melihat laporan
//    10% Export     — HR export CSV (paling berat)
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
        { duration: '30s', target: 50 },
        { duration: '2m',  target: 50 },

        // ===== TAHAP 2: PEAK LOAD (150 users) =====
        { duration: '30s', target: 150 },
        { duration: '2m',  target: 150 },

        // ===== TAHAP 3: STRESS TEST (300 users) =====
        { duration: '30s', target: 300 },
        { duration: '2m',  target: 300 },

        // ===== RECOVERY =====
        { duration: '30s', target: 0 },
    ],
    thresholds: {
        // --- Response Time ---
        'http_req_duration':      ['p(95)<1500', 'p(99)<3000'],
        'rt_post_clock_in':       ['p(95)<500',  'avg<200'],
        'rt_post_clock_out':      ['p(95)<500',  'avg<200'],
        'rt_get_my_attendance':   ['p(95)<800',  'avg<400'],
        'rt_get_report':          ['p(95)<1500', 'avg<800'],    // Report lebih berat (agregasi)
        'rt_get_export':          ['p(95)<2000', 'avg<1000'],   // Export paling berat (CSV generation)

        // --- Error Rate ---
        'error_rate': ['rate<0.05'],

        // --- Throughput ---
        'http_reqs': ['rate>10'],
    },
};

// ============================================================
//  SETUP — Login untuk mendapatkan JWT Token
// ============================================================
export function setup() {
    console.log(`\n🔧 Konfigurasi Stress Test (Attendance Modul):`);
    console.log(`   Base URL : ${BASE_URL}`);
    console.log(`   User     : ${USER_EMAIL}`);
    console.log(`   Stages   : 50 → 150 → 300 VUs\n`);

    const loginRes = http.post(
        `${BASE_URL}/api/login`,
        JSON.stringify({ email: USER_EMAIL, password: USER_PASSWORD }),
        { headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } }
    );

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
//  SKENARIO UTAMA — Dijalankan oleh setiap Virtual User
// ============================================================
export default function (data) {
    const headers = {
        Authorization: `Bearer ${data.token}`,
        'Content-Type': 'application/json',
        Accept: 'application/json',
    };

    const random = Math.random();

    // Distribusi: 25% clock-in, 25% clock-out, 25% my-attendance, 15% report, 10% export
    if (random < 0.25) {
        testClockIn(headers);
    } else if (random < 0.50) {
        testClockOut(headers);
    } else if (random < 0.75) {
        testMyAttendance(headers);
    } else if (random < 0.90) {
        testReport(headers);
    } else {
        testExport(headers);
    }

    // Think time: simulasi jeda user sebelum aksi berikutnya (1-3 detik)
    sleep(Math.random() * 2 + 1);
}

// ============================================================
//  FUNGSI TEST PER-ENDPOINT
// ============================================================

// ----- POST /api/v1/attendance/clock-in -----
// Karyawan melakukan clock-in di awal hari kerja
// Catatan: Jika sudah clock-in hari ini, API mengembalikan 400
//          Ini BUKAN error server, melainkan business logic yang benar.
function testClockIn(headers) {
    group('POST /api/v1/attendance/clock-in', () => {
        const res = http.post(
            `${BASE_URL}/api/v1/attendance/clock-in`,
            null,
            { headers, tags: { endpoint: 'clock_in' } }
        );

        rtClockIn.add(res.timings.duration);
        reqClockIn.add(1);

        // 200 = berhasil, 400 = sudah clock-in hari ini
        // HANYA 5xx yang dianggap error
        errorRate.add(res.status >= 500);
        if (res.status >= 500) serverErrors.add(1);

        check(res, {
            '[clock-in] status 200 or 400': (r) => r.status === 200 || r.status === 400,
        });
    });
}

// ----- POST /api/v1/attendance/clock-out -----
// Karyawan melakukan clock-out di akhir hari kerja
// Catatan: Jika belum clock-in atau sudah clock-out, API mengembalikan 400
function testClockOut(headers) {
    group('POST /api/v1/attendance/clock-out', () => {
        const res = http.post(
            `${BASE_URL}/api/v1/attendance/clock-out`,
            null,
            { headers, tags: { endpoint: 'clock_out' } }
        );

        rtClockOut.add(res.timings.duration);
        reqClockOut.add(1);

        // 200 = berhasil, 400 = belum clock-in / sudah clock-out
        errorRate.add(res.status >= 500);
        if (res.status >= 500) serverErrors.add(1);

        check(res, {
            '[clock-out] status 200 or 400': (r) => r.status === 200 || r.status === 400,
        });
    });
}

// ----- GET /api/v1/attendance/me -----
// Karyawan melihat riwayat kehadiran sendiri
// Mendukung query param: month, year, start_date, end_date
function testMyAttendance(headers) {
    group('GET /api/v1/attendance/me', () => {
        // Variasi query: kadang pakai month/year, kadang tanpa filter
        const random = Math.random();
        let url = `${BASE_URL}/api/v1/attendance/me`;

        if (random < 0.4) {
            // Filter berdasarkan bulan & tahun saat ini
            const now = new Date();
            url += `?month=${now.getMonth() + 1}&year=${now.getFullYear()}`;
        } else if (random < 0.7) {
            // Filter berdasarkan date range (bulan ini)
            const now = new Date();
            const startDate = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
            const endDate = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
            url += `?start_date=${startDate}&end_date=${endDate}`;
        }
        // else: tanpa filter (default bulan ini)

        const res = http.get(url, { headers, tags: { endpoint: 'my_attendance' } });

        rtMyAttendance.add(res.timings.duration);
        reqMyAttendance.add(1);
        errorRate.add(res.status >= 500);
        if (res.status >= 500) serverErrors.add(1);

        check(res, {
            '[my-attendance] status 200': (r) => r.status === 200,
            '[my-attendance] has data': (r) => r.json('success') === true,
        });
    });
}

// ----- GET /api/v1/attendance/report -----
// HR/Manager melihat laporan kehadiran (bisa semua karyawan atau spesifik)
function testReport(headers) {
    group('GET /api/v1/attendance/report', () => {
        const random = Math.random();
        let url = `${BASE_URL}/api/v1/attendance/report`;

        if (random < 0.5) {
            // Laporan bulanan (bulan ini)
            const now = new Date();
            url += `?month=${now.getMonth() + 1}&year=${now.getFullYear()}`;
        } else if (random < 0.8) {
            // Laporan karyawan tertentu (employee_id = 2)
            const now = new Date();
            const employeeId = Math.floor(Math.random() * 5) + 1;
            url += `?employee_id=${employeeId}&month=${now.getMonth() + 1}&year=${now.getFullYear()}`;
        }
        // else: default (bulan ini, semua karyawan untuk HR)

        const res = http.get(url, { headers, tags: { endpoint: 'report' } });

        rtReport.add(res.timings.duration);
        reqReport.add(1);
        errorRate.add(res.status >= 500);
        if (res.status >= 500) serverErrors.add(1);

        check(res, {
            '[report] status 200': (r) => r.status === 200,
            '[report] has data': (r) => r.json('success') === true,
        });
    });
}

// ----- GET /api/v1/attendance/report/export -----
// HR mengexport laporan kehadiran ke format CSV
// Endpoint ini paling berat karena harus generate file CSV
function testExport(headers) {
    group('GET /api/v1/attendance/report/export', () => {
        const now = new Date();
        const url = `${BASE_URL}/api/v1/attendance/report/export?month=${now.getMonth() + 1}&year=${now.getFullYear()}`;

        const res = http.get(url, { headers, tags: { endpoint: 'export' } });

        rtExport.add(res.timings.duration);
        reqExport.add(1);
        errorRate.add(res.status >= 500);
        if (res.status >= 500) serverErrors.add(1);

        check(res, {
            // Export bisa mengembalikan 200 (CSV) atau 200 (JSON jika tidak ada data)
            '[export] status 200': (r) => r.status === 200,
        });
    });
}

// ============================================================
//  TEARDOWN — Berjalan SEKALI setelah test selesai
// ============================================================
export function teardown(data) {
    console.log('\n✅ Stress test Modul Attendance & Reporting selesai!');
    console.log('📊 Lihat ringkasan indikator di atas.');
    console.log('📁 Untuk output JSON: k6 run --out json=results.json attendance-stress-test.js');
    console.log('');
}
