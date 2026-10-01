/* Bottom-sheet gestures. Scrolling and interactive controls keep their own input. */
(function () {
    'use strict';

    function bind(dialog, dismiss) {
        const mobile = window.matchMedia('(max-width: 540px), (max-width: 980px) and (max-height: 540px) and (pointer: coarse)');
        const content = dialog.querySelector('.lc-content');
        let gesture;
        let suppressClick = false;
        let clickTimer;

        function reset() {
            gesture = null;
            dialog.classList.remove('lc-dragging');
            dialog.style.removeProperty('--lc-drag-offset');
        }

        function start(event, point) {
            reset();
            const inContent = !!event.target.closest('.lc-content');
            if (!mobile.matches || !dialog.open || dialog.classList.contains('lc-exiting') || !point
                || event.target.closest('button,a,input,label,select,textarea')
                || (inContent && content.scrollTop > 0) || window.getSelection()?.toString()) return;
            gesture = { x: point.clientX, y: point.clientY, distance: 0, dragging: false, inContent };
        }

        function move(event, point) {
            if (!gesture || !point) return;
            const dx = Math.abs(point.clientX - gesture.x);
            const dy = point.clientY - gesture.y;
            if (!gesture.dragging) {
                if (dy < -8 || dx > Math.max(8, dy) || (gesture.inContent && content.scrollTop > 0)) {
                    reset();
                    return;
                }
                if (dy < 8 || !event.cancelable) return;
                gesture.dragging = true;
                dialog.classList.add('lc-dragging');
            }
            if (event.cancelable) event.preventDefault();
            gesture.distance = Math.max(0, dy);
            dialog.style.setProperty('--lc-drag-offset', `${gesture.distance}px`);
        }

        function finish() {
            if (!gesture) return;
            const { dragging, distance } = gesture;
            const threshold = Math.max(64, Math.min(120, dialog.getBoundingClientRect().height * .22));
            if (!dragging) { reset(); return; }
            // A drag ending on a control must not accidentally grant consent.
            suppressClick = true;
            clearTimeout(clickTimer);
            clickTimer = setTimeout(() => { suppressClick = false; }, 400);
            if (distance >= threshold) {
                gesture = null;
                dialog.classList.remove('lc-dragging');
                if (dismiss() !== true) reset();
            } else reset();
        }

        dialog.addEventListener('touchstart', event => {
            if (event.touches.length === 1) start(event, event.touches[0]);
            else reset();
        }, { passive: true });
        dialog.addEventListener('touchmove', event => {
            if (event.touches.length === 1) move(event, event.touches[0]);
            else reset();
        }, { passive: false });
        dialog.addEventListener('touchend', finish);
        dialog.addEventListener('touchcancel', reset);
        dialog.addEventListener('pointerdown', event => {
            if (event.pointerType === 'touch' || event.button !== 0) return;
            start(event, event);
            if (gesture) dialog.setPointerCapture(event.pointerId);
        });
        dialog.addEventListener('pointermove', event => {
            if (event.pointerType !== 'touch') move(event, event);
        });
        dialog.addEventListener('pointerup', event => {
            if (event.pointerType !== 'touch') finish();
        });
        dialog.addEventListener('pointercancel', event => {
            if (event.pointerType !== 'touch') reset();
        });
        dialog.addEventListener('click', event => {
            if (suppressClick) { event.preventDefault(); event.stopImmediatePropagation(); }
        }, true);
        dialog.addEventListener('close', reset);
        mobile.addEventListener('change', reset);
    }

    window.LocalConsentSheet = Object.freeze({ bind });
}());
