<?php
namespace LocalConsent;

function settings() {
    return array_merge(array(
        'services' => array('google_maps', 'google_tags', 'youtube', 'vimeo'),
        'replace_legacy' => true,
        'block_google_fonts' => true,
        'privacy_url' => '',
        'expiry_days' => 180,
        'design' => default_design(),
    ), (array) get_option(OPTION, array()));
}

function language() {
    $locale = function_exists('pll_current_language') ? pll_current_language('slug') : get_locale();
    return strpos((string) $locale, 'de') === 0 ? 'de' : 'en';
}

/**
 * Extension seam: local_consent_services receives id => definition entries.
 * Hosts match themselves and subdomains, paths match a literal prefix.
 * Never use unanchored URL substring detection for automatic blocking.
 */
function services() {
    $de = language() === 'de';
    return apply_filters('local_consent_services', array(
        'google_maps' => array(
            'label' => 'Google Maps', 'category' => 'external',
            'description' => $de ? 'Zeigt Karten und überträgt Verbindungsdaten an Google.' : 'Shows maps and sends connection data to Google.',
            'rules' => array(array('host' => 'google.com', 'path' => '/maps'), array('host' => 'maps.google.com'), array('host' => 'maps.googleapis.com'), array('host' => 'maps.gstatic.com')),
        ),
        'google_tags' => array(
            'label' => 'Google Analytics / Tag Manager', 'category' => 'statistics',
            'description' => $de ? 'Überträgt Nutzungsdaten an Google für Statistik und weitere konfigurierte Zwecke.' : 'Sends usage data to Google for statistics and other configured purposes.',
            'rules' => array(array('host' => 'googletagmanager.com'), array('host' => 'google-analytics.com'), array('host' => 'analytics.google.com')),
        ),
        'youtube' => array(
            'label' => 'YouTube', 'category' => 'external',
            'description' => $de ? 'Lädt Videos von YouTube. Dabei werden Verbindungsdaten an Google übertragen. Dies gilt auch für den erweiterten Datenschutzmodus.' : 'Loads videos from YouTube. Connection data is sent to Google, including in privacy-enhanced mode.',
            'rules' => array(array('host' => 'youtube.com', 'path' => '/embed'), array('host' => 'youtube-nocookie.com', 'path' => '/embed')),
        ),
        'vimeo' => array(
            'label' => 'Vimeo', 'category' => 'external',
            'description' => $de ? 'Lädt Videos von Vimeo. Dabei werden Verbindungsdaten an Vimeo übertragen.' : 'Loads videos from Vimeo. Connection data is sent to Vimeo.',
            'rules' => array(array('host' => 'player.vimeo.com')),
        ),
    ));
}

function host_matches($host, $rule) {
    $host = strtolower(rtrim($host, '.'));
    $rule = strtolower(rtrim($rule, '.'));
    return $host === $rule || substr($host, -strlen('.' . $rule)) === '.' . $rule;
}

function url_matches($url, $rules) {
    if (!is_string($url) || $url === '') {
        return false;
    }
    // Browsers treat backslashes as separators in special-scheme URLs.
    $url = str_replace('\\', '/', html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (strpos($url, '//') === 0) {
        $url = 'https:' . $url;
    }
    $parts = wp_parse_url($url);
    if (!isset($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), array('http', 'https'), true)) {
        return false;
    }
    foreach ($rules as $rule) {
        if (host_matches($parts['host'], $rule['host'])
            && (!isset($rule['path']) || strpos($parts['path'] ?? '/', $rule['path']) === 0)) {
            return true;
        }
    }
    return false;
}

function service_for_url($url, $registry) {
    foreach ($registry as $id => $definition) {
        if (url_matches($url, $definition['rules'])) {
            return $id;
        }
    }
    return null;
}

function consent_config() {
    $settings = settings();
    $registry = services();
    $active = array_intersect_key($registry, array_flip($settings['services']));
    $privacy = $settings['privacy_url'] ?: get_privacy_policy_url();
    // Switching languages must not invalidate the same provider choices.
    $definitions = array_map(function ($definition) {
        return array($definition['category'], $definition['rules']);
    }, $registry);
    $consent_settings = $settings;
    unset($consent_settings['design']);
    $revision = hash('sha256', wp_json_encode(array(VERSION, $consent_settings, $definitions, home_url())));
    return array(
        'revision' => $revision,
        'storageKey' => 'local-consent:' . substr(hash('sha256', home_url()), 0, 12),
        'expiryDays' => (int) $settings['expiry_days'],
        'privacyUrl' => $privacy,
        'designMode' => is_design_preview() ? 'auto' : $settings['design']['mode'],
        'designPreview' => is_design_preview(),
        'services' => $active,
        'text' => texts(),
    );
}
