const { test } = require('node:test');
const fs = require('node:fs');
const vm = require('node:vm');

test('dialog closure completes even when the browser stops advancing animation frames', async () => {
    const animation = { finished: new Promise(() => {}), cancel() {} };
    const panel = {
        getBoundingClientRect: () => ({ left: 16, top: 300, width: 400, height: 420 }),
        animate: () => animation,
    };
    const dialog = { querySelector: () => panel, classList: { add() {}, remove() {} }, addEventListener() {} };
    const fab = { getBoundingClientRect: () => ({ left: 16, top: 736, width: 48, height: 48 }) };
    const window = { matchMedia: () => ({ matches: false }) };
    vm.runInNewContext(fs.readFileSync(require.resolve('../assets/motion.js'), 'utf8'), {
        window, getComputedStyle: () => ({ transform: 'none', opacity: '1' }), setTimeout, clearTimeout,
    });
    let deadline;
    try {
        await Promise.race([
            window.LocalConsentMotion.bind(dialog, fab).exit(),
            new Promise((_, reject) => { deadline = setTimeout(() => reject(new Error('Closing waited for stalled animation')), 1000); }),
        ]);
    } finally { clearTimeout(deadline); }
});
