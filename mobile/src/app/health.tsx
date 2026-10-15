import { router } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { useLocale } from '../i18n/LocaleProvider';
import { checkApiHealth } from '../services/api/health';
import { colors, layout, radii, spacing, typography } from '../theme/tokens';

type ApiState = 'checking' | 'connected' | 'unavailable';

export default function HealthScreen() {
  const [apiState, setApiState] = useState<ApiState>('checking');
  const { t } = useLocale();

  const checkConnection = useCallback(async () => {
    setApiState('checking');

    try {
      await checkApiHealth();
      setApiState('connected');
    } catch {
      setApiState('unavailable');
    }
  }, []);

  useEffect(() => {
    let isMounted = true;

    void checkApiHealth()
      .then(() => {
        if (isMounted) {
          setApiState('connected');
        }
      })
      .catch(() => {
        if (isMounted) {
          setApiState('unavailable');
        }
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const isUnavailable = apiState === 'unavailable';

  return (
    <SafeAreaView edges={['top', 'bottom']} style={styles.safeArea}>
      <ScrollView contentContainerStyle={styles.content} alwaysBounceVertical={false}>
        <Pressable
          accessibilityRole="button"
          onPress={() => router.replace('/')}
          style={styles.backButton}
        >
          <Text style={styles.backLabel}>{t('health.back')}</Text>
        </Pressable>

        <Text style={styles.eyebrow}>{t('health.eyebrow')}</Text>
        <Text style={styles.title}>DUDHO</Text>
        <Text style={styles.subtitle}>{t('brand.tagline')}</Text>

        <View accessibilityLiveRegion="polite" style={styles.statusCard}>
          {apiState === 'checking' ? (
            <ActivityIndicator color={colors.brand.primary} />
          ) : (
            <View
              accessibilityLabel={isUnavailable ? t('health.unavailable') : t('health.connected')}
              style={[styles.statusDot, isUnavailable ? styles.unavailable : styles.connected]}
            />
          )}
          <View style={styles.statusCopy}>
            <Text style={styles.statusTitle}>
              {apiState === 'checking'
                ? t('health.checking')
                : isUnavailable
                  ? t('health.unavailable')
                  : t('health.connected')}
            </Text>
            <Text style={styles.statusText}>
              {isUnavailable ? t('health.retryHelp') : t('health.help')}
            </Text>
          </View>
        </View>

        {isUnavailable && (
          <Pressable
            accessibilityRole="button"
            onPress={() => void checkConnection()}
            style={({ pressed }) => [styles.button, pressed && styles.buttonPressed]}
          >
            <Text style={styles.buttonLabel}>{t('health.retry')}</Text>
          </Pressable>
        )}
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
    justifyContent: 'center',
    paddingHorizontal: layout.screenHorizontalPadding,
    paddingVertical: spacing.xxl,
  },
  backButton: {
    alignSelf: 'flex-start',
    justifyContent: 'center',
    marginBottom: spacing.xxl,
    minHeight: layout.minimumTouchTarget,
  },
  backLabel: {
    color: colors.brand.primary,
    fontSize: typography.supporting,
    fontWeight: '600',
  },
  eyebrow: {
    color: colors.brand.primary,
    fontSize: typography.meta,
    fontWeight: '700',
    letterSpacing: 1.2,
    marginBottom: spacing.sm,
  },
  title: {
    color: colors.neutral[900],
    fontSize: 40,
    fontWeight: '800',
  },
  subtitle: {
    color: colors.neutral[600],
    fontSize: typography.sectionTitle,
    lineHeight: 32,
    marginBottom: spacing.xxxl,
    marginTop: spacing.xs,
  },
  statusCard: {
    alignItems: 'center',
    backgroundColor: colors.neutral.white,
    borderColor: colors.neutral[300],
    borderRadius: radii.large,
    borderWidth: 1,
    flexDirection: 'row',
    minHeight: 112,
    padding: spacing.xl,
  },
  statusDot: {
    borderRadius: 8,
    height: 16,
    width: 16,
  },
  connected: {
    backgroundColor: colors.semantic.success,
  },
  unavailable: {
    backgroundColor: colors.semantic.error,
  },
  statusCopy: {
    flex: 1,
    marginLeft: spacing.lg,
  },
  statusTitle: {
    color: colors.neutral[900],
    fontSize: typography.cardTitle,
    fontWeight: '700',
  },
  statusText: {
    color: colors.neutral[600],
    fontSize: typography.supporting,
    lineHeight: 22,
    marginTop: spacing.xs,
  },
  button: {
    alignItems: 'center',
    backgroundColor: colors.brand.primary,
    borderRadius: radii.medium,
    justifyContent: 'center',
    marginTop: spacing.lg,
    minHeight: 52,
    paddingHorizontal: spacing.xl,
  },
  buttonPressed: {
    backgroundColor: colors.brand.primaryDark,
  },
  buttonLabel: {
    color: colors.neutral.white,
    fontSize: typography.body,
    fontWeight: '700',
  },
});
