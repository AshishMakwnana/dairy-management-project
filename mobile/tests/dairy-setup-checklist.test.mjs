import assert from 'node:assert/strict';
import test from 'node:test';

import { getSetupChecklist } from '../src/features/dairies/setupChecklist.ts';

test('marks only dairy registration complete when operational state is unavailable', () => {
  assert.deepEqual(getSetupChecklist(), [
    { id: 'dairy', status: 'complete' },
    { id: 'milk-quality', status: 'unverified' },
    { id: 'pricing', status: 'unverified' },
    { id: 'farmer', status: 'unverified' },
    { id: 'staff', status: 'optional-unverified' },
  ]);
});

test('does not report operational tasks complete without server status', () => {
  const items = getSetupChecklist();

  assert.equal(items.filter((item) => item.status === 'complete').length, 1);
  assert.equal(items.some((item) => item.id === 'staff' && item.status === 'optional-unverified'), true);
});
