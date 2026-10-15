import { router, useLocalSearchParams } from 'expo-router';
import { AppButton, FeedbackMessage, InfoCard, LoadingState, ScreenHeader } from '../../components/ui';
import { useLocale } from '../../i18n/LocaleProvider';
import type { TranslationKey } from '../../i18n/translations';
import { colors, layout, radii, spacing, typography } from '../../theme/tokens';
import { DairyStatus } from './DairyStatus';
import { useAccessibleDairy } from './useAccessibleDairy';
import {
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

function dashboardTitle(
  state: 'loading' | 'error' | 'not-found' | 'ready',
  t: (key: TranslationKey) => string,
): string {
  if (state === 'error') {
    return t('dashboard.loadError');
  }

  if (state === 'not-found') {
    return t('dashboard.notFound');
  }

  return t('dashboard.title');
}

export function DairyDashboardScreen() {
  const { dairyId } = useLocalSearchParams<{ dairyId: string }>();
  const { t } = useLocale();
  const dairyState = useAccessibleDairy(dairyId);
  const dairy = dairyState.status === 'ready' ? dairyState.dairy : null;

  return (
    <SafeAreaView edges={['top', 'bottom']} style={styles.safeArea}>
      <ScrollView contentContainerStyle={styles.content} alwaysBounceVertical={false}>
        <ScreenHeader />

        <Text style={styles.eyebrow}>{t('dashboard.eyebrow')}</Text>
        <Text style={styles.title}>{dashboardTitle(dairyState.status, t)}</Text>

        {dairyState.status === 'loading' ? (
          <LoadingState label={t('dashboard.loading')} />
        ) : dairyState.status === 'error' || dairyState.status === 'not-found' ? (
          <View accessibilityLiveRegion="polite" style={styles.messageCard}>
            <FeedbackMessage
              message={dairyState.status === 'error' ? t('dashboard.loadError') : t('dashboard.notFound')}
            />
            {dairyState.status === 'error' && (
              <AppButton label={t('health.retry')} onPress={dairyState.retry} variant="secondary" />
            )}
            <AppButton
              label={t('dashboard.switchDairy')}
              onPress={() => router.replace('/dairies')}
              variant="text"
            />
          </View>
        ) : dairy !== null ? (
          <>
            <View style={styles.dairyCard}>
              <View style={styles.dairyHeading}>
                <Text style={styles.dairyName}>{dairy.name}</Text>
                <DairyStatus state={dairy.state} />
              </View>
              <Text style={styles.location}>
                {[dairy.location.city, dairy.location.state]
                  .filter((part): part is string => part !== null && part.trim() !== '')
                  .join(', ') || t('dairy.locationMissing')}
              </Text>
            </View>

            <InfoCard title={t('dashboard.metricsTitle')} description={t('dashboard.metricsUnavailable')} />
            <InfoCard title={t('dashboard.collectionTitle')} description={t('dashboard.collectionUnavailable')} />
            <View style={styles.actions}>
              <AppButton
                label={t('dashboard.continueSetup')}
                onPress={() => router.push({
                  pathname: '/dairies/[dairyId]/checklist',
                  params: { dairyId: dairy.id },
                })}
              />
              <AppButton
                label={t('dashboard.switchDairy')}
                onPress={() => router.replace('/dairies')}
                variant="text"
              />
            </View>
          </>
        ) : null}
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
  eyebrow: {
    color: colors.brand.primary,
    fontSize: typography.meta,
    fontWeight: '700',
    letterSpacing: 1,
  },
  title: {
    color: colors.neutral[900],
    fontSize: typography.pageTitle,
    fontWeight: '700',
    lineHeight: 38,
    marginTop: spacing.xs,
  },
  dairyCard: {
    backgroundColor: colors.neutral.white,
    borderColor: colors.neutral[300],
    borderRadius: radii.large,
    borderWidth: 1,
    marginTop: spacing.xl,
    padding: spacing.xl,
  },
  dairyHeading: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    gap: spacing.sm,
    justifyContent: 'space-between',
  },
  dairyName: {
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
  messageCard: {
    alignItems: 'center',
    backgroundColor: colors.neutral.white,
    borderColor: colors.neutral[300],
    borderRadius: radii.medium,
    borderWidth: 1,
    marginTop: spacing.xxl,
    padding: spacing.xl,
  },
  actions: {
    gap: spacing.xs,
    marginTop: spacing.xxl,
  },
});
