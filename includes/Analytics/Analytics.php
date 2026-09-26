<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/GtmContainer.php';

const ICHRONOZ_ANALYTICS_SCHEMA_VERSION = '1.0.0';

/**
 * Event names accepted by the internal collector.
 *
 * @return array<int, string>
 */
function ichronoz_analytics_allowed_events()
{
    return array(
        'availability_searched',
        'room_selected',
        'checkout_started',
        'price_summary_opened',
        'booking_cta_clicked',
        'booking_submit_started',
        'booking_completed',
        'booking_submit_failed',
    );
}

/**
 * Return the prefixed analytics aggregate table name.
 *
 * @return string
 */
function ichronoz_analytics_table_name()
{
    global $wpdb;

    return $wpdb->prefix . 'ichronoz_analytics_daily';
}

/**
 * Create or update the privacy-conscious daily aggregate table.
 *
 * No visitor identifiers or personal booking data are stored.
 *
 * @return void
 */
function ichronoz_analytics_install_schema()
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table_name      = ichronoz_analytics_table_name();
    $charset_collate = $wpdb->get_charset_collate();
    $sql             = "CREATE TABLE {$table_name} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_date date NOT NULL,
        event_name varchar(64) NOT NULL,
        currency varchar(3) NOT NULL DEFAULT '',
        event_count bigint(20) unsigned NOT NULL DEFAULT 0,
        value_total decimal(20,2) NOT NULL DEFAULT 0,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY event_day_currency (event_date,event_name,currency),
        KEY event_name (event_name),
        KEY event_date (event_date)
    ) {$charset_collate};";

    dbDelta($sql);
    update_option('ichronoz_analytics_schema_version', ICHRONOZ_ANALYTICS_SCHEMA_VERSION, false);
}

/**
 * Run schema upgrades once after a plugin update.
 *
 * @return void
 */
function ichronoz_analytics_maybe_upgrade()
{
    if (get_option('ichronoz_analytics_schema_version') !== ICHRONOZ_ANALYTICS_SCHEMA_VERSION) {
        ichronoz_analytics_install_schema();
    }
}
add_action('plugins_loaded', 'ichronoz_analytics_maybe_upgrade');

/**
 * Normalize checkbox settings to the values used by WordPress options.
 *
 * @param mixed $value Submitted value.
 * @return string
 */
function ichronoz_analytics_sanitize_checkbox($value)
{
    return (string) $value === '1' ? '1' : '0';
}

/**
 * Sanitize the configurable dataLayer event name.
 *
 * @param mixed $value Submitted value.
 * @return string
 */
function ichronoz_analytics_sanitize_datalayer_event($value)
{
    $value = preg_replace('/[^A-Za-z0-9_.-]/', '_', (string) $value);

    return $value !== '' ? substr($value, 0, 80) : 'ichronoz_booking_funnel';
}

/**
 * Sanitize how the GTM container is installed.
 *
 * @param mixed $value Submitted value.
 * @return string
 */
function ichronoz_analytics_sanitize_gtm_install_mode($value)
{
    return (string) $value === 'ichronoz' ? 'ichronoz' : 'existing';
}

/**
 * Sanitize a Google Tag Manager container ID.
 *
 * @param mixed $value Submitted value.
 * @return string
 */
function ichronoz_analytics_sanitize_gtm_container_id($value)
{
    $value = strtoupper(trim(sanitize_text_field((string) $value)));

    if ($value === '') {
        return '';
    }

    if (!preg_match('/^GTM-[A-Z0-9]+$/', $value)) {
        add_settings_error(
            'ichronoz_analytics_gtm_container_id',
            'ichronoz_analytics_invalid_gtm_id',
            'The GTM Container ID must use the format GTM-XXXXXXX.',
            'error'
        );

        return '';
    }

    return $value;
}

/**
 * Sanitize the pages where the iChronoz GTM loader is rendered.
 *
 * @param mixed $value Submitted value.
 * @return string
 */
function ichronoz_analytics_sanitize_gtm_scope($value)
{
    return (string) $value === 'all' ? 'all' : 'ichronoz';
}

/**
 * Detect likely legacy GTM snippets in the existing Custom Code settings.
 *
 * @return bool
 */
function ichronoz_analytics_custom_code_has_gtm()
{
    $custom_code = (string) get_option('ichronoz_booking_script', '') . "\n" . (string) get_option('ichronoz_detail_script', '');

    return (bool) preg_match('/googletagmanager\.com\/gtm\.js|\bGTM-[A-Z0-9]+\b|\bdataLayer\b/i', $custom_code);
}

/**
 * Register analytics settings independently from other plugin tabs.
 *
 * @return void
 */
function ichronoz_analytics_register_settings()
{
    add_option('ichronoz_analytics_internal_enabled', '0');
    add_option('ichronoz_analytics_gtm_enabled', '0');
    add_option('ichronoz_analytics_track_value', '1');
    add_option('ichronoz_analytics_datalayer_event', 'ichronoz_booking_funnel');
    add_option('ichronoz_analytics_gtm_install_mode', 'existing');
    add_option('ichronoz_analytics_gtm_container_id', '');
    add_option('ichronoz_analytics_gtm_scope', 'ichronoz');

    register_setting(
        'ichronoz_analytics_group',
        'ichronoz_analytics_internal_enabled',
        array('sanitize_callback' => 'ichronoz_analytics_sanitize_checkbox')
    );
    register_setting(
        'ichronoz_analytics_group',
        'ichronoz_analytics_gtm_enabled',
        array('sanitize_callback' => 'ichronoz_analytics_sanitize_checkbox')
    );
    register_setting(
        'ichronoz_analytics_group',
        'ichronoz_analytics_track_value',
        array('sanitize_callback' => 'ichronoz_analytics_sanitize_checkbox')
    );
    register_setting(
        'ichronoz_analytics_group',
        'ichronoz_analytics_datalayer_event',
        array('sanitize_callback' => 'ichronoz_analytics_sanitize_datalayer_event')
    );
    register_setting(
        'ichronoz_analytics_group',
        'ichronoz_analytics_gtm_install_mode',
        array('sanitize_callback' => 'ichronoz_analytics_sanitize_gtm_install_mode')
    );
    register_setting(
        'ichronoz_analytics_group',
        'ichronoz_analytics_gtm_container_id',
        array('sanitize_callback' => 'ichronoz_analytics_sanitize_gtm_container_id')
    );
    register_setting(
        'ichronoz_analytics_group',
        'ichronoz_analytics_gtm_scope',
        array('sanitize_callback' => 'ichronoz_analytics_sanitize_gtm_scope')
    );
}
add_action('admin_init', 'ichronoz_analytics_register_settings');

/**
 * Runtime configuration exposed through the existing localized settings object.
 *
 * @return array<string, mixed>
 */
function ichronoz_analytics_runtime_config()
{
    return array(
        'internalEnabled' => get_option('ichronoz_analytics_internal_enabled', '0') === '1',
        'gtmEnabled'      => get_option('ichronoz_analytics_gtm_enabled', '0') === '1',
        'trackValue'      => get_option('ichronoz_analytics_track_value', '1') === '1',
        'dataLayerEvent'  => ichronoz_analytics_sanitize_datalayer_event(
            get_option('ichronoz_analytics_datalayer_event', 'ichronoz_booking_funnel')
        ),
        'endpoint'        => esc_url_raw(rest_url('ichronoz/v1/analytics/events')),
    );
}

/**
 * Limit anonymous analytics requests without persisting the visitor IP.
 *
 * @return true|WP_Error
 */
function ichronoz_analytics_check_rate_limit()
{
    $remote_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $key            = 'ichz_an_' . md5(wp_salt('nonce') . '|' . $remote_address);
    $requests       = (int) get_transient($key);

    if ($requests >= 120) {
        return new WP_Error(
            'ichronoz_analytics_rate_limited',
            'Too many analytics requests.',
            array('status' => 429)
        );
    }

    set_transient($key, $requests + 1, MINUTE_IN_SECONDS);

    return true;
}

/**
 * Reject browser requests from a different origin when an Origin header exists.
 *
 * @return true|WP_Error
 */
function ichronoz_analytics_check_origin()
{
    if (empty($_SERVER['HTTP_ORIGIN'])) {
        return true;
    }

    $origin_host = wp_parse_url(sanitize_text_field(wp_unslash($_SERVER['HTTP_ORIGIN'])), PHP_URL_HOST);
    $site_host   = wp_parse_url(home_url('/'), PHP_URL_HOST);

    if (!$origin_host || !$site_host || strtolower($origin_host) !== strtolower($site_host)) {
        return new WP_Error(
            'ichronoz_analytics_invalid_origin',
            'Analytics requests must originate from this site.',
            array('status' => 403)
        );
    }

    return true;
}

/**
 * Permission callback for the anonymous booking analytics endpoint.
 *
 * @return true|WP_Error
 */
function ichronoz_analytics_rest_permission()
{
    if (get_option('ichronoz_analytics_internal_enabled', '0') !== '1') {
        return new WP_Error(
            'ichronoz_analytics_disabled',
            'Internal analytics is disabled.',
            array('status' => 403)
        );
    }

    $origin_check = ichronoz_analytics_check_origin();
    if (is_wp_error($origin_check)) {
        return $origin_check;
    }

    return ichronoz_analytics_check_rate_limit();
}

/**
 * Store one event as an atomic daily aggregate increment.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response|WP_Error
 */
function ichronoz_analytics_collect_event(WP_REST_Request $request)
{
    global $wpdb;

    $payload    = $request->get_json_params();
    $event_name = isset($payload['event']) ? sanitize_key($payload['event']) : '';

    if (!in_array($event_name, ichronoz_analytics_allowed_events(), true)) {
        return new WP_Error(
            'ichronoz_analytics_invalid_event',
            'Unsupported analytics event.',
            array('status' => 400)
        );
    }

    $currency = isset($payload['currency']) ? strtoupper(sanitize_text_field($payload['currency'])) : '';
    if ($currency !== '' && !preg_match('/^[A-Z]{3}$/', $currency)) {
        $currency = '';
    }

    $track_value = get_option('ichronoz_analytics_track_value', '1') === '1';
    $value       = $track_value && isset($payload['value']) ? (float) $payload['value'] : 0;
    $value       = max(0, min($value, 999999999999999999.99));
    $table_name  = ichronoz_analytics_table_name();
    $now         = current_time('mysql');
    $event_date  = current_time('Y-m-d');

    $sql = $wpdb->prepare(
        "INSERT INTO {$table_name} (event_date, event_name, currency, event_count, value_total, updated_at)
        VALUES (%s, %s, %s, 1, %f, %s)
        ON DUPLICATE KEY UPDATE
            event_count = event_count + 1,
            value_total = value_total + VALUES(value_total),
            updated_at = VALUES(updated_at)",
        $event_date,
        $event_name,
        $currency,
        $value,
        $now
    );

    if (false === $wpdb->query($sql)) {
        return new WP_Error(
            'ichronoz_analytics_storage_failed',
            'The analytics event could not be stored.',
            array('status' => 500)
        );
    }

    return new WP_REST_Response(null, 204);
}

/**
 * Register the internal analytics collector endpoint.
 *
 * @return void
 */
function ichronoz_analytics_register_rest_routes()
{
    register_rest_route(
        'ichronoz/v1',
        '/analytics/events',
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'ichronoz_analytics_collect_event',
            'permission_callback' => 'ichronoz_analytics_rest_permission',
        )
    );
}
add_action('rest_api_init', 'ichronoz_analytics_register_rest_routes');

/**
 * Clamp and normalize an analytics dashboard date.
 *
 * @param mixed  $value    Candidate date.
 * @param string $fallback Fallback Y-m-d value.
 * @return string
 */
function ichronoz_analytics_sanitize_date($value, $fallback)
{
    $value = sanitize_text_field((string) $value);
    $date  = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());

    return $date && $date->format('Y-m-d') === $value ? $value : $fallback;
}

/**
 * Read dashboard totals for the selected date range.
 *
 * @param string $date_from Inclusive start date.
 * @param string $date_to   Inclusive end date.
 * @return array<string, mixed>
 */
function ichronoz_analytics_get_dashboard_data($date_from, $date_to)
{
    global $wpdb;

    $table_name = ichronoz_analytics_table_name();
    $rows       = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT event_name, SUM(event_count) AS event_count
            FROM {$table_name}
            WHERE event_date BETWEEN %s AND %s
            GROUP BY event_name",
            $date_from,
            $date_to
        ),
        ARRAY_A
    );
    $values = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT currency, SUM(value_total) AS value_total
            FROM {$table_name}
            WHERE event_date BETWEEN %s AND %s
                AND event_name = 'booking_completed'
                AND currency <> ''
            GROUP BY currency
            ORDER BY currency ASC",
            $date_from,
            $date_to
        ),
        ARRAY_A
    );

    $counts = array_fill_keys(ichronoz_analytics_allowed_events(), 0);
    foreach ((array) $rows as $row) {
        if (isset($counts[$row['event_name']])) {
            $counts[$row['event_name']] = (int) $row['event_count'];
        }
    }

    return array(
        'counts' => $counts,
        'values' => is_array($values) ? $values : array(),
    );
}

/**
 * Render the booking funnel above the settings navigation.
 *
 * @return void
 */
function ichronoz_analytics_render_admin_dashboard()
{
    $today         = current_datetime();
    $today_default = $today->format('Y-m-d');
    $from_default  = $today->modify('-29 days')->format('Y-m-d');
    $date_from     = ichronoz_analytics_sanitize_date(isset($_GET['analytics_from']) ? wp_unslash($_GET['analytics_from']) : '', $from_default);
    $date_to       = ichronoz_analytics_sanitize_date(isset($_GET['analytics_to']) ? wp_unslash($_GET['analytics_to']) : '', $today_default);

    if ($date_from > $date_to) {
        $date_from = $date_to;
    }

    $data   = ichronoz_analytics_get_dashboard_data($date_from, $date_to);
    $counts = $data['counts'];
    $steps  = array(
        'availability_searched' => 'Availability searches',
        'room_selected'         => 'Rooms selected',
        'checkout_started'      => 'Checkouts started',
        'booking_submit_started'=> 'Booking submissions',
        'booking_completed'     => 'Bookings created',
    );
    $searches        = max(0, (int) $counts['availability_searched']);
    $completed       = max(0, (int) $counts['booking_completed']);
    $conversion_rate = $searches > 0 ? ($completed / $searches) * 100 : 0;
    ?>
    <style>
        .ichz-analytics-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:20px 0}
        .ichz-analytics-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px}
        .ichz-analytics-card__label{color:#646970;font-size:13px;margin:0 0 8px}
        .ichz-analytics-card__value{font-size:28px;font-weight:700;line-height:1.1;margin:0}
        .ichz-analytics-panel{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px;margin-top:18px}
        .ichz-analytics-funnel{max-width:760px;margin-top:16px}
        .ichz-analytics-funnel__row{display:grid;grid-template-columns:minmax(180px,1fr) 3fr 80px;align-items:center;gap:12px;margin:10px 0}
        .ichz-analytics-funnel__track{height:14px;background:#f0f0f1;border-radius:999px;overflow:hidden}
        .ichz-analytics-funnel__bar{height:100%;min-width:0;background:#2271b1;border-radius:999px}
        @media(max-width:782px){.ichz-analytics-funnel__row{grid-template-columns:1fr 70px}.ichz-analytics-funnel__track{grid-column:1/-1}}
    </style>

    <div class="ichz-analytics-panel">
        <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;">
            <div>
                <h2 style="margin:0 0 6px;">Booking funnel</h2>
                <p class="description">Interaction counts for the selected period. Data collection starts after Internal Analytics is enabled.</p>
            </div>
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap;">
                <input type="hidden" name="page" value="ichronoz" />
                <input type="hidden" name="tab" value="analytics" />
                <label>From<br /><input type="date" name="analytics_from" value="<?php echo esc_attr($date_from); ?>" /></label>
                <label>To<br /><input type="date" name="analytics_to" value="<?php echo esc_attr($date_to); ?>" /></label>
                <button type="submit" class="button">Apply</button>
            </form>
        </div>

        <div class="ichz-analytics-grid">
            <div class="ichz-analytics-card"><p class="ichz-analytics-card__label">Availability searches</p><p class="ichz-analytics-card__value"><?php echo esc_html(number_format_i18n($searches)); ?></p></div>
            <div class="ichz-analytics-card"><p class="ichz-analytics-card__label">Bookings created</p><p class="ichz-analytics-card__value"><?php echo esc_html(number_format_i18n($completed)); ?></p></div>
            <div class="ichz-analytics-card"><p class="ichz-analytics-card__label">Search-to-booking conversion</p><p class="ichz-analytics-card__value"><?php echo esc_html(number_format_i18n($conversion_rate, 1)); ?>%</p></div>
            <div class="ichz-analytics-card"><p class="ichz-analytics-card__label">Failed submissions</p><p class="ichz-analytics-card__value"><?php echo esc_html(number_format_i18n((int) $counts['booking_submit_failed'])); ?></p></div>
        </div>

        <div class="ichz-analytics-funnel">
            <?php foreach ($steps as $event_name => $label): ?>
                <?php
                $count = max(0, (int) $counts[$event_name]);
                $width = $searches > 0 ? min(100, ($count / $searches) * 100) : 0;
                ?>
                <div class="ichz-analytics-funnel__row">
                    <strong><?php echo esc_html($label); ?></strong>
                    <div class="ichz-analytics-funnel__track" aria-hidden="true"><div class="ichz-analytics-funnel__bar" style="width:<?php echo esc_attr(number_format($width, 2, '.', '')); ?>%;"></div></div>
                    <span style="text-align:right;"><?php echo esc_html(number_format_i18n($count)); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($data['values'])): ?>
            <h3>Booking value by currency</h3>
            <ul>
                <?php foreach ($data['values'] as $row): ?>
                    <li><strong><?php echo esc_html($row['currency']); ?></strong> <?php echo esc_html(number_format_i18n((float) $row['value_total'], 2)); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render the Analytics provider settings below the settings navigation.
 *
 * @return void
 */
function ichronoz_analytics_render_admin_settings()
{
    $gtm_install_mode   = get_option('ichronoz_analytics_gtm_install_mode', 'existing');
    $custom_code_has_gtm = ichronoz_analytics_custom_code_has_gtm();
    ?>
    <div class="ichz-analytics-panel">
        <h2 style="margin-top:0;">Analytics configuration</h2>
        <p>Internal analytics and GTM forwarding operate independently. No guest names, email addresses, phone numbers, IP addresses, or booking payloads are stored.</p>
        <?php if ($custom_code_has_gtm): ?>
            <div class="notice notice-warning inline">
                <p><strong>Possible duplicate GTM installation:</strong> GTM-related code was detected under Custom Code. Remove the legacy snippet before selecting “Install through iChronoz”. iChronoz will not remove user code automatically.</p>
            </div>
        <?php endif; ?>
        <form method="post" action="options.php" class="ichz-settings-form">
            <?php settings_fields('ichronoz_analytics_group'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Internal analytics</th>
                    <td>
                        <input type="hidden" name="ichronoz_analytics_internal_enabled" value="0" />
                        <label><input type="checkbox" name="ichronoz_analytics_internal_enabled" value="1" <?php checked(get_option('ichronoz_analytics_internal_enabled', '0'), '1'); ?> /> Enable the internal WordPress dashboard</label>
                        <p class="description">Stores daily aggregate counts in a dedicated database table.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Google Tag Manager</th>
                    <td>
                        <input type="hidden" name="ichronoz_analytics_gtm_enabled" value="0" />
                        <label><input type="checkbox" name="ichronoz_analytics_gtm_enabled" value="1" <?php checked(get_option('ichronoz_analytics_gtm_enabled', '0'), '1'); ?> /> Forward booking events to <code>window.dataLayer</code></label>
                        <p class="description">Controls booking-event forwarding independently from how the GTM container is installed.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ichronoz_analytics_gtm_install_mode">GTM installation</label></th>
                    <td>
                        <select id="ichronoz_analytics_gtm_install_mode" name="ichronoz_analytics_gtm_install_mode">
                            <option value="existing" <?php selected($gtm_install_mode, 'existing'); ?>>GTM already installed by theme or another plugin</option>
                            <option value="ichronoz" <?php selected($gtm_install_mode, 'ichronoz'); ?>>Install GTM through iChronoz</option>
                        </select>
                        <p class="description">Select only one installation source to prevent duplicate page views and conversions.</p>
                    </td>
                </tr>
                <tr class="ichz-gtm-install-row">
                    <th scope="row"><label for="ichronoz_analytics_gtm_container_id">GTM Container ID</label></th>
                    <td>
                        <input id="ichronoz_analytics_gtm_container_id" name="ichronoz_analytics_gtm_container_id" type="text" class="regular-text" placeholder="GTM-XXXXXXX" pattern="GTM-[A-Za-z0-9]+" value="<?php echo esc_attr(get_option('ichronoz_analytics_gtm_container_id', '')); ?>" />
                        <p class="description">Only a validated Container ID is accepted; raw GTM JavaScript is not stored here.</p>
                    </td>
                </tr>
                <tr class="ichz-gtm-install-row">
                    <th scope="row"><label for="ichronoz_analytics_gtm_scope">GTM page scope</label></th>
                    <td>
                        <?php $gtm_scope = get_option('ichronoz_analytics_gtm_scope', 'ichronoz'); ?>
                        <select id="ichronoz_analytics_gtm_scope" name="ichronoz_analytics_gtm_scope">
                            <option value="ichronoz" <?php selected($gtm_scope, 'ichronoz'); ?>>Only pages containing an iChronoz shortcode</option>
                            <option value="all" <?php selected($gtm_scope, 'all'); ?>>All frontend pages</option>
                        </select>
                        <p class="description">Use all frontend pages only when this container is the website’s primary GTM installation.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ichronoz_analytics_datalayer_event">dataLayer event name</label></th>
                    <td>
                        <input id="ichronoz_analytics_datalayer_event" name="ichronoz_analytics_datalayer_event" type="text" class="regular-text" value="<?php echo esc_attr(get_option('ichronoz_analytics_datalayer_event', 'ichronoz_booking_funnel')); ?>" />
                        <p class="description">Use this name as the Custom Event trigger in Google Tag Manager.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Booking value</th>
                    <td>
                        <input type="hidden" name="ichronoz_analytics_track_value" value="0" />
                        <label><input type="checkbox" name="ichronoz_analytics_track_value" value="1" <?php checked(get_option('ichronoz_analytics_track_value', '1'), '1'); ?> /> Include booking currency and total value</label>
                        <p class="description">Browser totals are useful for analytics but are not authoritative accounting records.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Analytics Settings'); ?>
        </form>
        <script>
            (function() {
                var mode = document.getElementById('ichronoz_analytics_gtm_install_mode');
                var rows = document.querySelectorAll('.ichz-gtm-install-row');
                var containerId = document.getElementById('ichronoz_analytics_gtm_container_id');
                if (!mode || !rows.length) return;

                function syncGtmInstallFields() {
                    var installsThroughIchronoz = mode.value === 'ichronoz';
                    rows.forEach(function(row) {
                        row.style.display = installsThroughIchronoz ? '' : 'none';
                    });
                    if (containerId) {
                        containerId.required = installsThroughIchronoz;
                    }
                }

                mode.addEventListener('change', syncGtmInstallFields);
                syncGtmInstallFields();
            })();
        </script>
    </div>

    <?php
}
