# GENCAR — App Android (WebView Wrapper)

App Android buat GENCAR SE2026 (Kota Kupang) — bungkus web
`gencar.hqers.my.id` yang udah ada jadi app, tanpa nulis ulang logic
dashboard-nya. Semua fitur (Rekap PPL, Dashboard Wilayah + drill-down,
Catatan) otomatis ikut karena app ini cuma nampilin halaman web yang sama
persis kayak yang dibuka di browser.

## Kenapa WebView, bukan native penuh?

Karena semua logic (target, realisasi, drill-down, dll) udah jalan di
server (PHP). Nulis ulang itu semua di Kotlin bakal 2x kerja dan gampang
ketinggalan tiap kali web-nya diupdate. WebView wrapper otomatis selalu
sinkron — begitu web-nya diupdate, app-nya juga ikut update tanpa perlu
release APK baru.

## Struktur project

```
app/src/main/java/id/hqers/gencar/
├── MainActivity.kt   # Inti app: WebView, pull-to-refresh, bottom nav, tombol back
└── Config.kt          # SATU-SATUNYA file yang beda antar app (URL, dst)
```

## Fitur yang udah ada

- **3 menu di bawah**: Dashboard, Rekap PPL, Wilayah — masing-masing load
  URL yang sesuai
- **Pull-to-refresh** — tarik ke bawah buat reload halaman
- **Tombol back Android** — mundur di riwayat WebView dulu, baru keluar app
  kalau udah gak ada riwayat
- **Halaman error** — kalau gagal load (gak ada internet dll), muncul
  halaman "Coba Lagi" drpd WebView putih kosong
- **Session/login tetap kesimpen** — DOM storage diaktifkan, jadi kalau
  login diperlukan di suatu halaman, sesinya gak ke-reset tiap buka app

## Cara buka & jalankan

1. Install **Android Studio**: https://developer.android.com/studio
2. Open project ini di Android Studio, tunggu Gradle Sync
3. Run ke HP/emulator

## Cara build APK lewat GitHub Actions (gak perlu Android Studio)

1. Push semua isi folder ini ke repo GitHub (boleh private)
2. Tab **Actions** di repo → workflow "Build APK" jalan otomatis (atau klik "Run workflow")
3. Setelah selesai, download APK dari bagian **Artifacts**

## Kalau mau lanjut development (di Claude Code)

Beberapa ide pengembangan lanjutan yang masuk akal:
- **Push notification** (misal reminder harian buat import data) — butuh
  backend tambahan (Firebase Cloud Messaging atau semacamnya), gak ada di
  versi ini
- **Splash screen** custom
- **Deep link** dari notifikasi langsung ke halaman tertentu
- Icon app masih vector sederhana buatan sendiri — bisa didesain ulang
  yang lebih bagus (Android Studio ada Image Asset Studio buat generate
  icon adaptif resmi dari file PNG/SVG)

## Bikin versi buat kota lain

Cukup:
1. Copy folder project ini
2. Ganti isi `Config.kt` (BASE_URL, dst)
3. Ganti `applicationId` di `app/build.gradle.kts` (harus unik per app)
4. Ganti `app_name` di `strings.xml` + warna di `colors.xml` kalau perlu beda branding
