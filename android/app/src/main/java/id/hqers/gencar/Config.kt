package id.hqers.gencar

/**
 * Konfigurasi khusus deployment ini. Ini SATU-SATUNYA file yang beda antara
 * app GENCAR (Kupang) dan SELARAS (Payakumbuh) — kalau mau clone app ini
 * buat kota lain, cukup ganti isi file ini + nama package + ikon/warna.
 */
object Config {
    const val BASE_URL = "https://gencar.hqers.my.id"

    // URL tiap menu di bottom navigation
    const val URL_DASHBOARD = "$BASE_URL/dashboard/"
    const val URL_REKAP_PPL = "$BASE_URL/ppl_dashboard.php"
    const val URL_WILAYAH   = "$BASE_URL/dashboard_wilayah.php"

    // Host resmi — dipakai buat cek link internal vs eksternal
    val ALLOWED_HOSTS = listOf("gencar.hqers.my.id")
}
