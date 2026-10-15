import { environment } from '../../config/environment';
import type { Locale } from '../../i18n/translations';

export type AuthUser = {
  id: string;
  phone: string;
  fullName: string | null;
  preferredLanguage: Locale;
  status: string;
};

export type AuthSession = {
  accessToken: string;
  tokenType: 'Bearer';
  expiresAt: string | null;
  user: AuthUser;
};

export type OtpSendResult = {
  expiresAt: string;
  retryAfterSeconds: number;
};

export type AuthApiErrorKind =
  | 'network'
  | 'unauthorized'
  | 'forbidden'
  | 'validation'
  | 'rate-limited'
  | 'server'
  | 'invalid-response';

export class AuthApiError extends Error {
  constructor(
    public readonly kind: AuthApiErrorKind,
    public readonly retryAfterSeconds?: number,
  ) {
    super(`Authentication request failed: ${kind}.`);
    this.name = 'AuthApiError';
  }
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function positiveInteger(value: unknown): number | undefined {
  const parsed = typeof value === 'number' ? value : Number(value);

  return Number.isInteger(parsed) && parsed > 0 ? parsed : undefined;
}

function retryAfter(response: Response, envelope: unknown): number | undefined {
  const headerValue = response.headers.get('Retry-After');
  const headerSeconds = headerValue === null ? undefined : positiveInteger(headerValue);

  if (headerSeconds !== undefined) {
    return headerSeconds;
  }

  if (isRecord(envelope) && isRecord(envelope.meta)) {
    return positiveInteger(envelope.meta.retry_after_seconds);
  }

  return undefined;
}

async function post(path: string, body: Record<string, string>): Promise<unknown> {
  let response: Response;

  try {
    response = await fetch(`${environment.apiBaseUrl}${path}`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(body),
    });
  } catch {
    throw new AuthApiError('network');
  }

  if (!response.ok) {
    if (response.status === 401) {
      throw new AuthApiError('unauthorized');
    }

    if (response.status === 403) {
      throw new AuthApiError('forbidden');
    }

    let errorEnvelope: unknown;

    try {
      errorEnvelope = await response.json();
    } catch {
      errorEnvelope = undefined;
    }

    if (response.status === 429) {
      throw new AuthApiError('rate-limited', retryAfter(response, errorEnvelope));
    }

    if (response.status === 422) {
      throw new AuthApiError('validation');
    }

    throw new AuthApiError('server');
  }

  let envelope: unknown;

  try {
    envelope = await response.json();
  } catch {
    throw new AuthApiError('invalid-response');
  }

  if (!isRecord(envelope) || envelope.success !== true || !('data' in envelope)) {
    throw new AuthApiError('invalid-response');
  }

  return envelope.data;
}

export async function logout(accessToken: string): Promise<void> {
  let response: Response;

  try {
    response = await fetch(`${environment.apiBaseUrl}/auth/logout`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${accessToken}`,
      },
    });
  } catch {
    throw new AuthApiError('network');
  }

  if (response.status === 401) {
    throw new AuthApiError('unauthorized');
  }

  if (response.status === 403) {
    throw new AuthApiError('forbidden');
  }

  if (!response.ok) {
    throw new AuthApiError('server');
  }

  let envelope: unknown;

  try {
    envelope = await response.json();
  } catch {
    throw new AuthApiError('invalid-response');
  }

  if (!isRecord(envelope) || envelope.success !== true || !('data' in envelope)) {
    throw new AuthApiError('invalid-response');
  }
}

export async function sendOtp(phone: string, locale: Locale): Promise<OtpSendResult> {
  const data = await post('/auth/otp/send', { phone, channel: 'sms', locale });
  const retryAfterSeconds = isRecord(data)
    ? positiveInteger(data.retry_after_seconds)
    : undefined;

  if (
    !isRecord(data)
    || data.delivery_channel !== 'sms'
    || typeof data.expires_at !== 'string'
    || retryAfterSeconds === undefined
  ) {
    throw new AuthApiError('invalid-response');
  }

  return {
    expiresAt: data.expires_at,
    retryAfterSeconds,
  };
}

export async function verifyOtp(
  phone: string,
  code: string,
  clientUuid: string,
): Promise<AuthSession> {
  const data = await post('/auth/otp/verify', { phone, code, client_uuid: clientUuid });

  if (
    !isRecord(data)
    || typeof data.access_token !== 'string'
    || data.token_type !== 'Bearer'
    || !(data.expires_at === null || typeof data.expires_at === 'string')
    || !isRecord(data.user)
    || typeof data.user.id !== 'string'
    || typeof data.user.phone !== 'string'
    || !(data.user.full_name === null || typeof data.user.full_name === 'string')
    || !(data.user.preferred_language === 'en' || data.user.preferred_language === 'hi')
    || typeof data.user.status !== 'string'
  ) {
    throw new AuthApiError('invalid-response');
  }

  return {
    accessToken: data.access_token,
    tokenType: 'Bearer',
    expiresAt: data.expires_at,
    user: {
      id: data.user.id,
      phone: data.user.phone,
      fullName: data.user.full_name,
      preferredLanguage: data.user.preferred_language,
      status: data.user.status,
    },
  };
}
