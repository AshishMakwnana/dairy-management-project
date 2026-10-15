import assert from 'node:assert/strict';
import test from 'node:test';

import {
  emptyDairyForm,
  toDairyCreatePayload,
  validateDairyForm,
} from '../src/features/dairies/validation.ts';

test('requires a dairy name, city, and state', () => {
  assert.deepEqual(validateDairyForm(emptyDairyForm), {
    name: 'required',
    city: 'required',
    state: 'required',
  });
});

test('enforces the API field length limits', () => {
  assert.deepEqual(validateDairyForm({
    name: 'D'.repeat(192),
    legalName: 'L'.repeat(192),
    city: 'C'.repeat(151),
    state: 'S'.repeat(151),
    postalCode: '1'.repeat(21),
  }), {
    name: 'too-long',
    legalName: 'too-long',
    city: 'too-long',
    state: 'too-long',
    postalCode: 'too-long',
  });
});

test('builds only the contracted payload and fixes onboarding country to IN', () => {
  assert.deepEqual(toDairyCreatePayload({
    name: '  Green Valley Dairy ',
    legalName: ' Green Valley Dairy Ltd ',
    city: ' Bhopal ',
    state: ' Madhya Pradesh ',
    postalCode: ' 462001 ',
  }, '6ba7b810-9dad-11d1-80b4-00c04fd430c8'), {
    name: 'Green Valley Dairy',
    legal_name: 'Green Valley Dairy Ltd',
    location: {
      city: 'Bhopal',
      state: 'Madhya Pradesh',
      country: 'IN',
      postal_code: '462001',
    },
    client_uuid: '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
  });
});

test('omits blank optional fields from the create request', () => {
  assert.deepEqual(toDairyCreatePayload({
    name: 'Village Dairy',
    legalName: '   ',
    city: 'Sehore',
    state: 'Madhya Pradesh',
    postalCode: '',
  }, '6ba7b810-9dad-11d1-80b4-00c04fd430c8'), {
    name: 'Village Dairy',
    location: {
      city: 'Sehore',
      state: 'Madhya Pradesh',
      country: 'IN',
    },
    client_uuid: '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
  });
});
