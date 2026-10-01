<?php
/** Run against a disposable WordPress: LOCAL_CONSENT_WP=/path php tests/html.test.php */
$wordpress = getenv('LOCAL_CONSENT_WP');
if (!$wordpress || !is_file($wordpress . '/wp-load.php')) {
    fwrite(STDERR, "Set LOCAL_CONSENT_WP to an isolated WordPress installation.\n");
    exit(1);
}
require $wordpress . '/wp-load.php';
if (!function_exists('LocalConsent\\rewrite_html')) {
    require dirname(__DIR__) . '/local-consent.php';
}

function verify($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS $message\n";
}

$settings = LocalConsent\settings();
$registry = LocalConsent\services();
$settings['services'] = array_keys($registry);
$settings['replace_legacy'] = true;
$settings['block_google_fonts'] = true;
$html = <<<'HTML'
<!doctype html><html><head>
<script src="https://cdn.consentmanager.net/delivery/autoblock/36021.js"></script>
<script>window.__cmp('init');</script>
<link rel="preconnect" href="https://fonts.gstatic.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto">
<link rel="stylesheet" href="/local-fonts.css">
<link rel="preload" href="https://www.googletagmanager.com/gtag/js?id=test" as="script">
<script async src="https://www.googletagmanager.com/gtag/js?id=test"></script>
<script>window.dataLayer=[];function gtag(){dataLayer.push(arguments)}gtag('config','test');</script>
<script type="application/ld+json">{"url":"https://www.googletagmanager.com"}</script>
<script>window.unrelated = 'kept';</script>
<style>@import url("https://fonts.googleapis.com/css?family=Roboto"); .x{color:red}</style>
</head><body><iframe src="https://www.google.com/maps/embed?x=1&amp;y=2" width="510" height="280"></iframe>
<iframe src="//www.youtube-nocookie.com/embed/abc"></iframe>
<iframe src="https://player.vimeo.com/video/123"></iframe>
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-test"></iframe></noscript>
<iframe src="https://youtube.com.evil.test/embed/abc"></iframe>
<img src="https://www.google-analytics.com/collect?x=1" srcset="https://www.google-analytics.com/collect?x=2 2x">
<script data-local-consent="google_tags" src="/own-tracker.js"></script>
</body></html>
HTML;

$result = LocalConsent\rewrite_html($html, $settings, $registry);
$p = new WP_HTML_Tag_Processor($result);
$counts = array();
$kept = array();
while ($p->next_tag()) {
    $service = $p->get_attribute('data-lc-service');
    $disabled = $p->get_attribute('data-lc-disabled');
    if ($service || $disabled) {
        $id = $service ?: $disabled;
        $counts[$id] = ($counts[$id] ?? 0) + 1;
        verify($p->get_attribute('src') === null && $p->get_attribute('href') === null, "$id has no live resource URL");
        if ($p->get_tag() === 'SCRIPT') {
            verify($p->get_attribute('type') === 'application/x-local-consent', "$id script is inert");
        }
        if ($p->get_tag() === 'IFRAME') {
            verify($p->get_attribute('hidden') === true && $p->get_attribute('srcdoc') === null, "$id iframe is inert");
        }
        if ($p->get_tag() === 'IMG') verify($p->get_attribute('srcset') === null, 'tracking image has no srcset');
    } else {
        $kept[] = $p->get_attribute('src') ?: $p->get_attribute('href');
    }
}
verify(($counts['google_maps'] ?? 0) === 1, 'maps blocked');
verify(($counts['youtube'] ?? 0) === 1, 'privacy-enhanced YouTube blocked');
verify(($counts['vimeo'] ?? 0) === 1, 'Vimeo blocked');
verify(($counts['google_tags'] ?? 0) === 6, 'tags include bootstrap, preload, noscript and explicit integrations');
verify(($counts['legacy-consentmanager'] ?? 0) === 2, 'legacy CMP disabled');
verify(($counts['external-google-fonts'] ?? 0) === 2, 'fonts and speculative connection blocked');
verify(in_array('/local-fonts.css', $kept, true), 'local fonts preserved');
verify(in_array('https://youtube.com.evil.test/embed/abc', $kept, true), 'host matching does not use unsafe substrings');
verify(strpos($result, '.x{color:red}') !== false && strpos($result, '@import') === false, 'remote font import removed without losing other styles');
verify(LocalConsent\rewrite_html($result, $settings, $registry) === $result, 'rewriting is idempotent');
verify(!LocalConsent\url_matches('https://evil.test/?url=fonts.googleapis.com', array(array('host' => 'fonts.googleapis.com'))), 'query strings do not impersonate hosts');
verify(LocalConsent\url_matches('HTTPS://FONTS.GOOGLEAPIS.COM./css', array(array('host' => 'fonts.googleapis.com'))), 'mixed case and trailing host dot handled');

$input = LocalConsent\sanitize_settings(array('services' => array('google_maps', 'unknown'), 'expiry_days' => 999, 'privacy_url' => 'javascript:alert(1)'));
verify($input['services'] === array('google_maps') && $input['expiry_days'] === 365 && $input['privacy_url'] === '', 'admin settings sanitize services, expiry and URLs');
echo "HTML integration checks complete.\n";
