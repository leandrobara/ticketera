const ATTRIBUTION_STORAGE_KEY = 'entradatix.attribution';
const ATTRIBUTION_LIFETIME_IN_MILLISECONDS = 30 * 24 * 60 * 60 * 1000;
const ATTRIBUTION_QUERY_PARAMETER_NAMES = [
  'utm_source',
  'utm_medium',
  'utm_campaign',
  'utm_content',
  'utm_term',
  'fbclid',
];
const ATTRIBUTION_FIELD_NAMES = [
  'utm_source',
  'utm_medium',
  'utm_campaign',
  'utm_content',
  'utm_term',
  'fbclid',
  'fbc',
  'fbp',
];

const getQueryParameterValue = (queryParameters, parameterName) => {
  const parameterValue = queryParameters.get(parameterName);

  if (!parameterValue) {
    return null;
  }

  const normalizedParameterValue = parameterValue.trim();

  return normalizedParameterValue || null;
};

const getCookieValue = (cookieName) => {
  if (typeof document === 'undefined') {
    return null;
  }

  const cookiePrefix = `${cookieName}=`;
  const cookies = document.cookie.split(';');

  for (const cookie of cookies) {
    const normalizedCookie = cookie.trim();

    if (!normalizedCookie.startsWith(cookiePrefix)) {
      continue;
    }

    return decodeURIComponent(normalizedCookie.slice(cookiePrefix.length));
  }

  return null;
};

const hasCampaignAttribution = (queryParameters) => {
  for (const parameterName of ATTRIBUTION_QUERY_PARAMETER_NAMES) {
    if (getQueryParameterValue(queryParameters, parameterName)) {
      return true;
    }
  }

  return false;
};

const hasAttributionValues = (attribution) => {
  for (const fieldName of ATTRIBUTION_FIELD_NAMES) {
    if (attribution[fieldName]) {
      return true;
    }
  }

  return false;
};

const getAttributionFromCurrentUrl = () => {
  const queryParameters = new URLSearchParams(window.location.search);

  if (!hasCampaignAttribution(queryParameters)) {
    return null;
  }

  return {
    utm_source: getQueryParameterValue(queryParameters, 'utm_source'),
    utm_medium: getQueryParameterValue(queryParameters, 'utm_medium'),
    utm_campaign: getQueryParameterValue(queryParameters, 'utm_campaign'),
    utm_content: getQueryParameterValue(queryParameters, 'utm_content'),
    utm_term: getQueryParameterValue(queryParameters, 'utm_term'),
    fbclid: getQueryParameterValue(queryParameters, 'fbclid'),
    fbc: getCookieValue('_fbc'),
    fbp: getCookieValue('_fbp'),
  };
};

export const captureAttributionFromCurrentUrl = () => {
  if (typeof window === 'undefined') {
    return;
  }

  const attribution = getAttributionFromCurrentUrl();

  if (!attribution) {
    return;
  }

  const storedAttribution = {
    expires_at: Date.now() + ATTRIBUTION_LIFETIME_IN_MILLISECONDS,
    attribution,
  };

  window.localStorage.setItem(
    ATTRIBUTION_STORAGE_KEY,
    JSON.stringify(storedAttribution),
  );
};

export const getStoredAttribution = () => {
  if (typeof window === 'undefined') {
    return null;
  }

  const serializedAttribution = window.localStorage.getItem(ATTRIBUTION_STORAGE_KEY);

  if (!serializedAttribution) {
    return null;
  }

  try {
    const storedAttribution = JSON.parse(serializedAttribution);

    if (!storedAttribution?.expires_at || storedAttribution.expires_at <= Date.now()) {
      window.localStorage.removeItem(ATTRIBUTION_STORAGE_KEY);
      return null;
    }

    if (!hasAttributionValues(storedAttribution.attribution ?? {})) {
      return null;
    }

    return storedAttribution.attribution;
  } catch {
    window.localStorage.removeItem(ATTRIBUTION_STORAGE_KEY);
    return null;
  }
};
