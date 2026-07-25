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

export const formatLongDate = (date) => {
  if (!date) {
    return '-';
  }

  const formattedDate = new Intl.DateTimeFormat('es-AR', {
    weekday: 'long',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(new Date(date));

  return formattedDate.charAt(0).toUpperCase() + formattedDate.slice(1);
};

export const formatWeekdayTime = (date) => {
  if (!date) {
    return '-';
  }

  const parsedDate = new Date(date);
  const weekday = new Intl.DateTimeFormat('es-AR', {
    weekday: 'long',
  }).format(parsedDate);
  const capitalizedWeekday = weekday.charAt(0).toUpperCase() + weekday.slice(1);
  const formattedTime = new Intl.DateTimeFormat('es-AR', {
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).format(parsedDate);
  const [hours, minutes] = formattedTime.split(':');
  const time = minutes === '00'
    ? `${Number(hours)} hs`
    : `${Number(hours)}:${minutes} hs`;

  return `${capitalizedWeekday}, ${time}`;
};
