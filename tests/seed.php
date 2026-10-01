<?php
// wp eval-file tests/seed.php. Refuse to seed a production database.
if (wp_get_environment_type() !== 'local') {
    WP_CLI::error('Only a local WordPress installation can be seeded.');
}
wp_set_current_user(1);
$fixture_directory = WP_CONTENT_DIR . '/local-consent-test';
wp_mkdir_p($fixture_directory);
foreach (glob(__DIR__ . '/fixtures/*') as $fixture) {
    copy($fixture, $fixture_directory . '/' . basename($fixture));
}
$content = <<<'HTML'
<!-- wp:html -->
<section style="padding:40px 0"><p style="letter-spacing:.16em;text-transform:uppercase;font-size:12px">Local Consent · Lokale Entwicklung</p><h1 style="font-family:Georgia,serif;font-size:54px;color:#31554b">Ein guter Aufenthalt.<br>Eine klare Entscheidung.</h1><p>Externe Inhalte laden erst, wenn Sie sie erlauben. Probieren Sie die Karte und die Datenschutzeinstellungen aus.</p></section>
<h2>Anfahrt</h2>
<iframe title="Testkarte" data-local-consent="google_maps" src="FIXTURE_BASE/frame.html" width="510" height="280"></iframe>
<script data-local-consent="google_tags" src="FIXTURE_BASE/tag.js"></script>
<script data-local-consent="google_tags">window.lcFixtureOrder.push('inline');</script>
<!-- /wp:html -->
HTML;
$content = str_replace('FIXTURE_BASE', esc_url(content_url('local-consent-test')), $content);
$existing = get_page_by_path('consent-test');
$id = wp_insert_post(array('ID' => $existing ? $existing->ID : 0, 'post_title' => 'Lokaler Consent-Test', 'post_name' => 'consent-test', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => $content));
update_option('show_on_front', 'page');
update_option('page_on_front', $id);
$settings = LocalConsent\settings();
$settings['services'] = array('google_maps', 'google_tags');
update_option(LocalConsent\OPTION, $settings);
WP_CLI::success('Local test page created.');
