<?php

/**
 * Plugin Name: iChronoz Booking Engine
 * Description: Intelegent hotel booking engine by iChronoz
 * Version: 3.1.6
 * Author: iChronoz
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/Analytics/Analytics.php';
register_activation_hook(__FILE__, 'ichronoz_analytics_install_schema');

/**
 * Sanitize the grouped room layout while preserving the legacy layout as the default.
 *
 * @param mixed $value Submitted option value.
 * @return string
 */
function ichronoz_sanitize_room_group_layout($value)
{
    $value = sanitize_key((string) $value);

    return in_array($value, array('small-image', 'regular-image', 'compact-table', 'promo-cards'), true)
        ? $value
        : 'small-image';
}

/**
 * Optional content sections available inside room rate cards.
 *
 * @return array<string, string>
 */
function ichronoz_get_grouped_room_detail_options()
{
    return array(
        'availability' => 'Availability and recommendations',
        'meal' => 'Meal and breakfast information',
        'refund-policy' => 'Refund policy',
        'detail-links' => 'Rate details and terms links',
        'stay-average-rate' => 'Stay details and average nightly rate',
        'description' => 'Rate description (desc)',
        'deposit' => 'Deposit information',
        'discount' => 'Discount and original price',
        'currency-converter' => 'Currency converter',
    );
}

/**
 * Allow only supported room rate-card section keys.
 *
 * @param mixed $value Submitted option value.
 * @return array<int, string>
 */
function ichronoz_sanitize_grouped_room_details($value)
{
    if (!is_array($value)) {
        return array_keys(ichronoz_get_grouped_room_detail_options());
    }

    $allowed = array_keys(ichronoz_get_grouped_room_detail_options());
    $selected = array_map('sanitize_key', $value);

    return array_values(array_intersect($allowed, $selected));
}

/**
 * Sanitize bounded numeric promo-nudge settings.
 */
function ichronoz_sanitize_promo_minimum_searches($value)
{
    return max(1, min(20, absint($value)));
}

function ichronoz_sanitize_promo_minimum_seconds($value)
{
    return max(0, min(3600, absint($value)));
}

function ichronoz_sanitize_promo_delay_ms($value)
{
    return max(0, min(10000, absint($value)));
}

function ichronoz_sanitize_promo_dismiss_hours($value)
{
    return max(0, min(8760, absint($value)));
}

function ichronoz_sanitize_promo_maximum_visible($value)
{
    return max(1, min(10, absint($value)));
}

function ichronoz_sanitize_rooms_carousel_autoplay($value)
{
    return max(0, min(60, absint($value)));
}

function ichronoz_sanitize_rooms_carousel_position($value)
{
    $value = sanitize_key((string) $value);

    return in_array($value, array('bottom-right', 'bottom-left', 'top-right', 'top-left'), true)
        ? $value
        : 'bottom-right';
}

function ichronoz_admin_enqueue_scripts($hook)
{
    // Ensure we enqueue on our plugin settings page whether it's under Settings or top-level menu
    $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    if ($page !== 'ichronoz') {
        return;
    }

    $admin_style_path = plugin_dir_path(__FILE__) . 'assets/admin/settings.css';
    $admin_script_path = plugin_dir_path(__FILE__) . 'assets/admin/settings.js';

    wp_enqueue_style(
        'ichronoz-admin-settings',
        plugins_url('assets/admin/settings.css', __FILE__),
        array(),
        file_exists($admin_style_path) ? filemtime($admin_style_path) : null
    );
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script(
        'ichronoz-color-picker-script',
        plugins_url('js/color-picker.js', __FILE__),
        array('wp-color-picker', 'jquery'),
        false,
        true
    );
    wp_enqueue_script(
        'ichronoz-admin-settings',
        plugins_url('assets/admin/settings.js', __FILE__),
        array('jquery'),
        file_exists($admin_script_path) ? filemtime($admin_script_path) : null,
        true
    );
}
add_action('admin_enqueue_scripts', 'ichronoz_admin_enqueue_scripts');

function ichronoz_enqueue_scripts()
{
    $asset_file = include(plugin_dir_path(__FILE__) . 'build/index.asset.php');

    // Enqueue scoped Bootstrap CSS (prefixed under .ichronoz)
    $bootstrap_scoped_path = plugin_dir_path(__FILE__) . 'assets/ichronoz-bootstrap.min.css';
    $bootstrap_scoped_url  = plugins_url('assets/ichronoz-bootstrap.min.css', __FILE__);
    wp_enqueue_style(
        'ichronoz-bootstrap',
        $bootstrap_scoped_url,
        array(),
        file_exists($bootstrap_scoped_path) ? filemtime($bootstrap_scoped_path) : null
    );

    // Enqueue Font Awesome 6 (from CDN) for icons
    wp_enqueue_style(
        'fontawesome-css',
        'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css',
        array(),
        '6.5.2'
    );

    // Enqueue Lato font globally for the plugin
    wp_enqueue_style(
        'lato-font',
        'https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&display=swap',
        array(),
        null
    );


    wp_enqueue_script(
        'ichronoz-react-app',
        plugins_url('build/index.js', __FILE__),
        $asset_file['dependencies'],
        $asset_file['version'],
        true
    );

    // Also enqueue the style-index.css first (if it exists) so our overrides can load last
    $base_deps = array('ichronoz-bootstrap', 'lato-font', 'fontawesome-css');
    $index_css_deps = $base_deps;
    if (file_exists(plugin_dir_path(__FILE__) . 'build/style-index.css')) {
        wp_enqueue_style(
            'ichronoz-style-index',
            plugins_url('build/style-index.css', __FILE__),
            $base_deps,
            filemtime(plugin_dir_path(__FILE__) . 'build/style-index.css')
        );
        // Ensure index.css prints after style-index.css
        $index_css_deps[] = 'ichronoz-style-index';
    }

    // Enqueue the main CSS file (last) so its rules can override
    wp_enqueue_style(
        'ichronoz-react-app-styles',
        plugins_url('build/index.css', __FILE__),
        $index_css_deps,
        filemtime(plugin_dir_path(__FILE__) . 'build/index.css')
    );

    // Inline critical styles for floating search button to ensure visibility
    $btn_color_raw = get_option('ichronoz_search_button_color', '#1566d1');
    $btn_color = sanitize_hex_color($btn_color_raw);
    if (!$btn_color) {
        $btn_color = '#1566d1';
    }
    $btn_color_hex = ltrim($btn_color, '#');
    $btn_color_rgb = implode(', ', array(
        hexdec(substr($btn_color_hex, 0, 2)),
        hexdec(substr($btn_color_hex, 2, 2)),
        hexdec(substr($btn_color_hex, 4, 2)),
    ));
    $fab_position = get_option('ichronoz_fab_position', 'bottom-right'); // bottom-right, bottom-left, top-right, top-left
    // Compute positional CSS for wrapper and panel based on setting
    $pos_right = (strpos($fab_position, 'right') !== false);
    $pos_bottom = (strpos($fab_position, 'bottom') !== false);
    $wrapper_pos = ($pos_right ? 'right:16px;' : 'left:16px;') . ($pos_bottom ? 'bottom:16px;' : 'top:16px;');
    $panel_pos   = ($pos_right ? 'right:16px;' : 'left:16px;') . ($pos_bottom ? 'bottom:84px;' : 'top:84px;');
    $fab_transparent = get_option('ichronoz_fab_transparent', '0') === '1';
    $fab_border_color = get_option('ichronoz_fab_border_color', $btn_color ?: '#1566d1');
    $critical_css =
        '.ichronoz{--bs-primary:' . $btn_color . ';--bs-primary-rgb:' . $btn_color_rgb . ';}' .
        '.ichronoz-fab-wrapper{position:fixed;' . $wrapper_pos . 'z-index:999999}' .
        ($fab_transparent
            ? '.ichronoz-fab-button{background:transparent !important;color:' . $fab_border_color . ' !important;border:2px solid ' . $fab_border_color . ' !important}'
            . '.ichronoz-fab-button:hover,.ichronoz-fab-button:focus{background:' . $btn_color . ' !important; box-shadow:0 0 0 3px rgba(0,0,0,0.06); color:#FFFFFF !important;}'
            : '.ichronoz-fab-button{background:' . $btn_color . ' !important;color:#fff !important;border:none !important}'
            . '.ichronoz-fab-button:hover,.ichronoz-fab-button:focus{filter:brightness(0.95);}') .
        '.ichronoz-fab-panel{position:fixed;' . $panel_pos . 'z-index:999999;background:#fff;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.18);padding:12px;width:min(92vw,360px);display:none}' .
        '.ichronoz-fab-panel.open{display:block}' .
        '.ichronoz-fab-panel .ichronoz{max-height:80vh;}' .
        /* Improve contrast for day number text inside range */
        '.ichronoz .rdp-day_range_middle .rdp-day_button,' .
        '.ichronoz .rdp-day_range_start .rdp-day_button,' .
        '.ichronoz .rdp-day_range_end .rdp-day_button{color:#111 !important;}';
    wp_add_inline_style('ichronoz-react-app-styles', $critical_css);

    // Enqueue Custom CSS from settings (placed after all plugin styles)
    $custom_css_raw = get_option('ichronoz_custom_css', '');
    if (is_string($custom_css_raw) && trim($custom_css_raw) !== '') {
        // Light sanitization: strip any HTML tags, keep pure CSS text
        $custom_css = wp_strip_all_tags($custom_css_raw);
        if ($custom_css !== '') {
            wp_add_inline_style('ichronoz-react-app-styles', $custom_css);
        }
    }

    // Enqueue Bootstrap JS bundle (includes Popper) locally only (no CDN)
    $bootstrap_js_path = plugin_dir_path(__FILE__) . 'assets/bootstrap.bundle.min.js';
    $bootstrap_js_url  = plugins_url('assets/bootstrap.bundle.min.js', __FILE__);
    wp_enqueue_script(
        'bootstrap-js',
        $bootstrap_js_url,
        array(),
        file_exists($bootstrap_js_path) ? filemtime($bootstrap_js_path) : null,
        true
    );

    $layout_option = get_option('ichronoz_form_layout', 'vertical');
    // New: button rounding options
    $btn_round_enabled = get_option('ichronoz_btn_rounded', '0') === '1';
    $btn_radius = trim((string) get_option('ichronoz_btn_radius', '6px'));
    if ($btn_round_enabled && $btn_radius !== '') {
        $radius = esc_attr($btn_radius);
        $critical_css_extra =
            '.ichronoz{' .
            '--bs-border-radius:' . $radius . ';' .
            '--bs-border-radius-sm:' . $radius . ';' .
            '--bs-border-radius-lg:' . $radius . ';' .
            '--bs-border-radius-xl:' . $radius . ';' .
            '--bs-border-radius-xxl:' . $radius . ';' .
            '--bs-border-radius-2xl:' . $radius . ';' .
            '}' .
            '.ichronoz .btn,.ichronoz .card,.ichronoz .form-control,.ichronoz .form-select,.ichronoz .input-group-text,.ichronoz .dropdown-menu,.ichronoz .modal-content,.ichronoz .list-group-item,.ichronoz .alert,.ichronoz .ichz-ticket-list-card__image{border-radius:' . $radius . ' !important;}' .
            '.ichronoz .rounded,.ichronoz .rounded-1,.ichronoz .rounded-2,.ichronoz .rounded-3,.ichronoz .rounded-4,.ichronoz .rounded-5{border-radius:' . $radius . ' !important;}' .
            '.ichronoz .rounded-top{border-top-left-radius:' . $radius . ' !important;border-top-right-radius:' . $radius . ' !important;}' .
            '.ichronoz .rounded-end{border-top-right-radius:' . $radius . ' !important;border-bottom-right-radius:' . $radius . ' !important;}' .
            '.ichronoz .rounded-bottom{border-bottom-right-radius:' . $radius . ' !important;border-bottom-left-radius:' . $radius . ' !important;}' .
            '.ichronoz .rounded-start{border-top-left-radius:' . $radius . ' !important;border-bottom-left-radius:' . $radius . ' !important;}';
        wp_add_inline_style('ichronoz-react-app-styles', $critical_css_extra);
    }
    $selected_day_color = get_option('ichronoz_selected_day_color', '#0071c2');
    $search_button_color = get_option('ichronoz_search_button_color', '#1566d1');
    $room_hover_bg_color = get_option('ichronoz_room_hover_bg_color', '#e6e6e6');
    $secondary_color = get_option('ichronoz_secondary_color', '#6c757d');
    $success_color = get_option('ichronoz_success_color', '#198754');
    $warning_color = get_option('ichronoz_warning_color', '#ffc107');
    $link_color = get_option('ichronoz_link_color', '');
    $api_value = get_option('ichronoz_api_value', 'xxxxx');
    $booking_path = get_option('ichronoz_booking_path', '/index.php/book');
    $loading_message = get_option('ichronoz_loading_message', 'Searching for the best rate within your requested period: {fromLong} - {toShort}');
    $spinner_url = get_option('ichronoz_spinner_url', '/wp-admin/images/spinner.gif');
    $calendar_range_bg = get_option('ichronoz_calendar_range_bg', '#e3f2ff');
    $promo_nudge_config = array(
        'enabled' => get_option('ichronoz_promo_nudge_enabled', '0') === '1',
        'minimumSearches' => ichronoz_sanitize_promo_minimum_searches(get_option('ichronoz_promo_nudge_minimum_searches', 3)),
        'minimumSecondsOnPage' => ichronoz_sanitize_promo_minimum_seconds(get_option('ichronoz_promo_nudge_minimum_seconds', 30)),
        'alwaysShowAfterMinimumTime' => get_option('ichronoz_promo_nudge_always_after_time', '0') === '1',
        'delayAfterSearchMs' => ichronoz_sanitize_promo_delay_ms(get_option('ichronoz_promo_nudge_delay_ms', 800)),
        'showOncePerSession' => get_option('ichronoz_promo_nudge_show_once', '1') === '1',
        'dismissHours' => ichronoz_sanitize_promo_dismiss_hours(get_option('ichronoz_promo_nudge_dismiss_hours', 24)),
        'maximumVisiblePromos' => ichronoz_sanitize_promo_maximum_visible(get_option('ichronoz_promo_nudge_maximum_visible', 3)),
    );
    $rooms_carousel_config = array(
        'subheading' => sanitize_text_field(get_option('ichronoz_rooms_carousel_subheading', 'Stay period')),
        'buttonLabel' => sanitize_text_field(get_option('ichronoz_rooms_carousel_button_label', 'Book Room Now')),
        'autoplaySeconds' => ichronoz_sanitize_rooms_carousel_autoplay(get_option('ichronoz_rooms_carousel_autoplay', 5)),
        'dismissible' => get_option('ichronoz_rooms_carousel_dismissible', '1') === '1',
        'position' => ichronoz_sanitize_rooms_carousel_position(get_option('ichronoz_rooms_carousel_position', 'bottom-right')),
    );
    $room_group_layout = ichronoz_sanitize_room_group_layout(
        get_option('ichronoz_room_group_layout', 'small-image')
    );
    $grouped_room_details = ichronoz_sanitize_grouped_room_details(
        get_option('ichronoz_grouped_room_details', array_keys(ichronoz_get_grouped_room_detail_options()))
    );
    // HID selector config
    $hid_enabled = get_option('ichronoz_hid_enabled', '0') === '1';
    $hid_options_raw = get_option('ichronoz_hid_options_json', '[]');
    $hid_options = array();
    if (is_string($hid_options_raw)) {
        $decoded = json_decode($hid_options_raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (!is_array($item)) continue;
                $hid = isset($item['hid']) ? sanitize_text_field($item['hid']) : '';
                $name = isset($item['name']) ? sanitize_text_field($item['name']) : '';
                if ($hid && $name) $hid_options[] = array('hid' => $hid, 'name' => $name);
            }
        }
    }

    // Include configurable injection scripts
    $booking_script = get_option('ichronoz_booking_script', '');
    $detail_script  = get_option('ichronoz_detail_script', '');
    $booking_enabled = get_option('ichronoz_booking_script_enabled', '0') === '1';
    $detail_enabled  = get_option('ichronoz_detail_script_enabled', '0') === '1';
    $sanitization_mode = get_option('ichronoz_script_sanitization', 'raw');
    $csp_nonce = get_option('ichronoz_csp_nonce', '');

    // Apply sanitization if chosen server-side
    if ($sanitization_mode === 'strip_tags') {
        $booking_script = preg_replace('/<\\/?script[^>]*>/i', '', $booking_script);
        $detail_script  = preg_replace('/<\\/?script[^>]*>/i', '', $detail_script);
    } elseif ($sanitization_mode === 'kses') {
        $booking_script = wp_kses_post($booking_script);
        $detail_script  = wp_kses_post($detail_script);
    }

    // Gradient colors (4 colors) setting
    $gradient_colors_raw = get_option('ichronoz_gradient_colors', '');
    $gradient_colors = array();
    if (is_array($gradient_colors_raw)) {
        $gradient_colors = array_values(array_filter(array_map('sanitize_text_field', $gradient_colors_raw)));
    } elseif (is_string($gradient_colors_raw)) {
        $parts = preg_split('/[\s,]+/', $gradient_colors_raw);
        if (is_array($parts)) {
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p !== '') $gradient_colors[] = sanitize_text_field($p);
            }
        }
    }
    if (count($gradient_colors) < 4) {
        // leave empty; frontend will fallback to other colors
        $gradient_colors = $gradient_colors; // no-op, explicit for clarity
    } else {
        $gradient_colors = array_slice($gradient_colors, 0, 4);
    }

    wp_localize_script('ichronoz-react-app', 'ichronozSettings', array(
        'apiBase' => 'https://api.ichronoz.net',
        'debugEnabled' => defined('WP_DEBUG') && WP_DEBUG,
        'layout' => $layout_option,
        'selectedDayColor' => $selected_day_color,
        'searchButtonColor' => $search_button_color,
        'roomHoverBgColor' => $room_hover_bg_color,
        'roomCardType' => get_option('ichronoz_room_card_type', 'default'),
        'roomGroupLayout' => $room_group_layout,
        'roomDetails' => $grouped_room_details,
        'secondaryColor' => $secondary_color,
        'successColor' => $success_color,
        'warningColor' => $warning_color,
        'linkColor' => $link_color,
        'apiValue' => $api_value,
        'bookingPath' => $booking_path,
        'loadingMessage' => $loading_message,
        'spinnerUrl' => $spinner_url,
        'calendarRangeBg' => $calendar_range_bg,
        'bookingPageScript' => $booking_enabled ? $booking_script : '',
        'detailPageScript' => $detail_enabled ? $detail_script : '',
        'scriptSanitization' => $sanitization_mode,
        'scriptNonce' => $csp_nonce,
        'hidEnabled' => $hid_enabled,
        'hidOptions' => $hid_options,
        'gradientColors' => $gradient_colors,
        'promoNudge' => $promo_nudge_config,
        'roomsCarousel' => $rooms_carousel_config,
        'fabTransparent' => $fab_transparent,
        'fabBorderColor' => $fab_border_color,
        'analytics' => ichronoz_analytics_runtime_config(),
    ));

    $booking_data = array(
        'from' => isset($_GET['from']) ? esc_html($_GET['from']) : 'N/A',
        'to' => isset($_GET['to']) ? esc_html($_GET['to']) : 'N/A',
        'serverToday' => current_time('Y-m-d'),
        'rooms' => isset($_GET['rooms']) ? esc_html($_GET['rooms']) : 'N/A',
        'adults' => isset($_GET['adults']) ? esc_html($_GET['adults']) : 'N/A',
        'children' => isset($_GET['children']) ? esc_html($_GET['children']) : 'N/A',
    );
    wp_localize_script('ichronoz-react-app', 'ichronozBookingData', $booking_data);
}

function ichronoz_shortcode()
{
    // Load assets only when shortcode is present
    ichronoz_enqueue_scripts();
    do_action('ichronoz_rendered_search_form');
    return '<div class="ichronoz"><div data-ichronoz-mount="search"></div></div>';
}

function ichronoz_register_settings()
{
    add_option('ichronoz_form_layout', 'vertical');
    add_option('ichronoz_selected_day_color', '#0071c2');
    add_option('ichronoz_search_button_color', '#1566d1');
    add_option('ichronoz_room_hover_bg_color', '#e6e6e6');
    add_option('ichronoz_secondary_color', '#6c757d');
    add_option('ichronoz_success_color', '#198754');
    add_option('ichronoz_warning_color', '#ffc107');
    add_option('ichronoz_link_color', '');
    add_option('ichronoz_api_value', 'xxxxx');
    add_option('ichronoz_booking_path', '/index.php/book');
    add_option('ichronoz_loading_message', 'Searching for the best rate within your requested period: {fromLong} - {toShort}');
    add_option('ichronoz_spinner_url', '/wp-admin/images/spinner.gif');
    add_option('ichronoz_calendar_range_bg', '#e3f2ff');
    add_option('ichronoz_fab_position', 'bottom-right');
    // Floating button transparency toggle
    add_option('ichronoz_fab_transparent', '0');
    add_option('ichronoz_fab_border_color', '#1566d1');
    // Gradient colors (4 colors) string or array-compatible storage
    add_option('ichronoz_gradient_colors', '');
    // Script injection options
    add_option('ichronoz_booking_script', '');
    add_option('ichronoz_detail_script', '');
    add_option('ichronoz_booking_script_enabled', '0');
    add_option('ichronoz_detail_script_enabled', '0');
    add_option('ichronoz_script_sanitization', 'raw'); // raw | strip_tags | kses
    add_option('ichronoz_csp_nonce', '');
    // HID selector options
    add_option('ichronoz_hid_enabled', '0');
    add_option('ichronoz_hid_options_json', '[]');
    // Room card type (default | room)
    add_option('ichronoz_room_card_type', 'default');
    // Grouped room layout (small-image | regular-image | compact-table | promo-cards)
    add_option('ichronoz_room_group_layout', 'small-image');
    // Optional sections shown inside each grouped rate card.
    add_option('ichronoz_grouped_room_details', array_keys(ichronoz_get_grouped_room_detail_options()));
    add_option('ichronoz_promo_nudge_enabled', '0');
    add_option('ichronoz_promo_nudge_minimum_searches', 3);
    add_option('ichronoz_promo_nudge_minimum_seconds', 30);
    add_option('ichronoz_promo_nudge_always_after_time', '0');
    add_option('ichronoz_promo_nudge_delay_ms', 800);
    add_option('ichronoz_promo_nudge_show_once', '1');
    add_option('ichronoz_promo_nudge_dismiss_hours', 24);
    add_option('ichronoz_promo_nudge_maximum_visible', 3);
    add_option('ichronoz_rooms_carousel_subheading', 'Stay period');
    add_option('ichronoz_rooms_carousel_button_label', 'Book Room Now');
    add_option('ichronoz_rooms_carousel_autoplay', 5);
    add_option('ichronoz_rooms_carousel_dismissible', '1');
    add_option('ichronoz_rooms_carousel_position', 'bottom-right');
    // Split settings into per-tab groups to prevent cross-tab resets
    // General group
    register_setting('ichronoz_general_group', 'ichronoz_form_layout');
    register_setting('ichronoz_general_group', 'ichronoz_api_value');
    register_setting('ichronoz_general_group', 'ichronoz_booking_path');
    register_setting('ichronoz_general_group', 'ichronoz_loading_message');
    register_setting('ichronoz_general_group', 'ichronoz_spinner_url');
    register_setting('ichronoz_general_group', 'ichronoz_fab_position');
    register_setting('ichronoz_general_group', 'ichronoz_fab_transparent');
    register_setting('ichronoz_general_group', 'ichronoz_fab_border_color');
    register_setting('ichronoz_general_group', 'ichronoz_hid_enabled');
    register_setting('ichronoz_general_group', 'ichronoz_hid_options_json');
    register_setting('ichronoz_general_group', 'ichronoz_room_card_type');
    register_setting(
        'ichronoz_general_group',
        'ichronoz_room_group_layout',
        array(
            'type' => 'string',
            'sanitize_callback' => 'ichronoz_sanitize_room_group_layout',
            'default' => 'small-image',
        )
    );
    register_setting('ichronoz_general_group', 'ichronoz_promo_nudge_enabled', array('sanitize_callback' => 'absint'));
    register_setting('ichronoz_general_group', 'ichronoz_promo_nudge_minimum_searches', array('sanitize_callback' => 'ichronoz_sanitize_promo_minimum_searches'));
    register_setting('ichronoz_general_group', 'ichronoz_promo_nudge_minimum_seconds', array('sanitize_callback' => 'ichronoz_sanitize_promo_minimum_seconds'));
    register_setting('ichronoz_general_group', 'ichronoz_promo_nudge_always_after_time', array('sanitize_callback' => 'absint'));
    register_setting('ichronoz_general_group', 'ichronoz_promo_nudge_delay_ms', array('sanitize_callback' => 'ichronoz_sanitize_promo_delay_ms'));
    register_setting('ichronoz_general_group', 'ichronoz_promo_nudge_show_once', array('sanitize_callback' => 'absint'));
    register_setting('ichronoz_general_group', 'ichronoz_promo_nudge_dismiss_hours', array('sanitize_callback' => 'ichronoz_sanitize_promo_dismiss_hours'));
    register_setting('ichronoz_general_group', 'ichronoz_promo_nudge_maximum_visible', array('sanitize_callback' => 'ichronoz_sanitize_promo_maximum_visible'));
    register_setting('ichronoz_general_group', 'ichronoz_rooms_carousel_subheading', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('ichronoz_general_group', 'ichronoz_rooms_carousel_button_label', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('ichronoz_general_group', 'ichronoz_rooms_carousel_autoplay', array('sanitize_callback' => 'ichronoz_sanitize_rooms_carousel_autoplay'));
    register_setting('ichronoz_general_group', 'ichronoz_rooms_carousel_dismissible', array('sanitize_callback' => 'absint'));
    register_setting(
        'ichronoz_general_group',
        'ichronoz_rooms_carousel_position',
        array(
            'type' => 'string',
            'sanitize_callback' => 'ichronoz_sanitize_rooms_carousel_position',
            'default' => 'bottom-right',
        )
    );
    register_setting(
        'ichronoz_general_group',
        'ichronoz_grouped_room_details',
        array(
            'type' => 'array',
            'sanitize_callback' => 'ichronoz_sanitize_grouped_room_details',
            'default' => array_keys(ichronoz_get_grouped_room_detail_options()),
        )
    );

    // UI group
    register_setting('ichronoz_ui_group', 'ichronoz_selected_day_color');
    register_setting('ichronoz_ui_group', 'ichronoz_search_button_color');
    register_setting('ichronoz_ui_group', 'ichronoz_room_hover_bg_color');
    register_setting('ichronoz_ui_group', 'ichronoz_secondary_color');
    register_setting('ichronoz_ui_group', 'ichronoz_success_color');
    register_setting('ichronoz_ui_group', 'ichronoz_warning_color');
    register_setting('ichronoz_ui_group', 'ichronoz_link_color');
    register_setting('ichronoz_ui_group', 'ichronoz_calendar_range_bg');
    register_setting('ichronoz_ui_group', 'ichronoz_gradient_colors');
    // New UI options for button rounding
    register_setting('ichronoz_ui_group', 'ichronoz_btn_rounded');
    register_setting('ichronoz_ui_group', 'ichronoz_btn_radius');

    // Scripts group
    register_setting('ichronoz_scripts_group', 'ichronoz_booking_script');
    register_setting('ichronoz_scripts_group', 'ichronoz_detail_script');
    register_setting('ichronoz_scripts_group', 'ichronoz_booking_script_enabled');
    register_setting('ichronoz_scripts_group', 'ichronoz_detail_script_enabled');
    register_setting('ichronoz_scripts_group', 'ichronoz_script_sanitization');
    register_setting('ichronoz_scripts_group', 'ichronoz_csp_nonce');
    // Custom CSS option (enqueued on frontend)
    add_option('ichronoz_custom_css', '');
    register_setting('ichronoz_scripts_group', 'ichronoz_custom_css');
}

function ichronoz_settings_page()
{
?>
    <div class="wrap ichz-settings-page">
        <?php
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $ichz_pd = get_plugin_data(__FILE__, false, false);
        $ichz_ver = isset($ichz_pd['Version']) ? $ichz_pd['Version'] : '';
        ?>
        <div class="ichz-settings-page__header">
            <h1>iChronoz Settings</h1>
            <?php if ($ichz_ver): ?>
                <span class="ichz-settings-page__version">v<?php echo esc_html($ichz_ver); ?></span>
            <?php endif; ?>
        </div>
        <p class="ichz-settings-page__description">Configure the booking experience, appearance, analytics, and integrations for your iChronoz components.</p>

        <?php
        // Only show Maintenance if a newer version is available on GitHub
        $owner = 'ichronoz-be';
        $repo  = 'ichronoz-booking-engine';
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugin_data = get_plugin_data(__FILE__, false, false);
        $current_version = isset($plugin_data['Version']) ? $plugin_data['Version'] : '0.0.0';
        $has_update = false;
        $latest_version = '';

        // Helpers
        $normalize_version = function ($v) {
            $v = is_string($v) ? trim($v) : '';
            if ($v === '') return '';
            // strip leading 'v' or 'V'
            if ($v[0] === 'v' || $v[0] === 'V') $v = substr($v, 1);
            // allow digits, letters, dots, dashes, underscores
            $v = preg_replace('/[^0-9A-Za-z._-]/', '', $v);
            $v = ltrim($v, '.');
            return $v;
        };
        $is_semverish = function ($v) {
            return (bool) preg_match('/^\d+(?:\.\d+){1,3}(?:[-A-Za-z0-9_.]+)?$/', $v);
        };

        $gh_get = function ($url) {
            return wp_remote_get($url, array(
                'headers' => array('Accept' => 'application/vnd.github+json', 'User-Agent' => 'WordPress; iChronoz-Updater'),
                'timeout' => 10,
            ));
        };

        // Transient cache to avoid GitHub rate limits
        $force_check = isset($_GET['ichz_force_check']) && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'ichz_force_check');
        $cache = get_transient('ichronoz_update_meta');
        $last_checked_ts = 0;
        if (!$force_check && is_array($cache) && isset($cache['latest_version'], $cache['has_update'])) {
            $latest_version = $normalize_version($cache['latest_version']);
            $has_update = (bool) $cache['has_update'];
            $last_checked_ts = isset($cache['checked_at']) ? intval($cache['checked_at']) : 0;
        }

        if ($latest_version === '') {
            // Try latest release
            $resp = $gh_get(sprintf('https://api.github.com/repos/%s/%s/releases/latest', $owner, $repo));
            if (!is_wp_error($resp)) {
                $code = wp_remote_retrieve_response_code($resp);
                if ($code === 200) {
                    $data = json_decode(wp_remote_retrieve_body($resp), true);
                    if (is_array($data)) {
                        $candidate = '';
                        if (!empty($data['tag_name'])) $candidate = $data['tag_name'];
                        elseif (!empty($data['name'])) $candidate = $data['name'];
                        $latest_version = $normalize_version($candidate);
                    }
                } elseif ($code === 404) {
                    // Fallback to tags when no releases exist
                    $tags_resp = $gh_get(sprintf('https://api.github.com/repos/%s/%s/tags', $owner, $repo));
                    if (!is_wp_error($tags_resp) && wp_remote_retrieve_response_code($tags_resp) === 200) {
                        $tags = json_decode(wp_remote_retrieve_body($tags_resp), true);
                        if (is_array($tags) && !empty($tags) && !empty($tags[0]['name'])) {
                            $latest_version = $normalize_version($tags[0]['name']);
                        }
                    }
                }
            }
        }

        // Compare versions
        $current_norm = $normalize_version($current_version);
        $latest_version = $normalize_version($latest_version);
        if ($latest_version && $current_norm) {
            if ($is_semverish($current_norm) && $is_semverish($latest_version) && function_exists('version_compare')) {
                $has_update = version_compare($current_norm, $latest_version, '<');
            } else {
                $has_update = ($current_norm !== $latest_version);
            }
        }

        // Save or update transient cache (12 hours)
        set_transient('ichronoz_update_meta', array(
            'latest_version' => $latest_version,
            'has_update' => $has_update,
            'checked_at' => time(),
        ), 12 * HOUR_IN_SECONDS);

        if ($has_update): ?>
            <section class="ichz-update-card ichz-update-card--available" aria-labelledby="ichz-update-title">
                <span class="dashicons dashicons-update ichz-update-card__icon" aria-hidden="true"></span>
                <div class="ichz-update-card__content">
                    <h2 id="ichz-update-title">Plugin update available</h2>
                    <p>Update iChronoz to receive the latest improvements and bug fixes.</p>
                    <dl class="ichz-update-card__versions">
                        <div><dt>Installed</dt><dd>v<?php echo esc_html($current_version); ?></dd></div>
                        <div><dt>Available</dt><dd>v<?php echo esc_html($latest_version); ?></dd></div>
                    </dl>
                </div>
                <form method="post" class="ichz-update-card__action">
                    <?php wp_nonce_field('ichronoz_self_update'); ?>
                    <input type="hidden" name="ichronoz_do_self_update" value="1" />
                    <?php submit_button('Update now', 'primary', 'submit', false); ?>
                </form>
            </section>
        <?php else: ?>
            <section class="ichz-update-card ichz-update-card--current" aria-labelledby="ichz-update-title">
                <span class="dashicons dashicons-yes-alt ichz-update-card__icon" aria-hidden="true"></span>
                <div class="ichz-update-card__content">
                    <h2 id="ichz-update-title">Plugin is up to date</h2>
                    <p>
                        You are running iChronoz v<?php echo esc_html($current_version); ?>.
                        <?php if (!empty($last_checked_ts)): ?>
                            <span class="ichz-update-card__checked">Last checked <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_checked_ts)); ?>.</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="ichz-update-card__action">
                    <a class="button" href="<?php echo esc_url(add_query_arg(array('page' => 'ichronoz', 'ichz_force_check' => 1, '_wpnonce' => wp_create_nonce('ichz_force_check')), admin_url('admin.php'))); ?>">Check again</a>
                </div>
            </section>
        <?php endif; ?>

        <?php
        $tabs = array(
            'general' => array(
                'label' => 'Setup',
                'icon' => 'dashicons-admin-settings',
                'title' => 'Booking setup',
                'description' => 'Configure room presentation, booking connection, loading states, and multiple properties.',
            ),
            'ui' => array(
                'label' => 'Appearance',
                'icon' => 'dashicons-art',
                'title' => 'Appearance',
                'description' => 'Match iChronoz components to your brand using consistent colors and shape controls.',
            ),
            'analytics' => array(
                'label' => 'Analytics',
                'icon' => 'dashicons-chart-bar',
                'title' => 'Booking analytics',
                'description' => 'Review the booking funnel and configure privacy-conscious analytics integrations.',
            ),
            'scripts' => array(
                'label' => 'Advanced',
                'icon' => 'dashicons-editor-code',
                'title' => 'Advanced customization',
                'description' => 'Add trusted custom CSS or JavaScript and control how injected code is handled.',
            ),
            'howto' => array(
                'label' => 'Help & Shortcodes',
                'icon' => 'dashicons-editor-help',
                'title' => 'Help and shortcodes',
                'description' => 'Copy shortcode examples and learn where each iChronoz component should be used.',
            ),
        );
        $requested_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
        $active_tab = isset($tabs[$requested_tab]) ? $requested_tab : 'general';
        ?>
        <nav class="nav-tab-wrapper ichz-settings-tabs" aria-label="iChronoz settings sections">
            <?php foreach ($tabs as $tab_key => $tab): ?>
                <?php $tab_url = add_query_arg(array('page' => 'ichronoz', 'tab' => $tab_key), admin_url('admin.php')); ?>
                <a href="<?php echo esc_url($tab_url); ?>" class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>" <?php echo $active_tab === $tab_key ? 'aria-current="page"' : ''; ?>>
                    <span class="dashicons <?php echo esc_attr($tab['icon']); ?>" aria-hidden="true"></span>
                    <?php echo esc_html($tab['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="ichz-tab-summary">
            <h2><?php echo esc_html($tabs[$active_tab]['title']); ?></h2>
            <p><?php echo esc_html($tabs[$active_tab]['description']); ?></p>
        </div>
        <?php if ($active_tab === 'analytics'): ?>
            <?php ichronoz_analytics_render_admin_dashboard(); ?>
        <?php endif; ?>
        <?php
        // Handle self-update action
        if (isset($_POST['ichronoz_do_self_update'])) {
            if (!current_user_can('manage_options')) {
                wp_die('Insufficient permissions');
            }
            check_admin_referer('ichronoz_self_update');
            $result = ichronoz_handle_github_update();
            if (is_wp_error($result)) {
                echo '<div class="notice notice-error"><p>Update failed: ' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>Plugin updated to the latest release.</p></div>';
            }
        }
        ?>
        <?php if ($active_tab === 'analytics'): ?>
            <?php ichronoz_analytics_render_admin_settings(); ?>
        <?php else: ?>
        <?php $has_settings_form = $active_tab !== 'howto'; ?>
        <?php if ($has_settings_form): ?>
        <form method="post" action="options.php" class="ichz-settings-form">
            <?php
            // Render fields depending on active tab with separate groups
            if ($active_tab === 'general') {
                settings_fields('ichronoz_general_group');
            } elseif ($active_tab === 'ui') {
                settings_fields('ichronoz_ui_group');
            } elseif ($active_tab === 'scripts') {
                settings_fields('ichronoz_scripts_group');
            } else {
                // default to general
                settings_fields('ichronoz_general_group');
            }
            do_settings_sections('ichronoz_options_group');
            ?>
        <?php endif; ?>
            <?php if ($active_tab !== 'scripts'): ?>
            <div class="ichz-settings-card">
            <table class="form-table" role="presentation">
                <?php if ($active_tab === 'general'): ?>
                    <tr class="ichz-settings-section">
                        <th colspan="2">
                            <span class="ichz-settings-section__title">Room and search presentation</span>
                            <span class="ichz-settings-section__description">Choose how guests search and compare the available rooms.</span>
                        </th>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Room List View
                            <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span>
                                <span class="ichz-tip">Choose how rooms display on the booking page. Default lists every rate as a separate card. Grouped view groups by room type.</span>
                            </span>
                        </th>
                        <td>
                            <?php $room_view = get_option('ichronoz_room_card_type', 'default'); ?>
                            <select name="ichronoz_room_card_type" id="ichronoz_room_card_type">
                                <option value="default" <?php selected($room_view, 'default'); ?>>Default (flat list)</option>
                                <option value="room" <?php selected($room_view, 'room'); ?>>Grouped by room type</option>
                            </select>
                            <p class="description">Default: <code>default</code>
                                <a href="#" class="button-link" onclick="event.preventDefault(); var s=this.parentNode.previousElementSibling; if(s && s.tagName==='SELECT'){ s.value='default'; s.dispatchEvent(new Event('change')); }">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <?php
                    $room_group_layout = ichronoz_sanitize_room_group_layout(get_option('ichronoz_room_group_layout', 'small-image'));
                    $grouped_room_detail_options = ichronoz_get_grouped_room_detail_options();
                    $selected_grouped_room_details = ichronoz_sanitize_grouped_room_details(
                        get_option('ichronoz_grouped_room_details', array_keys($grouped_room_detail_options))
                    );
                    ?>
                    <tr valign="top" id="ichz-room-details-row">
                        <th scope="row">Item details to show
                            <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span>
                                <span class="ichz-tip">Select the optional content displayed inside every room rate card. Price and booking actions are always shown.</span>
                            </span>
                        </th>
                        <td>
                            <input type="hidden" name="ichronoz_grouped_room_details[]" value="" />
                            <div class="ichz-checkbox-grid" id="ichz-grouped-room-details">
                                <?php foreach ($grouped_room_detail_options as $detail_key => $detail_label): ?>
                                    <label class="ichz-checkbox-option">
                                        <input type="checkbox" name="ichronoz_grouped_room_details[]" value="<?php echo esc_attr($detail_key); ?>" <?php checked(in_array($detail_key, $selected_grouped_room_details, true)); ?> />
                                        <span><?php echo esc_html($detail_label); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="ichz-checkbox-actions">
                                <button type="button" class="button button-small" data-ichz-checkbox-action="select" data-ichz-checkbox-target="#ichz-grouped-room-details">Select all</button>
                                <button type="button" class="button button-small" data-ichz-checkbox-action="clear" data-ichz-checkbox-target="#ichz-grouped-room-details">Clear</button>
                            </div>
                            <p class="description">Price and booking actions are always visible. Select the additional information guests should see.</p>
                        </td>
                    </tr>
                    <tr valign="top" class="ichz-room-group-layout-row" style="<?php echo $room_view === 'room' ? '' : 'display:none;'; ?>">
                        <th scope="row">Grouped Layout Type
                            <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span>
                                <span class="ichz-tip">Choose the image presentation used when rooms are grouped by room type.</span>
                            </span>
                        </th>
                        <td>
                            <fieldset>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="ichronoz_room_group_layout" value="small-image" <?php checked($room_group_layout, 'small-image'); ?> />
                                    <strong>Small image</strong>
                                    <span class="description">— Compact overview beside the room rates (current layout).</span>
                                </label>
                                <label style="display:block;">
                                    <input type="radio" name="ichronoz_room_group_layout" value="regular-image" <?php checked($room_group_layout, 'regular-image'); ?> />
                                    <strong>Regular image</strong>
                                    <span class="description">— Larger image with room information beside it and rates below.</span>
                                </label>
                                <label style="display:block; margin-top:8px;">
                                    <input type="radio" name="ichronoz_room_group_layout" value="compact-table" <?php checked($room_group_layout, 'compact-table'); ?> />
                                    <strong>Compact rate table</strong>
                                    <span class="description">— Concise room overview followed by aligned, easy-to-compare rate rows.</span>
                                </label>
                                <label style="display:block; margin-top:8px;">
                                    <input type="radio" name="ichronoz_room_group_layout" value="promo-cards" <?php checked($room_group_layout, 'promo-cards'); ?> />
                                    <strong>Promo cards</strong>
                                    <span class="description">— Room overview on top with every promo displayed as a separate vertical card.</span>
                                </label>
                            </fieldset>
                            <p class="description">Default: <code>small-image</code></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Form Layout <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Choose vertical or horizontal layout for the search form.</span></span></th>
                        <td>
                            <select name="ichronoz_form_layout">
                                <option value="vertical" <?php selected(get_option('ichronoz_form_layout'), 'vertical'); ?>>Vertical</option>
                                <option value="horizontal" <?php selected(get_option('ichronoz_form_layout'), 'horizontal'); ?>>Horizontal</option>
                            </select>
                            <p class="description">Default: <code>vertical</code>
                                <a href="#" class="button-link" onclick="event.preventDefault(); var s=this.parentNode.previousElementSibling; if(s && s.tagName==='SELECT'){ s.value='vertical'; }">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr class="ichz-settings-section">
                        <th colspan="2">
                            <span class="ichz-settings-section__title">Floating search button</span>
                            <span class="ichz-settings-section__description">Control where the quick-search launcher appears and how it is styled.</span>
                        </th>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Floating Button Position <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Corner where the floating search button appears.</span></span></th>
                        <td>
                            <select name="ichronoz_fab_position">
                                <?php $fab_pos = get_option('ichronoz_fab_position', 'bottom-right'); ?>
                                <option value="bottom-right" <?php selected($fab_pos, 'bottom-right'); ?>>Bottom Right</option>
                                <option value="bottom-left" <?php selected($fab_pos, 'bottom-left'); ?>>Bottom Left</option>
                                <option value="top-right" <?php selected($fab_pos, 'top-right'); ?>>Top Right</option>
                                <option value="top-left" <?php selected($fab_pos, 'top-left'); ?>>Top Left</option>
                            </select>
                            <p class="description">Sets the floating search button corner. Default: <code>bottom-right</code>
                                <a href="#" class="button-link" onclick="event.preventDefault(); var s=this.parentNode.previousElementSibling; if(s && s.tagName==='SELECT'){ s.value='bottom-right'; }">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Floating Button Transparent
                            <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span>
                                <span class="ichz-tip">Make the floating button background transparent (outline style).</span>
                            </span>
                        </th>
                        <td>
                            <label style="display:inline-flex; align-items:center; gap:8px;">
                                <input type="checkbox" name="ichronoz_fab_transparent" value="1" <?php checked(get_option('ichronoz_fab_transparent', '0'), '1'); ?> />
                                <span>Enable transparent floating button</span>
                            </label>
                            <p class="description">When enabled, the floating button has transparent background with a border.</p>
                        </td>
                    </tr>
                    <?php $fab_transparent_checked = get_option('ichronoz_fab_transparent', '0') === '1'; ?>
                    <tr valign="top" class="ichz-fab-border-row" style="<?php echo $fab_transparent_checked ? '' : 'display:none'; ?>">
                        <th scope="row">Floating Button Border Color
                            <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span>
                                <span class="ichz-tip">Border color for the transparent floating button.</span>
                            </span>
                        </th>
                        <td>
                            <?php $fab_border_color = esc_attr(get_option('ichronoz_fab_border_color', '#1566d1')); ?>
                            <input type="text" name="ichronoz_fab_border_color" id="ichronoz_fab_border_color" value="<?php echo $fab_border_color; ?>" class="ichronoz-color-field" data-default-color="#1566d1" />
                            <p class="description">Displayed only when transparency is enabled. Default: <code>#1566d1</code></p>
                            <script>
                                jQuery(function($) {
                                    if ($.fn.wpColorPicker) {
                                        $('#ichronoz_fab_border_color').wpColorPicker();
                                    }
                                });
                            </script>
                        </td>
                    </tr>
                    <tr class="ichz-settings-section">
                        <th colspan="2">
                            <span class="ichz-settings-section__title">Promo suggestions</span>
                            <span class="ichz-settings-section__description">Control when eligible promotion codes returned by the API appear in the popup and room badges.</span>
                        </th>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Enable Promo Suggestions</th>
                        <td>
                            <input type="hidden" name="ichronoz_promo_nudge_enabled" value="0" />
                            <label><input type="checkbox" name="ichronoz_promo_nudge_enabled" value="1" <?php checked(get_option('ichronoz_promo_nudge_enabled', '0'), '1'); ?> /> Show eligible API promotions</label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="ichronoz_promo_nudge_minimum_searches">Minimum Searches</label></th>
                        <td><input id="ichronoz_promo_nudge_minimum_searches" type="number" name="ichronoz_promo_nudge_minimum_searches" value="<?php echo esc_attr(get_option('ichronoz_promo_nudge_minimum_searches', 3)); ?>" min="1" max="20" step="1" class="small-text" /><p class="description">User-initiated searches required before showing the popup and room badges. Default: <code>3</code>.</p></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="ichronoz_promo_nudge_minimum_seconds">Minimum Time on Page</label></th>
                        <td><input id="ichronoz_promo_nudge_minimum_seconds" type="number" name="ichronoz_promo_nudge_minimum_seconds" value="<?php echo esc_attr(get_option('ichronoz_promo_nudge_minimum_seconds', 30)); ?>" min="0" max="3600" step="1" class="small-text" /> seconds<p class="description">Minimum time before the popup and room badges can appear. Use <code>0</code> to disable the time requirement.</p></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Always Show After Minimum Time</th>
                        <td>
                            <input type="hidden" name="ichronoz_promo_nudge_always_after_time" value="0" />
                            <label><input type="checkbox" name="ichronoz_promo_nudge_always_after_time" value="1" <?php checked(get_option('ichronoz_promo_nudge_always_after_time', '0'), '1'); ?> /> Show eligible promos when the minimum time is reached, without waiting for Minimum Searches</label>
                            <p class="description">Campaign eligibility, dismissal cooldown, and show-once settings still apply.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="ichronoz_promo_nudge_delay_ms">Delay After Search</label></th>
                        <td><input id="ichronoz_promo_nudge_delay_ms" type="number" name="ichronoz_promo_nudge_delay_ms" value="<?php echo esc_attr(get_option('ichronoz_promo_nudge_delay_ms', 800)); ?>" min="0" max="10000" step="100" class="small-text" /> milliseconds<p class="description">Delay before the popup and room badges appear after eligible results finish loading. Default: <code>800</code> ms.</p></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Show Frequency</th>
                        <td><input type="hidden" name="ichronoz_promo_nudge_show_once" value="0" /><label><input type="checkbox" name="ichronoz_promo_nudge_show_once" value="1" <?php checked(get_option('ichronoz_promo_nudge_show_once', '1'), '1'); ?> /> Show each campaign only once per browser session</label></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="ichronoz_promo_nudge_dismiss_hours">Dismiss Cooldown</label></th>
                        <td><input id="ichronoz_promo_nudge_dismiss_hours" type="number" name="ichronoz_promo_nudge_dismiss_hours" value="<?php echo esc_attr(get_option('ichronoz_promo_nudge_dismiss_hours', 24)); ?>" min="0" max="8760" step="1" class="small-text" /> hours<p class="description">How long a dismissed campaign remains hidden.</p></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="ichronoz_promo_nudge_maximum_visible">Maximum Visible Promos</label></th>
                        <td><input id="ichronoz_promo_nudge_maximum_visible" type="number" name="ichronoz_promo_nudge_maximum_visible" value="<?php echo esc_attr(get_option('ichronoz_promo_nudge_maximum_visible', 3)); ?>" min="1" max="10" step="1" class="small-text" /><p class="description">Maximum eligible offers shown in the popup. Default: <code>3</code>.</p></td>
                    </tr>
                    <tr class="ichz-settings-section">
                        <th colspan="2">
                            <span class="ichz-settings-section__title">Rooms carousel shortcode</span>
                            <span class="ichz-settings-section__description">Configure the compact offer carousel rendered by <code>[ichronoz_rooms_carousel]</code>.</span>
                        </th>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="ichronoz_rooms_carousel_subheading">Stay Label</label></th>
                        <td><input id="ichronoz_rooms_carousel_subheading" type="text" name="ichronoz_rooms_carousel_subheading" value="<?php echo esc_attr(get_option('ichronoz_rooms_carousel_subheading', 'Stay period')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="ichronoz_rooms_carousel_button_label">Button Label</label></th>
                        <td><input id="ichronoz_rooms_carousel_button_label" type="text" name="ichronoz_rooms_carousel_button_label" value="<?php echo esc_attr(get_option('ichronoz_rooms_carousel_button_label', 'Book Room Now')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="ichronoz_rooms_carousel_autoplay">Autoplay Interval</label></th>
                        <td><input id="ichronoz_rooms_carousel_autoplay" type="number" name="ichronoz_rooms_carousel_autoplay" value="<?php echo esc_attr(get_option('ichronoz_rooms_carousel_autoplay', 5)); ?>" min="0" max="60" class="small-text" /> seconds<p class="description">Use <code>0</code> to disable automatic sliding.</p></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Close Button</th>
                        <td><input type="hidden" name="ichronoz_rooms_carousel_dismissible" value="0" /><label><input type="checkbox" name="ichronoz_rooms_carousel_dismissible" value="1" <?php checked(get_option('ichronoz_rooms_carousel_dismissible', '1'), '1'); ?> /> Allow visitors to dismiss the carousel</label></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="ichronoz_rooms_carousel_position">Carousel Position</label></th>
                        <td>
                            <?php $rooms_carousel_position = ichronoz_sanitize_rooms_carousel_position(get_option('ichronoz_rooms_carousel_position', 'bottom-right')); ?>
                            <select id="ichronoz_rooms_carousel_position" name="ichronoz_rooms_carousel_position">
                                <option value="bottom-right" <?php selected($rooms_carousel_position, 'bottom-right'); ?>>Bottom right</option>
                                <option value="bottom-left" <?php selected($rooms_carousel_position, 'bottom-left'); ?>>Bottom left</option>
                                <option value="top-right" <?php selected($rooms_carousel_position, 'top-right'); ?>>Top right</option>
                                <option value="top-left" <?php selected($rooms_carousel_position, 'top-left'); ?>>Top left</option>
                            </select>
                            <p class="description">Controls the viewport position of both the carousel and its collapsed Room Offers button.</p>
                        </td>
                    </tr>
                    <tr class="ichz-settings-section">
                        <th colspan="2">
                            <span class="ichz-settings-section__title">Connection and booking page</span>
                            <span class="ichz-settings-section__description">Connect your iChronoz account and define the booking transition experience.</span>
                        </th>
                    </tr>
                    <tr valign="top">
                        <th scope="row">API Key <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Get API key from iChronoz dashboard.</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_api_value" value="<?php echo esc_attr(get_option('ichronoz_api_value', 'xxxxx')); ?>" class="regular-text" />
                            <p class="description">Example: <code>xxxxx</code>
                                <a href="#" class="button-link" onclick="event.preventDefault(); var i=this.parentNode.previousElementSibling; if(i){ i.value='xxxxx'; }">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Booking Page Path <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Relative path to the booking page (e.g., /index.php/book).</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_booking_path" value="<?php echo esc_attr(get_option('ichronoz_booking_path', '/index.php/book')); ?>" class="regular-text" />
                            <p class="description">Relative path for the booking page (e.g., /index.php/book or /book). Default: <code>/index.php/book</code>
                                <a href="#" class="button-link" onclick="event.preventDefault(); var i=this.parentNode.previousElementSibling; if(i){ i.value='/index.php/book'; }">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Loading Message (Booking Page) <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Shown while searching on booking page. Supports {fromLong}/{toShort} placeholders.</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_loading_message" value="<?php echo esc_attr(get_option('ichronoz_loading_message', 'Searching for the best rate within your requested period: {fromLong} - {toShort}')); ?>" class="regular-text" />
                            <p class="description">Use placeholders: {fromLong}, {fromShort}, {toLong}, {toShort}. Default:
                                <code>Searching for the best rate within your requested period: {fromLong} - {toShort}</code>
                                <a href="#" class="button-link" onclick="event.preventDefault(); var i=this.parentNode.previousElementSibling; if(i){ i.value='Searching for the best rate within your requested period: {fromLong} - {toShort}'; }">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Loading Spinner URL <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">URL to a loading indicator image shown during search.</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_spinner_url" value="<?php echo esc_attr(get_option('ichronoz_spinner_url', '/wp-admin/images/spinner.gif')); ?>" class="regular-text" />
                            <p class="description">Absolute or site-relative URL to a loading indicator image. Default: <code>/wp-admin/images/spinner.gif</code>
                                <a href="#" class="button-link" onclick="event.preventDefault(); var i=this.parentNode.previousElementSibling; if(i){ i.value='/wp-admin/images/spinner.gif'; }">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr class="ichz-settings-section">
                        <th colspan="2">
                            <span class="ichz-settings-section__title">Multiple properties</span>
                            <span class="ichz-settings-section__description">Optionally let guests choose between multiple hotel properties.</span>
                        </th>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Is Multiproperty?
                            <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span>
                                <span class="ichz-tip">Enables property selection in the search form. Configure options below as JSON.</span>
                            </span>
                        </th>
                        <td>
                            <label style="display:inline-flex; align-items:center; gap:8px;">
                                <input id="ichronoz_hid_enabled" type="checkbox" name="ichronoz_hid_enabled" value="1" <?php checked(get_option('ichronoz_hid_enabled', '0'), '1'); ?> />
                                <span>Show Property select in search form</span>
                            </label>
                            <p class="description">When enabled, users can select a property before choosing dates.</p>
                        </td>
                    </tr>
                    <tr valign="top" class="ichz-hid-options-row">
                        <th scope="row">Property List Options (JSON)
                            <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span>
                                <span class="ichz-tip">Provide an array of {hid, name} objects. Example: <code>[{&quot;hid&quot;:&quot;123&quot;,&quot;name&quot;:&quot;Main Hotel&quot;},{&quot;hid&quot;:&quot;456&quot;,&quot;name&quot;:&quot;Beach Villas&quot;}]</code></span>
                            </span>
                        </th>
                        <td>
                            <textarea id="ichronoz_hid_options_json" name="ichronoz_hid_options_json" rows="5" class="large-text code" placeholder='[{"hid":"123","name":"Main Hotel"},{"hid":"456","name":"Beach Villas"}]'><?php echo esc_textarea(get_option('ichronoz_hid_options_json', '[]')); ?></textarea>
                            <p class="description">Each entry must include both <code>hid</code> and <code>name</code>. Example: <code>[{&quot;hid&quot;:&quot;123&quot;,&quot;name&quot;:&quot;Main Hotel&quot;},{&quot;hid&quot;:&quot;456&quot;,&quot;name&quot;:&quot;Beach Villas&quot;}]</code></p>
                        </td>
                    </tr>
                <?php elseif ($active_tab === 'howto'): // How To Use tab 
                ?>
                    <tr>
                        <td colspan="2">
                            <div class="ichz-code-panel" style="max-width:920px">
                                <h3>Shortcodes</h3>
                                <p>Use these shortcodes in pages or posts to embed iChronoz components:</p>

                                <h4>Component</h4>
                                <ul class="ichz-shortcode-list">
                                    <li>
                                        <code id="sc-search-form">[ichronoz_search_form]</code>
                                        <button type="button" class="button button-small" data-copy-target="#sc-search-form">Copy</button>
                                        <button type="button" class="button button-small" data-view-target="#sc-preview-search-form" data-view-title="Search Form Preview">Preview</button>
                                        — Renders the main search form.
                                    </li>
                                    <li>
                                        <code id="sc-search-button">[ichronoz_search_button]</code>
                                        <button type="button" class="button button-small" data-copy-target="#sc-search-button">Copy</button>
                                        <!-- <button type="button" class="button button-small" data-view-target="#sc-preview-search-button" data-view-title="Search Button Preview">Preview</button> -->
                                        — Adds a floating button that toggles the search form.
                                    </li>
                                    <li>
                                        <code id="sc-room-list">[ichronoz_room_list]</code>
                                        <button type="button" class="button button-small" data-copy-target="#sc-room-list">Copy</button>
                                        <button type="button" class="button button-small" data-view-target="#sc-preview-room-list" data-view-title="Room List Preview">Preview</button>
                                        — Renders the room list component.
                                    </li>
                                    <li>
                                        <code id="sc-rooms-carousel">[ichronoz_rooms_carousel]</code>
                                        <button type="button" class="button button-small" data-copy-target="#sc-rooms-carousel">Copy</button>
                                        <button type="button" class="button button-small" data-view-target="#sc-preview-rooms-carousel" data-view-title="Rooms Carousel Preview">Preview</button>
                                        — Renders the compact room offer carousel.
                                    </li>
                                </ul>

                                <h4>Pages</h4>
                                <ul class="ichz-shortcode-list">
                                    <li>
                                        <code id="sc-booking-page">[ichronoz_booking_page]</code>
                                        <button type="button" class="button button-small" data-copy-target="#sc-booking-page">Copy</button>
                                        <button type="button" class="button button-small" data-view-target="#sc-preview-booking-page" data-view-title="Booking Page Preview">Preview</button>
                                        — Renders the booking list page.
                                    </li>
                                    <li>
                                        <code id="sc-booking-page-multi">[ichronoz_booking_multi]</code>
                                        <button type="button" class="button button-small" data-copy-target="#sc-booking-page-multi">Copy</button>
                                        <button type="button" class="button button-small" data-view-target="#sc-preview-booking-page-multi" data-view-title="Booking Multi Preview">Preview</button>
                                        — Renders the booking list page with multi-room selection controls.
                                    </li>
                                    <li>
                                        <code id="sc-ticket-booking">[ichronoz_booking] / [ichronoz_booking type="activity-date"] / [ichronoz_booking type="activity-date,appointment-date"]</code>
                                        <button type="button" class="button button-small" data-copy-target="#sc-ticket-booking">Copy</button>
                                        <button type="button" class="button button-small" data-view-target="#sc-preview-ticket-booking" data-view-title="Ticket Booking Preview">Preview</button>
                                        — Renders the booking page filtering by "type" with default "activity-date", example types: activity-date and appointment-date.
                                    </li>
                                </ul>
                                <div id="sc-preview-search-form" style="display:none;"><?php echo do_shortcode('[ichronoz_search_form]'); ?></div>
                                <div id="sc-preview-booking-page" style="display:none;"><?php echo do_shortcode('[ichronoz_booking_page]'); ?></div>
                                <div id="sc-preview-booking-page-multi" style="display:none;"><?php echo do_shortcode('[ichronoz_booking_multi]'); ?></div>
                                <div id="sc-preview-search-button" style="display:none;"><?php echo do_shortcode('[ichronoz_search_button]'); ?></div>
                                <div id="sc-preview-ticket-booking" style="display:none;"><?php echo do_shortcode('[ichronoz_booking]'); ?></div>
                                <div id="sc-preview-room-list" style="display:none;"><?php echo do_shortcode('[ichronoz_room_list]'); ?></div>
                                <div id="sc-preview-rooms-carousel" style="display:none;"><?php echo do_shortcode('[ichronoz_rooms_carousel]'); ?></div>
                                <div id="ichz-shortcode-modal" style="display:none; position:fixed; z-index:100000; inset:0; background:rgba(0,0,0,0.45); align-items:center; justify-content:center;">
                                    <div role="dialog" aria-modal="true" aria-labelledby="ichz-shortcode-modal-title" style="background:#fff; width:100vw; height:100vh; border-radius:0; box-shadow:none; overflow:hidden;">
                                        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid #dcdcde;">
                                            <h3 id="ichz-shortcode-modal-title" style="margin:0; font-size:16px; line-height:1.3;">Shortcode</h3>
                                            <button type="button" id="ichz-shortcode-modal-close" class="button button-small">Close</button>
                                        </div>
                                        <div style="padding:16px; overflow:auto; height:calc(100vh - 64px);">
                                            <div id="ichz-shortcode-modal-value"></div>
                                        </div>
                                    </div>
                                </div>
                                <style>
                                    #ichz-shortcode-modal-value .card {
                                        max-width: none !important;
                                        width: auto !important;
                                    }
                                </style>
                                <script>
                                    (function() {
                                        var modal = document.getElementById('ichz-shortcode-modal');
                                        var modalValue = document.getElementById('ichz-shortcode-modal-value');
                                        var modalTitle = document.getElementById('ichz-shortcode-modal-title');
                                        var modalClose = document.getElementById('ichz-shortcode-modal-close');

                                        function openModal(title, html) {
                                            if (!modal || !modalValue || !modalTitle) return;
                                            modalTitle.textContent = title || 'Shortcode';
                                            modalValue.innerHTML = html || '';
                                            modal.style.display = 'flex';
                                        }

                                        function closeModal() {
                                            if (!modal) return;
                                            modal.style.display = 'none';
                                        }

                                        if (modalClose) {
                                            modalClose.addEventListener('click', function(e) {
                                                e.preventDefault();
                                                closeModal();
                                            });
                                        }

                                        document.addEventListener('click', function(e) {
                                            var viewBtn = e.target.closest('button[data-view-target]');
                                            if (viewBtn) {
                                                e.preventDefault();
                                                var viewTarget = viewBtn.getAttribute('data-view-target');
                                                var viewEl = viewTarget ? document.querySelector(viewTarget) : null;
                                                var previewHtml = viewEl ? viewEl.innerHTML : '';
                                                if (previewHtml) {
                                                    openModal(viewBtn.getAttribute('data-view-title') || 'Shortcode', previewHtml);
                                                }
                                                return;
                                            }

                                            var btn = e.target.closest('button[data-copy-target]');
                                            if (!btn) return;
                                            e.preventDefault();
                                            var sel = btn.getAttribute('data-copy-target');
                                            var el = document.querySelector(sel);
                                            if (!el) return;
                                            var text = el.textContent || el.innerText || '';
                                            if (!text) return;
                                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                                navigator.clipboard.writeText(text).then(function() {
                                                    btn.textContent = 'Copied!';
                                                    setTimeout(function() {
                                                        btn.textContent = 'Copy';
                                                    }, 1200);
                                                }).catch(function() {
                                                    try {
                                                        var ta = document.createElement('textarea');
                                                        ta.value = text;
                                                        document.body.appendChild(ta);
                                                        ta.select();
                                                        document.execCommand('copy');
                                                        document.body.removeChild(ta);
                                                        btn.textContent = 'Copied!';
                                                        setTimeout(function() {
                                                            btn.textContent = 'Copy';
                                                        }, 1200);
                                                    } catch (_) {}
                                                });
                                            } else {
                                                try {
                                                    var ta = document.createElement('textarea');
                                                    ta.value = text;
                                                    document.body.appendChild(ta);
                                                    ta.select();
                                                    document.execCommand('copy');
                                                    document.body.removeChild(ta);
                                                    btn.textContent = 'Copied!';
                                                    setTimeout(function() {
                                                        btn.textContent = 'Copy';
                                                    }, 1200);
                                                } catch (_) {}
                                            }
                                        });

                                        document.addEventListener('keydown', function(e) {
                                            if (e.key === 'Escape') closeModal();
                                        });

                                        if (modal) {
                                            modal.addEventListener('click', function(e) {
                                                if (e.target === modal) closeModal();
                                            });
                                        }
                                    })();
                                </script>
                                <h4>Examples</h4>
                                <p>
                                    Create a page named <em>Search</em> and insert <code>[ichronoz_search_form]</code>. Create another page named <em>Booking</em> and insert <code>[ichronoz_booking_page]</code>. Optionally add <code>[ichronoz_search_button]</code> to show a floating quick-search button anywhere on the site.
                                </p>
                                <h4>Customization</h4>
                                <p>
                                    - Colors and layout: adjust under <strong>UI Settings</strong>.<br />
                                    - Booking path and API key: configure under <strong>General</strong>.<br />
                                    - Optional scripts for booking/detail pages: add under <strong>Custom Code</strong>.<br />
                                    - Booking analytics and Google Tag Manager: configure under <strong>Analytics</strong>.
                                </p>
                                <h4>Notes</h4>
                                <p>
                                    Assets load automatically on pages where these shortcodes render, or when using the floating search button.
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php elseif ($active_tab === 'ui'): // UI Settings tab 
                ?>
                    <tr class="ichz-settings-section">
                        <th colspan="2">
                            <span class="ichz-settings-section__title">Shape and detail header</span>
                            <span class="ichz-settings-section__description">Control corner rounding and the gradient used on detail pages.</span>
                        </th>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Round Buttons
                            <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span>
                                <span class="ichz-tip">Apply rounded corners to all buttons inside the iChronoz UI scope (<code>.ichronoz .btn</code>).</span>
                            </span>
                        </th>
                        <td>
                            <label>
                                <input id="ichronoz_btn_rounded" type="checkbox" name="ichronoz_btn_rounded" value="1" <?php checked(get_option('ichronoz_btn_rounded', '0'), '1'); ?> />
                                <span>Round all</span>
                            </label>
                            <p class="description">Toggle rounded corners for all Bootstrap buttons rendered by the plugin.</p>
                        </td>
                    </tr>
                    <tr valign="top" class="ichz-btn-radius-row">
                        <th scope="row">Button Border Radius
                            <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span>
                                <span class="ichz-tip">Radius value used when rounding is enabled. Accepts units like <code>px</code>, <code>rem</code>, <code>em</code>, or <code>%</code>.</span>
                            </span>
                        </th>
                        <td>
                            <input id="ichronoz_btn_radius" type="text" name="ichronoz_btn_radius" value="<?php echo esc_attr(get_option('ichronoz_btn_radius', '6px')); ?>" class="regular-text" placeholder="6px" />
                            <p class="description">Examples: <code>6px</code>, <code>0.5rem</code>, <code>999px</code>. Default: <code>6px</code>.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Detail Header Gradient
                            <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span>
                                <span class="ichz-tip">Provide three gradient colors followed by the text color. Leave empty to use the standard UI palette.</span>
                            </span>
                        </th>
                        <td>
                            <input type="text" name="ichronoz_gradient_colors" value="<?php echo esc_attr(get_option('ichronoz_gradient_colors', '')); ?>" class="regular-text" placeholder="#1a2f78, #1870c9, #5195e3, #FFFFFF" />
                            <p class="description">Three gradient colors followed by the text color, separated by commas or spaces. Example: <code>#0d6efd, #6c757d, #20c997, #FFFFFF</code>
                                <a href="#" class="button-link" onclick="event.preventDefault(); var i=this.parentNode.previousElementSibling; if(i){ i.value=''; }">Clear</a>
                            </p>
                        </td>
                    </tr>
                    <tr class="ichz-settings-section">
                        <th colspan="2">
                            <span class="ichz-settings-section__title">Brand and component colors</span>
                            <span class="ichz-settings-section__description">Set the primary palette and colors for interactive booking elements.</span>
                        </th>
                    </tr>
                    <!-- <tr valign="top">
                        <th scope="row">Selected Day Color <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Accent color for selected days in the calendar.</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_selected_day_color" value="<?php //echo esc_attr(!empty(get_option('ichronoz_selected_day_color')) ? get_option('ichronoz_selected_day_color') :'#0071c2'); 
                                                                                            ?>" class="color-picker" />
                            <p class="description">Default: <code>#0071c2</code>
                                <a href="#" class="button-link" data-default-color="#0071c2">Reset</a>
                            </p>
                        </td>
                    </tr> -->
                    <tr valign="top">
                        <th scope="row">Primary Color <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Fill color of the floating search button.</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_search_button_color" value="<?php echo esc_attr(!empty(get_option('ichronoz_search_button_color')) ? get_option('ichronoz_search_button_color') : '#1566d1'); ?>" class="color-picker" />
                            <p class="description">Default: <code>#1566d1</code>
                                <a href="#" class="button-link" data-default-color="#1566d1">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Secondary Color <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Secondary UI accents (muted elements and borders).</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_secondary_color" value="<?php echo esc_attr(!empty(get_option('ichronoz_secondary_color')) ? get_option('ichronoz_secondary_color') : '#6c757d'); ?>" class="color-picker" />
                            <p class="description">Default: <code>#6c757d</code>
                                <a href="#" class="button-link" data-default-color="#6c757d">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Success Color <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Positive states (e.g., success messages).</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_success_color" value="<?php echo esc_attr(!empty(get_option('ichronoz_success_color')) ? get_option('ichronoz_success_color') : '#198754'); ?>" class="color-picker" />
                            <p class="description">Default: <code>#198754</code>
                                <a href="#" class="button-link" data-default-color="#198754">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Warning Color <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Warnings and non-blocking alerts.</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_warning_color" value="<?php echo esc_attr(!empty(get_option('ichronoz_warning_color')) ? get_option('ichronoz_warning_color') : '#ffc107'); ?>" class="color-picker" />
                            <p class="description">Default: <code>#ffc107</code>
                                <a href="#" class="button-link" data-default-color="#ffc107">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Link Color <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Overrides link color. Leave empty to inherit theme.</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_link_color" value="<?php echo esc_attr(get_option('ichronoz_link_color', '')); ?>" class="color-picker" />
                            <p class="description">Default: inherit theme (empty)
                                <a href="#" class="button-link" data-default-color="">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Room Hover Background Color <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Background color when hovering room cards.</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_room_hover_bg_color" value="<?php echo esc_attr(!empty(get_option('ichronoz_room_hover_bg_color')) ? get_option('ichronoz_room_hover_bg_color') : '#e6e6e6'); ?>" class="color-picker" />
                            <p class="description">Default: <code>#e6e6e6</code>
                                <a href="#" class="button-link" data-default-color="#e6e6e6">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Calendar Range Background <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Background for the selected date range.</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_calendar_range_bg" value="<?php echo esc_attr(!empty(get_option('ichronoz_calendar_range_bg')) ? get_option('ichronoz_calendar_range_bg') : '#e3f2ff'); ?>" class="color-picker" />
                            <p class="description">Background color for the selected date range in calendar fallback. Default: <code>#e3f2ff</code>
                                <a href="#" class="button-link" data-default-color="#e3f2ff" data-target-name="ichronoz_calendar_range_bg">Reset</a>
                            </p>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
            </div>
            <?php endif; ?>
            <?php if ($active_tab === 'ui'): ?>
                <p>
                    <button type="button" class="button" id="ichz-reset-all-ui-colors">Reset All UI Colors</button>
                </p>
                <!-- Reset All handled in js/color-picker.js -->
            <?php elseif ($active_tab === 'scripts'): // Scripts injection tab 
            ?>
                <div class="notice notice-info inline" style="margin:16px 0;">
                    <p><strong>Google Tag Manager belongs in Analytics.</strong> Use Custom Code only for page-specific JavaScript and CSS. This helps prevent duplicate GTM containers and duplicated conversions.</p>
                </div>
                <?php if (ichronoz_analytics_custom_code_has_gtm()): ?>
                    <div class="notice notice-warning inline" style="margin:16px 0;">
                        <p>GTM-related code is currently detected in these custom scripts. Remove it manually before enabling “Install GTM through iChronoz”. Existing user code is never removed automatically.</p>
                    </div>
                <?php endif; ?>
                <div class="ichz-settings-card">
                    <section class="ichz-code-panel">
                        <h3>Custom CSS
                            <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span>
                                <span class="ichz-tip">Add CSS to customize iChronoz components. Prefer scoping with .ichronoz to affect only the widget.</span>
                            </span>
                        </h3>
                        <p class="description">Loaded after plugin styles. Scope selectors with <code>.ichronoz</code> to prevent changes outside the booking interface.</p>
                        <textarea name="ichronoz_custom_css" rows="10" class="large-text code" aria-label="Custom CSS" placeholder=".ichronoz .btn { background: #1e88e5; }\n.ichronoz .card { border-radius: 8px; }\n"><?php echo esc_textarea(get_option('ichronoz_custom_css', '')); ?></textarea>
                    </section>
                    <section class="ichz-code-panel">
                        <h3>Booking page script</h3>
                        <input type="hidden" name="ichronoz_booking_script_enabled" value="0" />
                        <label>
                            <input type="checkbox" name="ichronoz_booking_script_enabled" value="1" data-ichz-script-toggle="#ichronoz-booking-script" <?php checked(get_option('ichronoz_booking_script_enabled', '0'), '1'); ?> />
                            Enable booking page script
                        </label>
                        <textarea id="ichronoz-booking-script" name="ichronoz_booking_script" rows="8" class="large-text code"><?php echo esc_textarea(get_option('ichronoz_booking_script', '')); ?></textarea>
                        <p class="description">Runs on the booking list page. Only use code from trusted sources.</p>
                    </section>
                    <section class="ichz-code-panel">
                        <h3>Detail page script</h3>
                        <input type="hidden" name="ichronoz_detail_script_enabled" value="0" />
                        <label>
                            <input type="checkbox" name="ichronoz_detail_script_enabled" value="1" data-ichz-script-toggle="#ichronoz-detail-script" <?php checked(get_option('ichronoz_detail_script_enabled', '0'), '1'); ?> />
                            Enable detail page script
                        </label>
                        <textarea id="ichronoz-detail-script" name="ichronoz_detail_script" rows="8" class="large-text code"><?php echo esc_textarea(get_option('ichronoz_detail_script', '')); ?></textarea>
                        <p class="description">Runs on the room detail page. Only use code from trusted sources.</p>
                    </section>
                    <section class="ichz-code-panel">
                        <h3>Security and execution</h3>
                        <?php $san_mode = get_option('ichronoz_script_sanitization', 'raw'); ?>
                        <p>
                            <label for="ichronoz-script-sanitization"><strong>Sanitization mode</strong></label><br />
                            <select id="ichronoz-script-sanitization" name="ichronoz_script_sanitization">
                                <option value="raw" <?php selected($san_mode, 'raw'); ?>>Raw (no filtering)</option>
                                <option value="strip_tags" <?php selected($san_mode, 'strip_tags'); ?>>Strip &lt;script&gt; tags</option>
                                <option value="kses" <?php selected($san_mode, 'kses'); ?>>KSES (limited HTML)</option>
                            </select>
                        </p>
                        <p class="description">Raw mode permits full JavaScript and should only be used for code you trust.</p>
                        <p>
                            <label for="ichronoz-csp-nonce"><strong>CSP nonce (optional)</strong></label><br />
                            <input id="ichronoz-csp-nonce" type="text" name="ichronoz_csp_nonce" value="<?php echo esc_attr(get_option('ichronoz_csp_nonce', '')); ?>" class="regular-text" />
                        </p>
                        <p class="description">Leave empty unless your site enforces a Content Security Policy with nonces.</p>
                    </section>
                </div>
            <?php endif; ?>
            <?php if ($has_settings_form): ?>
                <?php submit_button(); ?>
            </form>
            <?php endif; ?>
        <?php endif; ?>

        <script>
            (function() {
                // Grouped layout type is only relevant for the grouped room-list view.
                var roomView = document.getElementById('ichronoz_room_card_type');
                var roomDetailsRow = document.getElementById('ichz-room-details-row');
                var roomGroupLayoutRows = document.querySelectorAll('.ichz-room-group-layout-row');
                if (roomDetailsRow) {
                    roomDetailsRow.hidden = false;
                    roomDetailsRow.style.removeProperty('display');
                }
                if (roomView && roomGroupLayoutRows.length) {
                    function syncRoomGroupLayout() {
                        roomGroupLayoutRows.forEach(function(row) {
                            row.style.display = roomView.value === 'room' ? '' : 'none';
                        });
                    }
                    roomView.addEventListener('change', syncRoomGroupLayout);
                    syncRoomGroupLayout();
                }

                // Show/hide FAB border color when transparency toggled
                var fabCb = document.querySelector('input[name="ichronoz_fab_transparent"]');
                var fabRow = document.querySelector('tr.ichz-fab-border-row');
                if (fabCb && fabRow) {
                    function syncFab() {
                        fabRow.style.display = fabCb.checked ? '' : 'none';
                    }
                    fabCb.addEventListener('change', syncFab);
                    syncFab();
                }

                // Button rounding depends
                var roundCb = document.getElementById('ichronoz_btn_rounded');
                var roundRow = document.querySelector('.ichz-btn-radius-row');
                var radiusInput = document.getElementById('ichronoz_btn_radius');
                if (roundCb && roundRow) {
                    function syncRound() {
                        var on = roundCb.checked;
                        roundRow.style.display = on ? '' : 'none';
                        if (radiusInput) radiusInput.disabled = !on;
                    }
                    roundCb.addEventListener('change', syncRound);
                    syncRound();
                }
            })();
        </script>

        <script>
            (function() {
                var cb = document.getElementById('ichronoz_hid_enabled');
                var row = document.querySelector('.ichz-hid-options-row');
                var input = document.getElementById('ichronoz_hid_options_json');
                if (!cb || !row) return;

                function sync() {
                    var on = cb.checked;
                    row.style.display = on ? '' : 'none';
                    if (input) input.disabled = !on;
                }
                cb.addEventListener('change', sync);
                sync();

                // Lightweight client-side JSON validation with inline feedback
                if (input) {
                    var msgId = 'ichz-hid-json-msg';
                    var msg = document.getElementById(msgId);
                    if (!msg) {
                        msg = document.createElement('p');
                        msg.id = msgId;
                        msg.className = 'description';
                        input.parentNode.appendChild(msg);
                    }
                    var isValid = true;

                    function validate() {
                        if (input.disabled) {
                            msg.textContent = '';
                            isValid = true;
                            return;
                        }
                        var val = input.value.trim();
                        if (!val) {
                            msg.textContent = 'Required when multiproperty is enabled.';
                            msg.style.color = '#b32d2e';
                            isValid = false;
                            return;
                        }
                        try {
                            var parsed = JSON.parse(val);
                            if (!Array.isArray(parsed)) {
                                msg.textContent = 'JSON should be an array of {hid, name} objects.';
                                msg.style.color = '#b32d2e';
                                isValid = false;
                            } else {
                                var ok = parsed.every(function(it) {
                                    return it && typeof it === 'object' && 'hid' in it && 'name' in it;
                                });
                                if (!ok) {
                                    msg.textContent = 'Each item must include both "hid" and "name".';
                                    msg.style.color = '#b32d2e';
                                    isValid = false;
                                } else {
                                    msg.textContent = 'Looks good.';
                                    msg.style.color = '#0a7f36';
                                    isValid = true;
                                }
                            }
                        } catch (e) {
                            msg.textContent = 'Invalid JSON: ' + e.message;
                            msg.style.color = '#b32d2e';
                            isValid = false;
                        }
                    }
                    input.addEventListener('input', validate);
                    validate();

                    // Prevent settings form submission when invalid
                    var form = input.closest('form');
                    if (form) {
                        form.addEventListener('submit', function(e) {
                            validate();
                            if (!isValid) {
                                e.preventDefault();
                                input.focus();
                                // Ensure the UI tab is active (in case)
                                try {
                                    window.scrollTo({
                                        top: input.getBoundingClientRect().top + window.scrollY - 120,
                                        behavior: 'smooth'
                                    });
                                } catch (_) {}
                            }
                        });
                    }
                }
            })();
        </script>

    </div>
<?php
}

function ichronoz_add_settings_page()
{
    add_menu_page(
        'iChronoz Settings',       // Page title
        'iChronoz',                // Menu title
        'manage_options',          // Capability
        'ichronoz',                // Menu slug
        'ichronoz_settings_page',  // Callback function
        'dashicons-calendar-alt',  // Icon (Dashicons)
        56                         // Position
    );
}

add_shortcode('ichronoz_search_form', 'ichronoz_shortcode');
/**
 * Shortcode: [ichronoz_room_list]
 * Renders a lightweight room carousel (3-up) without the booking form.
 */
function ichronoz_room_list_shortcode()
{
    ichronoz_enqueue_scripts();
    do_action('ichronoz_rendered_room_list');
    return '<div class="ichronoz"><div data-ichronoz-mount="room-list"></div></div>';
}
add_shortcode('ichronoz_room_list', 'ichronoz_room_list_shortcode');

/**
 * Shortcode: [ichronoz_rooms_carousel]
 * Renders a compact, independently mounted room-offer carousel.
 */
function ichronoz_current_page_has_booking_shortcode()
{
    $post = get_post();
    if (!$post instanceof WP_Post || !is_string($post->post_content)) {
        return false;
    }

    $booking_shortcodes = array(
        'ichronoz_booking_page',
        'ichronoz_booking_multi',
        'ichronoz_booking',
    );

    foreach ($booking_shortcodes as $shortcode) {
        if (has_shortcode($post->post_content, $shortcode)) {
            return true;
        }
    }

    return false;
}

function ichronoz_rooms_carousel_shortcode()
{
    if (ichronoz_current_page_has_booking_shortcode()) {
        return '';
    }

    ichronoz_enqueue_scripts();
    do_action('ichronoz_rendered_rooms_carousel');

    return '<div class="ichronoz"><div data-ichronoz-mount="rooms-carousel"></div></div>';
}
add_shortcode('ichronoz_rooms_carousel', 'ichronoz_rooms_carousel_shortcode');
add_action('admin_init', 'ichronoz_register_settings');
add_action('admin_menu', 'ichronoz_add_settings_page');

// --- GitHub self-updater ---
function ichronoz_handle_github_update()
{
    // Configure your repository
    $owner = 'ichronoz-be';
    $repo  = 'ichronoz-booking-engine';

    if (!class_exists('Plugin_Upgrader')) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
    }

    // Helper to make GitHub requests
    $gh_get = function ($url) {
        return wp_remote_get($url, array(
            'headers' => array('Accept' => 'application/vnd.github+json', 'User-Agent' => 'WordPress; iChronoz-Updater'),
            'timeout' => 20,
        ));
    };

    // Fetch latest release metadata
    $api_url = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $owner, $repo);
    $response = $gh_get($api_url);
    if (is_wp_error($response)) return $response;
    $code = wp_remote_retrieve_response_code($response);

    $data = null;
    if ($code === 200) {
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!$data || !is_array($data)) return new WP_Error('github_json', 'Invalid GitHub API response');
    } elseif ($code === 404) {
        // Fallback: fetch latest tag and use its zipball
        $tags_url = sprintf('https://api.github.com/repos/%s/%s/tags', $owner, $repo);
        $tags_resp = $gh_get($tags_url);
        if (is_wp_error($tags_resp)) return $tags_resp;
        $tags_code = wp_remote_retrieve_response_code($tags_resp);
        if ($tags_code !== 200) return new WP_Error('github_http', 'GitHub API returned HTTP ' . $tags_code . ' for tags');
        $tags = json_decode(wp_remote_retrieve_body($tags_resp), true);
        if (!is_array($tags) || empty($tags)) return new WP_Error('no_tags', 'No tags found in repository');
        // Choose the first tag (GitHub returns in descending order)
        $first = $tags[0];
        $data = array(
            'tag_name' => isset($first['name']) ? $first['name'] : '',
            'zipball_url' => sprintf('https://api.github.com/repos/%s/%s/zipball/%s', $owner, $repo, isset($first['name']) ? $first['name'] : ''),
            'assets' => array(),
        );
    } else {
        return new WP_Error('github_http', 'GitHub API returned HTTP ' . $code);
    }

    // Prefer a .zip asset; fall back to zipball_url
    $zip_url = '';
    if (!empty($data['assets'])) {
        foreach ($data['assets'] as $asset) {
            if (isset($asset['browser_download_url']) && preg_match('/\.zip$/', $asset['browser_download_url'])) {
                $zip_url = $asset['browser_download_url'];
                break;
            }
        }
    }
    if (!$zip_url && isset($data['zipball_url'])) {
        $zip_url = $data['zipball_url'];
    }
    if (!$zip_url) return new WP_Error('no_zip', 'No downloadable ZIP found in the latest release');

    // Prepare WP filesystem
    $url = wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=ichronoz/ichronoz.php'), 'upgrade-plugin_ichronoz/ichronoz.php');
    $creds = request_filesystem_credentials($url);
    if (!WP_Filesystem($creds)) {
        return new WP_Error('fs_init', 'Could not initialize filesystem');
    }

    // Run upgrade
    $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
    // Force install from external package
    $result = $upgrader->install($zip_url);
    if (is_wp_error($result)) return $result;
    if (!$result) return new WP_Error('install_failed', 'Install failed');

    // Ensure plugin remains active if it was active
    $slug = 'ichronoz';
    $plugin_file = $slug . '/ichronoz.php';
    $is_active = is_plugin_active($plugin_file);

    // Normalize extracted folder name to the expected slug when coming from zipball/tag
    $installed = $upgrader->result;
    if (!empty($installed['destination']) && !empty($installed['destination_name'])) {
        $dest_dir = untrailingslashit($installed['destination']); // absolute path to extracted folder
        $dest_name = $installed['destination_name']; // folder name under plugins dir
        $expected_dir = WP_PLUGIN_DIR . '/' . $slug;

        // If the extracted folder isn't our slug, move/merge into expected folder
        if (basename($dest_dir) !== $slug) {
            global $wp_filesystem;
            if (!is_dir($expected_dir)) {
                // Simple rename when target doesn't exist
                $wp_filesystem->move($dest_dir, $expected_dir, true);
            } else {
                // Target exists: copy contents over then remove source
                $dirlist = $wp_filesystem->dirlist($dest_dir, false, true);
                if (is_array($dirlist)) {
                    foreach (array_keys($dirlist) as $entry) {
                        $wp_filesystem->move(trailingslashit($dest_dir) . $entry, trailingslashit($expected_dir) . $entry, true);
                    }
                }
                $wp_filesystem->delete($dest_dir, true);
            }
        }
    }

    // Re-activate if previously active
    if ($is_active && file_exists(WP_PLUGIN_DIR . '/' . $plugin_file)) {
        activate_plugin($plugin_file, '', false, true);
    }
    return true;
}

/**
 * Shortcode: [ichronoz_booking_page]
 * Renders booking list with single-room selection controls.
 */
function ichronoz_booking_page_shortcode()
{
    // Load assets only when booking shortcode is present
    ichronoz_enqueue_scripts();
    do_action('ichronoz_rendered_booking_page');
    return '<div class="ichronoz"><div data-ichronoz-mount="booking"></div></div>';
}
add_shortcode('ichronoz_booking_page', 'ichronoz_booking_page_shortcode');

/**
 * Shortcode: [ichronoz_booking_multi]
 * Renders booking list with multi-room selection controls.
 */
function ichronoz_booking_multi_shortcode()
{
    ichronoz_enqueue_scripts();
    do_action('ichronoz_rendered_booking_page');
    return '<div class="ichronoz"><div data-ichronoz-mount="booking" data-enable-multi-select="1"></div></div>';
}
add_shortcode('ichronoz_booking_multi', 'ichronoz_booking_multi_shortcode');

/**
 * Shortcode: [ichronoz_booking]
 * Renders ticket-specific booking flow.
 */
function ichronoz_booking_shortcode($atts = array())
{
    $atts = shortcode_atts(array(
        'type' => 'activity-date',
        'accType' => '',
    ), (array) $atts, 'ichronoz_booking');
    $acc_type = sanitize_text_field((string) ($atts['type'] !== '' ? $atts['type'] : $atts['accType']));
    if ($acc_type === '') $acc_type = 'activity-date';

    ichronoz_enqueue_scripts();
    do_action('ichronoz_rendered_ticket_booking_page');
    return '<div class="ichronoz"><div data-ichronoz-mount="ticket-booking" data-acc-type="' . esc_attr($acc_type) . '"></div></div>';
}
add_shortcode('ichronoz_booking', 'ichronoz_booking_shortcode');

/**
 * Shortcode: [ichronoz_search_button]
 * Renders a floating button at bottom-right that toggles the vertical search form.
 */
function ichronoz_search_button_shortcode()
{
    // Ensure required assets are loaded
    ichronoz_enqueue_scripts();

    ob_start();
?>
    <?php $fab_position = get_option('ichronoz_fab_position', 'bottom-right'); ?>
    <div class="ichronoz-fab-wrapper" data-fab-position="<?php echo esc_attr($fab_position); ?>">
        <button type="button" class="ichronoz-fab-button btn btn-sm" aria-expanded="false" aria-controls="ichronoz-fab-panel">
            <i class="fa fa-search" aria-hidden="true"></i>
            <span class="ichronoz-fab-text">Book Now</span>
        </button>
    </div>
    <div id="ichronoz-fab-panel" class="ichronoz-fab-panel" aria-hidden="true">
        <div class="ichronoz">
            <div data-ichronoz-mount="search"></div>
        </div>
    </div>
    <script>
        (function() {
            var btn = document.querySelector('.ichronoz-fab-button');
            var panel = document.getElementById('ichronoz-fab-panel');
            if (!btn || !panel) return;
            btn.addEventListener('click', function() {
                var isOpen = panel.classList.toggle('open');
                btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            });
        })();
    </script>
<?php
    return ob_get_clean();
}
add_shortcode('ichronoz_search_button', 'ichronoz_search_button_shortcode');

add_action('wp_footer', function () {
    // Show floating button only if no iChronoz search/booking/ticket shortcodes are present on the page
    if (is_admin()) return;

    $has_form = false;
    $has_booking = false;
    $has_ticket = false;

    // Check the global post content for shortcodes when available
    global $post;
    if ($post && isset($post->post_content)) {
        $content = $post->post_content;
        $has_form = has_shortcode($content, 'ichronoz_search_form');
        $has_booking = has_shortcode($content, 'ichronoz_booking_page') || has_shortcode($content, 'ichronoz_booking_multi');
        $has_ticket = has_shortcode($content, 'ichronoz_booking');
    }

    // Additionally, detect if our mount points already exist (rendered by other means)
    // Fallback: if another plugin/theme executed our shortcodes earlier via do_shortcode
    // we can rely on wp query var flag set during shortcode render.
    if (did_action('ichronoz_rendered_search_form')) $has_form = true;
    if (did_action('ichronoz_rendered_booking_page')) $has_booking = true;
    if (did_action('ichronoz_rendered_ticket_booking_page')) $has_ticket = true;

    if (!$has_form && !$has_booking && !$has_ticket) {
        echo do_shortcode('[ichronoz_search_button]');
    }
});
