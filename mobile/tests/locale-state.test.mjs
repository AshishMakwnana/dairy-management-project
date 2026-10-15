import assert from 'node:assert/strict';
import test from 'node:test';

import { normalizeLocale } from '../src/i18n/localeState.ts';

test('normalizes missing and unsupported locale values to English', () => {
  assert.equal(normalizeLocale(undefined), 'en');
  assert.equal(normalizeLocale(null), 'en');
  assert.equal(normalizeLocale('fr'), 'en');
  assert.equal(normalizeLocale('en'), 'en');
});

test('preserves the supported Hindi locale', () => {
  assert.equal(normalizeLocale('hi'), 'hi');
});
