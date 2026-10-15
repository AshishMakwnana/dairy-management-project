import { router, useLocalSearchParams } from 'expo-router';
import {
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { LanguageSwitcher } from '../../components/LanguageSwitcher';
import { useLocale } from '../../i18n/LocaleProvider';
import type { TranslationKey } from '../../i18n/translations';
import { colors, layout, radii, spacing, typography } from '../../theme/tokens';
import { getSetupChecklist } from './setupChecklist';
import { useAccessibleDairy } from './useAccessibleDairy';

const checklistLabels: Record<
  'dairy' | 'milk-quality' | 'pricing' | 'farmer' | 'staff',
  { title: TranslationKey; description: TranslationKey }
> = {
  dairy: {
    title: 'checklist.dairyTitle',
    description: 'checklist.dairyDescription',
  },
  'milk-quality': {
    title: 'checklist.qualityTitle',
    description: 'checklist.qualityDescription',
  },
  pricing: {
    title: 'checklist.pricingTitle',
    description: 'checklist.pricingDescription',
  },
  farmer: {
    title: 'checklist.farmerTitle',
    description: 'checklist.farmerDescription',
  },
  staff: {
    title: 'checklist.staffTitle',
    description: 'checklist.staffDescription',
  },
};

export function DairySetupChecklistScreen() {
  const { dairyId } = useLocalSearchParams<{ dairyId: string }>();
  const { t } = useLocale();
  const dairyState = useAccessibleDairy(dairyId);
  const dairy = dairyState.status === 'ready' ? dairyState.dairy : null;

  return (
    <SafeAreaView edges={['top', 'bottom']} style={styles.safeArea}>
      <ScrollView contentContainerStyle={styles.content} alwaysBounceVertical={false}>
        <View style={styles.toolbar}>
          <Pressable
            accessibilityRole="button"
            onPress={() => router.replace({
              pathname: '/dairies/[dairyId]',
              params: { dairyId },
            })}
            style={styles.backButton}
          >
            <Text style={styles.backLabel}>{t('checklist.back')}</Text>
          </Pressable>
          <LanguageSwitcher />
        </View>

        <Text style={styles.title}>{t('checklist.title')}</Text>

        {dairyState.status === 'loading' ? (
          <Text style={styles.bodyText}>{t('checklist.loading')}</Text>
        ) : dairyState.status === 'error' || dairyState.status === 'not-found' ? (
          <View style={styles.messageCard}>
            <Text style={styles.bodyText}>
              {t(dairyState.status === 'error' ? 'dashboard.loadError' : 'dashboard.notFound')}
            </Text>
            {dairyState.status === 'error' && (
              <Pressable
                accessibilityRole="button"
                onPress={dairyState.retry}
                style={styles.backButton}
              >
                <Text style={styles.backLabel}>{t('health.retry')}</Text>
              </Pressable>
            )}
          </View>
        ) : dairy !== null ? (
          <>
            <Text style={styles.dairyName}>{dairy.name}</Text>
            <Text style={styles.description}>{t('checklist.description')}</Text>

            <View accessibilityLiveRegion="polite" style={styles.confirmedNotice}>
              <Text style={styles.confirmedText}>{t('checklist.confirmedCount')}</Text>
            </View>

            <View style={styles.itemList}>
              {getSetupChecklist().map((item) => {
                const labels = checklistLabels[item.id];
                const statusLabel: TranslationKey = item.status === 'complete'
                  ? 'checklist.complete'
                  : item.status === 'optional-unverified'
                    ? 'checklist.optional'
                    : 'checklist.pending';

                return (
                  <View key={item.id} style={styles.itemCard}>
                    <View style={styles.itemHeading}>
                      <View
                        accessibilityLabel={t(statusLabel)}
                        style={[
                          styles.statusMark,
                          item.status === 'complete' ? styles.completeMark : styles.pendingMark,
                        ]}
                      />
                      <View style={styles.itemCopy}>
                        <Text style={styles.itemTitle}>{t(labels.title)}</Text>
                        <Text style={styles.itemDescription}>{t(labels.description)}</Text>
                      </View>
                    </View>
                    <Text
                      style={[
                        styles.statusLabel,
                        item.status === 'complete' ? styles.completeLabel : styles.pendingLabel,
                      ]}
                    >
                      {t(statusLabel)}
                    </Text>
                  </View>
                );
              })}
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
  toolbar: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: spacing.xl,
  },
  backButton: {
    justifyContent: 'center',
    minHeight: layout.minimumTouchTarget,
    paddingHorizontal: spacing.sm,
  },
  backLabel: {
    color: colors.brand.primary,
    fontSize: typography.supporting,
    fontWeight: '600',
  },
  title: {
    color: colors.neutral[900],
    fontSize: typography.pageTitle,
    fontWeight: '700',
    lineHeight: 38,
  },
  dairyName: {
    color: colors.brand.primaryDark,
    fontSize: typography.cardTitle,
    fontWeight: '700',
    marginTop: spacing.md,
  },
  description: {
    color: colors.neutral[600],
    fontSize: typography.supporting,
    lineHeight: 22,
    marginTop: spacing.sm,
  },
  confirmedNotice: {
    backgroundColor: colors.brand.primaryLight,
    borderRadius: radii.small,
    marginTop: spacing.xl,
    padding: spacing.md,
  },
  confirmedText: {
    color: colors.brand.primaryDark,
    fontSize: typography.supporting,
    lineHeight: 22,
  },
  itemList: {
    gap: spacing.md,
    marginTop: spacing.lg,
  },
  itemCard: {
    backgroundColor: colors.neutral.white,
    borderColor: colors.neutral[300],
    borderRadius: radii.medium,
    borderWidth: 1,
    padding: spacing.lg,
  },
  itemHeading: {
    alignItems: 'flex-start',
    flexDirection: 'row',
  },
  statusMark: {
    borderRadius: 10,
    height: 16,
    marginTop: spacing.xs,
    width: 16,
  },
  completeMark: {
    backgroundColor: colors.semantic.success,
  },
  pendingMark: {
    backgroundColor: colors.neutral[300],
  },
  itemCopy: {
    flex: 1,
    marginLeft: spacing.md,
  },
  itemTitle: {
    color: colors.neutral[900],
    fontSize: typography.body,
    fontWeight: '700',
  },
  itemDescription: {
    color: colors.neutral[600],
    fontSize: typography.supporting,
    lineHeight: 22,
    marginTop: spacing.xs,
  },
  statusLabel: {
    fontSize: typography.meta,
    fontWeight: '700',
    marginLeft: spacing.xxl,
    marginTop: spacing.sm,
  },
  completeLabel: {
    color: colors.semantic.success,
  },
  pendingLabel: {
    color: colors.neutral[600],
  },
  messageCard: {
    backgroundColor: colors.neutral.white,
    borderColor: colors.neutral[300],
    borderRadius: radii.medium,
    borderWidth: 1,
    marginTop: spacing.xxl,
    padding: spacing.lg,
  },
  bodyText: {
    color: colors.neutral[600],
    fontSize: typography.supporting,
    lineHeight: 22,
  },
});
