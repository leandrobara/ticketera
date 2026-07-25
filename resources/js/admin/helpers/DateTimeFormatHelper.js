export const formatDateTime = (date) => {
  if (!date) {
    return '-';
  }

  const parsedDate = new Date(date);
  const dayName = new Intl.DateTimeFormat('es-AR', {
    weekday: 'long',
  }).format(parsedDate);
  const capitalizedDay = dayName.charAt(0).toUpperCase() + dayName.slice(1);
  const datePart = new Intl.DateTimeFormat('es-AR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(parsedDate);
  const timePart = new Intl.DateTimeFormat('es-AR', {
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).format(parsedDate);

  return `${capitalizedDay} ${datePart} - ${timePart} hs`;
};
