import { jest } from '@jest/globals';
export const mockSecureStoreData = new Map<string, string>();
export const mockRouterPush = jest.fn();
export const mockRouterReplace = jest.fn();

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) => mockSecureStoreData.get(key) ?? null),
  setItemAsync: jest.fn(async (key: string, value: string) => {
    mockSecureStoreData.set(key, value);
  }),
  deleteItemAsync: jest.fn(async (key: string) => {
    mockSecureStoreData.delete(key);
  }),
}));

jest.mock('expo-router', () => ({
  ...(() => {
    const mockReact = jest.requireActual('react') as typeof import('react');
    const mockReactNative = jest.requireActual('react-native') as typeof import('react-native');

    return {
      Redirect: ({ href }: { href: string }) => mockReact.createElement(
        mockReactNative.Text,
        { testID: 'route-redirect' },
        href,
      ),
      Stack: () => mockReact.createElement(
        mockReactNative.View,
        { testID: 'authenticated-stack' },
      ),
    };
  })(),
  router: {
    push: mockRouterPush,
    replace: mockRouterReplace,
  },
  useLocalSearchParams: () => ({}),
}));
