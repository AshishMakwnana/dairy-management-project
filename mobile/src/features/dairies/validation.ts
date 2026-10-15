import type { DairyCreatePayload, DairyFormValues } from '../../types/dairy';

export type DairyFormField = keyof DairyFormValues;

export type DairyFormErrors = Partial<Record<DairyFormField, 'required' | 'too-long'>>;

const fields: DairyFormField[] = ['name', 'legalName', 'city', 'state', 'postalCode'];

export const emptyDairyForm: DairyFormValues = {
  name: '',
  legalName: '',
  city: '',
  state: '',
  postalCode: '',
};

export function validateDairyForm(values: DairyFormValues): DairyFormErrors {
  const errors: DairyFormErrors = {};
  const lengths: Record<DairyFormField, number> = {
    name: 191,
    legalName: 191,
    city: 150,
    state: 150,
    postalCode: 20,
  };

  for (const field of fields) {
    const value = values[field].trim();

    if (value.length > lengths[field]) {
      errors[field] = 'too-long';
    } else if (value === '' && ['name', 'city', 'state'].includes(field)) {
      errors[field] = 'required';
    }
  }

  return errors;
}

export function toDairyCreatePayload(
  values: DairyFormValues,
  clientUuid: string,
): DairyCreatePayload {
  const payload: DairyCreatePayload = {
    name: values.name.trim(),
    location: {
      city: values.city.trim(),
      state: values.state.trim(),
      country: 'IN',
    },
    client_uuid: clientUuid,
  };

  if (values.legalName.trim() !== '') {
    payload.legal_name = values.legalName.trim();
  }

  if (values.postalCode.trim() !== '') {
    payload.location.postal_code = values.postalCode.trim();
  }

  return payload;
}
