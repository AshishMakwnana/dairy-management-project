import { Pressable, StyleSheet, Text } from 'react-native';

import { useLocale } from '../i18n/LocaleProvider';
import { colors, layout, radii, spacing, typography } from '../theme/tokens';

export function LanguageSwitcher() {
  const { locale, setLocale, t } = useLocale();
  const nextLocale = locale === 'en' ? 'hi' : 'en';
  const label = nextLocale === 'hi' ? 'हिन्दी' : 'English';

  return (
    <Pressable
      accessibilityLabel={t('language.switch')}
      accessibilityRole="button"
      onPress={() => setLocale(nextLocale)}
      style={({ pressed }) => [styles.button, pressed && styles.pressed]}
    >
      <Text style={styles.label}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  button: {
    alignItems: 'center',
    backgroundColor: colors.brand.primaryLight,
    borderRadius: radii.small,
    justifyContent: 'center',
    minHeight: layout.minimumTouchTarget,
    minWidth: 88,
    paddingHorizontal: spacing.md,
  },
  pressed: {
    backgroundColor: colors.brand.primaryLight,
    opacity: 0.75,
  },
  label: {
    color: colors.brand.primaryDark,
    fontSize: typography.supporting,
    fontWeight: '700',
  },
});
