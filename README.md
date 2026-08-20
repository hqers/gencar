# GENCAR SE2026 — Kota Kupang

Sistem monitoring pendataan lapangan SE2026, **web + app Android** dalam 1 repo.
Fork dari SELARAS (Kota Payakumbuh) — kode intinya sama, data wilayah &
target-nya diganti khusus Kupang.

```
gencar/
├── web/       PHP + SQLite — dashboard, import data, rekap PPL, dsb
└── android/   App Android (Kotlin) — WebView wrapper dari web/
```

## `web/` — Aplikasi Web

**Setup pertama kali:**
1. Copy `config.php.example` jadi `config.php`
2. Ganti `API_SECRET` di dalamnya jadi string random sendiri
3. Upload semua isi `web/` ke hosting
4. Buka `web/reset_password_admin.php` sekali buat ganti password akun
   `admin` bawaan — habis itu **hapus file itu dari server**
5. Kalau database masih kebawa dari deployment lain (misal ke-copy dari
   Payakumbuh), jalankan `web/reset_database_kosong.php` DULU sebelum
   langkah 4 — baca peringatannya baik-baik, ini destruktif
6. Login lewat `auth/login.php`

**Data yang khusus Kupang** (beda dari SELARAS):
- `lib/wilayah_lookup.php` — 6 kecamatan, 51 kelurahan, 1.336 SLS, 1.404 Sub-SLS
- `lib/dtsen_target.php` — Target Keluarga (112.544), Target Pertanian (9.566),
  Target Usaha (66.636), per kelurahan & per Sub-SLS
- `dashboard/index.php`, `api/download_lk.php`, `api/download_xlsx.php`,
  `api/generate_xlsx.py` — daftar kecamatan disesuaikan (Alak, Maulafa,
  Oebobo, Kota Raja, Kelapa Lima, Kota Lama)

## `android/` — App Android

WebView wrapper, config ada di
`android/app/src/main/java/id/hqers/gencar/Config.kt` (BASE_URL:
`gencar.hqers.my.id`).

## Build APK otomatis (GitHub Actions)

Workflow di `.github/workflows/build-android.yml` otomatis jalan tiap ada
push yang nyentuh folder `android/`. APK bisa didownload dari tab
**Actions** → run yang selesai → **Artifacts**.

## Catatan

Kalau nanti BPS pusat ganti skema export Excel-nya lagi (kayak yang udah
2-3 kali kejadian sebelumnya), cek dulu `lib/sls_import_parser.php` — ada
auto-detect skema di situ (fungsi `slsDetectProporsiPertanianFields` dkk)
yang mungkin perlu disesuaikan lagi.
