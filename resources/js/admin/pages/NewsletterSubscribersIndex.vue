<script setup>
  import { computed, onMounted, ref } from 'vue';
  import { formatDateTime } from '@/admin/helpers/DateTimeFormatHelper';
  import NewsletterSubscriberService from '@/admin/services/NewsletterSubscriberService';

  // data
  const subscribers = ref([]);
  const pagination = ref(null);
  const errorMessage = ref('');
  const search = ref('');
  const isLoading = ref(false);

  // computed
  const hasSubscribers = computed(() => subscribers.value.length > 0);
  const totalSubscribers = computed(() => pagination.value?.total ?? subscribers.value.length);

  // methods
  const getSubscriberName = (subscriber) => {
    return subscriber.name || '-';
  };

  const getShowTitle = (subscriber) => {
    return subscriber.show?.title || '-';
  };

  const loadSubscribers = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
      const response = await NewsletterSubscriberService.getInstance().getSubscribers({
        search: search.value || undefined,
      });
      pagination.value = response.data.data;
      subscribers.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudo cargar el listado de suscriptores.';
    } finally {
      isLoading.value = false;
    }
  };

  const deleteSubscriber = async (subscriber) => {
    if (!window.confirm(`¿Eliminar el suscriptor "${subscriber.email}"?`)) {
      return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
      await NewsletterSubscriberService.getInstance().deleteSubscriber(subscriber.id);
      await loadSubscribers();
    } catch (error) {
      errorMessage.value = 'No se pudo eliminar el suscriptor.';
      isLoading.value = false;
    }
  };

  // lifecycle
  onMounted(loadSubscribers);
</script>

<template>
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h1 class="page-title">Listado de suscriptores</h1>
        <p class="card-subtitle">
          {{ totalSubscribers }} registros
        </p>
      </div>
    </div>
  </div>

  <div class="row row-cards mt-3">
    <div class="col-12">
      <div class="card">
        <div class="card-body border-bottom">
          <form class="row g-2" @submit.prevent="loadSubscribers">
            <div class="col">
              <input
                v-model.trim="search"
                type="search"
                class="form-control"
                placeholder="Buscar por nombre, email u obra"
              >
            </div>
            <div class="col-auto">
              <button class="btn btn-primary" type="submit">
                Buscar
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
            <span>Cargando suscriptores...</span>
          </div>
        </div>

        <div v-else-if="!hasSubscribers" class="empty">
          <p class="empty-title">No hay suscriptores cargados</p>
          <p class="empty-subtitle text-secondary">
            Cuando una persona se suscriba desde una ficha, va a aparecer en este listado.
          </p>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Email</th>
                <th>Obra de origen</th>
                <th>Fecha</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="subscriber in subscribers" :key="subscriber.id">
                <td>
                  <div class="fw-semibold">{{ getSubscriberName(subscriber) }}</div>
                </td>
                <td class="text-secondary">
                  {{ subscriber.email }}
                </td>
                <td class="text-secondary">
                  {{ getShowTitle(subscriber) }}
                </td>
                <td class="text-secondary">
                  {{ formatDateTime(subscriber.created_at) }}
                </td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteSubscriber(subscriber)">
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
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
