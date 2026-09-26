<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return whether the current frontend page is within the configured GTM scope.
 *
 * @return bool
 */
function ichronoz_gtm_should_render()
{
    if (is_admin() || wp_doing_ajax() || is_feed()) {
        return false;
    }

    if (get_option('ichronoz_analytics_gtm_install_mode', 'existing') !== 'ichronoz') {
        return false;
    }

    if (get_option('ichronoz_analytics_gtm_scope', 'ichronoz') === 'all') {
        return true;
    }

    global $post;
    if (!$post || empty($post->post_content)) {
        return false;
    }

    $shortcodes = array(
        'ichronoz_search_form',
        'ichronoz_room_list',
        'ichronoz_booking_page',
        'ichronoz_booking_multi',
        'ichronoz_booking',
        'ichronoz_search_button',
    );

    foreach ($shortcodes as $shortcode) {
        if (has_shortcode($post->post_content, $shortcode)) {
            return true;
        }
    }

    return false;
}

/**
 * Get a validated GTM container ID or an empty string.
 *
 * @return string
 */
function ichronoz_gtm_container_id()
{
    $container_id = strtoupper(trim((string) get_option('ichronoz_analytics_gtm_container_id', '')));

    return preg_match('/^GTM-[A-Z0-9]+$/', $container_id) ? $container_id : '';
}

/**
 * Render the GTM loader in the document head.
 *
 * The inline bootstrap detects an existing loader for the same container ID to
 * reduce accidental duplication with another integration.
 *
 * @return void
 */
function ichronoz_gtm_render_head()
{
    if (!ichronoz_gtm_should_render()) {
        return;
    }

    $container_id = ichronoz_gtm_container_id();
    if ($container_id === '') {
        return;
    }
    $nonce = trim((string) get_option('ichronoz_csp_nonce', ''));
    ?>
    <!-- Google Tag Manager installed by iChronoz -->
    <script id="ichronoz-gtm-loader"<?php echo $nonce !== '' ? ' nonce="' . esc_attr($nonce) . '"' : ''; ?>>
        (function(w,d,s,l,i){
            var encodedId=encodeURIComponent(i);
            var existing=d.querySelector('script[src*="googletagmanager.com/gtm.js?id='+encodedId+'"]');
            if(existing||(w.google_tag_manager&&w.google_tag_manager[i])){return;}
            w[l]=w[l]||[];
            w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
            var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!=='dataLayer'?'&l='+l:'';
            j.async=true;
            j.src='https://www.googletagmanager.com/gtm.js?id='+encodedId+dl;
            f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer',<?php echo wp_json_encode($container_id); ?>);
    </script>
    <!-- End Google Tag Manager installed by iChronoz -->
    <?php
}
add_action('wp_head', 'ichronoz_gtm_render_head', 1);

/**
 * Render the official GTM noscript fallback when the theme supports wp_body_open.
 *
 * @return void
 */
function ichronoz_gtm_render_noscript()
{
    if (!ichronoz_gtm_should_render()) {
        return;
    }

    $container_id = ichronoz_gtm_container_id();
    if ($container_id === '') {
        return;
    }

    $src = add_query_arg('id', rawurlencode($container_id), 'https://www.googletagmanager.com/ns.html');
    ?>
    <!-- Google Tag Manager (noscript) installed by iChronoz -->
    <noscript><iframe src="<?php echo esc_url($src); ?>" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) installed by iChronoz -->
    <?php
}
add_action('wp_body_open', 'ichronoz_gtm_render_noscript', 1);
