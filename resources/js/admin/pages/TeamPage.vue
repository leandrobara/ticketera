<script setup>
import { onMounted, ref } from 'vue';
import ApiService from '@/admin/services/ApiService';

const api = ApiService.getInstance();
const activeView = ref(new URLSearchParams(window.location.search).get('view') === 'invitations' ? 'invitations' : 'members');
const members = ref([]);
const invitations = ref([]);
const shows = ref([]);
const error = ref('');

const labels = {
  leader: 'Líder',
  administrator: 'Administrador',
  collaborator: 'Colaborador',
  door: 'Puerta',
};
const roleBadgeClasses = {
  leader: 'bg-blue-lt',
  administrator: 'bg-purple-lt',
  collaborator: 'bg-cyan-lt',
  door: 'bg-orange-lt',
};

async function load() {
  error.value = '';

  try {
    const [membersResponse, invitationsResponse, showsResponse] = await Promise.all([
      api.get('/api/admin/team'),
      api.get('/api/admin/invitations'),
      api.get('/api/admin/shows', { params: { per_page: 1000 } }),
    ]);

    members.value = membersResponse.data.data;
    invitations.value = invitationsResponse.data.data;
    shows.value = showsResponse.data.data.data;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudo cargar el equipo.';
  }
}

async function copy(link) {
  try {
    await navigator.clipboard.writeText(link);
  } catch {
    error.value = 'No se pudo copiar el enlace. Seleccionalo y copialo manualmente.';
  }
}

function invitationStatus(invitation) {
  if (invitation.revoked_at) return 'Inactivo';
  if (invitation.uses >= invitation.max_uses) return 'Agotado';
  if (invitation.expires_at && new Date(invitation.expires_at) < new Date()) return 'Vencido';

  return 'Activo';
}

function invitationStatusClass(invitation) {
  const status = invitationStatus(invitation);
  if (status === 'Activo') return 'bg-green-lt';
  return 'bg-orange-lt';
}

function assignedEvents(item) {
  if (item.show_ids === null || item.roles.includes('leader') || item.roles.includes('administrator')) {
    return 'Todos los eventos';
  }

  if (!item.show_ids.length) return 'Sin eventos seleccionados';

  const titles = new Map(shows.value.map(show => [Number(show.id), show.title]));
  return item.show_ids.map(id => titles.get(Number(id))).filter(Boolean).join(', ') || 'Sin eventos seleccionados';
}

function shortInvitationUrl(url) {
  const separator = url.lastIndexOf('/') + 1;
  const token = url.slice(separator);
  return url.slice(0, separator) + token.slice(0, 10) + (token.length > 10 ? '…' : '');
}

onMounted(load);
</script>

<template>
  <h1 class="page-title mb-4">Equipo e invitaciones</h1>

  <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>

  <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-4">
    <div class="btn-group" role="group" aria-label="Vista de equipo e invitaciones">
      <button
        type="button"
        class="btn"
        :class="activeView === 'members' ? 'btn-primary' : 'btn-outline-primary'"
        :aria-pressed="activeView === 'members'"
        @click="activeView = 'members'"
      >
        Ver miembros
      </button>
      <button
        type="button"
        class="btn"
        :class="activeView === 'invitations' ? 'btn-primary' : 'btn-outline-primary'"
        :aria-pressed="activeView === 'invitations'"
        @click="activeView = 'invitations'"
      >
        Ver links de invitaciones
      </button>
    </div>

    <a class="btn btn-success" href="/admin/invitations/new">Crear link de invitación</a>
  </div>

  <div v-if="activeView === 'members'" class="card">
    <div class="card-header">
      <h2 class="card-title">Miembros</h2>
    </div>
    <div class="table-responsive">
      <table class="table table-vcenter">
        <thead>
          <tr>
            <th>Usuario</th>
            <th>Roles</th>
            <th>Eventos</th>
            <th>Estado</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="member in members" :key="member.id">
            <td>
              {{ member.user.name }}
              <div class="text-secondary">{{ member.user.email }}</div>
            </td>
            <td>
              <div class="d-flex gap-2 flex-wrap">
                <span
                  v-for="role in member.roles"
                  :key="role"
                  class="badge"
                  :class="roleBadgeClasses[role] ?? 'bg-secondary-lt'"
                >
                  {{ labels[role] ?? role }}
                </span>
              </div>
            </td>
            <td class="text-secondary">{{ assignedEvents(member) }}</td>
            <td>
              <span class="badge" :class="member.is_active ? 'bg-green-lt' : 'bg-orange-lt'">
                {{ member.is_active ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td class="text-end">
              <a
                v-if="!member.roles.includes('leader')"
                class="btn btn-sm btn-primary"
                :href="'/admin/team/' + member.id + '/edit'"
              >
                Editar
              </a>
            </td>
          </tr>
          <tr v-if="!members.length">
            <td colspan="5" class="text-center text-secondary py-4">No hay miembros para mostrar.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div v-else class="card">
    <div class="card-header">
      <h2 class="card-title">Links de invitación</h2>
    </div>
    <div class="table-responsive">
      <table class="table table-vcenter team-invitations-table">
        <thead>
          <tr>
            <th>Roles</th>
            <th>Eventos</th>
            <th>Enlace</th>
            <th class="text-nowrap">Usos</th>
            <th class="text-nowrap">Estado</th>
            <th class="text-end text-nowrap">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="invitation in invitations" :key="invitation.id">
            <td>
              <div class="d-flex gap-2 flex-wrap">
                <span
                  v-for="role in invitation.roles"
                  :key="role"
                  class="badge"
                  :class="roleBadgeClasses[role] ?? 'bg-secondary-lt'"
                >
                  {{ labels[role] ?? role }}
                </span>
              </div>
            </td>
            <td class="text-secondary">{{ assignedEvents(invitation) }}</td>
            <td>
              <div class="input-group input-group-sm">
                <span class="form-control text-truncate font-monospace" :title="invitation.url">
                  {{ shortInvitationUrl(invitation.url) }}
                </span>
                <button type="button" class="btn" :aria-label="'Copiar enlace de invitación ' + invitation.id" @click="copy(invitation.url)">Copiar</button>
              </div>
            </td>
            <td class="text-nowrap">{{ invitation.uses }} / {{ invitation.max_uses }}</td>
            <td class="text-nowrap">
              <span class="badge" :class="invitationStatusClass(invitation)">
                {{ invitationStatus(invitation) }}
              </span>
            </td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-primary" :href="'/admin/invitations/' + invitation.id + '/edit'">Editar</a>
            </td>
          </tr>
          <tr v-if="!invitations.length">
            <td colspan="6" class="text-center text-secondary py-4">No hay links de invitación para mostrar.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
