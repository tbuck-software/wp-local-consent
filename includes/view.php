<?php
namespace LocalConsent;

function texts() {
    $de = array(
        'title' => 'Datenschutz',
        'intro' => 'Wir speichern Ihre Auswahl. Die Seite funktioniert auch ohne diese Dienste.',
        'reject' => 'Ablehnen', 'accept' => 'Alle erlauben', 'save' => 'Auswahl speichern',
        'settings' => 'Datenschutz', 'privacy' => 'Datenschutzerklärung',
        'close' => 'Schließen', 'load' => 'Inhalt laden', 'loadMap' => 'Karte laden', 'loadVideo' => 'Video laden',
        'storage' => 'Ihr Browser kann die Auswahl nicht dauerhaft speichern. Sie gilt nur für diese Seite.',
        'noscript' => 'Externe Inhalte bleiben gesperrt, weil JavaScript ausgeschaltet ist. Für Datenschutzeinstellungen aktivieren Sie JavaScript.',
        'external' => 'Externe Inhalte', 'statistics' => 'Statistik', 'marketing' => 'Marketing',
    );
    $en = array(
        'title' => 'Privacy',
        'intro' => 'We save your choice. The site works without these services.',
        'reject' => 'Reject all', 'accept' => 'Allow all', 'save' => 'Save selection',
        'settings' => 'Privacy', 'privacy' => 'Privacy policy',
        'close' => 'Close', 'load' => 'Load content', 'loadMap' => 'Load map', 'loadVideo' => 'Load video',
        'storage' => 'Your browser cannot save this choice permanently. It applies only to this page.',
        'noscript' => 'External content is blocked because JavaScript is disabled. Enable JavaScript to change privacy settings.',
        'external' => 'External content', 'statistics' => 'Statistics', 'marketing' => 'Marketing',
    );
    return language() === 'de' ? $de : $en;
}

function text($key) {
    return texts()[$key];
}

function render_ui() {
    $config = consent_config();
    ?>
    <script type="application/json" id="local-consent-config"><?php echo wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <div id="lc-root" class="lc-root">
        <button type="button" class="lc-reopen" data-lc-open aria-controls="lc-dialog" aria-expanded="false" title="<?php echo esc_attr(text('settings')); ?>" hidden><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M3 14v-2a9 9 0 0 1 18 0v2M6 12a6 6 0 0 1 12 0c0 2-.2 4-.8 6M9 12a3 3 0 0 1 6 0c0 3-.5 6-1.5 9M12 12c0 3-.5 6-2 9M6 12c0 3-.5 5-1.5 7M9 12c0 2-.3 4-.8 5"/></svg><span class="lc-sr-only"><?php echo esc_html(text('settings')); ?></span></button>
        <dialog id="lc-dialog" class="lc-dialog" aria-labelledby="lc-title" aria-describedby="lc-intro">
            <form method="dialog" class="lc-panel">
                <div class="lc-sheet-handle" aria-hidden="true"></div>
                <div class="lc-content">
                <div class="lc-heading"><h2 id="lc-title"><?php echo esc_html(text('title')); ?></h2><button type="button" class="lc-close" data-lc-close aria-label="<?php echo esc_attr(text('close')); ?>">×</button></div>
                <p id="lc-intro"><?php echo esc_html(text('intro')); ?></p>
                <?php if ($config['privacyUrl']) : ?><a class="lc-policy" href="<?php echo esc_url($config['privacyUrl']); ?>"><?php echo esc_html(text('privacy')); ?></a><?php endif; ?>
                <fieldset class="lc-services"><legend class="lc-sr-only"><?php echo esc_html(text('settings')); ?></legend>
                    <?php foreach ($config['services'] as $id => $definition) : ?>
                    <div class="lc-service">
                        <label class="lc-service-toggle"><strong><?php echo esc_html($definition['label']); ?></strong><input type="checkbox" data-lc-choice="<?php echo esc_attr($id); ?>" aria-describedby="lc-description-<?php echo esc_attr($id); ?>"><span class="lc-switch" aria-hidden="true"></span></label>
                        <p class="lc-service-description" id="lc-description-<?php echo esc_attr($id); ?>"><?php echo esc_html($definition['description']); ?></p>
                    </div>
                    <?php endforeach; ?>
                </fieldset>
                <p class="lc-storage" role="status" hidden><?php echo esc_html(text('storage')); ?></p>
                </div>
                <div class="lc-footer">
                <div class="lc-actions"><button type="button" data-lc-reject><?php echo esc_html(text('reject')); ?></button><button type="button" data-lc-accept><?php echo esc_html(text('accept')); ?></button></div>
                <button type="button" class="lc-save" data-lc-save hidden><?php echo esc_html(text('save')); ?></button>
                </div>
            </form>
        </dialog>
        <noscript><p class="lc-noscript"><?php echo esc_html(text('noscript')); ?></p></noscript>
    </div>
    <?php
}
