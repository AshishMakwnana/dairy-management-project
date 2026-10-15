import { Redirect, Stack } from 'expo-router';
import { StyleSheet } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { LoadingState } from '../../components/ui';
import { useLocale } from '../../i18n/LocaleProvider';
import { useAuthSession } from '../../services/auth/AuthSessionProvider';
import { colors } from '../../theme/tokens';

export default function DairiesLayout() {
  const { session, isLoading } = useAuthSession();
  const { t } = useLocale();

  if (isLoading) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <LoadingState label={t('auth.loadingSession')} />
      </SafeAreaView>
    );
  }

  if (session === null) {
    return <Redirect href="/" />;
  }

  return <Stack screenOptions={{ headerShown: false }} />;
}

const styles = StyleSheet.create({
  safeArea: {
    backgroundColor: colors.neutral[50],
    flex: 1,
  },
});
