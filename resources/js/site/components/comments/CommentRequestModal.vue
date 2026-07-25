<script setup>
  import { onBeforeUnmount, ref, watch } from 'vue';
  import CommentService from '@/site/services/CommentService';

  // props
  const props = defineProps({
    isOpen: {
      type: Boolean,
      default: false,
    },
    show: {
      type: Object,
      required: true,
    },
  });

  // emits
  const emit = defineEmits(['close']);

  // data
  const email = ref('');
  const errorMessage = ref('');
  const successMessage = ref('');
  const isRequesting = ref(false);

  // methods
  const close = () => {
    emit('close');
  };

  const requestCommentLink = async () => {
    errorMessage.value = '';
    successMessage.value = '';

    if (!email.value) {
      errorMessage.value = 'Ingresá el email utilizado en la compra.';
      return;
    }

    isRequesting.value = true;

    try {
      const response = await CommentService.getInstance().requestCommentLink(
        props.show.id,
        email.value
      );
      successMessage.value = response.data.data.message;
      email.value = '';
    } catch (error) {
      if (error.response?.status === 429) {
        errorMessage.value = 'Hiciste demasiadas solicitudes. Intentá nuevamente más tarde.';
      } else {
        errorMessage.value = 'No se pudo procesar la solicitud. Intentá nuevamente.';
      }
    } finally {
      isRequesting.value = false;
    }
  };

  // lifecycle
  watch(() => props.isOpen, (isOpen) => {
    document.body.classList.toggle('modal-open', isOpen);

    if (isOpen) {
      email.value = '';
      errorMessage.value = '';
      successMessage.value = '';
    }
  });

  onBeforeUnmount(() => {
    document.body.classList.remove('modal-open');
  });
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="comment-request-modal"
      @click.self="close"
      @keydown.esc="close"
    >
      <div
        class="comment-request-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="comment-request-title"
      >
        <header class="comment-request-header">
          <div>
            <h2 id="comment-request-title">Comentá {{ show.title }}</h2>
            <p>Te enviaremos un enlace personal a tu email para publicar tu opinión.</p>
          </div>
          <button class="checkout-close" type="button" aria-label="Cerrar" @click="close">×</button>
        </header>

        <form class="comment-request" @submit.prevent="requestCommentLink">
          <label for="comment-request-email">Email utilizado en la compra</label>
          <input
            id="comment-request-email"
            v-model.trim="email"
            type="email"
            autocomplete="email"
            placeholder="tu@email.com"
            autofocus
            required
          >

          <p v-if="successMessage" class="form-message is-success" role="status">
            {{ successMessage }}
          </p>
          <p v-if="errorMessage" class="form-message is-error" role="alert">
            {{ errorMessage }}
          </p>

          <button class="primary-button comment-request-submit" type="submit" :disabled="isRequesting">
            {{ isRequesting ? 'Enviando...' : 'Enviar enlace' }}
          </button>
        </form>
      </div>
    </div>
  </Teleport>
</template>
