import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import type { PropsWithChildren } from 'react';

import { normalizeLocale } from './localeState';
import { readPersistedLocale, writePersistedLocale } from './localeStorage';
import { translations } from './translations';
import type { Locale, TranslationKey } from './translations';

type LocaleContextValue = {
  locale: Locale;
  isLocaleReady: boolean;
  storageError: boolean;
  setLocale: (locale: Locale) => Promise<void>;
  t: (key: TranslationKey) => string;
};

const LocaleContext = createContext<LocaleContextValue | null>(null);

export function LocaleProvider({ children }: PropsWithChildren) {
  const [locale, setLocaleState] = useState<Locale>('en');
  const [isLocaleReady, setIsLocaleReady] = useState(false);
  const [storageError, setStorageError] = useState(false);

  useEffect(() => {
    let isMounted = true;

    void readPersistedLocale()
      .then((storedLocale) => {
        if (isMounted) {
          setLocaleState(normalizeLocale(storedLocale));
          setStorageError(false);
        }
      })
      .catch(() => {
        if (isMounted) {
          setLocaleState('en');
          setStorageError(true);
        }
      })
      .finally(() => {
        if (isMounted) {
          setIsLocaleReady(true);
        }
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const setLocale = useCallback(async (nextLocale: Locale) => {
    const normalized = normalizeLocale(nextLocale);
    setLocaleState(normalized);

    try {
      await writePersistedLocale(normalized);
      setStorageError(false);
    } catch {
      setStorageError(true);
    }
  }, []);

  const t = useCallback((key: TranslationKey) => translations[locale][key], [locale]);
  const value = useMemo(() => ({
    locale,
    isLocaleReady,
    storageError,
    setLocale,
    t,
  }), [locale, isLocaleReady, storageError, setLocale, t]);

  return <LocaleContext.Provider value={value}>{children}</LocaleContext.Provider>;
}

export function useLocale(): LocaleContextValue {
  const context = useContext(LocaleContext);

  if (context === null) {
    throw new Error('useLocale must be used within a LocaleProvider.');
  }

  return context;
}
