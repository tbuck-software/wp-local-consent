<?php
namespace LocalConsent;

function admin_menu() {
    add_submenu_page('plugins.php', 'Local Consent', 'Local Consent', 'manage_options', 'local-consent', __NAMESPACE__ . '\\admin_page');
}

function register_settings() {
    register_setting('local_consent', OPTION, array(
        'type' => 'array',
        'sanitize_callback' => __NAMESPACE__ . '\\sanitize_settings',
        'show_in_rest' => false,
    ));
}

function sanitize_settings($input) {
    $input = is_array($input) ? $input : array();
    $design = settings()['design'];
    if (!empty($input['design_json'])) {
        $design = parse_design_json($input['design_json']);
    } elseif (isset($input['design']) && is_array($input['design'])) {
        $raw = $input['design'];
        $tokens = array();
        foreach (design_fields() as $key => $field) {
            $value = $raw['tokens'][$key] ?? '';
            if ($value === '') continue;
            $tokens[$key] = $field['type'] === 'number' && is_numeric($value) ? (float) $value : $value;
        }
        $design = validate_design(array('version' => 1, 'mode' => $raw['mode'] ?? 'auto', 'tokens' => $tokens));
    }
    if (is_wp_error($design)) {
        add_settings_error(OPTION, 'invalid_design', $design->get_error_message());
        return settings();
    }
    $ids = array_keys(services());
    $chosen = isset($input['services']) && is_array($input['services']) ? $input['services'] : array();
    return array(
        'services' => array_values(array_intersect($ids, array_filter($chosen, 'is_string'))),
        'replace_legacy' => !empty($input['replace_legacy']),
        'block_google_fonts' => !empty($input['block_google_fonts']),
        'privacy_url' => esc_url_raw($input['privacy_url'] ?? '', array('http', 'https')),
        'expiry_days' => max(1, min(365, absint($input['expiry_days'] ?? 180))),
        'design' => $design,
    );
}

function render_design_fields($keys) {
    $tokens = settings()['design']['tokens'];
    $fields = design_fields();
    ?>
    <div class="lc-admin-fields">
        <?php foreach ($keys as $key) : $field = $fields[$key]; ?>
        <label for="lc-design-<?php echo esc_attr($key); ?>"><?php echo esc_html($field['label']); ?></label>
        <div<?php echo $field['type'] === 'color' ? ' class="lc-admin-color"' : ''; ?>>
            <?php if ($field['type'] === 'color') : ?><input type="color" data-lc-color="<?php echo esc_attr($key); ?>" aria-label="<?php echo esc_attr($field['label'] . ': Farbe wählen'); ?>" value="<?php echo esc_attr($tokens[$key] ?? '#000000'); ?>"><?php endif; ?>
            <input id="lc-design-<?php echo esc_attr($key); ?>" data-lc-token="<?php echo esc_attr($key); ?>" data-lc-kind="<?php echo esc_attr($field['type']); ?>" type="<?php echo $field['type'] === 'number' ? 'number' : 'text'; ?>" name="<?php echo esc_attr(OPTION); ?>[design][tokens][<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($tokens[$key] ?? ''); ?>"
            <?php if ($field['type'] === 'number') : ?>min="<?php echo (int) $field['min']; ?>" max="<?php echo (int) $field['max']; ?>" step="0.1"<?php else : ?>class="regular-text" placeholder="<?php echo $field['type'] === 'color' ? '#003154' : 'Automatisch'; ?>"<?php endif; ?>><?php echo $field['type'] === 'number' ? ' px' : ''; ?></div>
        <?php endforeach; ?>
    </div>
    <?php
}

function admin_page() {
    if (!current_user_can('manage_options')) return;
    $settings = settings();
    $design_admin = array(
        'previewUrl' => add_query_arg('local-consent-design-preview', '1', home_url('/')),
        'presets' => design_presets(),
    );
    $tabs = array('general' => 'Allgemein', 'design' => 'Design', 'transfer' => 'Import / Export', 'help' => 'Hilfe');
    $active = is_string($_GET['section'] ?? null) && isset($tabs[$_GET['section']]) ? $_GET['section'] : 'general';
    ?>
    <div class="wrap lc-admin">
        <h1>Local Consent</h1>
        <script id="local-consent-admin-config" type="application/json"><?php echo wp_json_encode($design_admin, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <?php settings_errors(OPTION); ?>
        <nav class="nav-tab-wrapper" aria-label="Plugin-Einstellungen">
            <?php foreach ($tabs as $id => $label) : ?>
            <a class="nav-tab<?php echo $active === $id ? ' nav-tab-active' : ''; ?>" data-lc-tab="<?php echo esc_attr($id); ?>" href="<?php echo esc_url(add_query_arg('section', $id, admin_url('admin.php?page=local-consent'))); ?>" <?php if ($active === $id) : ?>aria-current="page"<?php endif; ?>><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
        </nav>
        <form action="options.php" method="post">
            <?php settings_fields('local_consent'); ?>
            <section data-lc-panel="general" aria-label="Allgemein" <?php echo $active !== 'general' ? 'hidden' : ''; ?>>
                <fieldset class="lc-admin-group">
                    <legend>Dienste</legend>
                    <div class="lc-admin-services">
                        <?php foreach (services() as $id => $definition) : ?>
                        <label><input type="checkbox" name="<?php echo esc_attr(OPTION); ?>[services][]" value="<?php echo esc_attr($id); ?>" <?php checked(in_array($id, $settings['services'], true)); ?>> <?php echo esc_html($definition['label']); ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <fieldset class="lc-admin-group">
                    <legend>Schutz</legend>
                    <p><label><input type="checkbox" name="<?php echo esc_attr(OPTION); ?>[block_google_fonts]" value="1" <?php checked($settings['block_google_fonts']); ?>> Externe Google Fonts sperren</label></p>
                    <p><label><input type="checkbox" name="<?php echo esc_attr(OPTION); ?>[replace_legacy]" value="1" <?php checked($settings['replace_legacy']); ?>> Consentmanager.net/.de deaktivieren</label></p>
                </fieldset>
                <div class="lc-admin-fields">
                    <label for="lc-privacy-url">Datenschutzseite</label><input id="lc-privacy-url" class="regular-text" type="url" placeholder="WordPress-Standard" name="<?php echo esc_attr(OPTION); ?>[privacy_url]" value="<?php echo esc_attr($settings['privacy_url']); ?>">
                    <label for="lc-expiry">Erneut fragen nach</label><div><input id="lc-expiry" type="number" min="1" max="365" name="<?php echo esc_attr(OPTION); ?>[expiry_days]" value="<?php echo esc_attr($settings['expiry_days']); ?>"> Tagen</div>
                </div>
            </section>
            <section data-lc-panel="design" aria-label="Design" <?php echo $active !== 'design' ? 'hidden' : ''; ?>>
                <div class="lc-admin-design-layout"><div>
                <div class="lc-admin-fields">
                    <label for="lc-design-mode">Basis</label>
                    <select id="lc-design-mode" name="<?php echo esc_attr(OPTION); ?>[design][mode]">
                        <option value="auto" <?php selected($settings['design']['mode'], 'auto'); ?>>Automatisch</option>
                        <option value="custom" <?php selected($settings['design']['mode'], 'custom'); ?>>Eigenes Design</option>
                    </select>
                </div>
                <div data-lc-custom-design <?php echo $settings['design']['mode'] === 'auto' ? 'hidden' : ''; ?>>
                <div class="lc-admin-fields"><label for="lc-design-preset">Vorlage</label><select id="lc-design-preset"><option value="">Auswählen</option><option value="website">Automatisches Design</option><?php foreach (design_presets() as $id => $preset) : ?><option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($preset['label']); ?></option><?php endforeach; ?></select></div>
                <p id="lc-design-copy-status" class="description" role="status" hidden></p>
                <fieldset class="lc-admin-group"><legend>Schrift</legend><?php render_design_fields(array('fontFamily', 'headingFontFamily', 'fontSize')); ?></fieldset>
                <div class="lc-admin-design-columns">
                    <fieldset class="lc-admin-group"><legend>Farben</legend><?php render_design_fields(array('background', 'text', 'muted', 'border', 'accent', 'accentText', 'focus')); ?></fieldset>
                    <fieldset class="lc-admin-group"><legend>Rundungen</legend><?php render_design_fields(array('radius', 'buttonRadius', 'launcherRadius')); ?></fieldset>
                </div>
                </div>
                </div><aside class="lc-admin-preview" aria-label="Banner-Vorschau"><h2>Vorschau</h2><div id="lc-design-preview"><p class="description" data-lc-preview-status role="status">Wird geladen …</p></div></aside></div>
            </section>
            <section data-lc-panel="transfer" aria-label="Import und Export" <?php echo $active !== 'transfer' ? 'hidden' : ''; ?>>
                <div class="lc-admin-transfer">
                    <label for="lc-design-file">Design-Datei</label>
                    <input id="lc-design-file" type="file" accept=".json,application/json">
                    <label for="lc-design-json">Oder JSON einfügen</label>
                    <textarea id="lc-design-json" name="<?php echo esc_attr(OPTION); ?>[design_json]" rows="8" class="large-text code"></textarea>
                    <p id="lc-design-status" class="description" role="status" hidden></p>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=local_consent_export_design'), 'local_consent_export_design')); ?>">Design herunterladen</a>
                </div>
            </section>
            <section data-lc-panel="help" aria-label="Hilfe" <?php echo $active !== 'help' ? 'hidden' : ''; ?>>
                <ul class="lc-admin-help">
                    <li>Nur ausgewählte Dienste werden gesperrt. Tag-Manager-Inhalte müssen zur Dienstbeschreibung passen.</li>
                    <li>Beim Wechsel alte Consent-Plugins und deren Integrationscode entfernen.</li>
                    <li>Schriften lokal bereitstellen. Fonts innerhalb einer freigegebenen Google-Karte gehören zur Karte.</li>
                    <li>Leere Designfelder übernehmen Website- oder Standardwerte. Eigene Farben auf Kontrast prüfen.</li>
                    <li>Importe ersetzen nur das Design. Anschließend „Importieren“ wählen.</li>
                    <li>Die Sperre schützt erkannte Ressourcen im ausgelieferten HTML. Dynamische Loader, externe CSS-Dateien, HTTP-Link-Header und serverseitiges Tracking brauchen eine eigene Integration.</li>
                    <li>Nach Änderungen Seiten- und Divi-Caches leeren und Freigaben im Browser prüfen.</li>
                </ul>
                <p>Einstellungen im Inhalt: <code>[local_consent_settings]</code></p>
            </section>
            <div data-lc-submit <?php echo $active === 'help' ? 'hidden' : ''; ?>><?php submit_button($active === 'transfer' ? 'Importieren' : 'Speichern'); ?></div>
        </form>
    </div>
    <?php
}
