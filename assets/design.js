/* Read existing styles without loading fonts or contacting another server. */
(function () {
    'use strict';
    function rgb(value) {
        const match = /^rgba?\(([^)]+)\)$/.exec(value || '');
        if (!match) return null;
        const parts = match[1].split(/[,\s/]+/).filter(Boolean).map(Number);
        if (parts.length < 3 || parts.some(n => !Number.isFinite(n)) || (parts.length === 4 && parts[3] < 1)) return null;
        return parts.slice(0, 3);
    }
    function hex(color) {
        return '#' + color.map(n => Math.round(n).toString(16).padStart(2, '0')).join('');
    }
    function luminance(color) {
        return color.map(n => n / 255).map(n => n <= 0.04045 ? n / 12.92 : ((n + 0.055) / 1.055) ** 2.4)
            .reduce((sum, n, i) => sum + n * [0.2126, 0.7152, 0.0722][i], 0);
    }
    function contrast(a, b) {
        const values = [luminance(a), luminance(b)].sort((x, y) => y - x);
        return (values[0] + 0.05) / (values[1] + 0.05);
    }
    function readableColor(preferred, background) {
        if (preferred && contrast(preferred, background) >= 4.5) return preferred;
        return contrast([0, 0, 0], background) >= contrast([255, 255, 255], background) ? [0, 0, 0] : [255, 255, 255];
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = { rgb, contrast, readableColor };
    if (typeof document === 'undefined') return;
    const config = document.getElementById('local-consent-config');
    if (!config || (JSON.parse(config.textContent).designMode !== 'auto' && window.name !== 'local-consent-design-preview')) return;

    const outside = selector => [...document.querySelectorAll(selector)].filter(element =>
        !element.closest('.lc-root,.lc-placeholder,[id*="cmp"],[class*="cmp"]') && element.getClientRects().length
        && getComputedStyle(element).visibility !== 'hidden');
    const body = getComputedStyle(document.body);
    const background = rgb(body.backgroundColor) || rgb(getComputedStyle(document.documentElement).backgroundColor) || [255, 255, 255];
    const text = readableColor(rgb(body.color), background);
    const buttons = outside('.wp-element-button,.wp-block-button__link,.et_pb_button,.elementor-button,button[type="submit"],input[type="submit"]');
    const filled = buttons.map(element => getComputedStyle(element)).find(style => {
        const color = rgb(style.backgroundColor);
        return color && contrast(color, background) >= 3;
    });
    const link = outside('main a,#main-content a,.entry-content a,a').map(element => rgb(getComputedStyle(element).color))
        .find(color => color && contrast(color, background) >= 3);
    const accent = filled ? rgb(filled.backgroundColor) : (link || text);
    const accentText = readableColor(filled ? rgb(filled.color) : null, accent);
    const heading = outside('main h2,#main-content h2,h2,h1')[0];
    const values = {
        background: hex(background), text: hex(text), muted: hex(text),
        border: hex(background.map((n, i) => n * 0.8 + text[i] * 0.2)),
        accent: hex(accent), 'accent-text': hex(accentText), focus: hex(accent),
    };
    const font = value => /^[\p{L}\p{N} ,"'_-]+$/u.test(value) && value.length <= 200;
    if (font(body.fontFamily)) values['font-family'] = body.fontFamily;
    const headingFont = heading ? getComputedStyle(heading).fontFamily : body.fontFamily;
    if (font(headingFont)) values['heading-font-family'] = headingFont;
    const size = parseFloat(body.fontSize);
    if (Number.isFinite(size)) values['font-size'] = Math.min(18, Math.max(14, size)) + 'px';
    const buttonStyle = filled || (buttons[0] && getComputedStyle(buttons[0]));
    if (buttonStyle && /^\d+(?:\.\d+)?px$/.test(buttonStyle.borderTopLeftRadius)) {
        const radius = Math.min(32, parseFloat(buttonStyle.borderTopLeftRadius));
        values.radius = radius + 'px';
        values['button-radius'] = radius + 'px';
    }
    const style = document.createElement('style');
    style.id = 'local-consent-auto-design';
    style.textContent = '.lc-root,.lc-placeholder,.lc-link{' + Object.entries(values)
        .map(([key, value]) => `--lc-auto-${key}:${value};`).join('') + '}';
    document.head.append(style);
}());
