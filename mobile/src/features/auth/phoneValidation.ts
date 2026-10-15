export function normalizeIndianMobileInput(value: string): string {
  const digits = value.replace(/\D/g, '');
  const mobile = digits.length === 12 && digits.startsWith('91') ? digits.slice(2) : digits;

  return mobile.slice(0, 10);
}

export function isValidIndianMobile(value: string): boolean {
  return /^[6-9]\d{9}$/.test(value);
}

export function toIndianE164(value: string): string | null {
  return isValidIndianMobile(value) ? `+91${value}` : null;
}

export function normalizeOtpInput(value: string): string {
  return value.replace(/\D/g, '').slice(0, 6);
}
