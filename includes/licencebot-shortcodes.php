<?php
/**
 * LicenceBot Helper — Shortcodes bundle
 * Adds 7 standard widget shortcodes. Loaded automatically by the main plugin.
 *
 *  [licencebot_sales_notification]   Floating "X just bought ..." toaster
 *  [licencebot_sales_counter]        Static "N sales today" counter
 *  [licencebot_visitor_alerts]       "N visitors viewing this page" badge
 *  [licencebot_coupon_box]           Email-gated coupon claim form
 *  [licencebot_newsletter_signup]    Inline newsletter signup form
 *  [licencebot_sales_popup]          Centered social-proof popup
 *  [licencebot_popup]                Generic configurable popup
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'licencebot_sc_enabled' ) ) {
    /**
     * Per-shortcode on/off switch. Reads option licencebot_sc_<tag>_enabled (default '1').
     * The settings UI just sets this option to '1' or '0' — no other plumbing needed.
     */
    function licencebot_sc_enabled( $tag ) {
        return '1' === get_option( 'licencebot_sc_' . sanitize_key( $tag ) . '_enabled', '1' );
    }
}


if ( ! function_exists( 'licencebot_helper_api_base' ) ) {
    function licencebot_helper_api_base() {
        if ( defined( 'LICENCEBOT_HELPER_API_BASE' ) ) return rtrim( LICENCEBOT_HELPER_API_BASE, '/' );
        return 'https://yiczembsfiqqviqxxdxl.supabase.co';
    }
}
if ( ! function_exists( 'licencebot_helper_org_token' ) ) {
    function licencebot_helper_org_token() {
        foreach ( array( 'LICENCEBOT_HELPER_ORG_TOKEN', 'AC_SERIAL_ORG_TOKEN', 'LICENCEBOT_ORG_TOKEN' ) as $c ) {
            if ( defined( $c ) && constant( $c ) ) return constant( $c );
        }
        return '';
    }
}

/**
 * Shared inline CSS + helper JS for the bundle.
 * Printed once per page on first shortcode use.
 */
function licencebot_shortcodes_assets() {
    static $printed = false;
    if ( $printed ) return;
    $printed = true;
    $api  = esc_js( licencebot_helper_api_base() );
    $tok  = esc_js( licencebot_helper_org_token() );
    ?>
    <style>
      .lb-sc{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;line-height:1.45;color:#0f172a;box-sizing:border-box}
      .lb-sc *,.lb-sc *::before,.lb-sc *::after{box-sizing:border-box}
      .lb-sc-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px;box-shadow:0 4px 18px rgba(15,23,42,.06)}
      .lb-sc-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
      .lb-sc-input{flex:1;min-width:0;padding:11px 13px;border:1.5px solid #e2e8f0;border-radius:10px;font:inherit;background:#fff;color:#0f172a;outline:none;transition:border-color .15s}
      .lb-sc-input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.15)}
      .lb-sc-btn{background:#6366f1;color:#fff;border:0;border-radius:10px;padding:11px 18px;font:inherit;font-weight:600;cursor:pointer;transition:opacity .15s}
      .lb-sc-btn:hover{opacity:.92}
      .lb-sc-btn[disabled]{opacity:.6;cursor:wait}
      .lb-sc-msg{font-size:13px;margin-top:8px;min-height:18px}
      .lb-sc-msg.ok{color:#16a34a}
      .lb-sc-msg.err{color:#ef4444}
      .lb-sc-title{font-weight:700;font-size:18px;margin:0 0 6px}
      .lb-sc-sub{font-size:14px;color:#475569;margin:0 0 14px}
      .lb-sc-badge{display:inline-flex;align-items:center;gap:8px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:999px;padding:8px 14px;font-size:13.5px;color:#0f172a}
      .lb-sc-dot{width:8px;height:8px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 4px rgba(34,197,94,.18);animation:lb-pulse 1.6s infinite}
      @keyframes lb-pulse{0%,100%{opacity:1}50%{opacity:.55}}
      .lb-sc-counter{font-weight:800;color:#6366f1}
      .lb-toast{position:fixed;left:18px;bottom:18px;z-index:99998;max-width:320px;background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 12px 32px rgba(15,23,42,.18);padding:12px 14px;display:flex;align-items:center;gap:10px;transform:translateY(20px);opacity:0;transition:transform .3s,opacity .3s}
      .lb-toast.in{transform:translateY(0);opacity:1}
      .lb-toast img{width:40px;height:40px;border-radius:10px;object-fit:cover;flex-shrink:0}
      .lb-toast .lb-t-body{font-size:13px;color:#0f172a;line-height:1.35}
      .lb-toast .lb-t-meta{font-size:11.5px;color:#64748b;margin-top:2px}
      .lb-sc-popup-ov{position:fixed;inset:0;z-index:99997;background:rgba(15,23,42,.55);display:none;align-items:center;justify-content:center;padding:18px;animation:lb-fade .25s ease}
      .lb-sc-popup-ov.show{display:flex}
      .lb-sc-popup{background:#fff;border-radius:18px;width:min(440px,100%);padding:26px;box-shadow:0 30px 80px rgba(0,0,0,.35);position:relative;animation:lb-pop .35s cubic-bezier(.2,.8,.3,1)}
      .lb-sc-popup-x{position:absolute;top:10px;right:12px;background:transparent;border:0;font-size:22px;color:#64748b;cursor:pointer;line-height:1}
      @keyframes lb-fade{from{opacity:0}to{opacity:1}}
      @keyframes lb-pop{from{transform:scale(.92);opacity:0}to{transform:scale(1);opacity:1}}
    </style>
    <script>
      window.LBHelper = window.LBHelper || {};
      window.LBHelper.api  = "<?php echo $api; ?>";
      window.LBHelper.tok  = "<?php echo $tok; ?>";
      window.LBHelper.site = location.hostname;
      window.LBHelper.fetchWidget = window.LBHelper.fetchWidget || function (type) {
        var u = window.LBHelper.api + "/functions/v1/helper-public-widget?type=" + encodeURIComponent(type) +
                "&site=" + encodeURIComponent(window.LBHelper.site) +
                "&token=" + encodeURIComponent(window.LBHelper.tok);
        return fetch(u, { method: "GET" }).then(function (r) { return r.ok ? r.json() : { ok:false }; }).catch(function(){ return { ok:false }; });
      };
    </script>
    <?php
}

/* ------------------------------------------------------------------ */
/* 1. [licencebot_sales_notification]                                  */
/* ------------------------------------------------------------------ */
add_shortcode( 'licencebot_sales_notification', function ( $atts ) {
    if ( ! licencebot_sc_enabled( 'licencebot_sales_notification' ) ) return '';
    $atts = shortcode_atts( array(
        'interval' => 12,    // seconds between toasts
        'duration' => 6,     // visible seconds
    ), $atts, 'licencebot_sales_notification' );
    licencebot_shortcodes_assets();
    $iv = max( 4, intval( $atts['interval'] ) );
    $du = max( 3, intval( $atts['duration'] ) );
    ob_start(); ?>
    <div class="lb-sc lb-sc-sales-noti" data-interval="<?php echo $iv; ?>" data-duration="<?php echo $du; ?>"></div>
    <script>
    (function () {
      var hosts = document.querySelectorAll(".lb-sc-sales-noti:not([data-lb-init])");
      if (!hosts.length) return;
      hosts.forEach(function (h) { h.setAttribute("data-lb-init", "1"); });
      var iv = parseInt(hosts[0].getAttribute("data-interval"), 10) * 1000;
      var du = parseInt(hosts[0].getAttribute("data-duration"), 10) * 1000;
      var idx = 0, sales = [];
      function toast(s) {
        var el = document.createElement("div");
        el.className = "lb-toast";
        el.innerHTML = '<img src="' + (s.image || "https://www.gravatar.com/avatar/?d=mp&s=80") + '" alt=""/>' +
          '<div><div class="lb-t-body"><b>' + (s.who || "Someone") + '</b> bought <b>' + (s.product || "a product") + '</b></div>' +
          '<div class="lb-t-meta">' + (s.when || "just now") + '</div></div>';
        document.body.appendChild(el);
        setTimeout(function () { el.classList.add("in"); }, 30);
        setTimeout(function () { el.classList.remove("in"); setTimeout(function () { el.remove(); }, 400); }, du);
      }
      function loop() {
        if (!sales.length) return;
        toast(sales[idx % sales.length]); idx++;
        setTimeout(loop, iv);
      }
      window.LBHelper.fetchWidget("sales_notification").then(function (r) {
        sales = (r && r.items) || [];
        if (sales.length) setTimeout(loop, 2500);
      });
    })();
    </script>
    <?php
    return ob_get_clean();
} );

/* ------------------------------------------------------------------ */
/* 2. [licencebot_sales_counter]                                       */
/* ------------------------------------------------------------------ */
add_shortcode( 'licencebot_sales_counter', function ( $atts ) {
    if ( ! licencebot_sc_enabled( 'licencebot_sales_counter' ) ) return '';
    $atts = shortcode_atts( array(
        'label'  => 'sales today',
        'window' => 'day',  // day | week | month | total
    ), $atts, 'licencebot_sales_counter' );
    licencebot_shortcodes_assets();
    ob_start(); ?>
    <span class="lb-sc lb-sc-badge lb-sc-sales-counter" data-window="<?php echo esc_attr( $atts['window'] ); ?>">
        <span class="lb-sc-dot"></span>
        <span><span class="lb-sc-counter">…</span> <?php echo esc_html( $atts['label'] ); ?></span>
    </span>
    <script>
    (function () {
      document.querySelectorAll(".lb-sc-sales-counter:not([data-lb-init])").forEach(function (host) {
        host.setAttribute("data-lb-init", "1");
        var w = host.getAttribute("data-window") || "day";
        window.LBHelper.fetchWidget("sales_counter&window=" + encodeURIComponent(w)).then(function (r) {
          var n = (r && typeof r.count === "number") ? r.count : 0;
          var el = host.querySelector(".lb-sc-counter"); if (el) el.textContent = n.toLocaleString();
        });
      });
    })();
    </script>
    <?php
    return ob_get_clean();
} );

/* ------------------------------------------------------------------ */
/* 3. [licencebot_visitor_alerts]                                      */
/* ------------------------------------------------------------------ */
add_shortcode( 'licencebot_visitor_alerts', function ( $atts ) {
    if ( ! licencebot_sc_enabled( 'licencebot_visitor_alerts' ) ) return '';
    $atts = shortcode_atts( array(
        'label' => 'people viewing this page right now',
    ), $atts, 'licencebot_visitor_alerts' );
    licencebot_shortcodes_assets();
    ob_start(); ?>
    <span class="lb-sc lb-sc-badge lb-sc-visitor-alerts">
        <span class="lb-sc-dot"></span>
        <span><span class="lb-sc-counter">…</span> <?php echo esc_html( $atts['label'] ); ?></span>
    </span>
    <script>
    (function () {
      var u = encodeURIComponent(location.pathname);
      document.querySelectorAll(".lb-sc-visitor-alerts:not([data-lb-init])").forEach(function (host) {
        host.setAttribute("data-lb-init", "1");
        function pull() {
          window.LBHelper.fetchWidget("visitor_alerts&path=" + u).then(function (r) {
            var n = (r && typeof r.count === "number") ? r.count : Math.floor(2 + Math.random() * 8);
            var el = host.querySelector(".lb-sc-counter"); if (el) el.textContent = n;
          });
        }
        pull(); setInterval(pull, 30000);
      });
    })();
    </script>
    <?php
    return ob_get_clean();
} );

/* ------------------------------------------------------------------ */
/* 4. [licencebot_coupon_box]                                          */
/* ------------------------------------------------------------------ */
add_shortcode( 'licencebot_coupon_box', function ( $atts ) {
    if ( ! licencebot_sc_enabled( 'licencebot_coupon_box' ) ) return '';
    $atts = shortcode_atts( array(
        'title'    => 'Get your discount',
        'subtitle' => 'Drop your email and we will send your coupon.',
        'button'   => 'Claim my code',
    ), $atts, 'licencebot_coupon_box' );
    licencebot_shortcodes_assets();
    ob_start(); ?>
    <div class="lb-sc lb-sc-card lb-sc-coupon">
        <h3 class="lb-sc-title"><?php echo esc_html( $atts['title'] ); ?></h3>
        <p class="lb-sc-sub"><?php echo esc_html( $atts['subtitle'] ); ?></p>
        <div class="lb-sc-row">
            <input type="email" class="lb-sc-input" placeholder="you@example.com" required />
            <button type="button" class="lb-sc-btn"><?php echo esc_html( $atts['button'] ); ?></button>
        </div>
        <div class="lb-sc-msg"></div>
    </div>
    <script>
    (function () {
      document.querySelectorAll(".lb-sc-coupon:not([data-lb-init])").forEach(function (box) {
        box.setAttribute("data-lb-init", "1");
        var inp = box.querySelector(".lb-sc-input"), btn = box.querySelector(".lb-sc-btn"), msg = box.querySelector(".lb-sc-msg");
        function go() {
          var v = (inp.value || "").trim();
          msg.className = "lb-sc-msg";
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { msg.className = "lb-sc-msg err"; msg.textContent = "Please enter a valid email."; return; }
          btn.disabled = true;
          fetch(window.LBHelper.api + "/functions/v1/helper-coupon-claim", {
            method: "POST", headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email: v, site: window.LBHelper.site, token: window.LBHelper.tok, source: "shortcode" })
          }).then(function (r) { return r.json().catch(function(){return {};}).then(function(j){return {ok:r.ok,j:j};}); })
            .then(function (x) {
              btn.disabled = false;
              if (x.ok) { msg.className = "lb-sc-msg ok"; msg.textContent = (x.j && x.j.message) || "Check your email to confirm and get your code."; inp.value = ""; }
              else      { msg.className = "lb-sc-msg err"; msg.textContent = (x.j && x.j.error) || "Something went wrong. Please try again."; }
            })
            .catch(function () { btn.disabled = false; msg.className = "lb-sc-msg err"; msg.textContent = "Network error."; });
        }
        btn.addEventListener("click", go);
        inp.addEventListener("keydown", function (e) { if (e.key === "Enter") go(); });
      });
    })();
    </script>
    <?php
    return ob_get_clean();
} );

/* ------------------------------------------------------------------ */
/* 5. [licencebot_newsletter_signup]                                   */
/* ------------------------------------------------------------------ */
add_shortcode( 'licencebot_newsletter_signup', function ( $atts ) {
    if ( ! licencebot_sc_enabled( 'licencebot_newsletter_signup' ) ) return '';
    $atts = shortcode_atts( array(
        'title'    => 'Join our newsletter',
        'subtitle' => 'Tips, launches and exclusive deals — once a week.',
        'button'   => 'Subscribe',
    ), $atts, 'licencebot_newsletter_signup' );
    licencebot_shortcodes_assets();
    ob_start(); ?>
    <div class="lb-sc lb-sc-card lb-sc-nl">
        <h3 class="lb-sc-title"><?php echo esc_html( $atts['title'] ); ?></h3>
        <p class="lb-sc-sub"><?php echo esc_html( $atts['subtitle'] ); ?></p>
        <div class="lb-sc-row">
            <input type="email" class="lb-sc-input" placeholder="you@example.com" required />
            <button type="button" class="lb-sc-btn"><?php echo esc_html( $atts['button'] ); ?></button>
        </div>
        <div class="lb-sc-msg"></div>
    </div>
    <script>
    (function () {
      document.querySelectorAll(".lb-sc-nl:not([data-lb-init])").forEach(function (box) {
        box.setAttribute("data-lb-init", "1");
        var inp = box.querySelector(".lb-sc-input"), btn = box.querySelector(".lb-sc-btn"), msg = box.querySelector(".lb-sc-msg");
        function go() {
          var v = (inp.value || "").trim();
          msg.className = "lb-sc-msg";
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { msg.className = "lb-sc-msg err"; msg.textContent = "Please enter a valid email."; return; }
          btn.disabled = true;
          fetch(window.LBHelper.api + "/functions/v1/helper-newsletter-subscribe", {
            method: "POST", headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email: v, site: window.LBHelper.site, token: window.LBHelper.tok, source: "shortcode" })
          }).then(function (r) { return r.json().catch(function(){return {};}).then(function(j){return {ok:r.ok,j:j};}); })
            .then(function (x) {
              btn.disabled = false;
              if (x.ok) { msg.className = "lb-sc-msg ok"; msg.textContent = (x.j && x.j.message) || "Subscribed — thank you!"; inp.value = ""; }
              else      { msg.className = "lb-sc-msg err"; msg.textContent = (x.j && x.j.error) || "Something went wrong."; }
            })
            .catch(function () { btn.disabled = false; msg.className = "lb-sc-msg err"; msg.textContent = "Network error."; });
        }
        btn.addEventListener("click", go);
        inp.addEventListener("keydown", function (e) { if (e.key === "Enter") go(); });
      });
    })();
    </script>
    <?php
    return ob_get_clean();
} );

/* ------------------------------------------------------------------ */
/* 6. [licencebot_sales_popup]  (centered social-proof popup)          */
/* ------------------------------------------------------------------ */
add_shortcode( 'licencebot_sales_popup', function ( $atts ) {
    if ( ! licencebot_sc_enabled( 'licencebot_sales_popup' ) ) return '';
    $atts = shortcode_atts( array(
        'delay'  => 5,   // seconds
        'title'  => '🔥 Hot right now',
    ), $atts, 'licencebot_sales_popup' );
    licencebot_shortcodes_assets();
    $delay = max( 0, intval( $atts['delay'] ) );
    ob_start(); ?>
    <div class="lb-sc-popup-ov lb-sc-sales-popup" data-delay="<?php echo $delay; ?>">
      <div class="lb-sc-popup lb-sc">
        <button class="lb-sc-popup-x" aria-label="Close">×</button>
        <h3 class="lb-sc-title"><?php echo esc_html( $atts['title'] ); ?></h3>
        <div class="lb-sc-list" style="font-size:14px;color:#0f172a"></div>
      </div>
    </div>
    <script>
    (function () {
      var ov = document.querySelector(".lb-sc-sales-popup:not([data-lb-init])");
      if (!ov) return;
      ov.setAttribute("data-lb-init", "1");
      var d = parseInt(ov.getAttribute("data-delay"), 10) * 1000;
      var list = ov.querySelector(".lb-sc-list");
      ov.querySelector(".lb-sc-popup-x").onclick = function () { ov.classList.remove("show"); };
      ov.addEventListener("click", function (e) { if (e.target === ov) ov.classList.remove("show"); });
      setTimeout(function () {
        window.LBHelper.fetchWidget("sales_popup").then(function (r) {
          var items = (r && r.items) || [];
          if (!items.length) return;
          list.innerHTML = items.slice(0, 6).map(function (s) {
            return '<div style="display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid #f1f5f9"><span><b>' +
              (s.who || "Someone") + '</b> · ' + (s.product || "product") + '</span><span style="color:#64748b">' + (s.when || "") + '</span></div>';
          }).join("");
          ov.classList.add("show");
        });
      }, d);
    })();
    </script>
    <?php
    return ob_get_clean();
} );

/* ------------------------------------------------------------------ */
/* 7. [licencebot_popup]  (generic configurable popup)                 */
/* ------------------------------------------------------------------ */
add_shortcode( 'licencebot_popup', function ( $atts, $content = null ) {
    if ( ! licencebot_sc_enabled( 'licencebot_popup' ) ) return '';
    $atts = shortcode_atts( array(
        'delay'   => 4,
        'trigger' => 'time',     // time | exit
        'title'   => '',
    ), $atts, 'licencebot_popup' );
    licencebot_shortcodes_assets();
    $delay = max( 0, intval( $atts['delay'] ) );
    $body  = $content ? do_shortcode( $content ) : '';
    ob_start(); ?>
    <div class="lb-sc-popup-ov lb-sc-generic-popup" data-delay="<?php echo $delay; ?>" data-trigger="<?php echo esc_attr( $atts['trigger'] ); ?>">
      <div class="lb-sc-popup lb-sc">
        <button class="lb-sc-popup-x" aria-label="Close">×</button>
        <?php if ( $atts['title'] !== '' ) : ?><h3 class="lb-sc-title"><?php echo esc_html( $atts['title'] ); ?></h3><?php endif; ?>
        <div class="lb-sc-body"><?php echo $body; ?></div>
      </div>
    </div>
    <script>
    (function () {
      var ov = document.querySelector(".lb-sc-generic-popup:not([data-lb-init])");
      if (!ov) return;
      ov.setAttribute("data-lb-init", "1");
      var trig = ov.getAttribute("data-trigger");
      var d    = parseInt(ov.getAttribute("data-delay"), 10) * 1000;
      ov.querySelector(".lb-sc-popup-x").onclick = function () { ov.classList.remove("show"); };
      ov.addEventListener("click", function (e) { if (e.target === ov) ov.classList.remove("show"); });
      function open(){ if(!sessionStorage.getItem("lbp_seen")){ ov.classList.add("show"); sessionStorage.setItem("lbp_seen","1"); } }
      if (trig === "exit") {
        document.addEventListener("mouseleave", function (e) { if (e.clientY <= 0) open(); });
      } else {
        setTimeout(open, d);
      }
    })();
    </script>
    <?php
    return ob_get_clean();
} );
