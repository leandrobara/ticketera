<script setup>
  import { computed, onMounted, ref } from 'vue';
  import PersonService from '@/admin/services/PersonService';
  import PersonModal from '@/admin/components/people/PersonModal.vue';

  const people = ref([]);
  const pagination = ref(null);
  const search = ref('');
  const errorMessage = ref('');
  const isLoading = ref(false);
  const personModal = ref(null);

  const hasPeople = computed(() => people.value.length > 0);
  const totalPeople = computed(() => pagination.value?.total ?? people.value.length);

  const getDocument = (person) => {
    return [person.document_type, person.document_number].filter(Boolean).join(' ') || '-';
  };

  const loadPeople = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
      const response = await PersonService.getInstance().getPeople({
        search: search.value || undefined,
      });
      pagination.value = response.data.data;
      people.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudo cargar el listado de personas.';
    } finally {
      isLoading.value = false;
    }
  };

  const clearSearch = async () => {
    search.value = '';
    await loadPeople();
  };

  const openPersonModal = () => {
    personModal.value.openForCreate();
  };

  const openUpdatePersonModal = (person) => {
    personModal.value.openForUpdate(person);
  };

  const deletePerson = async (person) => {
    if (!window.confirm(`¿Eliminar la persona "${person.display_name}"?`)) {
      return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
      await PersonService.getInstance().deletePerson(person.id);
      await loadPeople();
    } catch (error) {
      errorMessage.value = 'No se pudo eliminar la persona.';
      isLoading.value = false;
    }
  };

  onMounted(loadPeople);
</script>

<template>
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h1 class="page-title">Listado de personas</h1>
        <p class="card-subtitle">
          {{ totalPeople }} registros
        </p>
      </div>
      <div class="col-auto ms-auto">
        <button class="btn btn-success" type="button" @click="openPersonModal">
          Crear una nueva persona
        </button>
      </div>
    </div>
  </div>

  <div class="row row-cards mt-3">
    <div class="col-12">
      <div class="card">
        <div class="card-body border-bottom">
          <form class="row g-2 align-items-center" @submit.prevent="loadPeople">
            <div class="col">
              <input
                v-model.trim="search"
                class="form-control"
                type="search"
                placeholder="Buscar por nombre, email o documento"
              >
            </div>
            <div class="col-auto">
              <button class="btn btn-outline-primary" type="submit" :disabled="isLoading">
                Buscar
              </button>
            </div>
            <div v-if="search" class="col-auto">
              <button class="btn btn-outline-secondary" type="button" :disabled="isLoading" @click="clearSearch">
                Limpiar
              </button>
            </div>
          </form>
        </div>

        <div v-if="errorMessage" class="alert alert-danger m-3 mb-0" role="alert">
          {{ errorMessage }}
        </div>

        <div v-if="isLoading" class="card-body">
          <div class="d-flex align-items-center">
            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
            <span>Cargando personas...</span>
          </div>
        </div>

        <div v-else-if="!hasPeople" class="empty">
          <p class="empty-title">No hay personas cargadas</p>
          <p class="empty-subtitle text-secondary">
            Cuando crees tu primera persona, va a aparecer en este listado.
          </p>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>Persona</th>
                <th>Email</th>
                <th>Teléfono</th>
                <th>Documento</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="person in people" :key="person.id">
                <td>
                  <div class="d-flex align-items-center">
                    <span
                      v-if="person.photo_path"
                      class="avatar me-2"
                      :style="{ backgroundImage: `url(${person.photo_path})` }"
                    ></span>
                    <span v-else class="avatar me-2">{{ person.display_name?.charAt(0) ?? 'P' }}</span>
                    <div>
                      <div class="fw-semibold">{{ person.display_name }}</div>
                      <div v-if="person.normalized_name" class="text-secondary small">
                        {{ person.normalized_name }}
                      </div>
                    </div>
                  </div>
                </td>
                <td class="text-secondary">
                  {{ person.email || '-' }}
                </td>
                <td class="text-secondary">
                  {{ person.phone || '-' }}
                </td>
                <td class="text-secondary">
                  {{ getDocument(person) }}
                </td>
                <td class="text-end">
                  <div class="btn-list justify-content-end flex-nowrap">
                    <button class="btn btn-sm btn-outline-primary" type="button" @click="openUpdatePersonModal(person)">
                      Editar
                    </button>
                    <button class="btn btn-sm btn-outline-danger" type="button" @click="deletePerson(person)">
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

  <PersonModal ref="personModal" @saved="loadPeople" />
</template>
