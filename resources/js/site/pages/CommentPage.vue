<script setup>
  import { computed, onMounted, ref } from 'vue';
  import CommentService from '@/site/services/CommentService';
  import SiteFooter from '@/site/components/show/SiteFooter.vue';
  import SiteHeader from '@/site/components/layout/SiteHeader.vue';

  // props
  const props = defineProps({
    token: {
      type: String,
      required: true,
    },
  });

  // data
  const invitation = ref(null);
  const form = ref({
    name: '',
    rating: 5,
    comment: '',
  });
  const errorMessage = ref('');
  const isLoading = ref(true);
  const isSubmitting = ref(false);
  const isSubmitted = ref(false);

  // computed
  const showUrl = computed(() => {
    if (!invitation.value?.show) {
      return '/';
    }

    return `/shows/${invitation.value.show.id}/${invitation.value.show.slug}`;
  });

  // methods
  const loadInvitation = async () => {
    try {
      const response = await CommentService.getInstance().validateToken(props.token);
      invitation.value = response.data.data;
    } catch (error) {
      errorMessage.value = 'Este enlace no es válido, venció o ya fue utilizado.';
    } finally {
      isLoading.value = false;
    }
  };

  const submitComment = async () => {
    errorMessage.value = '';
    isSubmitting.value = true;

    try {
      await CommentService.getInstance().createComment(props.token, form.value);
      isSubmitted.value = true;
    } catch (error) {
      if (error.response?.status === 422) {
        errorMessage.value = 'Revisá los datos o solicitá un nuevo enlace para comentar.';
      } else {
        errorMessage.value = 'No se pudo publicar el comentario. Intentá nuevamente.';
      }
    } finally {
      isSubmitting.value = false;
    }
  };

  // lifecycle
  onMounted(loadInvitation);
</script>

<template>
  <div class="static-page">
    <SiteHeader variant="solid" anchor-prefix="/" />

    <main class="comment-page-shell">
      <section class="comment-page-card">
        <div v-if="isLoading" class="comment-page-state">
          Validando enlace...
        </div>

        <div v-else-if="isSubmitted" class="comment-page-state">
          <p class="static-page-eyebrow">Comentario publicado</p>
          <h1>Gracias por compartir tu experiencia</h1>
          <p>Tu comentario ya está visible en la ficha de la obra.</p>
          <a class="primary-button" :href="showUrl">Volver a la obra</a>
        </div>

        <div v-else-if="errorMessage && !invitation" class="comment-page-state">
          <p class="static-page-eyebrow">Enlace no disponible</p>
          <h1>No pudimos abrir el formulario</h1>
          <p>{{ errorMessage }}</p>
          <a class="secondary-button" href="/">Volver al inicio</a>
        </div>

        <template v-else-if="invitation">
          <p class="static-page-eyebrow">Compra verificada</p>
          <h1>Contanos qué te pareció {{ invitation.show.title }}</h1>
          <form class="comment-form" @submit.prevent="submitComment">
            <label for="comment-name">Nombre para mostrar *</label>
            <input id="comment-name" v-model.trim="form.name" type="text" maxlength="160" required>

            <fieldset>
              <legend>Puntaje *</legend>
              <div class="rating-options">
                <label v-for="rating in 5" :key="rating">
                  <input v-model.number="form.rating" type="radio" name="rating" :value="rating">
                  <span>{{ rating }} ★</span>
                </label>
              </div>
            </fieldset>

            <label for="comment-text">Comentario *</label>
            <textarea
              id="comment-text"
              v-model.trim="form.comment"
              rows="6"
              maxlength="2000"
              required
            ></textarea>

            <p v-if="errorMessage" class="form-message is-error" role="alert">
              {{ errorMessage }}
            </p>

            <button class="primary-button" type="submit" :disabled="isSubmitting">
              {{ isSubmitting ? 'Publicando...' : 'Publicar comentario' }}
            </button>
          </form>
        </template>
      </section>
    </main>

    <SiteFooter />
  </div>
</template>
