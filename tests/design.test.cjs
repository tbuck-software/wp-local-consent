const { test } = require('node:test');
const assert = require('node:assert/strict');
const { rgb, contrast, readableColor } = require('../assets/design.js');

test('transparent theme surfaces do not become banner backgrounds', () => {
    assert.equal(rgb('rgba(255, 255, 255, 0)'), null);
    assert.deepEqual(rgb('rgb(0, 49, 84)'), [0, 49, 84]);
});

test('automatic colors preserve readable pairs and repair low contrast', () => {
    assert.equal(contrast([0, 0, 0], [255, 255, 255]), 21);
    assert.deepEqual(readableColor([102, 102, 102], [255, 255, 255]), [102, 102, 102]);
    assert.deepEqual(readableColor([255, 255, 255], [255, 255, 255]), [0, 0, 0]);
    assert.deepEqual(readableColor([0, 0, 0], [0, 49, 84]), [255, 255, 255]);
});
