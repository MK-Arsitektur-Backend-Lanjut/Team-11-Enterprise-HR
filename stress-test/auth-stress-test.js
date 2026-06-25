import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

// ============================================================
//  STRESS TEST — Modul Authentication
// ============================================================
//  Target   : 4 Endpoint API Utama Modul Auth
//  Fokus    : Login, Profile, Token Refresh, Logout
//  Catatan  : Test ini mensimulasikan siklus hidup autentikasi
//             penuh — dari login hingga logout — secara bersamaan
//             oleh ratusan user.
// ============================================================

const BASE_URL      = __ENV.BASE_URL      || 'http://localhost';
const USER_EMAIL    = __ENV.USER_EMAIL    || 'manager1.hr@enterprise.com';
const USER_PASSWORD = __ENV.USER_PASSWORD || 'password123';

// ============================================================
//  CUSTOM METRICS
// ============================================================

// --- Response Time (per endpoint, dalam ms) ---
const rtLogin   = new Trend('rt_post_login', true);
const rtMe      = new Trend('rt_get_me', true);
const rtRefresh = new Trend('rt_post_refresh', true);
const rtLogout  = new Trend('rt_post_logout', true);

// --- Error Rate ---
const errorRate    = new Rate('error_rate');
const serverErrors = new Counter('server_errors_5xx');

// --- Throughput (per endpoint) ---
const reqLogin   = new Counter('throughput_login');
const reqMe      = new Counter('throughput_me');
const reqRefresh = new Counter('throughput_refresh');
const reqLogout  = new Counter('throughput_logout');

// ============================================================
//  OPSI TEST & SKENARIO BEBAN
// ============================================================
//
//  Distribusi beban:
//    30% Login    — Proses autentikasi (hashing & JWT generation)
//    35% Me       — Endpoint paling sering diakses (setiap page load)
//    20% Refresh  — Token refresh berkala
//    15% Logout   — Invalidasi token
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
        'http_req_duration':  ['p(95)<1500', 'p(99)<3000'],
        'rt_post_login':      ['p(95)<1000', 'avg<500'],   // Login berat (bcrypt hashing)
        'rt_get_me':          ['p(95)<300',  'avg<100'],   // Me harus sangat cepat (read-only)
        'rt_post_refresh':    ['p(95)<500',  'avg<200'],   // Refresh cukup ringan
        'rt_post_logout':     ['p(95)<500',  'avg<200'],   // Logout cukup ringan

        // --- Error Rate ---
        'error_rate': ['rate<0.05'],

        // --- Throughput ---
        'http_reqs': ['rate>10'],
    },
};

// ============================================================
//  SETUP — Login untuk mendapatkan JWT Token awal
// ============================================================
//  Token dari setup dipakai oleh endpoint `me`, `refresh`, dan `logout`.
//  Endpoint `login` akan melakukan login sendiri di setiap iterasi.
//
export function setup() {
    console.log(`\n🔧 Konfigurasi Stress Test (Auth Modul):`);
    console.log(`   Base URL : ${BASE_URL}`);
    console.log(`   User     : ${USER_EMAIL}`);
    console.log(`   Stages   : 50 → 150 → 300 VUs\n`);

    const loginRes = http.post(
        `${BASE_URL}/api/login`,
        JSON.stringify({ email: USER_EMAIL, password: USER_PASSWORD }),
        { headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } }
    );

    const loginOk = check(loginRes, {
        'setup login status 200': (r) => r.status === 200,
        'setup login has token':  (r) => r.json('access_token') !== undefined,
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

    return {
        token: token,
        email: USER_EMAIL,
        password: USER_PASSWORD,
    };
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

    // Distribusi: 30% login, 35% me, 20% refresh, 15% logout
    if (random < 0.30) {
        testLogin(data.email, data.password);
    } else if (random < 0.65) {
        testMe(headers);
    } else if (random < 0.85) {
        testRefresh(headers);
    } else {
        testLogout(headers);
    }

    // Think time: simulasi jeda user sebelum aksi berikutnya (1-3 detik)
    sleep(Math.random() * 2 + 1);
}

// ============================================================
//  FUNGSI TEST PER-ENDPOINT
// ============================================================

// ----- POST /api/login -----
// Endpoint autentikasi utama. Berat karena melakukan bcrypt hash comparison.
// Ini adalah bottleneck umum pada sistem autentikasi di bawah load tinggi.
function testLogin(email, password) {
    group('POST /api/login', () => {
        const payload = JSON.stringify({
            email: email,
            password: password,
        });

        const res = http.post(`${BASE_URL}/api/login`, payload, {
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            tags: { endpoint: 'login' },
        });

        rtLogin.add(res.timings.duration);
        reqLogin.add(1);
        errorRate.add(res.status >= 500);
        if (res.status >= 500) serverErrors.add(1);

        check(res, {
            '[login] status 200': (r) => r.status === 200,
            '[login] has access_token': (r) => {
                try { return r.json('access_token') !== undefined; }
                catch (e) { return false; }
            },
            '[login] has token_type': (r) => {
                try { return r.json('token_type') === 'bearer'; }
                catch (e) { return false; }
            },
        });
    });
}

// ----- GET /api/me -----
// Endpoint paling sering diakses — setiap halaman frontend memanggil ini
// untuk mendapatkan profil user yang sedang login beserta relasi (manager, subordinates).
function testMe(headers) {
    group('GET /api/me', () => {
        const res = http.get(`${BASE_URL}/api/me`, {
            headers,
            tags: { endpoint: 'me' },
        });

        rtMe.add(res.timings.duration);
        reqMe.add(1);
        errorRate.add(res.status >= 500);
        if (res.status >= 500) serverErrors.add(1);

        check(res, {
            '[me] status 200': (r) => r.status === 200,
            '[me] has id': (r) => {
                try { return r.json('id') !== undefined; }
                catch (e) { return false; }
            },
            '[me] has email': (r) => {
                try { return r.json('email') !== undefined; }
                catch (e) { return false; }
            },
        });
    });
}

// ----- POST /api/refresh -----
// Refresh JWT token. Dipanggil secara berkala oleh frontend
// sebelum token expired untuk menjaga session tetap hidup.
// Catatan: Dalam stress test, semua VU menggunakan token yang sama dari setup.
//          Beberapa refresh mungkin gagal (401) karena token sudah di-blacklist
//          oleh VU lain. Ini adalah perilaku normal dalam stress test.
function testRefresh(headers) {
    group('POST /api/refresh', () => {
        const res = http.post(`${BASE_URL}/api/refresh`, null, {
            headers,
            tags: { endpoint: 'refresh' },
        });

        rtRefresh.add(res.timings.duration);
        reqRefresh.add(1);

        // 200 = berhasil, 401 = token sudah expired/blacklisted (normal di stress test)
        errorRate.add(res.status >= 500);
        if (res.status >= 500) serverErrors.add(1);

        check(res, {
            '[refresh] status 200 or 401': (r) => r.status === 200 || r.status === 401,
        });
    });
}

// ----- POST /api/logout -----
// Invalidasi JWT token. Setelah logout, token tidak bisa dipakai lagi.
// Catatan: Dalam stress test, semua VU berbagi token yang sama.
//          Setelah satu VU logout, VU lain mungkin gagal (401).
//          Ini adalah perilaku normal; kita hanya tracking 5xx errors.
function testLogout(headers) {
    group('POST /api/logout', () => {
        const res = http.post(`${BASE_URL}/api/logout`, null, {
            headers,
            tags: { endpoint: 'logout' },
        });

        rtLogout.add(res.timings.duration);
        reqLogout.add(1);

        // 200 = berhasil, 401 = token sudah expired/blacklisted (normal di stress test)
        errorRate.add(res.status >= 500);
        if (res.status >= 500) serverErrors.add(1);

        check(res, {
            '[logout] status 200 or 401': (r) => r.status === 200 || r.status === 401,
        });
    });
}

// ============================================================
//  TEARDOWN — Berjalan SEKALI setelah test selesai
// ============================================================
export function teardown(data) {
    console.log('\n✅ Stress test Modul Authentication selesai!');
    console.log('📊 Lihat ringkasan indikator di atas.');
    console.log('📁 Untuk output JSON: k6 run --out json=results.json auth-stress-test.js');
    console.log('');
}
