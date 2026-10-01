/* Design import stays in this browser until the settings form is saved. */
(function () {
    'use strict';
    const page = document.querySelector('.lc-admin');
    if (!page) return;
    const form = page.querySelector('form');
    const tabs = [...page.querySelectorAll('[data-lc-tab]')];
    const panels = [...page.querySelectorAll('[data-lc-panel]')];
    function showPanel(id) {
        if (!panels.some(panel => panel.dataset.lcPanel === id)) id = 'general';
        panels.forEach(panel => { panel.hidden = panel.dataset.lcPanel !== id; });
        tabs.forEach(tab => {
            const active = tab.dataset.lcTab === id;
            tab.classList.toggle('nav-tab-active', active);
            if (active) tab.setAttribute('aria-current', 'page');
            else tab.removeAttribute('aria-current');
        });
        page.querySelector('[data-lc-submit]').hidden = id === 'help';
        page.querySelector('#submit').value = id === 'transfer' ? 'Importieren' : 'Speichern';
        const url = new URL(location.href);
        url.searchParams.set('section', id);
        history.replaceState(history.state, '', url);
        form.querySelector('[name="_wp_http_referer"]').value = url.pathname + url.search;
        document.dispatchEvent(new CustomEvent('local-consent:admin-tab', { detail: id }));
    }
    tabs.forEach(tab => tab.addEventListener('click', event => {
        event.preventDefault();
        showPanel(tab.dataset.lcTab);
    }));
    // A bad value in another panel must remain reachable during validation.
    form.addEventListener('invalid', event => {
        const invalid = form.querySelector('input:invalid,select:invalid,textarea:invalid') || event.target;
        const panel = invalid.closest('[data-lc-panel]');
        if (panel) showPanel(panel.dataset.lcPanel);
        if (invalid.closest('[data-lc-custom-design]')) page.querySelector('[data-lc-custom-design]').hidden = false;
    }, true);
    showPanel(new URL(location.href).searchParams.get('section'));
    const file = document.getElementById('lc-design-file');
    const input = document.getElementById('lc-design-json');
    const status = document.getElementById('lc-design-status');
    if (!file || !input || !status) return;
    form.addEventListener('submit', () => {
        // Importing is an explicit action on the import panel.
        if (page.querySelector('[data-lc-panel="transfer"]').hidden) input.value = '';
    });
    file.addEventListener('change', async () => {
        const selected = file.files[0];
        if (!selected) return;
        status.hidden = false;
        try {
            if (selected.size > 16384) throw new Error('Maximal 16 KB.');
            const content = await selected.text();
            JSON.parse(content);
            input.value = content;
            status.textContent = 'Bereit. Speichern zum Importieren.';
        } catch (error) {
            input.value = '';
            file.value = '';
            status.textContent = error instanceof SyntaxError ? 'Ungültige JSON.' : error.message;
        }
    });
}());
