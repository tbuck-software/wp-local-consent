const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readRecord, makeRecord } = require('../assets/consent.js');
const config = { revision: 'a', expiryDays: 180, services: { google_maps: {}, google_tags: {} } };
const now = 1700000000000;

test('only explicit booleans grant consent; unknown services are excluded', () => {
    const record = makeRecord({ google_maps: true, google_tags: 'true', unknown: true }, config, now);
    assert.deepEqual(record.choices, { google_maps: true, google_tags: false });
    assert.deepEqual(readRecord(JSON.stringify(record), config, now), record);
});

test('reject-all survives a subsequent visit', () => {
    const record = makeRecord({}, config, now);
    assert.deepEqual(readRecord(JSON.stringify(record), config, now + 100).choices, { google_maps: false, google_tags: false });
});

test('expired, future, corrupt and configuration-mismatched records never grant consent', () => {
    const valid = makeRecord({ google_maps: true }, config, now);
    const invalid = [
        null, '{', 'true', '{}', '[]',
        JSON.stringify({ ...valid, revision: 'other' }),
        JSON.stringify({ ...valid, version: 2 }),
        JSON.stringify({ ...valid, updatedAt: now + 1 }),
        JSON.stringify({ ...valid, expiresAt: now }),
        JSON.stringify({ ...valid, expiresAt: valid.expiresAt + 1 }),
        JSON.stringify({ ...valid, choices: { google_maps: true } }),
        JSON.stringify({ ...valid, choices: { google_maps: 'true', google_tags: false } }),
        JSON.stringify({ ...valid, choices: { google_maps: true, google_tags: false, unknown: true } }),
    ];
    invalid.forEach(raw => assert.equal(readRecord(raw, config, now), null, String(raw)));
    assert.equal(readRecord(JSON.stringify(valid), config, valid.expiresAt), null);
});

test('adding or removing a service invalidates a previous selection', () => {
    const raw = JSON.stringify(makeRecord({ google_maps: true }, config, now));
    assert.equal(readRecord(raw, { ...config, services: { google_maps: {} } }, now), null);
    assert.equal(readRecord(raw, { ...config, services: { ...config.services, youtube: {} } }, now), null);
});

test('design preview never reads or activates saved visitor grants, including after a redirect', () => {
    const { readFileSync } = require('node:fs');
    const { runInNewContext } = require('node:vm');
    const source = readFileSync(require.resolve('../assets/consent.js'), 'utf8');
    for (const [designPreview, name] of [[true, ''], [false, 'local-consent-design-preview']]) {
        const document = {
            getElementById(id) {
                if (id === 'local-consent-config') return { textContent: JSON.stringify({ ...config, designPreview }) };
                if (id === 'lc-dialog') return { showModal() {} };
                throw new Error('Preview attempted to initialize consent controls');
            }
        };
        const localStorage = { getItem() { throw new Error('Preview attempted to restore saved grants'); } };
        assert.doesNotThrow(() => runInNewContext(source, { document, window: { name }, localStorage }));
    }
});
