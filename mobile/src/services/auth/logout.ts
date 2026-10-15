import { AuthApiError } from '../api/auth';

export type LogoutOutcome =
  | 'success'
  | 'unauthorized'
  | 'forbidden'
  | 'network'
  | 'server'
  | 'invalid-response';

export async function logoutSessionSafely(
  accessToken: string,
  request: (token: string) => Promise<void>,
): Promise<LogoutOutcome> {
  try {
    await request(accessToken);

    return 'success';
  } catch (error: unknown) {
    if (!(error instanceof AuthApiError)) {
      throw error;
    }

    switch (error.kind) {
      case 'unauthorized':
        return 'unauthorized';
      case 'forbidden':
        return 'forbidden';
      case 'network':
      case 'server':
      case 'invalid-response':
        return error.kind;
      case 'validation':
      case 'rate-limited':
        return 'server';
    }
  }
}
