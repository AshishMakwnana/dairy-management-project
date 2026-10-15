import { router } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';

import { DairyApiError, listDairies } from '../../services/api/dairies';
import { useAuthSession } from '../../services/auth/AuthSessionProvider';
import type { Dairy } from '../../types/dairy';

export type AccessibleDairyState =
  | { status: 'loading'; retry: () => void }
  | { status: 'error'; retry: () => void }
  | { status: 'not-found'; retry: () => void }
  | { status: 'ready'; dairy: Dairy; retry: () => void };

type LoadedDairyState = {
  requestKey: string;
  status: 'loading' | 'error' | 'not-found' | 'ready';
  dairy: Dairy | null;
};

export function useAccessibleDairy(dairyId: string): AccessibleDairyState {
  const { session, clearSession } = useAuthSession();
  const [refreshVersion, setRefreshVersion] = useState(0);
  const [loadedState, setLoadedState] = useState<LoadedDairyState>({
    requestKey: '',
    status: 'loading',
    dairy: null,
  });
  const requestKey = `${dairyId}:${refreshVersion}`;
  const retry = useCallback(() => {
    setRefreshVersion((version) => version + 1);
  }, []);

  useEffect(() => {
    if (session === null) {
      return undefined;
    }
    let isMounted = true;

    void listDairies(session.accessToken)
      .then((dairies) => {
        if (!isMounted) {
          return;
        }

        const accessibleDairy = dairies.find((item) => item.id === dairyId) ?? null;
        setLoadedState({
          requestKey,
          status: accessibleDairy === null ? 'not-found' : 'ready',
          dairy: accessibleDairy,
        });
      })
      .catch((error: unknown) => {
        if (!isMounted) {
          return;
        }

        setLoadedState({ requestKey, status: 'error', dairy: null });

        if (error instanceof DairyApiError && error.kind === 'unauthorized') {
          void clearSession()
            .then(() => router.replace('/'))
            .catch(() => {
              if (isMounted) {
                setLoadedState({ requestKey, status: 'error', dairy: null });
              }
            });
        }
      });

    return () => {
      isMounted = false;
    };
  }, [session, clearSession, dairyId, refreshVersion, requestKey]);

  if (loadedState.requestKey !== requestKey || loadedState.status === 'loading') {
    return { status: 'loading', retry };
  }

  if (loadedState.status === 'ready' && loadedState.dairy !== null) {
    return { status: 'ready', dairy: loadedState.dairy, retry };
  }

  if (loadedState.status === 'error' || loadedState.status === 'not-found') {
    return { status: loadedState.status, retry };
  }

  return { status: 'loading', retry };
}
