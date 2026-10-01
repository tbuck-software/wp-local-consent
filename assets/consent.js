/* Local Consent. No external dependencies. */
(function () {
    'use strict';

    function readRecord(raw, config, now) {
        try {
            const record = JSON.parse(raw);
            const ids = Object.keys(config.services);
            if (!record || record.version !== 1 || record.revision !== config.revision
                || !Number.isFinite(record.updatedAt) || record.updatedAt > now
                || !Number.isFinite(record.expiresAt) || record.expiresAt <= now
                || record.expiresAt - record.updatedAt !== config.expiryDays * 86400000
                || !record.choices || typeof record.choices !== 'object' || Array.isArray(record.choices)
                || Object.keys(record.choices).length !== ids.length
                || !ids.every(id => typeof record.choices[id] === 'boolean')) {
                return null;
            }
            return record;
        } catch (_) {
            return null;
        }
    }

    function makeRecord(choices, config, now) {
        return {
            version: 1, revision: config.revision, updatedAt: now,
            expiresAt: now + config.expiryDays * 86400000,
            choices: Object.fromEntries(Object.keys(config.services).map(id => [id, choices[id] === true]))
        };
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { readRecord, makeRecord };
    }
    if (typeof document === 'undefined') return;

    const configElement = document.getElementById('local-consent-config');
    const dialog = document.getElementById('lc-dialog');
    if (!configElement || !dialog || typeof dialog.showModal !== 'function') return;
    const config = JSON.parse(configElement.textContent);
    // Admin design sampling never restores visitor grants or activates services.
    if (config.designPreview || window.name === 'local-consent-design-preview') return;
    const storageKey = config.storageKey;
    const ids = Object.keys(config.services);
    const empty = () => Object.fromEntries(ids.map(id => [id, false]));
    const root = document.getElementById('lc-root');
    const fab = root.querySelector('.lc-reopen');
    const motion = window.LocalConsentMotion?.bind(dialog, fab);
    const feedbackKey = `${storageKey}:feedback`;
    const placeholders = new Map();
    const activated = new WeakSet();
    const failedServices = new Set();
    let record = readStored();
    let choices = record ? record.choices : empty();
    let previousFocus;
    let loading = false;
    let queued = false;
    let expiryTimer;
    let feedbackTimer;
    let closing;

    function readStored() {
        if (new URL(location.href).searchParams.get('local-consent-denied') === '1') return null;
        try { return readRecord(localStorage.getItem(storageKey), config, Date.now()); }
        catch (_) { return null; }
    }

    function allows(id) {
        // An open page must not continue using consent after expiry.
        if (record && record.expiresAt <= Date.now()) return false;
        return choices[id] === true;
    }

    function open() {
        if (dialog.open || closing) return;
        previousFocus = document.activeElement;
        refreshChoices();
        // Keep the FAB inside the modal's active layer so it remains clickable.
        dialog.append(fab);
        fab.setAttribute('aria-expanded', 'true');
        dialog.showModal();
        motion?.enter();
    }

    function refreshChoices() {
        root.querySelectorAll('[data-lc-choice]').forEach(input => {
            input.checked = allows(input.dataset.lcChoice);
        });
        root.querySelector('[data-lc-save]').hidden = true;
    }

    function close() {
        if (closing) return closing;
        if (!dialog.open) return Promise.resolve();
        closing = (motion?.exit() || Promise.resolve()).then(() => {
            dialog.close();
            restoreFab();
            closing = null;
            if (previousFocus && previousFocus.isConnected) previousFocus.focus();
        });
        return closing;
    }

    function restoreFab() {
        root.prepend(fab);
        fab.setAttribute('aria-expanded', 'false');
    }

    function feedback() {
        clearTimeout(feedbackTimer);
        fab.classList.remove('lc-feedback');
        // Restart the same animation when another choice is saved shortly after.
        void fab.offsetWidth;
        fab.classList.add('lc-feedback');
        feedbackTimer = setTimeout(() => fab.classList.remove('lc-feedback'), 600);
    }

    function rememberFeedback() {
        try { sessionStorage.setItem(feedbackKey, JSON.stringify({ revision: config.revision, at: Date.now() })); }
        catch (_) { /* Feedback never delays withdrawal or changes consent. */ }
    }

    function clearGoogleCookies() {
        const names = document.cookie.split(';').map(value => value.trim().split('=')[0])
            .filter(name => /^(?:_ga(?:_|$)|_gid$|_gat(?:_|$)|_gcl_)/.test(name));
        const paths = ['/'];
        const pieces = location.pathname.split('/');
        for (let i = 1; i < pieces.length; i++) paths.push(pieces.slice(0, i + 1).join('/'));
        const domains = [''];
        const host = location.hostname.split('.');
        for (let i = 0; i < host.length - 1; i++) domains.push(host.slice(i).join('.'));
        names.forEach(name => paths.forEach(path => domains.forEach(domain => {
            document.cookie = `${name}=; Max-Age=0; Path=${path}; SameSite=Lax${domain ? `; Domain=${domain}` : ''}`;
        })));
    }

    function commit(next) {
        if (closing) return false;
        const revoked = ids.some(id => allows(id) && next[id] !== true);
        record = makeRecord(next, config, Date.now());
        choices = record.choices;
        refreshChoices();
        let persisted = true;
        try {
            localStorage.setItem(storageKey, JSON.stringify(record));
            root.querySelector('.lc-storage').hidden = true;
            const url = new URL(location.href);
            if (url.searchParams.has('local-consent-denied')) {
                url.searchParams.delete('local-consent-denied');
                history.replaceState(history.state, '', url);
            }
        } catch (_) {
            persisted = false;
            root.querySelector('.lc-storage').hidden = false;
        }
        if (!choices.google_tags) clearGoogleCookies();
        document.dispatchEvent(new CustomEvent('local-consent:change', { detail: { ...choices } }));
        if (revoked) {
            // Withdraw frames immediately; reload ends already-running scripts.
            document.querySelectorAll('iframe[data-lc-service]').forEach(frame => {
                if (!allows(frame.dataset.lcService)) { frame.removeAttribute('src'); frame.hidden = true; }
            });
            if (persisted) rememberFeedback();
            close().then(() => {
                if (persisted) { location.reload(); return; }
                // If storage is readable but unwritable, an old grant must not
                // become active again after reload. Carry a deny flag in the URL.
                const url = new URL(location.href);
                url.searchParams.set('local-consent-denied', '1');
                location.replace(url);
            });
            return true;
        }
        if (persisted) close().then(feedback);
        discover();
        activate();
        armExpiry();
        return persisted;
    }

    function placeholder(frame) {
        if (placeholders.has(frame)) return;
        const id = frame.dataset.lcService;
        const definition = config.services[id];
        if (!definition) return;
        const panel = document.createElement('div');
        panel.className = 'lc-placeholder';
        panel.dataset.lcPlaceholder = id;
        const title = document.createElement('strong');
        title.textContent = definition.label;
        const description = document.createElement('p');
        description.textContent = definition.description;
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = id === 'google_maps' ? config.text.loadMap
            : ['youtube', 'vimeo'].includes(id) ? config.text.loadVideo : config.text.load;
        button.addEventListener('click', () => commit({ ...choices, [id]: true }));
        panel.append(title, description, button);
        frame.before(panel);
        placeholders.set(frame, panel);
    }

    function discover() {
        document.querySelectorAll('iframe[data-lc-service]').forEach(frame => {
            placeholder(frame);
            const panel = placeholders.get(frame);
            if (panel) panel.hidden = allows(frame.dataset.lcService);
        });
    }

    async function activateScript(source) {
        const script = document.createElement('script');
        for (const attribute of source.attributes) {
            if (!attribute.name.startsWith('data-lc-') && !['type', 'src', 'async', 'defer'].includes(attribute.name)) {
                script.setAttribute(attribute.name, attribute.value);
            }
        }
        const type = source.dataset.lcType;
        if (type) script.type = type;
        script.async = false;
        script.textContent = source.textContent;
        if (!source.dataset.lcSrc) {
            source.replaceWith(script);
            return;
        }
        // Keep dependent inline initializers behind their external library.
        await new Promise((resolve, reject) => {
            const timer = setTimeout(() => reject(new Error('Script load timed out')), 15000);
            script.onload = () => { clearTimeout(timer); resolve(); };
            script.onerror = () => { clearTimeout(timer); reject(new Error('Script load failed')); };
            script.src = source.dataset.lcSrc;
            source.replaceWith(script);
        });
    }

    async function activate() {
        if (loading) { queued = true; return; }
        loading = true;
        try {
            // Preserve document order, including an inline loader before an external tag.
            for (const element of document.querySelectorAll('[data-lc-service]')) {
                const id = element.dataset.lcService;
                if (!allows(id) || failedServices.has(id) || activated.has(element)) continue;
                activated.add(element);
                try {
                    if (element.tagName === 'SCRIPT') {
                        await activateScript(element);
                    } else if (element.dataset.lcSrc) {
                        const attribute = element.tagName === 'LINK' ? 'href' : 'src';
                        element.setAttribute(attribute, element.dataset.lcSrc);
                        element.hidden = false;
                        if (element.tagName === 'IFRAME') {
                            const panel = placeholders.get(element);
                            if (panel) panel.hidden = true;
                        }
                    }
                } catch (_) {
                    failedServices.add(id);
                    document.dispatchEvent(new CustomEvent('local-consent:load-error', { detail: { service: id } }));
                }
            }
        } finally {
            loading = false;
            if (queued) { queued = false; activate(); }
        }
    }

    document.addEventListener('click', event => {
        if (event.target.closest('[data-lc-open]')) {
            event.preventDefault();
            if (event.target.closest('.lc-reopen') && dialog.open) close();
            else open();
        }
    });
    root.querySelector('[data-lc-close]').addEventListener('click', close);
    dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
    root.querySelector('.lc-services').addEventListener('change', () => {
        root.querySelector('[data-lc-save]').hidden = ![...root.querySelectorAll('[data-lc-choice]')]
            .some(input => input.checked !== allows(input.dataset.lcChoice));
    });
    root.querySelector('[data-lc-reject]').addEventListener('click', () => commit(empty()));
    window.LocalConsentSheet?.bind(dialog, () => commit(empty()));
    root.querySelector('[data-lc-accept]').addEventListener('click', () => commit(Object.fromEntries(ids.map(id => [id, true]))));
    root.querySelector('[data-lc-save]').addEventListener('click', () => {
        const next = empty();
        root.querySelectorAll('[data-lc-choice]').forEach(input => { next[input.dataset.lcChoice] = input.checked; });
        commit(next);
    });
    dialog.addEventListener('close', () => {
        if (!dialog.open) restoreFab();
        if (previousFocus && previousFocus.isConnected) previousFocus.focus();
    });
    fab.hidden = false;
    fab.querySelectorAll('svg path').forEach(path => path.setAttribute('pathLength', '1'));
    try {
        const pending = JSON.parse(sessionStorage.getItem(feedbackKey));
        sessionStorage.removeItem(feedbackKey);
        if (record && pending?.revision === config.revision && Number.isFinite(pending.at)
            && Date.now() >= pending.at && Date.now() - pending.at < 10000) feedback();
    } catch (_) { /* Storage is optional for animation. */ }

    // Observe only already-inert fragments. This is not a network interception layer.
    new MutationObserver(mutations => {
        if (mutations.some(m => [...m.addedNodes].some(n => n.nodeType === 1 && (n.matches('[data-lc-service]') || n.querySelector('[data-lc-service]'))))) {
            discover(); activate();
        }
    }).observe(document.body, { childList: true, subtree: true });

    function sync() {
        const latest = readStored();
        const next = latest ? latest.choices : empty();
        if (ids.some(id => choices[id] && !next[id])) {
            location.reload();
        } else {
            record = latest; choices = next;
            discover(); activate();
            if (!latest) open();
            armExpiry();
        }
    }
    function armExpiry() {
        clearTimeout(expiryTimer);
        if (!record) return;
        expiryTimer = setTimeout(() => {
            if (record && record.expiresAt <= Date.now()) sync();
            else armExpiry();
        }, Math.max(1, Math.min(2147483647, record.expiresAt - Date.now())));
    }
    window.addEventListener('storage', event => { if (event.key === storageKey || event.key === null) sync(); });
    window.addEventListener('pageshow', event => { if (event.persisted) sync(); });
    window.addEventListener('focus', sync);

    window.LocalConsent = Object.freeze({ allows, open });
    document.dispatchEvent(new CustomEvent('local-consent:ready'));
    discover();
    activate();
    armExpiry();
    if (!record && ids.length) open();
}());
