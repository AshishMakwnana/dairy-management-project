import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import {
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import {
  AppButton,
  FeedbackMessage,
  InfoCard,
  LoadingState,
  ScreenHeader,
} from '../../components/ui';
import { useLocale } from '../../i18n/LocaleProvider';
import type { TranslationKey } from '../../i18n/translations';
import { DairyApiError, listDairies } from '../../services/api/dairies';
import { useAuthSession } from '../../services/auth/AuthSessionProvider';
import type { Dairy } from '../../types/dairy';
import { DairyStatus } from './DairyStatus';
import { colors, layout, radii, spacing, typography } from '../../theme/tokens';

function errorTranslationKey(error: unknown): TranslationKey {
  if (error instanceof DairyApiError) {
    switch (error.kind) {
      case 'network':
        return 'dairy.networkError';
      case 'validation':
        return 'dairy.validationError';
      case 'unauthorized':
        return 'dairy.sessionExpired';
      case 'conflict':
        return 'dairy.conflictError';
      case 'server':
        return 'dairy.serverError';
      case 'invalid-response':
        return 'dairy.invalidResponse';
    }
  }

  return 'dairy.serverError';
}

export function DairyListScreen() {
  const { t } = useLocale();
  const { session, clearSession } = useAuthSession();
  const { created, refresh } = useLocalSearchParams<{ created?: string; refresh?: string }>();
  const [dairies, setDairies] = useState<Dairy[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [errorKey, setErrorKey] = useState<TranslationKey | null>(null);
  const [refreshVersion, setRefreshVersion] = useState(0);

  useEffect(() => {
    if (session === null) {
      return undefined;
    }

    let isMounted = true;

    void listDairies(session.accessToken)
      .then((result) => {
        if (isMounted) {
          setDairies(result);
          setErrorKey(null);
        }
      })
      .catch((error: unknown) => {
        if (!isMounted) {
          return;
        }

        setErrorKey(errorTranslationKey(error));

        if (error instanceof DairyApiError && error.kind === 'unauthorized') {
          void clearSession()
            .then(() => router.replace('/'))
            .catch(() => {
              if (isMounted) {
                setErrorKey('dairy.storageError');
              }
            });
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
  }, [session, clearSession, refreshVersion, created, refresh]);

  const retry = () => {
    setIsLoading(true);
    setErrorKey(null);
    setRefreshVersion((version) => version + 1);
  };

  const registerDairy = () => router.push('/dairies/new');

  return (
    <SafeAreaView edges={['top', 'bottom']} style={styles.safeArea}>
      <ScrollView contentContainerStyle={styles.content} alwaysBounceVertical={false}>
        <ScreenHeader />

        <Text style={styles.title}>{t('dairy.listTitle')}</Text>
        <Text style={styles.description}>{t('dairy.listDescription')}</Text>

        {created !== undefined && (
          <FeedbackMessage message={t('dairy.createdNotice')} tone="success" />
        )}

        {isLoading ? (
          <LoadingState label={t('dairy.loading')} />
        ) : errorKey !== null ? (
          <View accessibilityLiveRegion="polite" style={styles.messageCard}>
            <FeedbackMessage message={t(errorKey)} />
            <AppButton label={t('health.retry')} onPress={retry} variant="secondary" />
          </View>
        ) : dairies.length === 0 ? (
          <InfoCard
            title={t('dairy.emptyTitle')}
            description={t('dairy.emptyDescription')}
          />
        ) : (
          <View style={styles.dairyList}>
            {dairies.map((dairy) => {
              const location = [dairy.location.city, dairy.location.state]
                .filter((part): part is string => part !== null && part.trim() !== '')
                .join(', ');

              return (
                <Pressable
                  key={dairy.id}
                  accessibilityHint={t('dashboard.openHint')}
                  accessibilityLabel={`${t('dashboard.openDairy')}: ${dairy.name}`}
                  accessibilityRole="button"
                  onPress={() => router.push({
                    pathname: '/dairies/[dairyId]',
                    params: { dairyId: dairy.id },
                  })}
                  style={({ pressed }) => [
                    styles.dairyCard,
                    pressed && styles.dairyCardPressed,
                  ]}
                >
                  <View style={styles.cardHeading}>
                    <Text style={styles.cardTitle}>{dairy.name}</Text>
                    <DairyStatus state={dairy.state} />
                  </View>
                  <Text style={styles.location}>
                    {location === '' ? t('dairy.locationMissing') : location}
                  </Text>
                </Pressable>
              );
            })}
          </View>
        )}

        <AppButton
          label={dairies.length === 0 ? t('dairy.addFirst') : t('dairy.addAnother')}
          onPress={registerDairy}
        />
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    backgroundColor: colors.neutral[50],
    flex: 1,
  },
  content: {
    flexGrow: 1,
    paddingHorizontal: layout.screenHorizontalPadding,
    paddingVertical: spacing.xxl,
  },
  toolbar: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: spacing.xxxl,
  },
  wordmark: {
    color: colors.brand.primary,
    fontSize: typography.sectionTitle,
    fontWeight: '800',
    letterSpacing: 0.4,
  },
  title: {
    color: colors.neutral[900],
    fontSize: typography.pageTitle,
    fontWeight: '700',
    lineHeight: 38,
  },
  description: {
    color: colors.neutral[600],
    fontSize: typography.body,
    lineHeight: 24,
    marginTop: spacing.sm,
  },
  successNotice: {
    backgroundColor: colors.brand.primaryLight,
    borderRadius: radii.small,
    color: colors.brand.primaryDark,
    fontSize: typography.supporting,
    lineHeight: 22,
    marginTop: spacing.xl,
    padding: spacing.md,
  },
  loading: {
    alignItems: 'center',
    backgroundColor: colors.neutral.white,
    borderRadius: radii.medium,
    marginTop: spacing.xxl,
    padding: spacing.xxl,
  },
  bodyText: {
    color: colors.neutral[600],
    fontSize: typography.supporting,
    lineHeight: 22,
    marginTop: spacing.sm,
  },
  messageCard: {
    backgroundColor: colors.neutral.white,
    borderColor: colors.neutral[300],
    borderRadius: radii.medium,
    borderWidth: 1,
    marginTop: spacing.xxl,
    padding: spacing.lg,
  },
  errorText: {
    color: colors.semantic.error,
    fontSize: typography.supporting,
    lineHeight: 22,
  },
  emptyCard: {
    backgroundColor: colors.neutral.white,
    borderColor: colors.neutral[300],
    borderRadius: radii.large,
    borderWidth: 1,
    marginTop: spacing.xxl,
    padding: spacing.xl,
  },
  dairyList: {
    gap: spacing.md,
    marginTop: spacing.xxl,
  },
  dairyCard: {
    backgroundColor: colors.neutral.white,
    borderColor: colors.neutral[300],
    borderRadius: radii.medium,
    borderWidth: 1,
    padding: spacing.lg,
  },
  dairyCardPressed: {
    opacity: 0.75,
  },
  cardHeading: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    gap: spacing.sm,
    justifyContent: 'space-between',
  },
  cardTitle: {
    color: colors.neutral[900],
    flex: 1,
    fontSize: typography.cardTitle,
    fontWeight: '700',
  },
  location: {
    color: colors.neutral[600],
    fontSize: typography.supporting,
    marginTop: spacing.sm,
  },
  primaryButton: {
    alignItems: 'center',
    backgroundColor: colors.brand.primary,
    borderRadius: radii.medium,
    justifyContent: 'center',
    marginTop: spacing.xxl,
    minHeight: 52,
    paddingHorizontal: spacing.lg,
  },
  primaryButtonLabel: {
    color: colors.neutral.white,
    fontSize: typography.body,
    fontWeight: '700',
    textAlign: 'center',
  },
  secondaryButton: {
    alignItems: 'center',
    alignSelf: 'flex-start',
    borderRadius: radii.small,
    justifyContent: 'center',
    marginTop: spacing.md,
    minHeight: layout.minimumTouchTarget,
    paddingHorizontal: spacing.md,
  },
  secondaryButtonLabel: {
    color: colors.brand.primary,
    fontSize: typography.supporting,
    fontWeight: '700',
  },
  buttonPressed: {
    opacity: 0.75,
  },
});
