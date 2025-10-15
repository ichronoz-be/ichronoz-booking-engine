<?php

/**
 * Plugin Name: iChronoz Booking Engine
 * Description: Intelegent hotel booking engine by iChronoz
 * Version: 3.0-beta.1
 * Author: iChronoz
 */

if (!defined('ABSPATH')) {
    exit;
}

function ichronoz_admin_enqueue_scripts($hook)
{
    // Ensure we enqueue on our plugin settings page whether it's under Settings or top-level menu
    $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    if ($page !== 'ichronoz') {
        return;
    }
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script(
        'ichronoz-color-picker-script',
        plugins_url('js/color-picker.js', __FILE__),
        array('wp-color-picker', 'jquery'),
        false,
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
    $btn_color = get_option('ichronoz_search_button_color', '#007BFF');
    $fab_position = get_option('ichronoz_fab_position', 'bottom-right'); // bottom-right, bottom-left, top-right, top-left
    // Compute positional CSS for wrapper and panel based on setting
    $pos_right = (strpos($fab_position, 'right') !== false);
    $pos_bottom = (strpos($fab_position, 'bottom') !== false);
    $wrapper_pos = ($pos_right ? 'right:16px;' : 'left:16px;') . ($pos_bottom ? 'bottom:16px;' : 'top:16px;');
    $panel_pos   = ($pos_right ? 'right:16px;' : 'left:16px;') . ($pos_bottom ? 'bottom:84px;' : 'top:84px;');
    $critical_css =
        '.ichronoz-fab-wrapper{position:fixed;' . $wrapper_pos . 'z-index:999999}' .
        '.ichronoz-fab-button{background:' . $btn_color . ' !important;color:#fff !important;width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 16px rgba(0,0,0,.25);cursor:pointer;border:none !important;outline:none !important}' .
        '.ichronoz-fab-button .fa{font-size:22px}' .
        '.ichronoz-fab-panel{position:fixed;' . $panel_pos . 'z-index:999999;background:#fff;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.18);padding:12px;width:min(92vw,360px);display:none}' .
        '.ichronoz-fab-panel.open{display:block}' .
        '.ichronoz-fab-panel .ichronoz{max-height:80vh;}';
    wp_add_inline_style('ichronoz-react-app-styles', $critical_css);

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
    $selected_day_color = get_option('ichronoz_selected_day_color', '#0071c2');
    $search_button_color = get_option('ichronoz_search_button_color', '#007BFF');
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

    wp_localize_script('ichronoz-react-app', 'ichronozSettings', array(
        'apiBase' => 'https://api.ichronoz.net',
        'layout' => $layout_option,
        'selectedDayColor' => $selected_day_color,
        'searchButtonColor' => $search_button_color,
        'roomHoverBgColor' => $room_hover_bg_color,
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
    ));

    $booking_data = array(
        'from' => isset($_GET['from']) ? esc_html($_GET['from']) : 'N/A',
        'to' => isset($_GET['to']) ? esc_html($_GET['to']) : 'N/A',
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
    add_option('ichronoz_search_button_color', '#007BFF');
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
    register_setting('ichronoz_options_group', 'ichronoz_form_layout');
    register_setting('ichronoz_options_group', 'ichronoz_selected_day_color');
    register_setting('ichronoz_options_group', 'ichronoz_search_button_color');
    register_setting('ichronoz_options_group', 'ichronoz_room_hover_bg_color');
    register_setting('ichronoz_options_group', 'ichronoz_secondary_color');
    register_setting('ichronoz_options_group', 'ichronoz_success_color');
    register_setting('ichronoz_options_group', 'ichronoz_warning_color');
    register_setting('ichronoz_options_group', 'ichronoz_link_color');
    register_setting('ichronoz_options_group', 'ichronoz_api_value');
    register_setting('ichronoz_options_group', 'ichronoz_booking_path');
    register_setting('ichronoz_options_group', 'ichronoz_loading_message');
    register_setting('ichronoz_options_group', 'ichronoz_spinner_url');
    register_setting('ichronoz_options_group', 'ichronoz_calendar_range_bg');
    register_setting('ichronoz_options_group', 'ichronoz_fab_position');
    // Register script injection settings (raw)
    register_setting('ichronoz_options_group', 'ichronoz_booking_script');
    register_setting('ichronoz_options_group', 'ichronoz_detail_script');
    register_setting('ichronoz_options_group', 'ichronoz_booking_script_enabled');
    register_setting('ichronoz_options_group', 'ichronoz_detail_script_enabled');
    register_setting('ichronoz_options_group', 'ichronoz_script_sanitization');
    register_setting('ichronoz_options_group', 'ichronoz_csp_nonce');
    register_setting('ichronoz_options_group', 'ichronoz_hid_enabled');
    register_setting('ichronoz_options_group', 'ichronoz_hid_options_json');
}

function ichronoz_settings_page()
{
?>
    <div class="wrap">
        <h1>iChronoz Settings</h1>
        <style>
            .ichz-help {
                position: relative;
                display: inline-flex;
                align-items: center;
                vertical-align: middle;
                margin-left: 6px;
                cursor: help
            }

            .ichz-help .dashicons {
                font-size: 18px;
                width: 18px;
                height: 18px;
                line-height: 18px
            }

            .ichz-help .ichz-tip {
                position: absolute;
                left: 50%;
                transform: translateX(-50%);
                bottom: 140%;
                white-space: normal;
                max-width: 420px;
                background: #222;
                color: #fff;
                padding: 10px 12px;
                border-radius: 6px;
                font-size: 12.5px;
                line-height: 1.5;
                box-shadow: 0 6px 20px rgba(0, 0, 0, .28);
                opacity: 0;
                pointer-events: none;
                transition: opacity .15s ease, transform .15s ease;
                z-index: 99999
            }

            .ichz-help:hover .ichz-tip {
                opacity: 1
            }

            .ichz-help .ichz-tip:after {
                content: "";
                position: absolute;
                top: 100%;
                left: 50%;
                transform: translateX(-50%);
                border-width: 7px;
                border-style: solid;
                border-color: #222 transparent transparent transparent
            }
        </style>

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

        $gh_get = function ($url) {
            return wp_remote_get($url, array(
                'headers' => array('Accept' => 'application/vnd.github+json', 'User-Agent' => 'WordPress; iChronoz-Updater'),
                'timeout' => 10,
            ));
        };

        // Try latest release
        $resp = $gh_get(sprintf('https://api.github.com/repos/%s/%s/releases/latest', $owner, $repo));
        if (!is_wp_error($resp)) {
            $code = wp_remote_retrieve_response_code($resp);
            if ($code === 200) {
                $data = json_decode(wp_remote_retrieve_body($resp), true);
                if (is_array($data) && !empty($data['tag_name'])) {
                    $latest_version = ltrim($data['tag_name'], 'v');
                }
            } elseif ($code === 404) {
                // Fallback to tags when no releases exist
                $tags_resp = $gh_get(sprintf('https://api.github.com/repos/%s/%s/tags', $owner, $repo));
                if (!is_wp_error($tags_resp) && wp_remote_retrieve_response_code($tags_resp) === 200) {
                    $tags = json_decode(wp_remote_retrieve_body($tags_resp), true);
                    if (is_array($tags) && !empty($tags) && !empty($tags[0]['name'])) {
                        $latest_version = ltrim($tags[0]['name'], 'v');
                    }
                }
            }
        }

        if ($latest_version) {
            $has_update = function_exists('version_compare')
                ? version_compare($current_version, $latest_version, '<')
                : ($current_version !== $latest_version);
        }

        if ($has_update): ?>
            <hr />
            <div class="notice wpforms-notice notice-info is-dismissible wpforms-review-notice">
                <h2>Improve your iChronoz Booking Engine Plugin</h2>
                <p>New version available: <strong><?php echo esc_html($latest_version); ?></strong> (installed: <strong><?php echo esc_html($current_version); ?></strong>)</p>
                <form method="post">
                    <?php wp_nonce_field('ichronoz_self_update'); ?>
                    <p class="description">✨ To enjoy the latest features and bug fixes, simply download and install the newest version..</p>
                    <input type="hidden" name="ichronoz_do_self_update" value="1" />
                    <?php submit_button('Update', 'secondary'); ?>
                </form>
            </div>
        <?php endif; ?>

        <?php $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general'; ?>
        <h2 class="nav-tab-wrapper">
            <a href="?page=ichronoz&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">General</a>
            <a href="?page=ichronoz&tab=ui" class="nav-tab <?php echo $active_tab === 'ui' ? 'nav-tab-active' : ''; ?>">UI Settings</a>
            <a href="?page=ichronoz&tab=scripts" class="nav-tab <?php echo $active_tab === 'scripts' ? 'nav-tab-active' : ''; ?>">Scripts</a>
        </h2>
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
        <form method="post" action="options.php">
            <?php
            settings_fields('ichronoz_options_group');
            do_settings_sections('ichronoz_options_group');
            ?>
            <table class="form-table">
                <?php if ($active_tab === 'general'): ?>
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
                    <tr valign="top">
                        <th scope="row">Is Multiproperty?
                            <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span>
                                <span class="ichz-tip">Enables property selection in the search form. Configure options below as JSON.</span>
                            </span>
                        </th>
                        <td>
                            <label style="display:inline-flex; align-items:center; gap:8px;">
                                <input type="checkbox" name="ichronoz_hid_enabled" value="1" <?php checked(get_option('ichronoz_hid_enabled', '0'), '1'); ?> />
                                <span>Show HID select in search form</span>
                            </label>
                            <p class="description">When enabled, users can select a property (HID) before choosing dates.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">HID Options (JSON)
                            <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span>
                                <span class="ichz-tip">Provide an array of {hid, name} objects. Example: <code>[{&quot;hid&quot;:&quot;123&quot;,&quot;name&quot;:&quot;Main Hotel&quot;},{&quot;hid&quot;:&quot;456&quot;,&quot;name&quot;:&quot;Beach Villas&quot;}]</code></span>
                            </span>
                        </th>
                        <td>
                            <textarea name="ichronoz_hid_options_json" rows="5" class="large-text code" placeholder='[{"hid":"123","name":"Main Hotel"},{"hid":"456","name":"Beach Villas"}]'><?php echo esc_textarea(get_option('ichronoz_hid_options_json', '[]')); ?></textarea>
                            <p class="description">Each entry must include both <code>hid</code> and <code>name</code>. Example: <code>[{&quot;hid&quot;:&quot;123&quot;,&quot;name&quot;:&quot;Main Hotel&quot;},{&quot;hid&quot;:&quot;456&quot;,&quot;name&quot;:&quot;Beach Villas&quot;}]</code></p>
                        </td>
                    </tr>
                <?php elseif ($active_tab === 'ui'): // UI Settings tab 
                ?>
                    <tr valign="top">
                        <th scope="row">Selected Day Color <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Accent color for selected days in the calendar.</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_selected_day_color" value="<?php echo esc_attr(get_option('ichronoz_selected_day_color')); ?>" class="color-picker" />
                            <p class="description">Default: <code>#0071c2</code>
                                <a href="#" class="button-link" onclick="event.preventDefault(); var i=this.parentNode.previousElementSibling; i.value='#0071c2'; if (jQuery && jQuery(i).wpColorPicker) jQuery(i).wpColorPicker('color', '#0071c2');">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Search Button Color <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Fill color of the floating search button.</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_search_button_color" value="<?php echo esc_attr(get_option('ichronoz_search_button_color')); ?>" class="color-picker" />
                            <p class="description">Default: <code>#007BFF</code>
                                <a href="#" class="button-link" onclick="event.preventDefault(); var i=this.parentNode.previousElementSibling; i.value='#007BFF'; if (jQuery && jQuery(i).wpColorPicker) jQuery(i).wpColorPicker('color', '#007BFF');">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Secondary Color <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Secondary UI accents (muted elements and borders).</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_secondary_color" value="<?php echo esc_attr(get_option('ichronoz_secondary_color', '#6c757d')); ?>" class="color-picker" />
                            <p class="description">Default: <code>#6c757d</code>
                                <a href="#" class="button-link" onclick="event.preventDefault(); var i=this.parentNode.previousElementSibling; i.value='#6c757d'; if (jQuery && jQuery(i).wpColorPicker) jQuery(i).wpColorPicker('color', '#6c757d');">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Success Color <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Positive states (e.g., success messages).</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_success_color" value="<?php echo esc_attr(get_option('ichronoz_success_color', '#198754')); ?>" class="color-picker" />
                            <p class="description">Default: <code>#198754</code>
                                <a href="#" class="button-link" onclick="event.preventDefault(); var i=this.parentNode.previousElementSibling; i.value='#198754'; if (jQuery && jQuery(i).wpColorPicker) jQuery(i).wpColorPicker('color', '#198754');">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Warning Color <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Warnings and non-blocking alerts.</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_warning_color" value="<?php echo esc_attr(get_option('ichronoz_warning_color', '#ffc107')); ?>" class="color-picker" />
                            <p class="description">Default: <code>#ffc107</code>
                                <a href="#" class="button-link" onclick="event.preventDefault(); var i=this.parentNode.previousElementSibling; i.value='#ffc107'; if (jQuery && jQuery(i).wpColorPicker) jQuery(i).wpColorPicker('color', '#ffc107');">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Link Color <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Overrides link color. Leave empty to inherit theme.</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_link_color" value="<?php echo esc_attr(get_option('ichronoz_link_color', '')); ?>" class="color-picker" />
                            <p class="description">Default: inherit theme (empty)
                                <a href="#" class="button-link" onclick="event.preventDefault(); var i=this.parentNode.previousElementSibling; i.value=''; if (jQuery && jQuery(i).wpColorPicker) jQuery(i).wpColorPicker('color', '');">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Room Hover Background Color <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Background color when hovering room cards.</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_room_hover_bg_color" value="<?php echo esc_attr(get_option('ichronoz_room_hover_bg_color', '#e6e6e6')); ?>" class="color-picker" />
                            <p class="description">Default: <code>#e6e6e6</code>
                                <a href="#" class="button-link" onclick="event.preventDefault(); var i=this.parentNode.previousElementSibling; i.value='#e6e6e6'; if (jQuery && jQuery(i).wpColorPicker) jQuery(i).wpColorPicker('color', '#e6e6e6');">Reset</a>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Calendar Range Background <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span><span class="ichz-tip">Background for the selected date range.</span></span></th>
                        <td>
                            <input type="text" name="ichronoz_calendar_range_bg" value="<?php echo esc_attr(get_option('ichronoz_calendar_range_bg', '#e3f2ff')); ?>" class="color-picker" />
                            <p class="description">Background color for the selected date range in calendar fallback. Default: <code>#e3f2ff</code>
                                <a href="#" class="button-link" onclick="event.preventDefault(); var i=this.parentNode.previousElementSibling; i.value='#e3f2ff'; if (jQuery && jQuery(i).wpColorPicker) jQuery(i).wpColorPicker('color', '#e3f2ff');">Reset</a>
                            </p>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
            <?php if ($active_tab === 'ui'): ?>
                <p>
                    <button type="button" class="button" id="ichz-reset-all-ui-colors">Reset All UI Colors</button>
                </p>
                <script>
                    (function() {
                        document.addEventListener('DOMContentLoaded', function() {
                            var btn = document.getElementById('ichz-reset-all-ui-colors');
                            if (!btn) return;
                            btn.addEventListener('click', function() {
                                if (!confirm('Reset all UI colors to defaults?')) return;
                                var defaults = {
                                    ichronoz_selected_day_color: '#0071c2',
                                    ichronoz_search_button_color: '#007BFF',
                                    ichronoz_secondary_color: '#6c757d',
                                    ichronoz_success_color: '#198754',
                                    ichronoz_warning_color: '#ffc107',
                                    ichronoz_link_color: '',
                                    ichronoz_room_hover_bg_color: '#e6e6e6',
                                    ichronoz_calendar_range_bg: '#e3f2ff'
                                };
                                Object.keys(defaults).forEach(function(key) {
                                    var inp = document.querySelector('input[name="' + key + '"]');
                                    if (!inp) return;
                                    inp.value = defaults[key];
                                    try {
                                        if (window.jQuery && jQuery(inp).wpColorPicker) jQuery(inp).wpColorPicker('color', defaults[key]);
                                    } catch (e) {}
                                });
                            });
                        });
                    })();
                </script>
            <?php elseif ($active_tab === 'scripts'): // Scripts injection tab 
            ?>
                <tr valign="top">
                    <th scope="row">Booking Page Script
                        <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span>
                            <span class="ichz-tip">Runs on the booking list page. Paste raw JS or a &lt;script&gt;...&lt;/script&gt; snippet. Admins only.</span>
                        </span>
                    </th>
                    <td>
                        <label style="display:inline-flex; align-items:center; gap:8px; margin-bottom:6px;">
                            <input type="checkbox" name="ichronoz_booking_script_enabled" value="1" <?php checked(get_option('ichronoz_booking_script_enabled', '0'), '1'); ?> />
                            <span>Enable booking page script</span>
                        </label>
                        <textarea name="ichronoz_booking_script" rows="8" class="large-text code"><?php echo esc_textarea(get_option('ichronoz_booking_script', '')); ?></textarea>
                        <p class="description">This code will be injected when the booking page visited. Only paste code from trusted sources. Inline scripts run with your site's privileges.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Detail Page Script
                        <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span>
                            <span class="ichz-tip">Runs on the room detail page. Paste raw JS or a &lt;script&gt;...&lt;/script&gt; snippet. Admins only.</span>
                        </span>
                    </th>
                    <td>
                        <label style="display:inline-flex; align-items:center; gap:8px; margin-bottom:6px;">
                            <input type="checkbox" name="ichronoz_detail_script_enabled" value="1" <?php checked(get_option('ichronoz_detail_script_enabled', '0'), '1'); ?> />
                            <span>Enable detail page script</span>
                        </label>
                        <textarea name="ichronoz_detail_script" rows="8" class="large-text code"><?php echo esc_textarea(get_option('ichronoz_detail_script', '')); ?></textarea>
                        <p class="description">This code will be injected when the room detail visited. Only paste code from trusted sources. Inline scripts run with your site's privileges.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Sanitization Mode
                        <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span>
                            <span class="ichz-tip">Choose how to sanitize the script before exposing to the frontend. Raw allows full code. Strip tags removes &lt;script&gt; wrappers. KSES strips most HTML (JS likely removed).</span>
                        </span>
                    </th>
                    <td>
                        <?php $san_mode = get_option('ichronoz_script_sanitization', 'raw'); ?>
                        <select name="ichronoz_script_sanitization">
                            <option value="raw" <?php selected($san_mode, 'raw'); ?>>Raw (no filtering)</option>
                            <option value="strip_tags" <?php selected($san_mode, 'strip_tags'); ?>>Strip &lt;script&gt; tags</option>
                            <option value="kses" <?php selected($san_mode, 'kses'); ?>>KSES (limited HTML)</option>
                        </select>
                        <p class="description">If unsure, choose <strong>Raw</strong> for analytics or custom JS snippets you trust.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">CSP Nonce (optional)
                        <span class="ichz-help" aria-label="Help"><span class="dashicons dashicons-editor-help"></span>
                            <span class="ichz-tip">If your site enforces a Content Security Policy with nonces, provide the nonce value here. It will be set on injected &lt;script&gt; tags.</span>
                        </span>
                    </th>
                    <td>
                        <input type="text" name="ichronoz_csp_nonce" value="<?php echo esc_attr(get_option('ichronoz_csp_nonce', '')); ?>" class="regular-text" />
                        <p class="description">Leave empty if you don't use CSP nonces.</p>
                    </td>
                </tr>
            <?php endif; ?>
            <?php submit_button(); ?>
        </form>

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

function ichronoz_booking_page_shortcode()
{
    // Load assets only when booking shortcode is present
    ichronoz_enqueue_scripts();
    do_action('ichronoz_rendered_booking_page');
    return '<div class="ichronoz"><div data-ichronoz-mount="booking"></div></div>';
}
add_shortcode('ichronoz_booking_page', 'ichronoz_booking_page_shortcode');

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
        <button type="button" class="ichronoz-fab-button" aria-expanded="false" aria-controls="ichronoz-fab-panel">
            <i class="fa fa-search"></i>
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
    // Show floating button only if no iChronoz search/booking shortcodes are present on the page
    if (is_admin()) return;

    $has_form = false;
    $has_booking = false;

    // Check the global post content for shortcodes when available
    global $post;
    if ($post && isset($post->post_content)) {
        $content = $post->post_content;
        $has_form = has_shortcode($content, 'ichronoz_search_form');
        $has_booking = has_shortcode($content, 'ichronoz_booking_page');
    }

    // Additionally, detect if our mount points already exist (rendered by other means)
    // Fallback: if another plugin/theme executed our shortcodes earlier via do_shortcode
    // we can rely on wp query var flag set during shortcode render.
    if (did_action('ichronoz_rendered_search_form')) $has_form = true;
    if (did_action('ichronoz_rendered_booking_page')) $has_booking = true;

    if (!$has_form && !$has_booking) {
        echo do_shortcode('[ichronoz_search_button]');
    }
});
