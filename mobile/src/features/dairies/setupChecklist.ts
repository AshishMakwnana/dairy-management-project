import type { SetupChecklistItem } from '../../types/dairy';

export function getSetupChecklist(): SetupChecklistItem[] {
  return [
    { id: 'dairy', status: 'complete' },
    { id: 'milk-quality', status: 'unverified' },
    { id: 'pricing', status: 'unverified' },
    { id: 'farmer', status: 'unverified' },
    { id: 'staff', status: 'optional-unverified' },
  ];
}
