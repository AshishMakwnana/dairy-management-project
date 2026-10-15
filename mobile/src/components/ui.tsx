import type { ReactNode } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { LanguageSwitcher } from './LanguageSwitcher';
import { colors, radii, spacing, typography } from '../theme/tokens';

type AppButtonProps = {
  label: string;
  onPress: () => void;
  disabled?: boolean;
  variant?: 'primary' | 'secondary' | 'text';
  loading?: boolean;
  loadingLabel?: string;
};

export function AppButton({
  label,
  onPress,
  disabled = false,
  variant = 'primary',
  loading = false,
  loadingLabel,
}: AppButtonProps) {
  return (
    <Pressable
      accessibilityLabel={loading ? loadingLabel ?? label : label}
      accessibilityRole="button"
      disabled={disabled || loading}
      onPress={onPress}
      accessibilityState={{ busy: loading, disabled: disabled || loading }}
      style={({ pressed }) => [
        styles.base,
        variant === 'primary' && styles.primary,
        variant === 'secondary' && styles.secondary,
        variant === 'text' && styles.text,
        (pressed || loading) && !disabled && styles.pressed,
        disabled && styles.disabled,
      ]}
    >
      <Text style={[
        styles.label,
        variant === 'secondary' && styles.secondaryLabel,
        variant === 'text' && styles.textLabel,
        disabled && styles.disabledLabel,
      ]}>
        {loading ? loadingLabel ?? label : label}
      </Text>
    </Pressable>
  );
}

type ScreenHeaderProps = {
  showLanguageSwitcher?: boolean;
};

export function ScreenHeader({ showLanguageSwitcher = true }: ScreenHeaderProps) {
  return (
    <View style={styles.headerRow}>
      <Text style={styles.wordmark}>DUDHO</Text>
      {showLanguageSwitcher ? <LanguageSwitcher /> : null}
    </View>
  );
}

type InputFieldProps = {
  label: string;
  value: string;
  onChangeText: (next: string) => void;
  placeholder: string;
  keyboardType?: 'default' | 'phone-pad' | 'number-pad';
  maxLength?: number;
  secureTextEntry?: boolean;
  error?: string | null;
  accessibilityLabel: string;
  prefix?: string;
  editable?: boolean;
  autoComplete?: 'off' | 'tel-national' | 'one-time-code';
  autoCapitalize?: 'none' | 'sentences' | 'words' | 'characters';
  textContentType?: 'none' | 'telephoneNumber' | 'oneTimeCode';
};

export function InputField({
  label,
  value,
  onChangeText,
  placeholder,
  keyboardType = 'default',
  maxLength,
  secureTextEntry = false,
  error = null,
  accessibilityLabel,
  prefix,
  editable = true,
  autoComplete,
  autoCapitalize = 'none',
  textContentType,
}: InputFieldProps) {
  return (
    <View style={styles.fieldWrap}>
      <Text style={styles.fieldLabel}>{label}</Text>
      <View style={[styles.inputRow, error !== null && styles.inputError]}>
        {prefix !== undefined ? <Text style={styles.prefix}>{prefix}</Text> : null}
        <TextInput
          accessibilityLabel={accessibilityLabel}
          autoCapitalize={autoCapitalize}
          autoComplete={autoComplete}
          autoCorrect={false}
          editable={editable}
          keyboardType={keyboardType}
          maxLength={maxLength}
          onChangeText={onChangeText}
          placeholder={placeholder}
          placeholderTextColor={colors.neutral[600]}
          secureTextEntry={secureTextEntry}
          style={styles.input}
          textContentType={textContentType}
          value={value}
        />
      </View>
      {error !== null ? <Text style={styles.helperError}>{error}</Text> : null}
    </View>
  );
}

type LoadingStateProps = {
  label: string;
};

export function LoadingState({ label }: LoadingStateProps) {
  return (
    <View accessibilityLiveRegion="polite" style={styles.loadingState}>
      <ActivityIndicator accessibilityLabel={label} color={colors.brand.primary} />
      <Text style={styles.loadingLabel}>{label}</Text>
    </View>
  );
}

type FeedbackMessageProps = {
  message: string;
  tone?: 'error' | 'success' | 'info';
};

export function FeedbackMessage({ message, tone = 'error' }: FeedbackMessageProps) {
  return (
    <View accessibilityLiveRegion="polite" style={[styles.feedback, styles[`feedback_${tone}`]]}>
      <Text style={[styles.feedbackText, styles[`feedbackText_${tone}`]]}>{message}</Text>
    </View>
  );
}

type InfoCardProps = {
  title: string;
  description?: string;
  children?: ReactNode;
};

export function InfoCard({ title, description, children }: InfoCardProps) {
  return (
    <View style={styles.card}>
      <Text style={styles.cardTitle}>{title}</Text>
      {description !== null && description !== undefined ? (
        <Text style={styles.cardBody}>{description}</Text>
      ) : null}
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  base: {
    alignItems: 'center',
    borderRadius: radii.medium,
    justifyContent: 'center',
    minHeight: 52,
    paddingHorizontal: spacing.lg,
  },
  primary: {
    backgroundColor: colors.brand.primary,
  },
  secondary: {
    backgroundColor: colors.brand.primaryLight,
  },
  text: {
    backgroundColor: 'transparent',
  },
  pressed: {
    opacity: 0.9,
  },
  disabled: {
    opacity: 0.55,
  },
  label: {
    color: colors.neutral.white,
    fontSize: typography.body,
    fontWeight: '700',
  },
  secondaryLabel: {
    color: colors.brand.primaryDark,
  },
  textLabel: {
    color: colors.brand.primary,
    fontSize: typography.supporting,
  },
  disabledLabel: {
    color: colors.neutral[600],
  },
  headerRow: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: spacing.xxxl,
  },
  wordmark: {
    color: colors.brand.primary,
    fontSize: typography.sectionTitle,
    fontWeight: '800',
    letterSpacing: 0.4,
  },
  headerMeta: {
    color: colors.brand.primaryDark,
    fontSize: typography.supporting,
    fontWeight: '700',
  },
  fieldWrap: {
    marginTop: spacing.sm,
  },
  fieldLabel: {
    color: colors.neutral[900],
    fontSize: typography.supporting,
    fontWeight: '600',
    marginBottom: spacing.sm,
  },
  input: {
    color: colors.neutral[900],
    flex: 1,
    minHeight: 56,
    paddingVertical: spacing.md,
  },
  inputRow: {
    alignItems: 'center',
    backgroundColor: colors.neutral.white,
    borderColor: colors.neutral[300],
    borderRadius: radii.medium,
    borderWidth: 1,
    flexDirection: 'row',
    minHeight: 56,
    paddingHorizontal: spacing.lg,
  },
  inputError: {
    borderColor: colors.semantic.error,
  },
  prefix: {
    color: colors.neutral[900],
    fontSize: typography.body,
    fontWeight: '600',
    marginRight: spacing.md,
  },
  helperError: {
    color: colors.semantic.error,
    fontSize: typography.supporting,
    marginTop: spacing.sm,
  },
  card: {
    backgroundColor: colors.neutral.white,
    borderColor: colors.neutral[300],
    borderRadius: radii.medium,
    borderWidth: 1,
    marginTop: spacing.xl,
    padding: spacing.lg,
  },
  cardTitle: {
    color: colors.neutral[900],
    fontSize: typography.body,
    fontWeight: '700',
    marginBottom: spacing.sm,
  },
  cardBody: {
    color: colors.neutral[600],
    fontSize: typography.supporting,
    lineHeight: 22,
  },
  loadingState: {
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 112,
    padding: spacing.xxl,
  },
  loadingLabel: {
    color: colors.neutral[600],
    fontSize: typography.supporting,
    marginTop: spacing.md,
    textAlign: 'center',
  },
  feedback: {
    borderRadius: radii.small,
    borderWidth: 1,
    marginTop: spacing.md,
    padding: spacing.md,
  },
  feedback_error: {
    backgroundColor: colors.neutral[100],
    borderColor: colors.semantic.error,
  },
  feedback_success: {
    backgroundColor: colors.brand.primaryLight,
    borderColor: colors.brand.primary,
  },
  feedback_info: {
    backgroundColor: colors.neutral[100],
    borderColor: colors.neutral[300],
  },
  feedbackText: {
    fontSize: typography.supporting,
    lineHeight: 22,
  },
  feedbackText_error: {
    color: colors.semantic.error,
  },
  feedbackText_success: {
    color: colors.brand.primaryDark,
  },
  feedbackText_info: {
    color: colors.neutral[600],
  },
});
