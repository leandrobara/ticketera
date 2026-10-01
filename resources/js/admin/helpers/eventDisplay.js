export const publicationLabel = status => ({
  draft: 'Borrador',
  published: 'Publicado',
  finished: 'Finalizado',
  cancelled: 'Cancelado',
})[status] ?? status;

export const publicationClass = status => ({
  draft: 'bg-purple-lt',
  published: 'bg-green-lt',
  finished: 'bg-blue-lt',
  cancelled: 'bg-red-lt',
})[status] ?? 'bg-secondary-lt';

export function eventPeriod(event) {
  if (!event.first_presentation_at || !event.last_presentation_at) return 'Sin fechas';
  const now = Date.now();
  if (new Date(event.first_presentation_at).getTime() > now) return 'Próximo';
  if (new Date(event.last_presentation_at).getTime() <= now) return 'Finalizado';
  return 'En curso';
}

export const periodClass = period => ({
  'Próximo': 'bg-cyan-lt',
  'En curso': 'bg-orange-lt',
  'Finalizado': 'bg-indigo-lt',
  'Sin fechas': 'bg-yellow-lt',
})[period] ?? 'bg-secondary-lt';

export function formatEventDate(value) {
  if (!value) return '—';
  return new Intl.DateTimeFormat('es-AR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}
