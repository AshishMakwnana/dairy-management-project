import { Platform } from 'react-native';

const defaultApiOrigin = Platform.select({
  android: 'http://10.0.2.2:8082',
  default: 'http://localhost:8082',
});

export const environment = {
  apiBaseUrl:
    process.env.EXPO_PUBLIC_API_URL ?? `${defaultApiOrigin}/api/v1`,
};
