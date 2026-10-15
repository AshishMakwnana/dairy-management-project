import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';

import type { AuthSession } from '../api/auth';
import type { DairyCreationDraft } from '../../types/dairy';

const sessionKey = 'dudho.auth.session';
const dairyDraftKey = 'dudho.dairy.creation.draft';
let browserSession: AuthSession | null = null;
let browserDairyDraft: DairyCreationDraft | null = null;

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isAuthSession(value: unknown): value is AuthSession {
  if (!isRecord(value) || !isRecord(value.user)) {
    return false;
  }

  const user = value.user;

  return value.tokenType === 'Bearer'
    && typeof value.accessToken === 'string'
    && (
      value.expiresAt === null
      || (typeof value.expiresAt === 'string' && Number.isFinite(Date.parse(value.expiresAt)))
    )
    && typeof user.id === 'string'
    && typeof user.phone === 'string'
    && (
      user.fullName === null
      || typeof user.fullName === 'string'
    )
    && (
      user.preferredLanguage === 'en'
      || user.preferredLanguage === 'hi'
    )
    && typeof user.status === 'string';
}

export async function readAuthSession(): Promise<AuthSession | null> {
  if (Platform.OS === 'web') {
    if (
      browserSession !== null
      && browserSession.expiresAt !== null
      && Date.parse(browserSession.expiresAt) <= Date.now()
    ) {
      browserSession = null;
    }

    return browserSession;
  }

  const serializedSession = await SecureStore.getItemAsync(sessionKey);

  if (serializedSession === null) {
    return null;
  }

  const storedSession: unknown = JSON.parse(serializedSession);

  if (!isAuthSession(storedSession)) {
    throw new Error('Stored authentication session is invalid.');
  }

  if (storedSession.expiresAt !== null && Date.parse(storedSession.expiresAt) <= Date.now()) {
    await SecureStore.deleteItemAsync(sessionKey);

    return null;
  }

  return storedSession;
}

export async function writeAuthSession(session: AuthSession): Promise<void> {
  if (Platform.OS === 'web') {
    browserSession = session;

    return;
  }

  await SecureStore.setItemAsync(sessionKey, JSON.stringify(session));
}

export async function deleteAuthSession(): Promise<void> {
  if (Platform.OS === 'web') {
    browserSession = null;

    return;
  }

  await SecureStore.deleteItemAsync(sessionKey);
}

function isDairyCreationDraft(value: unknown): value is DairyCreationDraft {
  if (!isRecord(value) || !isRecord(value.values)) {
    return false;
  }

  const values = value.values;

  return typeof value.clientUuid === 'string'
    && (value.status === 'ready' || value.status === 'pending' || value.status === 'conflict')
    && typeof values.name === 'string'
    && typeof values.legalName === 'string'
    && typeof values.city === 'string'
    && typeof values.state === 'string'
    && typeof values.postalCode === 'string';
}

export async function readDairyCreationDraft(): Promise<DairyCreationDraft | null> {
  if (Platform.OS === 'web') {
    return browserDairyDraft;
  }

  const serializedDraft = await SecureStore.getItemAsync(dairyDraftKey);

  if (serializedDraft === null) {
    return null;
  }

  const storedDraft: unknown = JSON.parse(serializedDraft);

  if (!isDairyCreationDraft(storedDraft)) {
    throw new Error('Stored dairy creation draft is invalid.');
  }

  return storedDraft;
}

export async function writeDairyCreationDraft(draft: DairyCreationDraft): Promise<void> {
  if (Platform.OS === 'web') {
    browserDairyDraft = draft;

    return;
  }

  await SecureStore.setItemAsync(dairyDraftKey, JSON.stringify(draft));
}

export async function deleteDairyCreationDraft(): Promise<void> {
  if (Platform.OS === 'web') {
    browserDairyDraft = null;

    return;
  }

  await SecureStore.deleteItemAsync(dairyDraftKey);
}
