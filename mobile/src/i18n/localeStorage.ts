import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';

import { normalizeLocale } from './localeState';
import type { Locale } from './translations';

const localeStorageKey = 'dudho.locale.preference';

function browserStorage(): Storage {
  try {
    const storage = globalThis.localStorage;

    if (storage === undefined) {
      throw new Error('Browser storage is unavailable.');
    }

    return storage;
  } catch {
    throw new Error('Browser locale storage is unavailable.');
  }
}

export async function readPersistedLocale(): Promise<Locale> {
  if (Platform.OS === 'web') {
    return normalizeLocale(browserStorage().getItem(localeStorageKey));
  }

  const storedValue = await SecureStore.getItemAsync(localeStorageKey);

  if (storedValue === null) {
    return 'en';
  }

  return normalizeLocale(storedValue);
}

export async function writePersistedLocale(locale: Locale): Promise<void> {
  const normalized = normalizeLocale(locale);

  if (Platform.OS === 'web') {
    browserStorage().setItem(localeStorageKey, normalized);

    return;
  }

  await SecureStore.setItemAsync(localeStorageKey, normalized);
}
