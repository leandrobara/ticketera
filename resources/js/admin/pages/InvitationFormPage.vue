<script setup>
import { onMounted, reactive, ref } from 'vue';
import ApiService from '@/admin/services/ApiService';

const api = ApiService.getInstance();
const invitationId = Number(window.location.pathname.match(/^\/admin\/invitations\/(\d+)\/edit$/)?.[1] ?? 0);
const isEditing = Boolean(invitationId);
const labels = {
  administrator: 'Administrador',
  collaborator: 'Colaborador',
  door: 'Puerta',
};
const form = reactive({
  roles: ['collaborator'],
  max_uses: 1,
  expires_at: '',
  show_ids: [],
  all_shows: true,
  is_active: true,
});
const shows = ref([]);
const invitation = ref(null);
const createdUrl = ref('');
const error = ref('');
const loadError = ref('');
const fieldErrors = ref({});
const busy = ref(false);
const copied = ref(false);
const showFullUrl = ref(false);
const loading = ref(true);
const busyAction = ref(null);
const actionError = ref('');

function localDateTime(value) {
  if (!value) return '';
  const date = new Date(value);
  const pad = number => String(number).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function shortInvitationUrl(url) {
  const separator = url.lastIndexOf('/') + 1;
  const token = url.slice(separator);
  return url.slice(0, separator) + token.slice(0, 10) + (token.length > 10 ? '…' : '');
}

async function load() {
  try {
    const [showsResponse, invitationResponse] = await Promise.all([
      api.get('/api/admin/shows', { params: { per_page: 1000 } }),
      isEditing ? api.get('/api/admin/invitations/' + invitationId) : Promise.resolve(null),
    ]);
    shows.value = showsResponse.data.data.data;

    if (invitationResponse) {
      invitation.value = invitationResponse.data.data;
      form.roles = [...invitation.value.roles];
      form.max_uses = invitation.value.max_uses;
      form.expires_at = localDateTime(invitation.value.expires_at);
      form.show_ids = [...(invitation.value.show_ids ?? [])];
      form.all_shows = invitation.value.show_ids === null;
      form.is_active = !invitation.value.revoked_at;
    }
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'No se pudieron cargar los datos de la invitación.';
  } finally {
    loading.value = false;
  }
}

async function saveInvitation() {
  if (busy.value || busyAction.value) return;

  busy.value = true;
  error.value = '';
  fieldErrors.value = {};
  copied.value = false;

  try {
    const payload = {
      roles: form.roles,
      max_uses: form.max_uses,
      expires_at: form.expires_at ? new Date(form.expires_at).toISOString() : null,
      show_ids: form.all_shows ? null : form.show_ids,
      ...(isEditing ? { is_active: form.is_active } : {}),
    };
    if (isEditing) {
      await api.put('/api/admin/invitations/' + invitationId, payload);
      window.location.assign('/admin/team?view=invitations');
    } else {
      const response = await api.post('/api/admin/invitations', payload);
      createdUrl.value = response.data.data.url;
    }
  } catch (e) {
    error.value = e.response?.data?.error?.message ?? e.response?.data?.message ?? (isEditing ? 'No se pudo actualizar la invitación.' : 'No se pudo crear la invitación.');
    fieldErrors.value = e.response?.data?.errors ?? {};
  } finally {
    busy.value = false;
  }
}

async function copyUrl() {
  try {
    await navigator.clipboard.writeText(isEditing ? invitation.value.url : createdUrl.value);
    copied.value = true;
  } catch {
    showFullUrl.value = isEditing;
    error.value = isEditing
      ? 'No se pudo copiar automáticamente. Seleccioná el enlace completo que aparece debajo.'
      : 'No se pudo copiar el enlace. Seleccionalo y copialo manualmente.';
  }
}

async function remove() {
  if (busy.value || busyAction.value) return;
  if (!window.confirm('¿Eliminar definitivamente este link de invitación? Dejará de funcionar y desaparecerá del listado. Los miembros que lo usaron conservarán su acceso.')) return;

  busyAction.value = 'delete';
  actionError.value = '';

  try {
    await api.delete('/api/admin/invitations/' + invitationId);
    window.location.assign('/admin/team?view=invitations');
  } catch (e) {
    actionError.value = e.response?.data?.error?.message ?? e.response?.data?.message ?? 'No se pudo eliminar la invitación.';
    busyAction.value = null;
  }
}

onMounted(load);
</script>

<template>
  <div class="admin-form-page">
    <a class="d-inline-block mb-3" href="/admin/team?view=invitations">← Links de invitación</a>

    <div class="page-header mb-4">
      <h1 class="page-title">{{ isEditing ? 'Editar link de invitación' : 'Crear link de invitación' }}</h1>
      <p class="text-secondary mt-2 mb-0">{{ isEditing ? 'Actualizá los permisos y límites de este enlace.' : 'Definí qué permisos tendrá la persona que use este enlace.' }}</p>
    </div>

    <div v-if="loading" class="card card-body text-secondary">Cargando…</div>
    <div v-else-if="loadError" class="alert alert-danger" role="alert">{{ loadError }}</div>

    <div v-else-if="createdUrl" class="card">
      <div class="card-body">
        <div class="alert alert-success" role="status">El link de invitación se creó correctamente.</div>
        <label class="form-label" for="created-invitation-url">Link para compartir</label>
        <div class="input-group">
          <input id="created-invitation-url" :value="createdUrl" readonly class="form-control" @focus="$event.target.select()">
          <button type="button" class="btn btn-primary" @click="copyUrl">{{ copied ? 'Copiado' : 'Copiar' }}</button>
        </div>
      </div>
      <div class="card-footer d-flex gap-2 flex-wrap">
        <a class="btn btn-success" href="/admin/team?view=invitations">Ver links de invitación</a>
        <button type="button" class="btn" @click="createdUrl = ''; copied = false">Crear otro link</button>
      </div>
    </div>

    <form v-else class="card" @submit.prevent="saveInvitation">
      <div class="card-body">
        <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>

        <div v-if="isEditing" class="mb-4">
          <label class="form-check form-switch mt-3">
            <input v-model="form.is_active" type="checkbox" class="form-check-input">
            <span class="form-check-label">Enlace de invitación activo</span>
          </label>
          <p class="text-secondary small mb-0">Los links inactivos no permiten nuevos ingresos. El vencimiento y el límite de usos también se aplican a los links activos.</p>
        </div>
        
        <div v-if="isEditing" class="mb-4">
          <div class="form-label">Enlace</div>
          <div class="input-group">
            <span class="form-control text-truncate font-monospace" :title="invitation.url">{{ shortInvitationUrl(invitation.url) }}</span>
            <button type="button" class="btn" :disabled="busy || busyAction !== null" @click="copyUrl">{{ copied ? 'Copiado' : 'Copiar' }}</button>
          </div>
          <input v-if="showFullUrl" :value="invitation.url" readonly class="form-control mt-2" aria-label="Enlace completo de invitación" @focus="$event.target.select()">
        </div>

        <div class="mb-4">
          <label class="form-label" for="invitation-uses">Cantidad máxima de usos <span class="text-danger">*</span></label>
          <input
            id="invitation-uses"
            v-model.number="form.max_uses"
            type="number"
            :min="Math.max(1, invitation?.uses ?? 0)"
            max="10000"
            class="form-control"
            :class="{'is-invalid': fieldErrors.max_uses}"
            required
            autofocus
          >
          <div v-if="fieldErrors.max_uses" class="invalid-feedback">{{ fieldErrors.max_uses[0] }}</div>
          <p v-if="isEditing" class="text-secondary small mt-2 mb-0">Usos actuales: {{ invitation.uses }}</p>
        </div>

        <fieldset class="mb-4">
          <legend class="form-label">Roles <span class="text-danger">*</span></legend>
          <p class="text-secondary small">Podés seleccionar más de uno.</p>
          <label v-for="(label, role) in labels" :key="role" class="form-check">
            <input v-model="form.roles" type="checkbox" :value="role" class="form-check-input">
            <span class="form-check-label">{{ label }}</span>
          </label>
          <div v-if="fieldErrors.roles" class="text-danger small mt-2">{{ fieldErrors.roles[0] }}</div>
        </fieldset>

        <div class="mb-4">
          <label class="form-label" for="invitation-expiration">Vencimiento <span class="text-secondary fw-normal">(opcional)</span></label>
          <input
            id="invitation-expiration"
            v-model="form.expires_at"
            type="datetime-local"
            class="form-control"
            :class="{'is-invalid': fieldErrors.expires_at}"
          >
          <div v-if="fieldErrors.expires_at" class="invalid-feedback">{{ fieldErrors.expires_at[0] }}</div>
        </div>

        <div>
          <label class="form-check">
            <input v-model="form.all_shows" type="checkbox" class="form-check-input">
            <span class="form-check-label">Acceso a todos los eventos</span>
          </label>
          <div v-if="!form.all_shows" class="mt-3">
            <label class="form-label" for="invitation-events">Eventos permitidos</label>
            <select
              id="invitation-events"
              v-model="form.show_ids"
              multiple
              class="form-select"
              :class="{'is-invalid': fieldErrors.show_ids}"
              size="6"
            >
              <option v-for="show in shows" :key="show.id" :value="show.id">{{ show.title }}</option>
            </select>
            <div v-if="fieldErrors.show_ids" class="invalid-feedback">{{ fieldErrors.show_ids[0] }}</div>
            <p class="text-secondary small mt-2 mb-0">Mantené presionada Ctrl o Cmd para seleccionar varios eventos.</p>
          </div>
        </div>
      </div>

      <div class="card-footer d-flex gap-2 flex-wrap">
        <button class="btn btn-success" type="submit" :disabled="busy || busyAction !== null || !form.roles.length">
          {{ busy ? 'Guardando…' : (isEditing ? 'Guardar cambios' : 'Crear link de invitación') }}
        </button>
        <a class="btn" href="/admin/team?view=invitations">Cancelar</a>
      </div>
    </form>

    <section v-if="isEditing && invitation && !loadError" class="card mt-4" aria-label="Acciones del link de invitación">
      <div class="card-body">
        <p class="small text-secondary mb-3">Si eliminás este link, dejará de estar disponible. Los miembros que ya ingresaron conservarán su acceso.</p>
        <div v-if="actionError" class="alert alert-danger" role="alert">{{ actionError }}</div>
        <button type="button" class="btn btn-outline-danger btn-sm" :disabled="busy || busyAction !== null" @click="remove">
          {{ busyAction === 'delete' ? 'Eliminando…' : 'Eliminar link' }}
        </button>
      </div>
    </section>
  </div>
</template>
