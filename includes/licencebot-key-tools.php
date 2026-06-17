<?php
/**
 * LicenceBot Helper — Key Tools
 *  [licencebot_get_cid]     — Generate Confirmation ID from Installation ID
 *  [licencebot_redeem]      — Microsoft key redeem helper
 *  [licencebot_check_key]   — Check Windows / Office key status
 *
 * Each shortcode submits to a WP REST proxy that calls the LicenceBot backend.
 * No LicenceBot credentials are exposed to the browser.
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


/* ---------------- WP REST proxy routes ---------------- */
add_action( 'rest_api_init', function () {
    register_rest_route( 'licencebot/v1', '/key-tools', array(
        'methods'             => 'POST',
        'callback'            => 'licencebot_key_tools_proxy',
        'permission_callback' => '__return_true',
    ) );
} );

function licencebot_key_tools_proxy( WP_REST_Request $req ) {
    $body = $req->get_json_params();
    if ( ! is_array( $body ) ) $body = array();

    $action = isset( $body['action'] ) ? sanitize_text_field( (string) $body['action'] ) : '';
    $allowed = array( 'check_key', 'get_cid', 'redeem_ms' );
    if ( ! in_array( $action, $allowed, true ) ) {
        return new WP_REST_Response( array( 'error' => 'invalid action' ), 400 );
    }

    $payload = array(
        'action' => $action,
        'site'   => parse_url( home_url(), PHP_URL_HOST ),
        'token'  => licencebot_helper_org_token(),
    );
    foreach ( array( 'keys', 'iid', 'accounts', 'justGetDescription' ) as $k ) {
        if ( isset( $body[ $k ] ) ) $payload[ $k ] = is_string( $body[ $k ] ) ? sanitize_text_field( $body[ $k ] ) : $body[ $k ];
    }

    $url = licencebot_helper_api_base() . '/functions/v1/helper-key-tools';
    $res = wp_remote_post( $url, array(
        'timeout' => 120,
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => wp_json_encode( $payload ),
    ) );
    if ( is_wp_error( $res ) ) {
        return new WP_REST_Response( array( 'error' => $res->get_error_message() ), 502 );
    }
    $code = wp_remote_retrieve_response_code( $res );
    $raw  = wp_remote_retrieve_body( $res );
    $json = json_decode( $raw, true );
    return new WP_REST_Response( $json !== null ? $json : array( 'raw' => $raw ), $code ?: 200 );
}

/* ---------------- Shared form renderer ---------------- */
function licencebot_keytool_form( $id, $action, $field, $placeholder, $title, $sub, $button ) {
    licencebot_shortcodes_assets();
    ob_start(); ?>
    <div class="lb-sc lb-sc-card lb-sc-keytool" id="<?php echo esc_attr( $id ); ?>"
         data-action="<?php echo esc_attr( $action ); ?>" data-field="<?php echo esc_attr( $field ); ?>">
        <h3 class="lb-sc-title"><?php echo esc_html( $title ); ?></h3>
        <p class="lb-sc-sub"><?php echo esc_html( $sub ); ?></p>
        <textarea class="lb-sc-input lb-sc-kt-in" rows="3" placeholder="<?php echo esc_attr( $placeholder ); ?>"></textarea>
        <div style="margin-top:10px"><button type="button" class="lb-sc-btn lb-sc-kt-go"><?php echo esc_html( $button ); ?></button></div>
        <div class="lb-sc-msg"></div>
        <pre class="lb-sc-kt-out" style="display:none;white-space:pre-wrap;word-break:break-word;background:#0f172a;color:#e2e8f0;padding:14px;border-radius:10px;margin-top:12px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;max-height:340px;overflow:auto"></pre>
    </div>
    <script>
    (function () {
      var root = document.getElementById(<?php echo wp_json_encode( $id ); ?>);
      if (!root || root.getAttribute("data-lb-init")) return;
      root.setAttribute("data-lb-init", "1");
      var inp = root.querySelector(".lb-sc-kt-in"),
          btn = root.querySelector(".lb-sc-kt-go"),
          msg = root.querySelector(".lb-sc-msg"),
          out = root.querySelector(".lb-sc-kt-out"),
          action = root.getAttribute("data-action"),
          field = root.getAttribute("data-field");
      btn.addEventListener("click", function () {
        var v = (inp.value || "").trim();
        msg.className = "lb-sc-msg";
        out.style.display = "none";
        if (!v) { msg.className = "lb-sc-msg err"; msg.textContent = "Please enter a value."; return; }
        btn.disabled = true; btn.textContent = "Working…";
        var body = { action: action }; body[field] = v;
        fetch("<?php echo esc_url_raw( rest_url( 'licencebot/v1/key-tools' ) ); ?>", {
          method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body)
        }).then(function (r) { return r.json().catch(function(){return {};}).then(function(j){return {ok:r.ok,j:j};}); })
          .then(function (x) {
            btn.disabled = false; btn.textContent = <?php echo wp_json_encode( $button ); ?>;
            if (x.ok && !x.j.error) {
              msg.className = "lb-sc-msg ok"; msg.textContent = "Done.";
              out.style.display = "block";
              out.textContent = typeof x.j === "string" ? x.j : JSON.stringify(x.j, null, 2);
            } else {
              msg.className = "lb-sc-msg err"; msg.textContent = (x.j && x.j.error) || "Request failed.";
            }
          })
          .catch(function () { btn.disabled = false; btn.textContent = <?php echo wp_json_encode( $button ); ?>; msg.className = "lb-sc-msg err"; msg.textContent = "Network error."; });
      });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ---------------- Shortcodes ---------------- */
add_shortcode( 'licencebot_check_key', function ( $atts ) {
    if ( ! licencebot_sc_enabled( 'licencebot_check_key' ) ) return '';
    $atts = shortcode_atts( array(
        'title'  => 'Check your product key',
        'sub'    => 'Paste one or more keys (one per line) to check their status.',
        'button' => 'Check key',
    ), $atts, 'licencebot_check_key' );
    return licencebot_keytool_form( 'lb-kt-check-' . wp_unique_id(), 'check_key', 'keys',
        'XXXXX-XXXXX-XXXXX-XXXXX-XXXXX', $atts['title'], $atts['sub'], $atts['button'] );
} );

add_shortcode( 'licencebot_get_cid', function ( $atts ) {
    if ( ! licencebot_sc_enabled( 'licencebot_get_cid' ) ) return '';
    $atts = shortcode_atts( array(
        'title'  => 'Get Confirmation ID',
        'sub'    => 'Paste your Installation ID (IID) to generate a Confirmation ID.',
        'button' => 'Generate CID',
    ), $atts, 'licencebot_get_cid' );
    return licencebot_keytool_form( 'lb-kt-cid-' . wp_unique_id(), 'get_cid', 'iid',
        'Installation ID…', $atts['title'], $atts['sub'], $atts['button'] );
} );

add_shortcode( 'licencebot_redeem', function ( $atts ) {
    if ( ! licencebot_sc_enabled( 'licencebot_redeem' ) ) return '';
    $atts = shortcode_atts( array(
        'title'  => 'Redeem your key',
        'sub'    => 'Paste one or more keys (one per line) to redeem with Microsoft.',
        'button' => 'Redeem key',
    ), $atts, 'licencebot_redeem' );
    return licencebot_keytool_form( 'lb-kt-redeem-' . wp_unique_id(), 'redeem_ms', 'keys',
        'XXXXX-XXXXX-XXXXX-XXXXX-XXXXX', $atts['title'], $atts['sub'], $atts['button'] );
} );

if ( ! function_exists( 'wp_unique_id' ) ) {
    function wp_unique_id( $prefix = '' ) { static $n = 0; $n++; return $prefix . $n; }
}
