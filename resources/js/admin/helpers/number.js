export const normalizeDecimalInput = (value, fallback = '') => {
  if (value === null || value === undefined || value === '') {
    return fallback;
  }

  const decimal = String(value);

  if (!decimal.includes('.')) {
    return decimal;
  }

  return decimal.replace(/0+$/, '').replace(/\.$/, '');
};
