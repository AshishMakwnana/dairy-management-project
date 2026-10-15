export const colors = {
  brand: {
    primary: '#1565C0',
    primaryDark: '#0D47A1',
    primaryLight: '#E8F2FF',
    green: '#4CAF50',
    greenDark: '#2E7D32',
  },
  neutral: {
    900: '#172033',
    600: '#667085',
    300: '#D0D5DD',
    100: '#F2F4F7',
    50: '#F8FAFC',
    white: '#FFFFFF',
  },
  semantic: {
    success: '#2E7D32',
    warning: '#ED6C02',
    error: '#D32F2F',
    info: '#0277BD',
  },
} as const;

export const spacing = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 20,
  xxl: 24,
  xxxl: 32,
} as const;

export const radii = {
  small: 8,
  medium: 12,
  large: 16,
} as const;

export const typography = {
  display: 32,
  pageTitle: 28,
  sectionTitle: 22,
  cardTitle: 18,
  body: 16,
  supporting: 14,
  meta: 12,
} as const;

export const layout = {
  screenHorizontalPadding: spacing.lg,
  minimumTouchTarget: 48,
} as const;
