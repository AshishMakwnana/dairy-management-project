import assert from 'node:assert/strict';
import test from 'node:test';

import {
  isValidIndianMobile,
  normalizeIndianMobileInput,
  normalizeOtpInput,
  toIndianE164,
} from '../src/features/auth/phoneValidation.ts';

test('normalizes local and pasted Indian mobile numbers', () => {
  assert.equal(normalizeIndianMobileInput('98765 43210'), '9876543210');
  assert.equal(normalizeIndianMobileInput('+91 98765-43210'), '9876543210');
  assert.equal(normalizeIndianMobileInput('987654321098'), '9876543210');
});

test('accepts only ten-digit Indian mobile numbers beginning with 6 through 9', () => {
  assert.equal(isValidIndianMobile('9876543210'), true);
  assert.equal(isValidIndianMobile('5876543210'), false);
  assert.equal(isValidIndianMobile('987654321'), false);
});

test('converts a valid mobile number to the API E.164 format', () => {
  assert.equal(toIndianE164('9876543210'), '+919876543210');
  assert.equal(toIndianE164('5876543210'), null);
});

test('normalizes OTP input to at most six digits', () => {
  assert.equal(normalizeOtpInput('12a34-56789'), '123456');
});
