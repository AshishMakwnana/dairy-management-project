export type Locale = 'en' | 'hi';

export function normalizeLocale(value: unknown): Locale {
  return value === 'hi' ? 'hi' : 'en';
}
