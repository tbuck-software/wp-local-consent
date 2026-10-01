/* Sample the protected site page, then edit local design tokens. */
(function () {
    'use strict';
    const configElement = document.getElementById('local-consent-admin-config');
    const mode = document.getElementById('lc-design-mode');
    const fields = document.querySelector('[data-lc-custom-design]');
    const preset = document.getElementById('lc-design-preset');
    const status = document.getElementById('lc-design-copy-status');
    if (!configElement || !mode || !fields || !preset || !status) return;
    const config = JSON.parse(configElement.textContent);
    const save = document.getElementById('submit');
    let sample;
    let request = 0;
    let previewDoc;
    let previewFrame;
    let autoSheet;
    const preview = document.getElementById('lc-design-preview');
    const previewStatus = preview.querySelector('[data-lc-preview-status]');
    const inputs = [...fields.querySelectorAll('[data-lc-token]')];
    const aliases = { background: '--lc-surface', text: '--lc-ink', muted: '--lc-secondary', border: '--lc-line', accent: '--lc-primary', accentText: '--lc-on-primary', focus: '--lc-focus-color' };
    function color(value) {
        value = value.trim();
        if (/^#[\da-f]{6}$/i.test(value)) return value;
        if (/^#[\da-f]{3}$/i.test(value)) return '#' + [...value.slice(1)].map(n => n + n).join('');
        const match = /^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/.exec(value);
        if (!match) throw new Error('Website-Design konnte nicht gelesen werden.');
        return '#' + match.slice(1).map(n => Number(n).toString(16).padStart(2, '0')).join('');
    }
    function readTokens(doc) {
        const root = doc.getElementById('lc-root');
        if (!root) throw new Error('Website-Design nicht verfügbar.');
        const style = element => doc.defaultView.getComputedStyle(element);
        const base = style(root);
        const tokens = {};
        for (const [key, property] of Object.entries(aliases)) tokens[key] = color(base.getPropertyValue(property));
        tokens.fontFamily = base.fontFamily;
        tokens.headingFontFamily = style(doc.getElementById('lc-title')).fontFamily;
        tokens.fontSize = parseFloat(base.fontSize);
        tokens.radius = parseFloat(style(doc.getElementById('lc-dialog')).borderTopLeftRadius);
        tokens.buttonRadius = parseFloat(style(root.querySelector('[data-lc-accept]')).borderTopLeftRadius);
        const launcher = style(root.querySelector('.lc-reopen'));
        tokens.launcherRadius = launcher.borderTopLeftRadius.endsWith('%') ? 24 : parseFloat(launcher.borderTopLeftRadius);
        return tokens;
    }
    function websiteTokens() {
        if (sample) return sample;
        sample = new Promise((resolve, reject) => {
            const url = new URL(config.previewUrl, location.href);
            if (url.origin !== location.origin) {
                reject(new Error('Website-Design nicht verfügbar.'));
                return;
            }
            const frame = document.createElement('iframe');
            frame.name = 'local-consent-design-preview';
            frame.className = 'lc-design-sample';
            frame.title = 'Website-Design';
            frame.setAttribute('aria-hidden', 'true');
            frame.tabIndex = -1;
            let poll;
            const timer = setTimeout(() => {
                clearInterval(poll);
                frame.onload = null;
                frame.remove();
                reject(new Error('Website-Design konnte nicht geladen werden.'));
            }, 8000);
            const ready = () => {
                clearTimeout(timer);
                clearInterval(poll);
                frame.onload = null;
                try {
                    const tokens = readTokens(frame.contentDocument);
                    mountPreview(frame);
                    resolve(tokens);
                } catch (_) {
                    previewDoc = null;
                    previewFrame = null;
                    frame.remove();
                    reject(new Error('Website-Design nicht verfügbar.'));
                }
            };
            frame.onload = ready;
            frame.src = url.href;
            preview.append(frame);
            // Theme styles are ready before unrelated page images finish loading.
            poll = setInterval(() => {
                try {
                    if (frame.contentDocument?.getElementById('local-consent-auto-design')) ready();
                } catch (_) { /* The timeout handles unavailable pages. */ }
            }, 100);
        }).catch(error => { sample = null; throw error; });
        return sample;
    }
    function fitPreview() {
        if (!previewDoc) return;
        const root = previewDoc.getElementById('lc-root');
        const height = Math.ceil(root.getBoundingClientRect().height + 32);
        if (height > 32) previewFrame.style.height = height + 'px';
    }
    function mountPreview(frame) {
        previewFrame = frame;
        previewDoc = frame.contentDocument;
        autoSheet = previewDoc.getElementById('local-consent-auto-design')?.sheet;
        const css = previewDoc.createElement('style');
        css.textContent = 'html,body{margin:0!important;padding:0!important;min-height:0!important;background:transparent!important}body{padding:16px!important}body>:not(#lc-root){display:none!important}.lc-root .lc-dialog{display:block;position:relative;inset:auto;margin:0;width:100%;max-width:none;max-height:none;box-shadow:none;z-index:auto}.lc-root .lc-panel{box-shadow:none}.lc-root .lc-reopen{position:relative;inset:auto;margin-top:14px;opacity:1!important;pointer-events:auto!important;z-index:auto}';
        previewDoc.head.append(css);
        const root = previewDoc.getElementById('lc-root');
        root.querySelector('dialog').setAttribute('open', '');
        root.querySelector('.lc-reopen').hidden = false;
        root.querySelector('form').addEventListener('submit', event => event.preventDefault());
        frame.className = 'lc-design-preview-frame';
        frame.title = 'Banner-Vorschau';
        frame.removeAttribute('aria-hidden');
        frame.removeAttribute('tabindex');
        previewStatus.hidden = true;
        updatePreview();
        new previewDoc.defaultView.ResizeObserver(fitPreview).observe(root);
    }
    function updatePreview() {
        if (!previewDoc) return;
        const root = previewDoc.getElementById('lc-root');
        if (autoSheet) autoSheet.disabled = mode.value === 'custom';
        for (const input of inputs) {
            const key = input.dataset.lcToken;
            const value = input.value.trim();
            const property = '--lc-' + key.replace(/[A-Z]/g, letter => '-' + letter.toLowerCase());
            if (value === '') { root.style.setProperty(property, 'initial'); continue; }
            if (input.dataset.lcKind === 'color' && /^#[\da-f]{6}$/i.test(value)) root.style.setProperty(property, value);
            if (input.dataset.lcKind === 'font' && /^[\p{L}\p{N} ,"'_-]+$/u.test(value) && value.length <= 200) root.style.setProperty(property, value);
            if (input.dataset.lcKind === 'number' && input.validity.valid && Number.isFinite(Number(value))) root.style.setProperty(property, value + 'px');
        }
        const computed = previewDoc.defaultView.getComputedStyle(root);
        for (const picker of fields.querySelectorAll('[data-lc-color]')) {
            const input = document.getElementById('lc-design-' + picker.dataset.lcColor);
            if (/^#[\da-f]{6}$/i.test(input.value)) picker.value = input.value;
            else if (input.value === '') picker.value = color(computed.getPropertyValue(aliases[picker.dataset.lcColor]));
        }
        fitPreview();
    }
    function startPreview() {
        websiteTokens().catch(error => {
            previewStatus.hidden = false;
            previewStatus.textContent = error.message;
        });
    }
    for (const picker of fields.querySelectorAll('[data-lc-color]')) {
        picker.addEventListener('input', () => {
            const input = document.getElementById('lc-design-' + picker.dataset.lcColor);
            input.value = picker.value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }
    inputs.forEach(input => input.addEventListener('input', updatePreview));
    document.addEventListener('local-consent:admin-tab', event => {
        if (event.detail === 'design') startPreview();
    });
    if (!document.querySelector('[data-lc-panel="design"]').hidden) startPreview();
    function fill(tokens, onlyEmpty) {
        for (const [key, value] of Object.entries(tokens)) {
            const input = document.getElementById('lc-design-' + key);
            if (input && (!onlyEmpty || input.value === '')) input.value = String(value);
        }
        updatePreview();
    }
    async function copyWebsite(onlyEmpty) {
        if (onlyEmpty && !inputs.some(input => input.value === '')) {
            ++request;
            save.disabled = false;
            status.hidden = true;
            return;
        }
        const current = ++request;
        save.disabled = true;
        status.hidden = false;
        status.textContent = 'Design wird übernommen …';
        try {
            const tokens = await websiteTokens();
            if (current !== request || mode.value !== 'custom') return;
            fill(tokens, onlyEmpty);
            status.hidden = true;
        } catch (error) {
            if (current === request) status.textContent = error.message;
        } finally {
            if (current === request) save.disabled = false;
        }
    }
    mode.addEventListener('change', () => {
        fields.hidden = mode.value !== 'custom';
        updatePreview();
        if (!fields.hidden) copyWebsite(true);
        else { ++request; status.hidden = true; save.disabled = false; }
    });
    preset.addEventListener('change', () => {
        if (preset.value === 'website') copyWebsite(false);
        else if (config.presets[preset.value]) {
            ++request;
            fill(config.presets[preset.value].tokens, false);
            copyWebsite(true);
        }
    });
}());
