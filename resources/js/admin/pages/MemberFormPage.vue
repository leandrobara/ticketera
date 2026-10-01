<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import ApiService from '@/admin/services/ApiService';

const api = ApiService.getInstance();
const memberId = Number(window.location.pathname.match(/^\/admin\/team\/(\d+)\/edit$/)?.[1] ?? 0);
const member = ref(null);
const shows = ref([]);
const loading = ref(true);
const busy = ref(false);
const error = ref('');
const fieldErrors = ref({});
const form = reactive({
  roles: [],
  show_ids: [],
  all_shows: true,
  is_active: true,
});
const labels = {
  administrator: 'Administrador',
  collaborator: 'Colaborador',
  door: 'Puerta',
};
const isAdministrator = computed(() => form.roles.includes('administrator'));

async function load() {
  try {
    const [membersResponse, showsResponse] = await Promise.all([
      api.get('/api/admin/team'),
      api.get('/api/admin/shows', { params: { per_page: 1000 } }),
    ]);
    member.value = membersResponse.data.data.find(item => Number(item.id) === memberId) ?? null;
    shows.value = showsResponse.data.data.data;

    if (!member.value) {
      error.value = 'El miembro no existe o no está disponible.';
      return;
    }

    form.roles = [...member.value.roles];
    form.show_ids = [...(member.value.show_ids ?? [])];
    form.all_shows = member.value.show_ids === null;
    form.is_active = member.value.is_active;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudo cargar el miembro.';
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (busy.value || !member.value) return;

  busy.value = true;
  error.value = '';
  fieldErrors.value = {};

  try {
    await api.put('/api/admin/team/' + member.value.id, {
      roles: form.roles,
      is_active: form.is_active,
      show_ids: form.all_shows || isAdministrator.value ? null : form.show_ids,
    });
    window.location.assign('/admin/team');
  } catch (e) {
    error.value = e.response?.data?.error?.message ?? e.response?.data?.message ?? 'No se pudo actualizar el miembro.';
    fieldErrors.value = e.response?.data?.errors ?? {};
  } finally {
    busy.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div class="admin-form-page">
    <a class="d-inline-block mb-3" href="/admin/team">← Equipo e invitaciones</a>

    <div class="page-header mb-4">
      <h1 class="page-title">Editar miembro</h1>
      <p v-if="member" class="text-secondary mt-2 mb-0">{{ member.user.name }} · {{ member.user.email }}</p>
    </div>

    <div v-if="loading" class="card card-body text-secondary">Cargando miembro…</div>
    <div v-else-if="error && !member" class="alert alert-danger" role="alert">{{ error }}</div>
    <div v-else-if="member.roles.includes('leader')" class="alert alert-warning" role="alert">
      El líder de la organización no se puede modificar.
    </div>

    <form v-else class="card" @submit.prevent="save">
      <div class="card-body">
        <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>

        <fieldset class="mb-4">
          <legend class="form-label">Roles <span class="text-danger">*</span></legend>
          <p class="text-secondary small">Podés seleccionar más de uno.</p>
          <label v-for="(label, role) in labels" :key="role" class="form-check">
            <input v-model="form.roles" type="checkbox" :value="role" class="form-check-input">
            <span class="form-check-label">{{ label }}</span>
          </label>
          <div v-if="fieldErrors.roles" class="text-danger small mt-2">{{ fieldErrors.roles[0] }}</div>
        </fieldset>

        <fieldset class="mb-4" :disabled="isAdministrator">
          <legend class="form-label">Eventos</legend>
          <label class="form-check">
            <input v-model="form.all_shows" type="checkbox" class="form-check-input">
            <span class="form-check-label">Acceso a todos los eventos</span>
          </label>
          <div v-if="!form.all_shows && !isAdministrator" class="mt-3">
            <label class="form-label" for="member-events">Eventos permitidos</label>
            <select
              id="member-events"
              v-model="form.show_ids"
              multiple
              size="7"
              class="form-select"
              :class="{'is-invalid': fieldErrors.show_ids}"
            >
              <option v-for="show in shows" :key="show.id" :value="show.id">{{ show.title }}</option>
            </select>
            <div v-if="fieldErrors.show_ids" class="invalid-feedback">{{ fieldErrors.show_ids[0] }}</div>
            <p class="text-secondary small mt-2 mb-0">Mantené presionada Ctrl o Cmd para seleccionar varios eventos.</p>
          </div>
          <p v-if="isAdministrator" class="text-secondary small mt-2 mb-0">Los administradores tienen acceso a todos los eventos.</p>
        </fieldset>

        <div>
          <label class="form-check form-switch">
            <input v-model="form.is_active" type="checkbox" class="form-check-input">
            <span class="form-check-label">Miembro activo</span>
          </label>
          <p class="text-secondary small mb-0">Los miembros desactivados no pueden acceder a esta organización.</p>
        </div>
      </div>

      <div class="card-footer d-flex gap-2 flex-wrap">
        <button class="btn btn-success" type="submit" :disabled="busy || !form.roles.length">
          {{ busy ? 'Guardando…' : 'Guardar cambios' }}
        </button>
        <a class="btn" href="/admin/team">Cancelar</a>
      </div>
    </form>
  </div>
</template>
