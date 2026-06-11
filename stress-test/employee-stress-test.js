import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

// ============================================================
//  STRESS TEST — Modul Employee & Organization
// ============================================================
//  Target   : 4 Endpoint API Utama Modul Employee
//  Fokus    : Operasi baca berat (Hierarki, Relasi, Statistik, Filter)
// ============================================================

const BASE_URL    = __ENV.BASE_URL    || 'http://localhost';
const USER_EMAIL  = __ENV.USER_EMAIL  || 'ceo@enterprise.com';
const USER_PASSWORD = __ENV.USER_PASSWORD || 'password123';
const TARGET_ID   = __ENV.TARGET_ID   || 2; // Target ID karyawan untuk tes hierarki (Default: 2/Director)

// ============================================================
//  CUSTOM METRICS
// ============================================================
const rtList         = new Trend('rt_get_employees_list', true);
const rtSubordinates = new Trend('rt_get_subordinates', true);
const rtStatistics   = new Trend('rt_get_statistics', true);
const rtHierarchy    = new Trend('rt_get_hierarchy', true);

const errorRate    = new Rate('error_rate');
const serverErrors = new Counter('server_errors_5xx');

const reqList         = new Counter('throughput_list');
const reqSubordinates = new Counter('throughput_subordinates');
const reqStatistics   = new Counter('throughput_statistics');
const reqHierarchy    = new Counter('throughput_hierarchy');

// ============================================================
//  OPSI TEST & SKENARIO BEBAN
// ============================================================
export const options = {
    stages: [
        // TAHAP 1: NORMAL LOAD (50 users)
        { duration: '30s', target: 50 },
        { duration: '2m',  target: 50 },

        // TAHAP 2: PEAK LOAD (150 users)
        { duration: '30s', target: 150 },
        { duration: '2m',  target: 150 },

        // TAHAP 3: STRESS TEST (300 users)
        { duration: '30s', target: 300 },
        { duration: '2m',  target: 300 },

        // RECOVERY
        { duration: '30s', target: 0 },
    ],
    thresholds: {
        'http_req_duration':       ['p(95)<1500', 'p(99)<3000'],
        'rt_get_employees_list':   ['p(95)<1000', 'avg<500'],
        'rt_get_subordinates':     ['p(95)<1000', 'avg<500'],
        'rt_get_hierarchy':        ['p(95)<1000', 'avg<500'],
        'rt_get_statistics':       ['p(95)<2000', 'avg<1000'], // Statistik lebih berat
        'error_rate':              ['rate<0.05'],
        'http_reqs':               ['rate>10'],
    },
};

// ============================================================
//  SETUP
// ============================================================
export function setup() {
    console.log(`\n🔧 Konfigurasi Stress Test (Employee Modul):`);
    console.log(`   Base URL : ${BASE_URL}`);
    console.log(`   User     : ${USER_EMAIL}`);
    console.log(`   Target ID: ${TARGET_ID}`);
    
    const loginRes = http.post(
        `${BASE_URL}/api/login`,
        JSON.stringify({ email: USER_EMAIL, password: USER_PASSWORD }),
        { headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } }
    );

    const loginOk = check(loginRes, {
        'login status 200': (r) => r.status === 200,
        'login has token': (r) => r.json('access_token') !== undefined,
    });

    if (!loginOk) {
        throw new Error(`Login gagal! Status: ${loginRes.status}. Output: ${loginRes.body}`);
    }

    return { token: loginRes.json('access_token'), targetId: TARGET_ID };
}

// ============================================================
//  SKENARIO UTAMA
// ============================================================
export default function (data) {
    const headers = {
        Authorization: `Bearer ${data.token}`,
        'Content-Type': 'application/json',
        Accept: 'application/json',
    };

    const random = Math.random();

    if (random < 0.35) {
        testEmployeeList(headers);
    } else if (random < 0.65) {
        testSubordinates(headers, data.targetId);
    } else if (random < 0.90) {
        testStatistics(headers);
    } else {
        testHierarchy(headers, data.targetId);
    }

    sleep(Math.random() * 2 + 1);
}

// ============================================================
//  FUNGSI TEST PER-ENDPOINT
// ============================================================

function testEmployeeList(headers) {
    group('GET /api/employees', () => {
        // Simulasi fitur search/filter
        const searchTerms = ['Staff', 'Manager', 'Director', ''];
        const term = searchTerms[Math.floor(Math.random() * searchTerms.length)];
        const url = term ? `${BASE_URL}/api/employees?search=${term}` : `${BASE_URL}/api/employees`;

        const res = http.get(url, { headers, tags: { endpoint: 'employee_list' } });

        rtList.add(res.timings.duration);
        reqList.add(1);
        errorRate.add(res.status >= 500);
        if (res.status >= 500) serverErrors.add(1);

        check(res, {
            '[list] status 200': (r) => r.status === 200,
        });
    });
}

function testSubordinates(headers, targetId) {
    group('GET /api/employees/{id}/subordinates', () => {
        const res = http.get(`${BASE_URL}/api/employees/${targetId}/subordinates`, { headers, tags: { endpoint: 'subordinates' } });

        rtSubordinates.add(res.timings.duration);
        reqSubordinates.add(1);
        errorRate.add(res.status >= 500);
        if (res.status >= 500) serverErrors.add(1);

        check(res, {
            '[subordinates] status 200': (r) => r.status === 200,
        });
    });
}

function testStatistics(headers) {
    group('GET /api/employees/statistics', () => {
        const res = http.get(`${BASE_URL}/api/employees/statistics`, { headers, tags: { endpoint: 'statistics' } });

        rtStatistics.add(res.timings.duration);
        reqStatistics.add(1);
        errorRate.add(res.status >= 500);
        if (res.status >= 500) serverErrors.add(1);

        check(res, {
            '[statistics] status 200': (r) => r.status === 200,
        });
    });
}

function testHierarchy(headers, targetId) {
    group('GET /api/employees/{id}/hierarchy', () => {
        const res = http.get(`${BASE_URL}/api/employees/${targetId}/hierarchy`, { headers, tags: { endpoint: 'hierarchy' } });

        rtHierarchy.add(res.timings.duration);
        reqHierarchy.add(1);
        errorRate.add(res.status >= 500);
        if (res.status >= 500) serverErrors.add(1);

        check(res, {
            '[hierarchy] status 200': (r) => r.status === 200,
        });
    });
}

export function teardown(data) {
    console.log('\n✅ Stress test Modul Employee selesai!');
}
