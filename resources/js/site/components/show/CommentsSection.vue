<script setup>
  import { computed, ref, watch } from 'vue';
  import { Star } from '@lucide/vue';
  import CommentRequestModal from '@/site/components/comments/CommentRequestModal.vue';
  import { formatLongDate } from '@/site/helpers/DateTimeFormatHelper';
  import CommentService from '@/site/services/CommentService';

  // props
  const props = defineProps({
    show: {
      type: Object,
      required: true,
    },
    initialComments: {
      type: Array,
      default: () => [],
    },
    initialPagination: {
      type: Object,
      default: () => ({
        page: 1,
        limit: 5,
        total: 0,
        last_page: 1,
        has_more: false,
      }),
    },
  });

  // data
  const commentsPerPage = 5;
  const isRequestModalOpen = ref(false);
  const comments = ref([...props.initialComments]);
  const pagination = ref({ ...props.initialPagination });
  const isLoadingComments = ref(false);
  const commentsErrorMessage = ref('');

  // computed
  const commentCount = computed(() => {
    return Number(props.show.comments_summary?.count ?? pagination.value.total);
  });
  const hasComments = computed(() => commentCount.value > 0);
  const averageRating = computed(() => {
    const average = props.show.comments_summary?.average_rating;

    if (average !== null && average !== undefined) {
      return Number(average).toFixed(1).replace('.', ',');
    }

    if (!comments.value.length) {
      return '0,0';
    }

    const ratingTotal = comments.value.reduce((total, comment) => {
      return total + Number(comment.rating ?? 0);
    }, 0);

    return (ratingTotal / comments.value.length).toFixed(1).replace('.', ',');
  });
  const hasMoreComments = computed(() => {
    return pagination.value.has_more;
  });

  // methods
  const ratingLabel = (rating) => `${rating} de 5 estrellas`;

  const loadComments = async (page = 1) => {
    isLoadingComments.value = true;
    commentsErrorMessage.value = '';

    try {
      const response = await CommentService.getInstance().getComments(props.show.season_id, {
        page,
        limit: commentsPerPage,
        sort: 'desc',
      });
      const payload = response.data.data;

      comments.value = page === 1
        ? payload.items
        : [...comments.value, ...payload.items];
      pagination.value = payload.pagination;
    } catch (error) {
      commentsErrorMessage.value = 'No se pudieron cargar los comentarios.';
    } finally {
      isLoadingComments.value = false;
    }
  };

  const showMoreComments = () => {
    loadComments(pagination.value.page + 1);
  };

  const showRequestForm = () => {
    isRequestModalOpen.value = true;
  };

  // lifecycle
  watch(() => props.initialComments, (initialComments) => {
    comments.value = [...initialComments];
  });

  watch(() => props.initialPagination, (initialPagination) => {
    pagination.value = { ...initialPagination };
  });
</script>

<template>
  <section id="comments" class="comments-section">
    <div class="comments-heading">
      <div>
        <h2>Puntuación y comentarios del público</h2>
        <p>Opiniones verificadas de personas que compraron entradas.</p>
      </div>
      <button class="secondary-button" type="button" @click="showRequestForm">
        Comentar
      </button>
    </div>

    <div class="comments-card">
      <div
        v-if="hasComments"
        class="comments-summary"
        :aria-label="`${averageRating} de 5, según ${commentCount} comentarios`"
      >
        <div class="comments-summary-rating">
          <Star class="comments-summary-star" aria-hidden="true" />
          <strong>{{ averageRating }} de 5 estrellas</strong>
        </div>
        <span>{{ commentCount }} comentarios</span>
      </div>

      <div v-if="commentsErrorMessage" class="comments-empty">
        {{ commentsErrorMessage }}
      </div>
      <div v-else-if="!hasComments && !isLoadingComments" class="comments-empty">
        Todavía no hay comentarios publicados.
      </div>
      <div v-else class="comments-list">
        <article v-for="comment in comments" :key="comment.id" class="comment-card">
          <div class="comment-meta">
            <strong>{{ comment.name }}</strong>
            <span class="comment-rating" :aria-label="ratingLabel(comment.rating)">
              {{ '★'.repeat(comment.rating) }}<span aria-hidden="true">{{ '☆'.repeat(5 - comment.rating) }}</span>
            </span>
          </div>
          <p>{{ comment.comment }}</p>
          <small>Publicado el {{ formatLongDate(comment.created_at) }}</small>
        </article>
        <button
          v-if="hasMoreComments"
          class="secondary-button comments-more-button"
          type="button"
          :disabled="isLoadingComments"
          @click="showMoreComments"
        >
          {{ isLoadingComments ? 'Cargando comentarios...' : 'Ver más comentarios' }}
        </button>
      </div>
    </div>
  </section>

  <CommentRequestModal
    :is-open="isRequestModalOpen"
    :show="show"
    @close="isRequestModalOpen = false"
  />
</template>
