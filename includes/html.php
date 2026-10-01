<?php
namespace LocalConsent;

/** Make managed resources inert before the browser receives their addresses. */
function rewrite_html($html, $settings, $registry) {
    if (!is_string($html) || strpos($html, '<') === false) {
        return $html;
    }
    $active = array_intersect_key($registry, array_flip($settings['services']));
    $processor = new \WP_HTML_Tag_Processor($html);
    while ($processor->next_tag()) {
        $tag = $processor->get_tag();
        if ($processor->get_attribute('data-lc-service') !== null || $processor->get_attribute('data-lc-disabled') !== null) {
            continue;
        }
        if (!in_array($tag, array('SCRIPT', 'IFRAME', 'LINK', 'IMG', 'STYLE'), true)) {
            continue;
        }
        $attribute = $tag === 'LINK' ? 'href' : 'src';
        $url = $processor->get_attribute($attribute);
        $content = ($tag === 'SCRIPT' || $tag === 'STYLE') ? $processor->get_modifiable_text() : '';
        $explicit = $processor->get_attribute('data-local-consent');
        $service = is_string($explicit) && isset($active[$explicit]) ? $explicit : service_for_url($url, $active);
        $disabled = false;

        // Remove the old CMP, including its inline loader, instead of running two banners.
        if ($settings['replace_legacy'] && (
            url_matches($url, array(array('host' => 'consentmanager.net'), array('host' => 'consentmanager.de')))
            || ($tag === 'SCRIPT' && preg_match('~(?:consentmanager\.(?:net|de)|\b__cmp\s*\()~i', $content))
        )) {
            $disabled = 'legacy-consentmanager';
        }
        if ($settings['block_google_fonts'] && url_matches($url, array(array('host' => 'fonts.googleapis.com'), array('host' => 'fonts.gstatic.com')))) {
            $disabled = 'external-google-fonts';
        }
        // Common inline gtag.js / analytics.js / GTM bootstrap patterns.
        $type = $processor->get_attribute('type');
        $executable = $type === null || $type === '' || in_array(strtolower((string) $type), array('module', 'text/javascript', 'application/javascript'), true);
        if ($tag === 'SCRIPT' && $executable && !$service && isset($active['google_tags'])
            && preg_match('~(?:\bgtag\s*\(|\bga\s*\(\s*[\'\"](?:create|send)|GoogleAnalyticsObject|googletagmanager\.com|google-analytics\.com)~i', $content)) {
            $service = 'google_tags';
        }
        if ($tag === 'STYLE' && $settings['block_google_fonts']) {
            // Disable complete @import rules; replace remote font URLs with an empty URL.
            $content = preg_replace('~@import\s+(?:url\([^;]*fonts\.(?:googleapis|gstatic)\.com[^;]*\)|[\'\"][^;]*fonts\.(?:googleapis|gstatic)\.com[^;]*[\'\"])[^;]*;~i', '', $content);
            $content = preg_replace('~url\(\s*[\'\"]?(?:https?:)?//fonts\.(?:googleapis|gstatic)\.com[^)]*\)~i', 'url("")', $content);
            $processor->set_modifiable_text($content);
        }
        if (!$service && !$disabled) {
            continue;
        }
        if ($disabled) {
            $processor->set_attribute('data-lc-disabled', $disabled);
        } else {
            $processor->set_attribute('data-lc-service', $service);
        }
        if (is_string($url)) {
            $processor->set_attribute('data-lc-src', $url);
            $processor->remove_attribute($attribute);
        }
        // Prevent speculative fetches from script preloads and tracking images.
        $processor->remove_attribute('srcset');
        if ($tag === 'SCRIPT') {
            if ($type !== null) {
                $processor->set_attribute('data-lc-type', $type);
            }
            $processor->set_attribute('type', 'application/x-local-consent');
        } elseif ($tag === 'IFRAME') {
            $processor->remove_attribute('srcdoc');
            $processor->set_attribute('hidden', true);
        } elseif ($tag === 'IMG') {
            $processor->set_attribute('hidden', true);
        }
    }
    return $processor->get_updated_html();
}
