import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

test('page restoration preserves server-disabled actions and restores only submitted buttons', () => {
    const buttons = [
        { dataset: {}, disabled: true, textContent: 'Kirim usulan' },
        { dataset: {}, disabled: false, textContent: 'Simpan item' },
    ];
    const listeners = new Map();
    const forms = buttons.map((button) => ({
        dataset: {}, action: '/usulan/1/item',
        addEventListener(name, callback) { this[name] = callback; },
        querySelector(selector) { return selector === 'button[type="submit"]' ? button : null; },
    }));
    runInNewContext(readFileSync(new URL('../../resources/js/accounts.js', import.meta.url), 'utf8'), {
        document: {
            querySelectorAll(selector) {
                if (selector === '[data-submit-form]') return forms;
                if (selector === '[data-submit-form] button[type="submit"]') return buttons;
                return [];
            },
            querySelector() { return null; }, addEventListener() {},
            body: { classList: { toggle() {}, contains() { return false; } } },
        },
        window: {
            matchMedia() { return { matches: false, addEventListener() {} }; },
            addEventListener(name, callback) { listeners.set(name, callback); },
            confirm() { return true; },
        },
    });
    listeners.get('pageshow')();
    assert.equal(buttons[0].disabled, true);
    assert.equal(buttons[1].disabled, false);
    forms[1].submit({ preventDefault() {} });
    assert.equal(buttons[1].disabled, true);
    assert.equal(buttons[1].textContent, 'Menyimpan…');
    listeners.get('pageshow')();
    assert.equal(buttons[0].disabled, true);
    assert.equal(buttons[0].textContent, 'Kirim usulan');
    assert.equal(buttons[1].disabled, false);
    assert.equal(buttons[1].textContent, 'Simpan item');
    assert.equal(Object.hasOwn(buttons[1].dataset, 'originalLabel'), false);
});
