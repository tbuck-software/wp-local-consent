<?php
namespace LocalConsent;

/** Only design tokens are importable. No resource URLs or arbitrary CSS. */
function design_fields() {
    return array(
        'fontFamily' => array('label' => 'Text', 'type' => 'font'),
        'headingFontFamily' => array('label' => 'Überschriften', 'type' => 'font'),
        'fontSize' => array('label' => 'Größe', 'type' => 'number', 'min' => 14, 'max' => 22),
        'background' => array('label' => 'Hintergrund', 'type' => 'color'),
        'text' => array('label' => 'Text', 'type' => 'color'),
        'muted' => array('label' => 'Details', 'type' => 'color'),
        'border' => array('label' => 'Rahmen', 'type' => 'color'),
        'accent' => array('label' => 'Buttons', 'type' => 'color'),
        'accentText' => array('label' => 'Buttontext', 'type' => 'color'),
        'focus' => array('label' => 'Fokus', 'type' => 'color'),
        'radius' => array('label' => 'Dialog', 'type' => 'number', 'min' => 0, 'max' => 32),
        'buttonRadius' => array('label' => 'Buttons', 'type' => 'number', 'min' => 0, 'max' => 32),
        'launcherPosition' => array('label' => 'Position', 'type' => 'position', 'options' => array('bottom-left' => 'Unten links', 'bottom-right' => 'Unten rechts', 'top-left' => 'Oben links', 'top-right' => 'Oben rechts')),
        'launcherRadius' => array('label' => 'Icon', 'type' => 'number', 'min' => 0, 'max' => 24),
    );
}

function launcher_position($design) {
    $position = $design['tokens']['launcherPosition'] ?? 'bottom-left';
    return is_string($position) && isset(design_fields()['launcherPosition']['options'][$position]) ? $position : 'bottom-left';
}

function default_design() {
    return array('version' => 1, 'mode' => 'auto', 'tokens' => array());
}

function is_design_preview() {
    return isset($_GET['local-consent-design-preview'])
        && $_GET['local-consent-design-preview'] === '1' && current_user_can('manage_options');
}

function design_presets() {
    return array(
        'light' => array('label' => 'Hell', 'tokens' => array('background' => '#ffffff', 'text' => '#222222', 'muted' => '#555555', 'border' => '#d6d6d6', 'accent' => '#222222', 'accentText' => '#ffffff', 'focus' => '#222222', 'radius' => 3, 'buttonRadius' => 3, 'launcherRadius' => 24)),
        'soft' => array('label' => 'Weich', 'tokens' => array('background' => '#faf8f3', 'text' => '#30362e', 'muted' => '#575f53', 'border' => '#d5d9ce', 'accent' => '#3c543f', 'accentText' => '#ffffff', 'focus' => '#3c543f', 'radius' => 16, 'buttonRadius' => 8, 'launcherRadius' => 24)),
        'dark' => array('label' => 'Dunkel', 'tokens' => array('background' => '#191c20', 'text' => '#f2f4f6', 'muted' => '#c2c7ce', 'border' => '#555c65', 'accent' => '#f2f4f6', 'accentText' => '#191c20', 'focus' => '#f2f4f6', 'radius' => 8, 'buttonRadius' => 4, 'launcherRadius' => 24)),
    );
}

function validate_design($input) {
    if (!is_array($input) || ($input['version'] ?? null) !== 1
        || !in_array($input['mode'] ?? null, array('auto', 'custom'), true)
        || !isset($input['tokens']) || !is_array($input['tokens'])
        || array_diff(array_keys($input), array('version', 'mode', 'tokens'))) {
        return new \WP_Error('design', 'Ungültiges Designformat. Erwartet: version 1, mode auto/custom und tokens.');
    }
    $fields = design_fields();
    $tokens = array();
    foreach ($input['tokens'] as $key => $value) {
        if (!isset($fields[$key])) {
            return new \WP_Error('design', 'Unbekannter Designwert: ' . $key);
        }
        $field = $fields[$key];
        if ($field['type'] === 'position') {
            $valid = is_string($value) && isset($field['options'][$value]);
        } elseif ($field['type'] === 'number') {
            $valid = (is_int($value) || is_float($value)) && is_finite((float) $value)
                && $value >= $field['min'] && $value <= $field['max'];
        } elseif ($field['type'] === 'color') {
            $valid = is_string($value) && preg_match('/^#[0-9a-f]{6}$/iD', $value);
        } else {
            $valid = is_string($value) && strlen($value) <= 200
                && preg_match('/^[\p{L}\p{N} ,\'"_-]+$/uD', $value) && trim($value) !== '';
        }
        if (!$valid) {
            return new \WP_Error('design', 'Ungültiger Designwert: ' . $field['label']);
        }
        $tokens[$key] = $value;
    }
    return array('version' => 1, 'mode' => $input['mode'], 'tokens' => $tokens);
}

function parse_design_json($json) {
    if (!is_string($json) || strlen($json) > 16384) {
        return new \WP_Error('design', 'Die Design-JSON darf höchstens 16 KB groß sein.');
    }
    $decoded = json_decode($json, true, 8);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new \WP_Error('design', 'Die Design-JSON konnte nicht gelesen werden.');
    }
    return validate_design($decoded);
}

function design_css($design) {
    $design = validate_design($design);
    if (is_wp_error($design)) return '';
    $css = '';
    foreach ($design['tokens'] as $key => $value) {
        if (design_fields()[$key]['type'] === 'position') continue;
        $property = strtolower(preg_replace('/[A-Z]/', '-$0', $key));
        $unit = design_fields()[$key]['type'] === 'number' ? 'px' : '';
        $css .= '--lc-' . $property . ':' . $value . $unit . ';';
    }
    return $css ? '.lc-root,.lc-placeholder,.lc-link{' . $css . '}' : '';
}

function export_design() {
    if (!current_user_can('manage_options')) wp_die('Keine Berechtigung.', '', array('response' => 403));
    check_admin_referer('local_consent_export_design');
    $design = settings()['design'];
    $design['tokens'] = (object) $design['tokens'];
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="local-consent-design.json"');
    echo wp_json_encode($design, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
