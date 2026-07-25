<script setup>
  import { computed, onMounted, ref } from 'vue';
  import { formatDateTime } from '@/admin/helpers/DateTimeFormatHelper';
  import CommentService from '@/admin/services/CommentService';
  import ShowService from '@/admin/services/ShowService';

  // data
  const comments = ref([]);
  const shows = ref([]);
  const pagination = ref(null);
  const filters = ref({
    search: '',
    show_id: '',
    status: '',
  });
  const errorMessage = ref('');
  const isLoading = ref(false);

  // computed
  const hasComments = computed(() => comments.value.length > 0);
  const totalComments = computed(() => pagination.value?.total ?? comments.value.length);

  // methods
  const buyerName = (comment) => {
    return [comment.buyer?.name, comment.buyer?.last_name].filter(Boolean).join(' ') || '-';
  };

  const loadShows = async () => {
    const response = await ShowService.getInstance().getShows({ per_page: 100 });
    shows.value = response.data.data.data ?? response.data.data ?? [];
  };

  const loadComments = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
      const response = await CommentService.getInstance().getComments({
        search: filters.value.search || undefined,
        show_id: filters.value.show_id || undefined,
        status: filters.value.status || undefined,
      });
      pagination.value = response.data.data;
      comments.value = response.data.data.data ?? [];
    } catch (error) {
      errorMessage.value = 'No se pudo cargar el listado de comentarios.';
    } finally {
      isLoading.value = false;
    }
  };

  const toggleVisibility = async (comment) => {
    errorMessage.value = '';
    const status = comment.status === 'visible' ? 'hidden' : 'visible';

    try {
      await CommentService.getInstance().updateComment(comment.id, { status });
      await loadComments();
    } catch (error) {
      errorMessage.value = 'No se pudo actualizar el comentario.';
    }
  };

  const deleteComment = async (comment) => {
    if (!window.confirm(`¿Eliminar el comentario de "${comment.name}"?`)) {
      return;
    }

    try {
      await CommentService.getInstance().deleteComment(comment.id);
      await loadComments();
    } catch (error) {
      errorMessage.value = 'No se pudo eliminar el comentario.';
    }
  };

  // lifecycle
  onMounted(async () => {
    try {
      await Promise.all([loadShows(), loadComments()]);
    } catch (error) {
      errorMessage.value = 'No se pudieron cargar los filtros.';
    }
  });

</script>

<template>
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h1 class="page-title">Comentarios</h1>
        <p class="card-subtitle">{{ totalComments }} registros</p>
      </div>
    </div>
  </div>

  <div class="row row-cards mt-3">
    <div class="col-12">
      <div class="card">
        <div class="card-body border-bottom">
          <form class="row g-2" @submit.prevent="loadComments">
            <div class="col-md-4">
              <input
                v-model.trim="filters.search"
                type="search"
                class="form-control"
                placeholder="Buscar comentario, comprador u orden"
              >
            </div>
            <div class="col-md-4">
              <select v-model="filters.show_id" class="form-select">
                <option value="">Todos los shows</option>
                <option v-for="show in shows" :key="show.id" :value="show.id">
                  {{ show.title }}
                </option>
              </select>
            </div>
            <div class="col-md-4">
              <select v-model="filters.status" class="form-select">
                <option value="">Todos los estados</option>
                <option value="visible">Visible</option>
                <option value="hidden">Oculto</option>
              </select>
            </div>
            <div class="col-12 text-end">
              <button class="btn btn-primary" type="submit">Filtrar</button>
            </div>
          </form>
        </div>

        <div v-if="errorMessage" class="alert alert-danger m-3 mb-0" role="alert">
          {{ errorMessage }}
        </div>

        <div v-if="isLoading" class="card-body">
          <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
          Cargando comentarios...
        </div>

        <div v-else-if="!hasComments" class="empty">
          <p class="empty-title">No hay comentarios</p>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-vcenter card-table admin-comments-table">
            <thead>
              <tr>
                <th>Obra</th>
                <th>Comentario</th>
                <th>Comprador</th>
                <th>Orden</th>
                <th>Estado</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="comment in comments" :key="comment.id">
                <td>
                  <div class="fw-semibold">{{ comment.show?.title }}</div>
                </td>
                <td class="admin-comment-content">
                  <div class="fw-semibold">{{ comment.name }} · {{ comment.rating }}/5</div>
                  <div class="text-secondary">{{ comment.comment }}</div>
                  <small class="text-secondary">{{ formatDateTime(comment.created_at) }}</small>
                </td>
                <td>
                  <div>{{ buyerName(comment) }}</div>
                  <div class="text-secondary">{{ comment.buyer?.email }}</div>
                  <div class="text-secondary">{{ comment.buyer?.phone || '-' }}</div>
                </td>
                <td>{{ comment.order?.code }}</td>
                <td>
                  <span class="badge" :class="comment.status === 'visible' ? 'bg-green-lt' : 'bg-secondary-lt'">
                    {{ comment.status === 'visible' ? 'Visible' : 'Oculto' }}
                  </span>
                </td>
                <td class="text-end text-nowrap">
                  <button class="btn btn-sm btn-outline-secondary me-2" type="button" @click="toggleVisibility(comment)">
                    {{ comment.status === 'visible' ? 'Ocultar' : 'Mostrar' }}
                  </button>
                  <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteComment(comment)">
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
