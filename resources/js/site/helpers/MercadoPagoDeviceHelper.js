export const getMercadoPagoDeviceId = () => {
  if (typeof window === 'undefined') {
    return null;
  }

  const deviceId = window.MP_DEVICE_SESSION_ID;

  if (typeof deviceId !== 'string') {
    return null;
  }

  const normalizedDeviceId = deviceId.trim();

  return normalizedDeviceId || null;
};
