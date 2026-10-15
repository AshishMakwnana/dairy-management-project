import { environment } from '../../config/environment';
import type { Dairy, DairyCreatePayload } from '../../types/dairy';

export type DairyApiErrorKind =
  | 'network'
  | 'unauthorized'
  | 'validation'
  | 'conflict'
  | 'server'
  | 'invalid-response';

export class DairyApiError extends Error {
  constructor(
    public readonly kind: DairyApiErrorKind,
    public readonly validationFields: string[] = [],
  ) {
    super(`Dairy request failed: ${kind}.`);
    this.name = 'DairyApiError';
  }
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function parseDairy(value: unknown): Dairy {
  if (
    !isRecord(value)
    || typeof value.id !== 'string'
    || typeof value.name !== 'string'
    || !(value.state === 'draft' || value.state === 'complete')
    || !isRecord(value.location)
    || !(value.location.city === null || typeof value.location.city === 'string')
    || !(value.location.state === null || typeof value.location.state === 'string')
    || typeof value.location.country !== 'string'
  ) {
    throw new DairyApiError('invalid-response');
  }

  return {
    id: value.id,
    name: value.name,
    state: value.state,
    location: {
      city: value.location.city,
      state: value.location.state,
      country: value.location.country,
    },
  };
}

async function request(
  path: string,
  accessToken: string,
  method: 'GET' | 'POST',
  payload?: DairyCreatePayload,
): Promise<unknown> {
  let response: Response;

  try {
    response = await fetch(`${environment.apiBaseUrl}${path}`, {
      method,
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${accessToken}`,
        ...(payload === undefined ? {} : { 'Content-Type': 'application/json' }),
      },
      ...(payload === undefined ? {} : { body: JSON.stringify(payload) }),
    });
  } catch {
    throw new DairyApiError('network');
  }

  let envelope: unknown;

  try {
    envelope = await response.json();
  } catch {
    throw new DairyApiError('invalid-response');
  }

  if (!response.ok) {
    if (response.status === 401) {
      throw new DairyApiError('unauthorized');
    }

    if (response.status === 422) {
      const errors = isRecord(envelope) && isRecord(envelope.errors)
        ? Object.keys(envelope.errors)
        : [];

      throw new DairyApiError('validation', errors);
    }

    if (response.status === 409) {
      throw new DairyApiError('conflict');
    }

    throw new DairyApiError('server');
  }

  if (!isRecord(envelope) || envelope.success !== true || !('data' in envelope)) {
    throw new DairyApiError('invalid-response');
  }

  return envelope.data;
}

export async function listDairies(accessToken: string): Promise<Dairy[]> {
  const data = await request('/dairies', accessToken, 'GET');

  if (!Array.isArray(data)) {
    throw new DairyApiError('invalid-response');
  }

  return data.map(parseDairy);
}

export async function createDairy(
  accessToken: string,
  payload: DairyCreatePayload,
): Promise<Dairy> {
  return parseDairy(await request('/dairies', accessToken, 'POST', payload));
}
