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

import { LanguageSwitcher } from '../../components/LanguageSwitcher';
import { AppButton, FeedbackMessage, InputField, LoadingState } from '../../components/ui';
import { useLocale } from '../../i18n/LocaleProvider';
import type { TranslationKey } from '../../i18n/translations';
import { DairyApiError, createDairy } from '../../services/api/dairies';
import { useAuthSession } from '../../services/auth/AuthSessionProvider';
import {
  deleteDairyCreationDraft,
  readDairyCreationDraft,
  writeDairyCreationDraft,
} from '../../services/auth/sessionStorage';
import type { DairyCreationDraft, DairyFormValues } from '../../types/dairy';
import { colors, layout, radii, spacing, typography } from '../../theme/tokens';
import {
  emptyDairyForm,
  toDairyCreatePayload,
  validateDairyForm,
} from './validation';
import type { DairyFormErrors, DairyFormField } from './validation';

const fieldTranslations: Record<DairyFormField, TranslationKey> = {
  name: 'dairy.name',
  legalName: 'dairy.legalName',
  city: 'dairy.city',
  state: 'dairy.state',
  postalCode: 'dairy.postalCode',
};

const apiFieldMap: Record<string, DairyFormField> = {
  name: 'name',
  legal_name: 'legalName',
  'location.city': 'city',
  'location.state': 'state',
  'location.postal_code': 'postalCode',
};

function errorTranslationKey(error: DairyApiError): TranslationKey {
  switch (error.kind) {
    case 'network':
      return 'dairy.networkError';
    case 'unauthorized':
      return 'dairy.sessionExpired';
    case 'validation':
      return 'dairy.validationError';
    case 'conflict':
      return 'dairy.conflictError';
    case 'server':
      return 'dairy.serverError';
    case 'invalid-response':
      return 'dairy.invalidResponse';
  }
}

function validationTranslationKey(
  error: DairyFormErrors[DairyFormField],
): TranslationKey | null {
  if (error === 'required') {
    return 'dairy.required';
  }

  if (error === 'too-long') {
    return 'dairy.tooLong';
  }

  return null;
}

export function DairyOnboardingScreen() {
  const { t } = useLocale();
  const { session, clearSession } = useAuthSession();
  const [values, setValues] = useState<DairyFormValues>(emptyDairyForm);
  const [clientUuid, setClientUuid] = useState(() => Crypto.randomUUID());
  const [isDraftLoaded, setIsDraftLoaded] = useState(false);
  const [hasDraftLoadError, setHasDraftLoadError] = useState(false);
  const [hasStorageError, setHasStorageError] = useState(false);
  const [isRetryOnly, setIsRetryOnly] = useState(false);
  const [hasConflict, setHasConflict] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Record<DairyFormField, TranslationKey | null>>({
    name: null,
    legalName: null,
    city: null,
    state: null,
    postalCode: null,
  });
  const [errorKey, setErrorKey] = useState<TranslationKey | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    let isMounted = true;

    void readDairyCreationDraft()
      .then((draft) => {
        if (isMounted && draft !== null) {
          setValues(draft.values);
          setClientUuid(draft.clientUuid);
          setIsRetryOnly(draft.status !== 'ready');
          setHasConflict(draft.status === 'conflict');

          if (draft.status === 'conflict') {
            setErrorKey('dairy.conflictError');
          }
        }
      })
      .catch(() => {
        if (isMounted) {
          setHasDraftLoadError(true);
          setHasStorageError(true);
        }
      })
      .finally(() => {
        if (isMounted) {
          setIsDraftLoaded(true);
        }
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const changeField = (field: DairyFormField, value: string) => {
    if (isRetryOnly || hasConflict) {
      return;
    }

    setValues((current) => ({ ...current, [field]: value }));
    setClientUuid(Crypto.randomUUID());
    setFieldErrors((current) => ({ ...current, [field]: null }));
    setErrorKey(null);
  };

  const retryDraftLoad = async () => {
    setIsDraftLoaded(false);
    setHasStorageError(false);

    try {
      const draft = await readDairyCreationDraft();

      if (draft !== null) {
        setValues(draft.values);
        setClientUuid(draft.clientUuid);
        setIsRetryOnly(draft.status !== 'ready');
        setHasConflict(draft.status === 'conflict');
        setErrorKey(draft.status === 'conflict' ? 'dairy.conflictError' : null);
      } else {
        setValues(emptyDairyForm);
        setClientUuid(Crypto.randomUUID());
        setIsRetryOnly(false);
        setHasConflict(false);
        setErrorKey(null);
      }

      setHasStorageError(false);
      setHasDraftLoadError(false);
    } catch {
      setHasDraftLoadError(true);
      setHasStorageError(true);
    } finally {
      setIsDraftLoaded(true);
    }
  };

  const submit = async () => {
    const validationErrors = validateDairyForm(values);
    setFieldErrors({
      name: validationTranslationKey(validationErrors.name),
      legalName: validationTranslationKey(validationErrors.legalName),
      city: validationTranslationKey(validationErrors.city),
      state: validationTranslationKey(validationErrors.state),
      postalCode: validationTranslationKey(validationErrors.postalCode),
    });
    setErrorKey(null);

    if (Object.keys(validationErrors).length > 0 || session === null) {
      return;
    }

    if (hasConflict) {
      return;
    }

    setIsSubmitting(true);
    const draft: DairyCreationDraft = { clientUuid, status: 'pending', values };

    try {
      await writeDairyCreationDraft(draft);
      setHasStorageError(false);
    } catch {
      setHasStorageError(true);
      setErrorKey('dairy.storageError');
      setIsSubmitting(false);

      return;
    }

    try {
      const dairy = await createDairy(
        session.accessToken,
        toDairyCreatePayload(values, clientUuid),
      );

      await deleteDairyCreationDraft();
      router.replace({ pathname: '/dairies', params: { created: dairy.id } });
    } catch (error) {
      if (error instanceof DairyApiError && error.kind === 'unauthorized') {
        try {
          await clearSession();
          router.replace('/');
        } catch {
          setHasStorageError(true);
          setErrorKey('dairy.storageError');
        }
      } else if (error instanceof DairyApiError && error.kind === 'conflict') {
        try {
          await writeDairyCreationDraft({ clientUuid, status: 'conflict', values });
        } catch {
          setHasStorageError(true);
        }

        setIsRetryOnly(true);
        setHasConflict(true);
        setErrorKey('dairy.conflictError');
      } else if (error instanceof DairyApiError && error.kind === 'validation') {
        try {
          await writeDairyCreationDraft({ clientUuid, status: 'ready', values });
          setHasStorageError(false);
        } catch {
          setHasStorageError(true);
        }

        setIsRetryOnly(false);
        const nextFieldErrors: DairyFormErrors = {};

        for (const field of error.validationFields) {
          const mappedField = apiFieldMap[field];

          if (mappedField !== undefined) {
            nextFieldErrors[mappedField] = 'required';
          }
        }

        setFieldErrors({
          name: nextFieldErrors.name ? 'dairy.validationError' : null,
          legalName: nextFieldErrors.legalName ? 'dairy.validationError' : null,
          city: nextFieldErrors.city ? 'dairy.validationError' : null,
          state: nextFieldErrors.state ? 'dairy.validationError' : null,
          postalCode: nextFieldErrors.postalCode ? 'dairy.validationError' : null,
        });
        setErrorKey(errorTranslationKey(error));
      } else if (error instanceof DairyApiError) {
        setIsRetryOnly(true);
        setErrorKey(errorTranslationKey(error));
      } else {
        setIsRetryOnly(true);
        setHasStorageError(true);
        setErrorKey('dairy.storageError');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  const renderField = (
    field: DairyFormField,
    placeholderKey: TranslationKey,
    maxLength: number,
  ) => {
    const fieldError = fieldErrors[field];

    return (
      <View key={field} style={styles.field}>
        <InputField
          accessibilityLabel={t(fieldTranslations[field])}
          autoCapitalize={field === 'postalCode' ? 'none' : 'words'}
          editable={!isSubmitting && isDraftLoaded && !isRetryOnly && !hasConflict}
          error={fieldError === null ? null : t(fieldError)}
          label={t(fieldTranslations[field])}
          maxLength={maxLength}
          onChangeText={(value) => changeField(field, value)}
          placeholder={t(placeholderKey)}
          value={values[field]}
        />
      </View>
    );
  };

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
          <View style={styles.toolbar}>
            <Pressable
              accessibilityRole="button"
              onPress={() => router.replace({
                pathname: '/dairies',
                params: { refresh: Crypto.randomUUID() },
              })}
              style={styles.backButton}
            >
              <Text style={styles.backLabel}>{t('dairy.back')}</Text>
            </Pressable>
            <LanguageSwitcher />
          </View>

          <Text style={styles.title}>{t('dairy.formTitle')}</Text>
          <Text style={styles.description}>{t('dairy.formDescription')}</Text>

          {!isDraftLoaded ? (
            <LoadingState label={t('dairy.loading')} />
          ) : !hasDraftLoadError ? (
            <View style={styles.form}>
              {renderField('name', 'dairy.namePlaceholder', 191)}
              {renderField('legalName', 'dairy.legalNamePlaceholder', 191)}
              {renderField('city', 'dairy.cityPlaceholder', 150)}
              {renderField('state', 'dairy.statePlaceholder', 150)}
              {renderField('postalCode', 'dairy.postalCodePlaceholder', 20)}

              <View style={styles.field}>
                <Text style={styles.fieldLabel}>{t('dairy.country')}</Text>
                <View style={styles.readOnlyField}>
                  <Text style={styles.readOnlyValue}>
                    {t('dairy.countryIndia')} (IN)
                  </Text>
                </View>
              </View>
            </View>
          ) : null}

          {hasStorageError && (
            <FeedbackMessage message={t('dairy.storageError')} />
          )}

          {hasDraftLoadError && (
            <AppButton
              variant="secondary"
              label={t('dairy.retryStorage')}
              onPress={() => void retryDraftLoad()}
            />
          )}

          {errorKey !== null && errorKey !== 'dairy.storageError' && (
            <FeedbackMessage message={t(errorKey)} />
          )}

          {isRetryOnly && !hasConflict && (
            <Text style={styles.helperText}>{t('dairy.pendingRequest')}</Text>
          )}

          <AppButton
            disabled={isSubmitting || !isDraftLoaded || hasDraftLoadError || hasConflict}
            label={t(isRetryOnly ? 'dairy.retrySave' : 'dairy.save')}
            loading={isSubmitting}
            loadingLabel={t('dairy.saving')}
            onPress={() => void submit()}
          />
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
    paddingHorizontal: layout.screenHorizontalPadding,
    paddingVertical: spacing.lg,
  },
  toolbar: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: spacing.xl,
  },
  backButton: {
    justifyContent: 'center',
    minHeight: layout.minimumTouchTarget,
    paddingHorizontal: spacing.sm,
  },
  backLabel: {
    color: colors.brand.primary,
    fontSize: typography.supporting,
    fontWeight: '600',
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
    marginTop: spacing.xl,
  },
  field: {
    marginBottom: spacing.lg,
  },
  fieldLabel: {
    color: colors.neutral[900],
    fontSize: typography.supporting,
    fontWeight: '600',
    marginBottom: spacing.sm,
  },
  input: {
    backgroundColor: colors.neutral.white,
    borderColor: colors.neutral[300],
    borderRadius: radii.medium,
    borderWidth: 1,
    color: colors.neutral[900],
    fontSize: typography.body,
    minHeight: 54,
    paddingHorizontal: spacing.lg,
  },
  inputError: {
    borderColor: colors.semantic.error,
  },
  fieldError: {
    color: colors.semantic.error,
    fontSize: typography.meta,
    marginTop: spacing.xs,
  },
  helperText: {
    color: colors.neutral[600],
    fontSize: typography.supporting,
    lineHeight: 22,
    marginTop: spacing.md,
  },
  readOnlyField: {
    backgroundColor: colors.neutral[100],
    borderColor: colors.neutral[300],
    borderRadius: radii.medium,
    borderWidth: 1,
    justifyContent: 'center',
    minHeight: 54,
    paddingHorizontal: spacing.lg,
  },
  readOnlyValue: {
    color: colors.neutral[600],
    fontSize: typography.body,
  },
  loading: {
    alignItems: 'center',
    marginTop: spacing.xxxl,
  },
  errorText: {
    color: colors.semantic.error,
    fontSize: typography.supporting,
    lineHeight: 22,
    marginTop: spacing.md,
  },
  secondaryButton: {
    alignSelf: 'flex-start',
    justifyContent: 'center',
    marginTop: spacing.md,
    minHeight: layout.minimumTouchTarget,
    paddingHorizontal: spacing.md,
  },
  secondaryButtonLabel: {
    color: colors.brand.primary,
    fontSize: typography.supporting,
    fontWeight: '700',
  },
  primaryButton: {
    alignItems: 'center',
    backgroundColor: colors.brand.primary,
    borderRadius: radii.medium,
    justifyContent: 'center',
    marginTop: spacing.md,
    minHeight: 52,
    paddingHorizontal: spacing.lg,
  },
  primaryButtonLabel: {
    color: colors.neutral.white,
    fontSize: typography.body,
    fontWeight: '700',
  },
  buttonPressed: {
    backgroundColor: colors.brand.primaryDark,
  },
  disabledButton: {
    opacity: 0.55,
  },
});
