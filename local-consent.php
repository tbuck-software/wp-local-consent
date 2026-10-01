<?php
/**
 * Plugin Name: Local Consent
 * Description: Lokale Einwilligungen und serverseitige Sperren für externe Inhalte und Google-Tags. Ohne Account oder externe Abhängigkeiten.
 * Version: 0.1.5
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: local-consent
 * Update URI: false
 */

namespace LocalConsent;

if (!defined('ABSPATH')) {
    exit;
}

const VERSION = '0.1.5';
const OPTION = 'local_consent_settings';

require_once __DIR__ . '/includes/design.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/html.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/view.php';

add_action('admin_menu', __NAMESPACE__ . '\\admin_menu');
add_action('admin_init', __NAMESPACE__ . '\\register_settings');
add_action('admin_post_local_consent_export_design', __NAMESPACE__ . '\\export_design');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=local-consent')) . '">Einstellungen</a>');
    return $links;
});
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'plugins_page_local-consent') {
        wp_enqueue_style('local-consent-admin', plugins_url('assets/admin.css', __FILE__), array(), VERSION);
        wp_enqueue_script('local-consent-admin', plugins_url('assets/admin.js', __FILE__), array(), VERSION, true);
        wp_enqueue_script('local-consent-admin-design', plugins_url('assets/admin-design.js', __FILE__), array('local-consent-admin'), VERSION, true);
    }
});

// Start before template output. A single complete buffer prevents scripts split
// across PHP writes from escaping the HTML processor.
add_action('template_redirect', function () {
    $editing = current_user_can('edit_posts') && (
        is_preview() || is_customize_preview()
        || (function_exists('et_core_is_fb_enabled') && et_core_is_fb_enabled())
    );
    if (is_feed() || is_trackback() || is_robots() || is_favicon()
        || $editing || wp_doing_ajax()
        || (defined('REST_REQUEST') && REST_REQUEST)
    ) {
        return;
    }
    ob_start(function ($html) {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'text/html') === false) {
                return $html;
            }
        }
        return rewrite_html($html, settings(), services());
    });
}, -999);

// Content filters also protect server-rendered fragments used by builders.
add_filter('the_content', function ($html) {
    if (is_admin() || is_feed() || (is_preview() && current_user_can('edit_posts')) || (defined('REST_REQUEST') && REST_REQUEST)) {
        return $html;
    }
    return rewrite_html($html, settings(), services());
}, PHP_INT_MAX);

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('local-consent', plugins_url('assets/consent.css', __FILE__), array(), VERSION);
    wp_add_inline_style('local-consent', design_css(settings()['design']));
    wp_enqueue_script('local-consent-design', plugins_url('assets/design.js', __FILE__), array(), VERSION, true);
    wp_enqueue_script('local-consent-sheet', plugins_url('assets/sheet.js', __FILE__), array(), VERSION, true);
    wp_enqueue_script('local-consent-motion', plugins_url('assets/motion.js', __FILE__), array(), VERSION, true);
    wp_enqueue_script('local-consent', plugins_url('assets/consent.js', __FILE__), array('local-consent-design', 'local-consent-sheet', 'local-consent-motion'), VERSION, true);
    // A JSON data block is cache-safe: the server never renders a visitor's consent.
    add_action('wp_footer', __NAMESPACE__ . '\\render_ui', 5);
});

add_shortcode('local_consent_settings', function () {
    return '<button type="button" class="lc-link" data-lc-open>' . esc_html(text('settings')) . '</button>';
});
