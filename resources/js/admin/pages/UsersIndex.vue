<script setup>
  import { computed, onMounted, reactive, ref } from 'vue';
  import { formatDateTime } from '@/admin/helpers/DateTimeFormatHelper';
  import UserModal from '@/admin/components/users/UserModal.vue';
  import UserService from '@/admin/services/UserService';

  // data
  const users = ref([]);
  const pagination = ref(null);
  const errorMessage = ref('');
  const isLoading = ref(false);
  const userModal = ref(null);
  const filters = reactive({
    search: '',
    role: '',
  });

  const roles = [
    { value: 'admin', label: 'Admin', badgeClass: 'bg-red-lt' },
    { value: 'operador', label: 'Operador', badgeClass: 'bg-blue-lt' },
    { value: 'puerta', label: 'Puerta', badgeClass: 'bg-green-lt' },
  ];

  // computed
  const hasUsers = computed(() => users.value.length > 0);
  const totalUsers = computed(() => pagination.value?.total ?? users.value.length);

  // methods
  const roleLabel = (roleValue) => {
    return roles.find((role) => role.value === roleValue)?.label ?? roleValue;
  };

  const roleBadgeClass = (roleValue) => {
    return roles.find((role) => role.value === roleValue)?.badgeClass ?? 'bg-secondary-lt';
  };

  const getParams = () => {
    return {
      search: filters.search || undefined,
      role: filters.role || undefined,
    };
  };

  const loadUsers = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
      const response = await UserService.getInstance().getUsers(getParams());
      pagination.value = response.data.data;
      users.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudo cargar el listado de usuarios.';
    } finally {
      isLoading.value = false;
    }
  };

  const applyFilters = async () => {
    await loadUsers();
  };

  const clearFilters = async () => {
    filters.search = '';
    filters.role = '';
    await loadUsers();
  };

  const openUserModal = () => {
    userModal.value.openForCreate();
  };

  const openUpdateUserModal = (user) => {
    userModal.value.openForUpdate(user);
  };

  const deleteUser = async (user) => {
    if (!window.confirm(`¿Eliminar el usuario "${user.name}"?`)) {
      return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
      await UserService.getInstance().deleteUser(user.id);
      await loadUsers();
    } catch (error) {
      errorMessage.value = 'No se pudo eliminar el usuario.';
      isLoading.value = false;
    }
  };

  // lifecycle
  onMounted(loadUsers);
</script>

<template>
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h1 class="page-title">Usuarios</h1>
        <p class="card-subtitle">
          {{ totalUsers }} registros
        </p>
      </div>
      <div class="col-auto ms-auto">
        <button class="btn btn-success" type="button" @click="openUserModal">
          Crear usuario
        </button>
      </div>
    </div>
  </div>

  <div class="row row-cards mt-3">
    <div class="col-12">
      <div class="card">
        <div class="card-body border-bottom py-3">
          <form class="row g-2 align-items-end" @submit.prevent="applyFilters">
            <div class="col-12 col-md-6">
              <label class="form-label" for="users-search">Buscar</label>
              <input
                id="users-search"
                v-model.trim="filters.search"
                class="form-control"
                type="search"
                placeholder="Nombre o email"
              >
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label" for="users-role">Rol</label>
              <select id="users-role" v-model="filters.role" class="form-select">
                <option value="">Todos los roles</option>
                <option v-for="role in roles" :key="role.value" :value="role.value">
                  {{ role.label }}
                </option>
              </select>
            </div>
            <div class="col-12 col-md-auto">
              <div class="btn-list">
                <button class="btn btn-primary" type="submit" :disabled="isLoading">
                  Filtrar
                </button>
                <button class="btn btn-outline-secondary" type="button" :disabled="isLoading" @click="clearFilters">
                  Limpiar
                </button>
              </div>
            </div>
          </form>
        </div>

        <div v-if="errorMessage" class="alert alert-danger m-3 mb-0" role="alert">
          {{ errorMessage }}
        </div>

        <div v-if="isLoading" class="card-body">
          <div class="d-flex align-items-center">
            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
            <span>Cargando usuarios...</span>
          </div>
        </div>

        <div v-else-if="!hasUsers" class="empty">
          <p class="empty-title">No hay usuarios cargados</p>
          <p class="empty-subtitle text-secondary">
            Cuando crees el primer usuario, va a aparecer en este listado.
          </p>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Creado</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in users" :key="user.id">
                <td>
                  <div class="d-flex align-items-center">
                    <span class="avatar avatar-sm me-3">
                      {{ user.name?.charAt(0) ?? 'U' }}
                    </span>
                    <div>
                      <div class="fw-semibold">
                        {{ user.name }}
                      </div>
                      <div class="text-secondary small">
                        {{ user.email }}
                      </div>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="badge" :class="roleBadgeClass(user.role)">
                    {{ roleLabel(user.role) }}
                  </span>
                </td>
                <td class="text-secondary">
                  {{ user.created_at ? formatDateTime(user.created_at) : '-' }}
                </td>
                <td class="text-end">
                  <div class="btn-list justify-content-end flex-nowrap">
                    <button class="btn btn-sm btn-outline-primary" type="button" @click="openUpdateUserModal(user)">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="icon"
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                        fill="none"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                      >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" />
                        <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" />
                        <path d="M16 5l3 3" />
                      </svg>
                      Editar
                    </button>
                    <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteUser(user)">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="icon"
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                        fill="none"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                      >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M4 7h16" />
                        <path d="M10 11v6" />
                        <path d="M14 11v6" />
                        <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                        <path d="M9 7v-3h6v3" />
                      </svg>
                      Eliminar
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <UserModal ref="userModal" @saved="loadUsers" />
</template>
