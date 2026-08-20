package id.hqers.gencar

import android.annotation.SuppressLint
import android.net.Uri
import android.net.http.SslError
import android.os.Bundle
import android.view.View
import android.webkit.SslErrorHandler
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.activity.OnBackPressedCallback
import androidx.appcompat.app.AppCompatActivity
import id.hqers.gencar.databinding.ActivityMainBinding

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private var sedangLoadError = false

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        setupWebView()
        setupSwipeRefresh()
        setupBottomNav()
        setupBackButton()
        binding.btnCobaLagi.setOnClickListener { cobaLagi() }

        if (savedInstanceState == null) {
            binding.webview.loadUrl(Config.URL_DASHBOARD)
        }
    }

    private fun setupWebView() {
        val webView = binding.webview
        webView.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true          // wajib buat session/login browser-based tetap kesimpen
            databaseEnabled = true
            cacheMode = android.webkit.WebSettings.LOAD_DEFAULT
            setSupportZoom(true)
            builtInZoomControls = true
            displayZoomControls = false
            useWideViewPort = true
            loadWithOverviewMode = true
        }

        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                val uri = request.url
                val scheme = uri.scheme ?: ""
                // Skema non-http (mailto:, tel:, dll) -> serahkan ke app sistem, bukan dibuka di WebView
                if (scheme != "http" && scheme != "https") {
                    return try {
                        startActivity(android.content.Intent(android.content.Intent.ACTION_VIEW, uri))
                        true
                    } catch (e: Exception) {
                        true // gak ada app yg bisa buka, abaikan aja drpd crash
                    }
                }
                // Tetap di dalam WebView (baik domain sendiri atau redirect SSO dsb)
                return false
            }

            override fun onPageStarted(view: WebView, url: String?, favicon: android.graphics.Bitmap?) {
                super.onPageStarted(view, url, favicon)
                sedangLoadError = false
                binding.errorView.visibility = View.GONE
                binding.webview.visibility = View.VISIBLE
            }

            override fun onPageFinished(view: WebView, url: String?) {
                super.onPageFinished(view, url)
                binding.swipeRefresh.isRefreshing = false
                updateBottomNavSelection(url)
            }

            override fun onReceivedError(view: WebView, request: WebResourceRequest, error: WebResourceError) {
                super.onReceivedError(view, request, error)
                // Cuma tampilin halaman error kalau yg gagal itu frame utama (bukan sub-resource kayak gambar/font)
                if (request.isForMainFrame) {
                    sedangLoadError = true
                    binding.swipeRefresh.isRefreshing = false
                    binding.webview.visibility = View.GONE
                    binding.errorView.visibility = View.VISIBLE
                }
            }

            override fun onReceivedSslError(view: WebView, handler: SslErrorHandler, error: SslError) {
                // Domain sendiri (hqers.my.id) — kalau ada masalah SSL beneran,
                // mending tetep ditolak drpd nurunin keamanan diam-diam.
                handler.cancel()
                super.onReceivedSslError(view, handler, error)
            }
        }
    }

    private fun setupSwipeRefresh() {
        binding.swipeRefresh.setOnRefreshListener {
            binding.webview.reload()
        }
        binding.swipeRefresh.setColorSchemeResources(R.color.orange, R.color.amber)
    }

    private fun setupBottomNav() {
        binding.bottomNav.setOnItemSelectedListener { item ->
            val url = when (item.itemId) {
                R.id.nav_dashboard -> Config.URL_DASHBOARD
                R.id.nav_rekap_ppl -> Config.URL_REKAP_PPL
                R.id.nav_wilayah   -> Config.URL_WILAYAH
                else -> null
            }
            if (url != null) {
                binding.webview.loadUrl(url)
                true
            } else {
                false
            }
        }
    }

    /** Sesuaikan tab bottom-nav yg lagi aktif berdasar URL yg lagi dibuka (misal user klik link internal) */
    private fun updateBottomNavSelection(url: String?) {
        if (url == null) return
        val menu = binding.bottomNav.menu
        when {
            url.contains("dashboard_wilayah.php") -> menu.findItem(R.id.nav_wilayah)?.isChecked = true
            url.contains("ppl_dashboard.php") -> menu.findItem(R.id.nav_rekap_ppl)?.isChecked = true
            url.contains("/dashboard/") -> menu.findItem(R.id.nav_dashboard)?.isChecked = true
        }
    }

    private fun setupBackButton() {
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (binding.webview.canGoBack()) {
                    binding.webview.goBack()
                } else {
                    isEnabled = false
                    onBackPressedDispatcher.onBackPressed()
                }
            }
        })
    }

    fun cobaLagi() {
        binding.webview.reload()
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        binding.webview.saveState(outState)
    }

    override fun onRestoreInstanceState(savedInstanceState: Bundle) {
        super.onRestoreInstanceState(savedInstanceState)
        binding.webview.restoreState(savedInstanceState)
    }
}
