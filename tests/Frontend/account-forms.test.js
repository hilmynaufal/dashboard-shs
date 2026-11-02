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
        querySelectorAll(selector) { return selector === 'button[type="submit"]' ? [button] : []; },
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

test('multi-action forms retain the clicked intent, block duplicate submit, and restore server-disabled states', () => {
    const buttons = [
        { dataset: {}, disabled: false, textContent: 'Simpan draf', name: 'intent', value: 'save' },
        { dataset: {}, disabled: false, textContent: 'Simpan & selesai', name: 'intent', value: 'complete' },
        { dataset: {}, disabled: true, textContent: 'Aksi belum tersedia' },
    ];
    const hidden = [], listeners = new Map();
    const form = {
        dataset: {}, action: '/reviu/1',
        addEventListener(name, callback) { this[name] = callback; },
        querySelector(selector) { return selector === 'button[type="submit"]' ? buttons[0] : null; },
        querySelectorAll(selector) { return selector === 'button[type="submit"]' ? buttons : hidden; },
        append(input) { hidden.push(input); },
    };
    runInNewContext(readFileSync(new URL('../../resources/js/accounts.js', import.meta.url), 'utf8'), {
        document: {
            querySelectorAll(selector) {
                if (selector === '[data-submit-form]') return [form];
                if (selector === '[data-submit-form] button[type="submit"]') return buttons;
                return [];
            },
            createElement() { return { dataset: {}, remove() { hidden.splice(hidden.indexOf(this), 1); } }; },
            querySelector() { return null; }, addEventListener() {},
            body: { classList: { toggle() {}, contains() { return false; } } },
        },
        window: {
            matchMedia() { return { matches: false, addEventListener() {} }; },
            addEventListener(name, callback) { listeners.set(name, callback); }, confirm() { return true; },
        },
    });
    form.submit({ submitter: buttons[1], preventDefault() { assert.fail('first submit was prevented'); } });
    assert.equal(hidden.length, 1);
    assert.equal(hidden[0].name, 'intent');
    assert.equal(hidden[0].value, 'complete');
    assert.equal(buttons.every((button) => button.disabled), true);
    let prevented = false;
    form.submit({ submitter: buttons[0], preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(hidden.length, 1);
    listeners.get('pageshow')();
    assert.equal(hidden.length, 0);
    assert.equal(buttons[0].disabled, false);
    assert.equal(buttons[1].disabled, false);
    assert.equal(buttons[1].textContent, 'Simpan & selesai');
    assert.equal(buttons[2].disabled, true);
    form.submit({ submitter: buttons[0], preventDefault() {} });
    assert.equal(hidden[0].value, 'save');
});
