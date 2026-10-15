import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import type { PropsWithChildren } from 'react';

import { logout } from '../api/auth';
import { useLocale } from '../../i18n/LocaleProvider';
import { logoutSessionSafely } from './logout';
import { deleteAuthSession, readAuthSession, writeAuthSession } from './sessionStorage';

import type { AuthSession } from '../api/auth';
import type { LogoutOutcome } from './logout';

type AuthSessionContextValue = {
  session: AuthSession | null;
  isLoading: boolean;
  isLoggingOut: boolean;
  storageError: boolean;
  restoreSession: () => Promise<void>;
  saveSession: (session: AuthSession) => Promise<void>;
  clearSession: () => Promise<void>;
  signOut: () => Promise<LogoutOutcome>;
};

const AuthSessionContext = createContext<AuthSessionContextValue | null>(null);

export function AuthSessionProvider({ children }: PropsWithChildren) {
  const [session, setSession] = useState<AuthSession | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const [storageError, setStorageError] = useState(false);
  const signOutRequest = useRef<Promise<LogoutOutcome> | null>(null);
  const { setLocale } = useLocale();

  const applySession = useCallback((storedSession: AuthSession | null) => {
    setSession(storedSession);
    setStorageError(false);

    if (storedSession !== null) {
      void setLocale(storedSession.user.preferredLanguage);
    }
  }, [setLocale]);

  useEffect(() => {
    let isMounted = true;

    void readAuthSession()
      .then((storedSession) => {
        if (isMounted) {
          applySession(storedSession);
        }
      })
      .catch(() => {
        if (isMounted) {
          setStorageError(true);
        }
      })
      .finally(() => {
        if (isMounted) {
          setIsLoading(false);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [applySession]);

  const restoreSession = useCallback(async () => {
    setIsLoading(true);

    try {
      applySession(await readAuthSession());
    } catch {
      setStorageError(true);
    } finally {
      setIsLoading(false);
    }
  }, [applySession]);

  const saveSession = useCallback(async (verifiedSession: AuthSession) => {
    await writeAuthSession(verifiedSession);
    applySession(verifiedSession);
  }, [applySession]);

  const clearSession = useCallback(async () => {
    await deleteAuthSession();
    applySession(null);
  }, [applySession]);

  const signOut = useCallback((): Promise<LogoutOutcome> => {
    if (signOutRequest.current !== null) {
      return signOutRequest.current;
    }

    const currentSession = session;
    const attempt = async (): Promise<LogoutOutcome> => {
      setIsLoggingOut(true);

      try {
        if (currentSession === null) {
          await clearSession();

          return 'success';
        }

        const outcome = await logoutSessionSafely(currentSession.accessToken, logout);

        if (outcome === 'success' || outcome === 'unauthorized' || outcome === 'forbidden') {
          await clearSession();
        }

        return outcome;
      } finally {
        setIsLoggingOut(false);
        signOutRequest.current = null;
      }
    };

    signOutRequest.current = attempt();

    return signOutRequest.current;
  }, [clearSession, session]);

  const value = useMemo(() => ({
    session,
    isLoading,
    isLoggingOut,
    storageError,
    restoreSession,
    saveSession,
    clearSession,
    signOut,
  }), [session, isLoading, isLoggingOut, storageError, restoreSession, saveSession, clearSession, signOut]);

  return <AuthSessionContext.Provider value={value}>{children}</AuthSessionContext.Provider>;
}

export function useAuthSession(): AuthSessionContextValue {
  const context = useContext(AuthSessionContext);

  if (context === null) {
    throw new Error('useAuthSession must be used within an AuthSessionProvider.');
  }

  return context;
}
