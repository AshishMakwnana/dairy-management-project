import * as Crypto from 'expo-crypto';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import {
  AppButton,
  FeedbackMessage,
  InfoCard,
  InputField,
  LoadingState,
  ScreenHeader,
} from '../../components/ui';
import { useLocale } from '../../i18n/LocaleProvider';
import type { TranslationKey } from '../../i18n/translations';
import {
  AuthApiError,
  sendOtp,
  verifyOtp,
  type OtpDeliveryChannel,
} from '../../services/api/auth';
import { useAuthSession } from '../../services/auth/AuthSessionProvider';
import {
  normalizeIndianMobileInput,
  normalizeOtpInput,
  toIndianE164,
} from './phoneValidation';
import { colors, layout, radii, spacing, typography } from '../../theme/tokens';

type Step = 'phone' | 'code';
type PendingAction = 'send' | 'resend' | 'verify' | null;

function errorTranslationKey(error: unknown, step: Step): TranslationKey {
  if (error instanceof AuthApiError) {
    switch (error.kind) {
      case 'unauthorized':
      case 'forbidden':
        return 'auth.serverError';
      case 'validation':
        return step === 'phone' ? 'auth.phoneInvalid' : 'auth.codeInvalid';
      case 'rate-limited':
        return 'auth.rateLimited';
      case 'network':
        return 'auth.connectionError';
      case 'server':
      case 'invalid-response':
        return 'auth.serverError';
    }
  }

  return step === 'code' ? 'auth.sessionError' : 'auth.serverError';
}

export function LoginScreen() {
  const {
    locale,
    setLocale,
    t,
    isLocaleReady,
    storageError: localeStorageError,
  } = useLocale();
  const {
    session,
    isLoading,
    storageError,
    restoreSession,
    saveSession,
    isLoggingOut,
    signOut,
  } = useAuthSession();
  const [step, setStep] = useState<Step>('phone');
  const [mobile, setMobile] = useState('');
  const [deliveryChannel, setDeliveryChannel] = useState<OtpDeliveryChannel>('whatsapp');
  const [code, setCode] = useState('');
  const [clientUuid, setClientUuid] = useState(() => Crypto.randomUUID());
  const [pendingAction, setPendingAction] = useState<PendingAction>(null);
  const [resendSeconds, setResendSeconds] = useState(0);
  const [errorKey, setErrorKey] = useState<TranslationKey | null>(null);
  const [logoutErrorKey, setLogoutErrorKey] = useState<TranslationKey | null>(null);

  useEffect(() => {
    if (resendSeconds === 0) {
      return;
    }

    const timer = setTimeout(() => {
      setResendSeconds((seconds) => Math.max(0, seconds - 1));
    }, 1000);

    return () => clearTimeout(timer);
  }, [resendSeconds]);

  const phone = toIndianE164(mobile);
  const isBusy = pendingAction !== null;

  const signOutNow = async () => {
    setLogoutErrorKey(null);

    try {
      const outcome = await signOut();

      if (outcome === 'network') {
        setLogoutErrorKey('auth.logoutNetworkError');
      } else if (outcome === 'server') {
        setLogoutErrorKey('auth.logoutServerError');
      } else if (outcome === 'invalid-response') {
        setLogoutErrorKey('auth.logoutResponseError');
      }
    } catch {
      setLogoutErrorKey('auth.sessionCleanupError');
    }
  };

  const requestCode = async (isResend = false) => {
    if (phone === null) {
      setErrorKey('auth.phoneInvalid');

      return;
    }

    setPendingAction(isResend ? 'resend' : 'send');
    setErrorKey(null);

    try {
      const result = await sendOtp(phone, locale, deliveryChannel);
      setStep('code');
      setCode('');
      setClientUuid(Crypto.randomUUID());
      setResendSeconds(result.retryAfterSeconds);
    } catch (error) {
      setErrorKey(errorTranslationKey(error, 'phone'));

      if (error instanceof AuthApiError && error.kind === 'rate-limited') {
        setResendSeconds(error.retryAfterSeconds ?? 60);
      }
    } finally {
      setPendingAction(null);
    }
  };

  const verifyCode = async () => {
    if (phone === null || !/^\d{6}$/.test(code)) {
      setErrorKey(phone === null ? 'auth.phoneInvalid' : 'auth.codeInvalid');

      return;
    }

    setPendingAction('verify');
    setErrorKey(null);

    try {
      const verifiedSession = await verifyOtp(phone, code, clientUuid);
      await saveSession(verifiedSession);
      await setLocale(verifiedSession.user.preferredLanguage);
    } catch (error) {
      setErrorKey(errorTranslationKey(error, 'code'));

      if (error instanceof AuthApiError && error.kind === 'rate-limited') {
        setResendSeconds(error.retryAfterSeconds ?? 60);
      }
    } finally {
      setPendingAction(null);
    }
  };

  if (isLoading || !isLocaleReady) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <LoadingState label={t('auth.loadingSession')} />
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView edges={['top', 'bottom']} style={styles.safeArea}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={styles.keyboardView}
      >
        <ScrollView
          contentContainerStyle={styles.content}
          keyboardShouldPersistTaps="handled"
          alwaysBounceVertical={false}
        >
          <ScreenHeader />

          {session === null ? (
            <View>
              <Text style={styles.title}>{step === 'phone' ? t('auth.title') : t('auth.codeTitle')}</Text>
              <Text style={styles.description}>
                {step === 'phone'
                  ? t('auth.description')
                  : t('auth.codeSent').replace('{phone}', phone ?? '')}
              </Text>

              {step === 'phone' ? (
                <View style={styles.form}>
                  <InputField
                    accessibilityLabel={t('auth.phoneLabel')}
                    autoComplete="tel-national"
                    label={t('auth.phoneLabel')}
                    keyboardType="phone-pad"
                    maxLength={20}
                    onChangeText={(value) => {
                      setMobile(normalizeIndianMobileInput(value));
                      setErrorKey(null);
                    }}
                    placeholder={t('auth.phonePlaceholder')}
                    prefix="+91"
                    textContentType="telephoneNumber"
                    value={mobile}
                  />
                  <Text style={styles.helperText}>{t('auth.phoneHint')}</Text>
                  <Text style={styles.channelHeading}>{t('auth.channelPrompt')}</Text>
                  <View accessibilityRole="radiogroup" style={styles.channelOptions}>
                    {(['whatsapp', 'sms'] as const).map((channel) => {
                      const selected = deliveryChannel === channel;

                      return (
                        <Pressable
                          accessibilityLabel={t(`auth.channel.${channel}`)}
                          accessibilityRole="radio"
                          accessibilityState={{ checked: selected, disabled: isBusy }}
                          disabled={isBusy}
                          key={channel}
                          onPress={() => setDeliveryChannel(channel)}
                          style={[
                            styles.channelCard,
                            selected && styles.channelCardSelected,
                          ]}
                        >
                          <View style={[styles.radioOuter, selected && styles.radioOuterSelected]}>
                            {selected ? <View style={styles.radioInner} /> : null}
                          </View>
                          <View style={styles.channelCopy}>
                            <Text style={styles.channelTitle}>{t(`auth.channel.${channel}`)}</Text>
                            <Text style={styles.channelDescription}>
                              {t(`auth.channel.${channel}Description`)}
                            </Text>
                          </View>
                        </Pressable>
                      );
                    })}
                  </View>
                </View>
              ) : (
                <View style={styles.form}>
                  <InputField
                    accessibilityLabel={t('auth.codeLabel')}
                    autoComplete="one-time-code"
                    label={t('auth.codeLabel')}
                    keyboardType="number-pad"
                    maxLength={6}
                    onChangeText={(value) => {
                      const nextCode = normalizeOtpInput(value);

                      if (nextCode !== code) {
                        setClientUuid(Crypto.randomUUID());
                      }

                      setCode(nextCode);
                      setErrorKey(null);
                    }}
                    placeholder={t('auth.codePlaceholder')}
                    textContentType="oneTimeCode"
                    value={code}
                  />
                  <Pressable
                    accessibilityRole="button"
                    disabled={isBusy || resendSeconds > 0}
                    onPress={() => void requestCode(true)}
                    style={styles.textButton}
                  >
                    <Text
                      style={[
                        styles.textButtonLabel,
                        (isBusy || resendSeconds > 0) && styles.disabledText,
                      ]}
                    >
                      {resendSeconds > 0
                        ? t('auth.resendWait').replace('{seconds}', String(resendSeconds))
                        : t('auth.resendCode')}
                    </Text>
                  </Pressable>
                </View>
              )}

              {errorKey !== null && (
                <FeedbackMessage message={t(errorKey)} />
              )}

              {storageError && (
                <View style={styles.storageWarning}>
                  <FeedbackMessage message={t('auth.sessionRestoreError')} />
                  <Pressable
                    accessibilityRole="button"
                    onPress={() => void restoreSession()}
                    style={styles.textButton}
                  >
                    <Text style={styles.textButtonLabel}>{t('auth.retrySession')}</Text>
                  </Pressable>
                </View>
              )}
              {localeStorageError ? (
                <FeedbackMessage message={t('language.storageError')} tone="info" />
              ) : null}

              <AppButton
                disabled={
                  isBusy
                  || (step === 'phone' && resendSeconds > 0)
                  || (step === 'code' && code.length !== 6)
                }
                label={
                  step === 'phone'
                    ? resendSeconds > 0
                      ? t('auth.resendWait').replace('{seconds}', String(resendSeconds))
                      : t('auth.sendCode')
                    : t('auth.verifyCode')
                }
                loading={isBusy}
                loadingLabel={t(
                  pendingAction === 'verify' ? 'auth.verifying' : 'auth.sendingCode',
                )}
                onPress={() => {
                  if (step === 'phone') {
                    void requestCode();
                  } else {
                    void verifyCode();
                  }
                }}
              />

              {step === 'code' && (
                <Pressable
                  accessibilityRole="button"
                  disabled={isBusy}
                  onPress={() => {
                    setStep('phone');
                    setErrorKey(null);
                  }}
                  style={styles.textButton}
                >
                  <Text style={styles.textButtonLabel}>{t('auth.backToPhone')}</Text>
                </Pressable>
              )}
            </View>
          ) : (
            <InfoCard
              title={t('auth.signedInTitle')}
              description={`${t('auth.signedInPhone').replace('{phone}', session.user.phone)}\n${t('auth.signedInDescription')}`}
            >
              {logoutErrorKey !== null ? (
                <FeedbackMessage message={t(logoutErrorKey)} />
              ) : null}
              <AppButton
                label={t('auth.openDairies')}
                onPress={() => router.push('/dairies')}
              />
              <AppButton
                disabled={isLoggingOut}
                label={t('auth.logout')}
                loading={isLoggingOut}
                loadingLabel={t('auth.loggingOut')}
                onPress={() => void signOutNow()}
                variant="secondary"
              />
            </InfoCard>
          )}

          <Pressable
            accessibilityRole="button"
            onPress={() => router.push('/health')}
            style={styles.healthLink}
          >
            <Text style={styles.textButtonLabel}>{t('auth.openHealth')}</Text>
          </Pressable>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    backgroundColor: colors.neutral[50],
    flex: 1,
  },
  keyboardView: {
    flex: 1,
  },
  content: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingHorizontal: layout.screenHorizontalPadding,
    paddingVertical: spacing.xxl,
  },
  toolbar: {
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
  title: {
    color: colors.neutral[900],
    fontSize: typography.pageTitle,
    fontWeight: '700',
    lineHeight: 38,
  },
  description: {
    color: colors.neutral[600],
    fontSize: typography.body,
    lineHeight: 24,
    marginTop: spacing.sm,
  },
  form: {
    marginTop: spacing.xxxl,
  },
  fieldLabel: {
    color: colors.neutral[900],
    fontSize: typography.supporting,
    fontWeight: '600',
    marginBottom: spacing.sm,
  },
  phoneField: {
    alignItems: 'center',
    backgroundColor: colors.neutral.white,
    borderColor: colors.neutral[300],
    borderRadius: radii.medium,
    borderWidth: 1,
    flexDirection: 'row',
    minHeight: 56,
    paddingHorizontal: spacing.lg,
  },
  countryCode: {
    color: colors.neutral[900],
    fontSize: typography.body,
    fontWeight: '600',
    marginRight: spacing.md,
  },
  phoneInput: {
    color: colors.neutral[900],
    flex: 1,
    fontSize: typography.body,
    minHeight: 54,
  },
  codeInput: {
    backgroundColor: colors.neutral.white,
    borderColor: colors.neutral[300],
    borderRadius: radii.medium,
    borderWidth: 1,
    color: colors.neutral[900],
    fontSize: 24,
    letterSpacing: 8,
    minHeight: 56,
    paddingHorizontal: spacing.lg,
  },
  helperText: {
    color: colors.neutral[600],
    fontSize: typography.supporting,
    lineHeight: 22,
    marginTop: spacing.sm,
  },
  channelHeading: {
    color: colors.neutral[900],
    fontSize: typography.supporting,
    fontWeight: '700',
    marginTop: spacing.xl,
  },
  channelOptions: {
    gap: spacing.md,
    marginTop: spacing.md,
  },
  channelCard: {
    alignItems: 'center',
    backgroundColor: colors.neutral.white,
    borderColor: colors.neutral[300],
    borderRadius: radii.medium,
    borderWidth: 1,
    flexDirection: 'row',
    minHeight: 68,
    padding: spacing.lg,
  },
  channelCardSelected: {
    backgroundColor: colors.brand.primaryLight,
    borderColor: colors.brand.primary,
    borderWidth: 2,
  },
  radioOuter: {
    alignItems: 'center',
    borderColor: colors.neutral[600],
    borderRadius: 10,
    borderWidth: 2,
    height: 20,
    justifyContent: 'center',
    width: 20,
  },
  radioOuterSelected: {
    borderColor: colors.brand.primary,
  },
  radioInner: {
    backgroundColor: colors.brand.primary,
    borderRadius: 5,
    height: 10,
    width: 10,
  },
  channelCopy: {
    flex: 1,
    marginLeft: spacing.md,
  },
  channelTitle: {
    color: colors.neutral[900],
    fontSize: typography.body,
    fontWeight: '700',
  },
  channelDescription: {
    color: colors.neutral[600],
    fontSize: typography.supporting,
    marginTop: spacing.xs,
  },
  bodyText: {
    color: colors.neutral[600],
    fontSize: typography.body,
    lineHeight: 24,
    marginTop: spacing.lg,
    textAlign: 'center',
  },
  errorText: {
    color: colors.semantic.error,
    fontSize: typography.supporting,
    lineHeight: 22,
    marginTop: spacing.md,
  },
  storageWarning: {
    backgroundColor: colors.neutral[100],
    borderColor: colors.semantic.error,
    borderRadius: radii.small,
    borderWidth: 1,
    marginTop: spacing.md,
    padding: spacing.md,
  },
  primaryButton: {
    alignItems: 'center',
    backgroundColor: colors.brand.primary,
    borderRadius: radii.medium,
    justifyContent: 'center',
    marginTop: spacing.xl,
    minHeight: 52,
    paddingHorizontal: spacing.lg,
  },
  primaryButtonPressed: {
    backgroundColor: colors.brand.primaryDark,
  },
  disabledButton: {
    opacity: 0.55,
  },
  primaryButtonLabel: {
    color: colors.neutral.white,
    fontSize: typography.body,
    fontWeight: '700',
  },
  textButton: {
    alignSelf: 'flex-start',
    justifyContent: 'center',
    marginTop: spacing.md,
    minHeight: layout.minimumTouchTarget,
    paddingHorizontal: spacing.sm,
  },
  textButtonLabel: {
    color: colors.brand.primary,
    fontSize: typography.supporting,
    fontWeight: '600',
  },
  disabledText: {
    color: colors.neutral[600],
  },
  healthLink: {
    alignItems: 'center',
    alignSelf: 'center',
    justifyContent: 'center',
    marginTop: spacing.xxxl,
    minHeight: layout.minimumTouchTarget,
    paddingHorizontal: spacing.md,
  },
  signedInCard: {
    alignItems: 'center',
    backgroundColor: colors.neutral.white,
    borderColor: colors.neutral[300],
    borderRadius: radii.large,
    borderWidth: 1,
    padding: spacing.xxl,
  },
  loading: {
    alignItems: 'center',
    flex: 1,
    justifyContent: 'center',
    padding: spacing.xxl,
  },
});
