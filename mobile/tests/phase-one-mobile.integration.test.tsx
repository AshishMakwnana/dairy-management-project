import React from 'react';
import { afterEach, beforeEach, expect, jest, test } from '@jest/globals';
import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from '@testing-library/react-native';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { DairyListScreen } from '../src/features/dairies/DairyListScreen';
import { DairyOnboardingScreen } from '../src/features/dairies/DairyOnboardingScreen';
import { LoginScreen } from '../src/features/auth/LoginScreen';
import { LocaleProvider } from '../src/i18n/LocaleProvider';
import { AuthApiError, logout, sendOtp } from '../src/services/api/auth';
import { AuthSessionProvider } from '../src/services/auth/AuthSessionProvider';
import { logoutSessionSafely } from '../src/services/auth/logout';
import DairiesLayout from '../src/app/dairies/_layout';
import {
  mockRouterPush,
  mockRouterReplace,
  mockSecureStoreData,
} from './integration.setup';

const mockFetch = jest.fn<typeof fetch>();
const originalFetch = globalThis.fetch;
const originalPlatformDescriptor = Object.getOwnPropertyDescriptor(Platform, 'OS');
const originalLocalStorageDescriptor = Object.getOwnPropertyDescriptor(globalThis, 'localStorage');

const sessionKey = 'dudho.auth.session';
const localeKey = 'dudho.locale.preference';
const dairyDraftKey = 'dudho.dairy.creation.draft';
const session = {
  accessToken: 'integration-session-token',
  tokenType: 'Bearer' as const,
  expiresAt: null,
  user: {
    id: 'user-uuid',
    phone: '+919876543210',
    fullName: 'Asha',
    preferredLanguage: 'en' as const,
    status: 'active',
  },
};

function setPlatformOS(os: 'ios' | 'web') {
  Object.defineProperty(Platform, 'OS', { configurable: true, value: os });
}

function setLocalStorage(data: Map<string, string>) {
  Object.defineProperty(globalThis, 'localStorage', {
    configurable: true,
    value: {
      getItem: (key: string) => data.get(key) ?? null,
      setItem: (key: string, value: string) => data.set(key, value),
      removeItem: (key: string) => data.delete(key),
      clear: () => data.clear(),
    },
  });
}

function apiResponse(status: number, body?: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: { get: () => null },
    json: async () => body,
  } as unknown as Response;
}

function mountWithProviders(child: React.ReactElement) {
  return render(
    <LocaleProvider>
      <AuthSessionProvider>{child}</AuthSessionProvider>
    </LocaleProvider>,
  );
}

function storeSession(value = session, expiresAt: string | null = value.expiresAt) {
  mockSecureStoreData.set(sessionKey, JSON.stringify({ ...value, expiresAt }));
}

beforeEach(() => {
  cleanup();
  mockSecureStoreData.clear();
  mockRouterPush.mockReset();
  mockRouterReplace.mockReset();
  setPlatformOS('ios');
  setLocalStorage(new Map());
  mockFetch.mockReset();
  globalThis.fetch = mockFetch;
  jest.mocked(SecureStore.getItemAsync).mockImplementation(
    async (key: string) => mockSecureStoreData.get(key) ?? null,
  );
  jest.mocked(SecureStore.setItemAsync).mockImplementation(async (key, value) => {
    mockSecureStoreData.set(key, value);
  });
  jest.mocked(SecureStore.deleteItemAsync).mockImplementation(async (key) => {
    mockSecureStoreData.delete(key);
  });
});

afterEach(() => {
  cleanup();
  globalThis.fetch = originalFetch;

  if (originalPlatformDescriptor !== undefined) {
    Object.defineProperty(Platform, 'OS', originalPlatformDescriptor);
  }

  if (originalLocalStorageDescriptor !== undefined) {
    Object.defineProperty(globalThis, 'localStorage', originalLocalStorageDescriptor);
  } else {
    Reflect.deleteProperty(globalThis, 'localStorage');
  }
});

test('persists a pre-authentication locale in browser storage and restores it after remount', async () => {
  setPlatformOS('web');
  const browserValues = new Map<string, string>();
  setLocalStorage(browserValues);

  const firstMount = mountWithProviders(<LoginScreen />);
  expect(await screen.findByText('Sign in to DUDHO')).toBeTruthy();
  fireEvent.press(screen.getByRole('button', { name: 'Change language' }));

  await waitFor(() => expect(browserValues.get(localeKey)).toBe('hi'));
  expect(await screen.findByText('DUDHO में साइन इन करें')).toBeTruthy();
  firstMount.unmount();

  mountWithProviders(<LoginScreen />);
  expect(await screen.findByText('DUDHO में साइन इन करें')).toBeTruthy();
});

test('defaults a missing or invalid web locale to English without blocking sign-in', async () => {
  setPlatformOS('web');
  const browserValues = new Map([[localeKey, 'fr']]);
  setLocalStorage(browserValues);

  mountWithProviders(<LoginScreen />);
  expect(await screen.findByText('Sign in to DUDHO')).toBeTruthy();
  expect(screen.queryByText('Your language choice could not be saved on this device.')).toBeNull();
});

test('persists the pre-authentication locale in native SecureStore and safely falls back on a failed read', async () => {
  const mount = mountWithProviders(<LoginScreen />);
  expect(await screen.findByText('Sign in to DUDHO')).toBeTruthy();
  fireEvent.press(screen.getByRole('button', { name: 'Change language' }));
  await waitFor(() => expect(mockSecureStoreData.get(localeKey)).toBe('hi'));
  mount.unmount();

  jest.mocked(SecureStore.getItemAsync).mockImplementation(async (key: string) => {
    if (key === localeKey) {
      throw new Error('secure storage read failed');
    }

    return mockSecureStoreData.get(key) ?? null;
  });

  mountWithProviders(<LoginScreen />);
  expect(await screen.findByText('Your language choice could not be saved on this device.')).toBeTruthy();
  expect(screen.getByText('Sign in to DUDHO')).toBeTruthy();
});

test('restores a valid native session', async () => {
  storeSession();
  mountWithProviders(<LoginScreen />);
  expect(await screen.findByRole('button', { name: 'Sign out' })).toBeTruthy();
});

test('clears an expired native session during restoration', async () => {
  storeSession(session, new Date(Date.now() - 60_000).toISOString());
  mountWithProviders(<LoginScreen />);
  expect(await screen.findByText('Sign in to DUDHO')).toBeTruthy();
  expect(mockSecureStoreData.has(sessionKey)).toBe(false);
});

test('logs out successfully, clears local session, and returns to the login screen', async () => {
  storeSession();
  mockFetch.mockResolvedValue(apiResponse(200, {
    success: true,
    message: 'Signed out successfully.',
    data: null,
    meta: {},
  }));
  mountWithProviders(<LoginScreen />);

  fireEvent.press(await screen.findByRole('button', { name: 'Sign out' }));

  expect(await screen.findByText('Sign in to DUDHO')).toBeTruthy();
  expect(mockSecureStoreData.has(sessionKey)).toBe(false);
  expect(mockFetch).toHaveBeenCalledWith(
    expect.stringContaining('/auth/logout'),
    expect.objectContaining({
      method: 'POST',
      headers: expect.objectContaining({
        Authorization: `Bearer ${session.accessToken}`,
      }),
    }),
  );
  expect(screen.queryByText(session.accessToken)).toBeNull();
});

test.each([401, 403])('clears the local session after logout status %s', async (status) => {
  storeSession();
  mockFetch.mockResolvedValue(apiResponse(status));
  mountWithProviders(<LoginScreen />);

  fireEvent.press(await screen.findByRole('button', { name: 'Sign out' }));

  expect(await screen.findByText('Sign in to DUDHO')).toBeTruthy();
  expect(mockSecureStoreData.has(sessionKey)).toBe(false);
});

test.each([
  ['network', () => Promise.reject(new TypeError('fetch failed')), 'Could not contact the service.'],
  ['server', () => Promise.resolve(apiResponse(503)), 'The service could not sign you out.'],
  ['invalid-response', () => Promise.resolve(apiResponse(200, { success: false })), 'unexpected response'],
])('preserves the session and shows a translated %s logout failure', async (_kind, fetchResult, message) => {
  storeSession();
  mockFetch.mockImplementation(fetchResult);
  mountWithProviders(<LoginScreen />);

  fireEvent.press(await screen.findByRole('button', { name: 'Sign out' }));

  expect(await screen.findByText(new RegExp(message, 'i'))).toBeTruthy();
  expect(screen.getByRole('button', { name: 'Sign out' })).toBeTruthy();
  expect(mockSecureStoreData.has(sessionKey)).toBe(true);
});

test('prevents duplicate logout requests while the first request is pending', async () => {
  storeSession();
  let resolveResponse: ((response: Response) => void) | undefined;
  mockFetch.mockImplementation(() => new Promise((resolve) => {
    resolveResponse = resolve;
  }));
  mountWithProviders(<LoginScreen />);
  const button = await screen.findByRole('button', { name: 'Sign out' });

  fireEvent.press(button);
  fireEvent.press(button);
  expect(mockFetch).toHaveBeenCalledTimes(1);

  resolveResponse?.(apiResponse(200, { success: true, data: null }));
  expect(await screen.findByText('Sign in to DUDHO')).toBeTruthy();
});

test('maps authentication API errors to explicit response outcomes', async () => {
  mockFetch.mockResolvedValue(apiResponse(422, {
    success: false,
    message: 'Validation failed.',
    errors: { phone: ['Invalid.'] },
  }));
  await expect(sendOtp('+919876543210', 'en')).rejects.toMatchObject({
    kind: 'validation',
  });

  mockFetch.mockResolvedValue(apiResponse(401));
  await expect(logout(session.accessToken)).rejects.toBeInstanceOf(AuthApiError);
  await expect(logout(session.accessToken)).rejects.toMatchObject({ kind: 'unauthorized' });

  mockFetch.mockResolvedValue(apiResponse(403));
  await expect(logout(session.accessToken)).rejects.toMatchObject({ kind: 'forbidden' });

  mockFetch.mockRejectedValue(new TypeError('fetch failed'));
  await expect(logout(session.accessToken)).rejects.toMatchObject({ kind: 'network' });

  await expect(logoutSessionSafely(session.accessToken, async () => {
    throw new Error('401 in an unrelated diagnostic message');
  })).rejects.toThrow('401 in an unrelated diagnostic message');
});

test('allows navigation to a dairy returned by the authenticated API', async () => {
  storeSession();
  mockFetch.mockResolvedValue(apiResponse(200, {
    success: true,
    data: [{
      id: 'dairy-uuid',
      name: 'Green Valley Dairy',
      state: 'draft',
      location: { city: 'Indore', state: 'Madhya Pradesh', country: 'IN' },
    }],
  }));
  mountWithProviders(<DairyListScreen />);

  fireEvent.press(await screen.findByRole('button', {
    name: 'Open dairy dashboard: Green Valley Dairy',
  }));
  expect(mockRouterPush).toHaveBeenCalledWith({
    pathname: '/dairies/[dairyId]',
    params: { dairyId: 'dairy-uuid' },
  });
});

test('redirects unauthenticated access to the login route', async () => {
  mountWithProviders(<DairiesLayout />);
  expect(await screen.findByTestId('route-redirect')).toBeTruthy();
  expect(screen.getByText('/')).toBeTruthy();
});

test('clears an expired token after the dairy API denies access', async () => {
  storeSession();
  mockFetch.mockResolvedValue(apiResponse(401));
  mountWithProviders(<DairyListScreen />);

  expect(await screen.findByText('Your sign-in has expired. Please sign in again.')).toBeTruthy();
  await waitFor(() => expect(mockRouterReplace).toHaveBeenCalledWith('/'));
  expect(mockSecureStoreData.has(sessionKey)).toBe(false);
});

test('onboarding retries preserve the same client UUID and submitted payload', async () => {
  storeSession();
  mockFetch
    .mockResolvedValueOnce(apiResponse(503))
    .mockResolvedValueOnce(apiResponse(201, {
      success: true,
      data: {
        id: 'created-dairy-uuid',
        name: 'Green Valley Dairy',
        state: 'draft',
        location: { city: 'Indore', state: 'Madhya Pradesh', country: 'IN' },
      },
    }));
  mountWithProviders(<DairyOnboardingScreen />);

  fireEvent.changeText(await screen.findByLabelText('Dairy name *'), 'Green Valley Dairy');
  fireEvent.changeText(screen.getByLabelText('City or village *'), 'Indore');
  fireEvent.changeText(screen.getByLabelText('State *'), 'Madhya Pradesh');
  fireEvent.press(screen.getByRole('button', { name: 'Create dairy' }));
  expect(await screen.findByText('The service could not complete this request. Please try again.')).toBeTruthy();

  const firstBody = JSON.parse(mockFetch.mock.calls[0][1]?.body as string);
  const pendingDraft = JSON.parse(mockSecureStoreData.get(dairyDraftKey) as string);
  expect(pendingDraft.clientUuid).toBe(firstBody.client_uuid);
  expect(pendingDraft.status).toBe('pending');

  fireEvent.press(screen.getByRole('button', { name: 'Retry same request' }));
  await waitFor(() => expect(mockRouterReplace).toHaveBeenCalled());

  const secondBody = JSON.parse(mockFetch.mock.calls[1][1]?.body as string);
  expect(secondBody).toEqual(firstBody);
  expect(mockSecureStoreData.has(dairyDraftKey)).toBe(false);
});
