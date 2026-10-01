/* Dialog motion is separate from consent state and resource activation. */
(function () {
    'use strict';

    function bind(dialog, fab) {
        const panel = dialog.querySelector('.lc-panel');
        const mobile = window.matchMedia('(max-width: 540px), (max-width: 980px) and (max-height: 540px) and (pointer: coarse)');
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
        let animation;

        function collapsed() {
            if (mobile.matches) return { transform: 'translateY(calc(100% + 24px))', opacity: 1 };
            const card = panel.getBoundingClientRect();
            const button = fab.getBoundingClientRect();
            return {
                transform: `translate(${button.left - card.left}px, ${button.top - card.top}px) scale(${button.width / card.width}, ${button.height / card.height})`,
                opacity: 0,
            };
        }

        function enter() {
            animation?.cancel();
            if (reduced.matches || typeof panel.animate !== 'function') return;
            animation = panel.animate([collapsed(), { transform: 'none', opacity: 1 }], {
                duration: 240, easing: 'cubic-bezier(.2,.8,.2,1)',
            });
        }

        function exit() {
            dialog.classList.add('lc-exiting');
            if (reduced.matches || typeof panel.animate !== 'function') {
                animation?.cancel();
                return Promise.resolve();
            }
            const current = { transform: getComputedStyle(panel).transform, opacity: getComputedStyle(panel).opacity };
            animation?.cancel();
            animation = panel.animate([current, collapsed()], {
                duration: 200, easing: 'cubic-bezier(.4,0,.6,1)', fill: 'forwards',
            });
            // A throttled rendering clock must not postpone consent withdrawal.
            return new Promise(resolve => {
                const timer = setTimeout(resolve, 250);
                animation.finished.catch(() => {}).then(() => { clearTimeout(timer); resolve(); });
            });
        }

        dialog.addEventListener('close', () => {
            if (dialog.open) return;
            animation?.cancel();
            dialog.classList.remove('lc-exiting');
        });
        return { enter, exit };
    }

    window.LocalConsentMotion = Object.freeze({ bind });
}());
