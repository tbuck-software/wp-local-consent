<?php
$wordpress = getenv('LOCAL_CONSENT_WP');
if (!$wordpress || !is_file($wordpress . '/wp-load.php')) exit(1);
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
if (!function_exists('LocalConsent\\validate_design')) require dirname(__DIR__) . '/local-consent.php';
function check_design($value, $message) {
    if (!$value) throw new RuntimeException($message);
    echo "PASS $message\n";
}
$valid = '{"version":1,"mode":"auto","tokens":{"accent":"#003154","radius":0,"fontFamily":"\"Open Sans\", sans-serif"}}';
$design = LocalConsent\parse_design_json($valid);
check_design(!is_wp_error($design) && strpos(LocalConsent\design_css($design), '--lc-radius:0px') !== false, 'valid JSON produces CSS, including square corners');
foreach (array(
    '{broken',
    '{"version":2,"mode":"auto","tokens":{}}',
    '{"version":1,"mode":"auto","tokens":{"background":"url(https://example.test)"}}',
    '{"version":1,"mode":"auto","tokens":{"fontFamily":"Arial; background:red"}}',
    '{"version":1,"mode":"auto","tokens":{"radius":100}}',
    '{"version":1,"mode":"auto","tokens":{"unknown":"value"}}',
    '{"version":1,"mode":"auto","tokens":{},"services":[]}',
) as $json) {
    check_design(is_wp_error(LocalConsent\parse_design_json($json)), 'unsafe or incompatible import rejected');
}
check_design(is_wp_error(LocalConsent\parse_design_json(str_repeat(' ', 16385))), 'oversized import rejected');
foreach (LocalConsent\design_presets() as $preset) {
    check_design(!is_wp_error(LocalConsent\validate_design(array('version' => 1, 'mode' => 'custom', 'tokens' => $preset['tokens']))), 'preset can be imported and exported');
}
$previous_user = get_current_user_id();
$previous_get = $_GET;
try {
    $_GET['local-consent-design-preview'] = '1';
    wp_set_current_user(0);
    check_design(!LocalConsent\consent_config()['designPreview'], 'public visitors cannot enter admin design sampling');
    $admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
    wp_set_current_user($admins[0]);
    $preview = LocalConsent\consent_config();
    check_design($preview['designPreview'] && $preview['designMode'] === 'auto', 'authenticated sampling uses automatic design with activation disabled');
} finally {
    wp_set_current_user($previous_user);
    $_GET = $previous_get;
}
$old = get_option(LocalConsent\OPTION);
try {
    $settings = LocalConsent\settings();
    $before = LocalConsent\consent_config()['revision'];
    $settings['design'] = $design;
    update_option(LocalConsent\OPTION, $settings);
    check_design(LocalConsent\consent_config()['revision'] === $before, 'design changes preserve existing consent');
    $saved = LocalConsent\sanitize_settings(array_merge($settings, array('design_json' => $valid)));
    check_design($saved['services'] === $settings['services'] && $saved['design']['tokens']['accent'] === '#003154', 'design import preserves service settings');
    $invalid = LocalConsent\sanitize_settings(array('design_json' => '{broken', 'services' => array()));
    check_design($invalid['services'] === $settings['services'], 'failed import preserves existing settings');
} finally {
    if ($old === false) delete_option(LocalConsent\OPTION);
    else update_option(LocalConsent\OPTION, $old);
}
