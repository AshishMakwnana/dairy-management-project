import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { LocaleProvider } from '../i18n/LocaleProvider';
import { AuthSessionProvider } from '../services/auth/AuthSessionProvider';
import { colors } from '../theme/tokens';

export default function RootLayout() {
  return (
    <SafeAreaProvider>
      <LocaleProvider>
        <AuthSessionProvider>
          <StatusBar style="dark" />
          <Stack
            screenOptions={{
              contentStyle: { backgroundColor: colors.neutral[50] },
              headerShown: false,
            }}
          />
        </AuthSessionProvider>
      </LocaleProvider>
    </SafeAreaProvider>
  );
}
