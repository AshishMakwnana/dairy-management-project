import { StyleSheet, Text } from 'react-native';

import { useLocale } from '../../i18n/LocaleProvider';
import { colors, typography } from '../../theme/tokens';
import type { Dairy } from '../../types/dairy';

export function DairyStatus({ state }: { state: Dairy['state'] }) {
  const { t } = useLocale();

  return (
    <Text style={styles.label}>
      {t(state === 'complete' ? 'dairy.stateComplete' : 'dairy.stateDraft')}
    </Text>
  );
}

const styles = StyleSheet.create({
  label: {
    color: colors.brand.primaryDark,
    fontSize: typography.meta,
    fontWeight: '600',
    textAlign: 'right',
  },
});
