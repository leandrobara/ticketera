<script setup>
  import { ref } from 'vue';
  import EntradatixLogo from '@/site/components/brand/EntradatixLogo.vue';
  import BrandIcon from '@/site/components/icons/BrandIcon.vue';
  import NewsletterService from '@/site/services/NewsletterService';

  // props
  const props = defineProps({
    show: {
      type: Object,
      default: null,
    },
    hasTicketBar: {
      type: Boolean,
      default: false,
    },
  });

  // data
  const form = ref({
    name: '',
    email: '',
  });
  const isSubmitting = ref(false);
  const successMessage = ref('');
  const errorMessage = ref('');

  // methods
  const subscribe = async () => {
    errorMessage.value = '';
    successMessage.value = '';

    if (!form.value.email.trim()) {
      errorMessage.value = 'El email es obligatorio.';
      return;
    }

    isSubmitting.value = true;

    try {
      await NewsletterService.getInstance().subscribe({
        show_id: props.show?.id ?? null,
        name: form.value.name,
        email: form.value.email,
      });

      form.value = {
        name: '',
        email: '',
      };
      successMessage.value = 'Listo, te sumamos al newsletter.';
    } catch (error) {
      const fields = error.response?.data?.error?.fields ?? {};
      errorMessage.value = fields.email?.[0] ?? 'No pudimos guardar tu suscripción.';
    } finally {
      isSubmitting.value = false;
    }
  };
</script>

<template>
  <footer class="site-footer" :class="{ 'site-footer--show': hasTicketBar }">
    <section class="site-footer-brand">
      <div class="site-footer-brand-inner">
        <div class="footer-brand-column footer-brand-main">
          <a class="footer-logo" href="/">
            <EntradatixLogo />
          </a>
          <p>Infraestructura digital para proyectos culturales independientes.</p>

          <div class="footer-socials" aria-label="Redes sociales">
            <a href="https://www.instagram.com/" target="_blank" rel="noreferrer" aria-label="Instagram">
              <BrandIcon name="instagram" />
            </a>
          </div>
        </div>

        <div class="footer-brand-column footer-brand-middle">
          <div class="footer-dark-newsletter">
            <div class="footer-dark-newsletter-head">
              <div class="footer-dark-newsletter-icon" aria-hidden="true">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path d="M4 6h16v12h-16z" />
                  <path d="m4 7 8 6 8 -6" />
                </svg>
              </div>

              <h2>Recibí descuentos exclusivos</h2>
            </div>

            <p>Promociones, descuento y noticias. Sin spam.</p>

            <form class="footer-dark-subscribe" @submit.prevent="subscribe">
              <input
                v-model.trim="form.name"
                type="text"
                placeholder="Tu nombre"
                aria-label="Nombre"
                maxlength="160"
              >
              <input
                v-model.trim="form.email"
                type="email"
                placeholder="Tu correo electrónico *"
                aria-label="Email"
                maxlength="255"
                required
              >
              <button type="submit" :disabled="isSubmitting">
                {{ isSubmitting ? 'Guardando...' : 'Quiero sumarme' }}
              </button>
            </form>

            <p v-if="successMessage" class="footer-dark-form-message is-success">
              {{ successMessage }}
            </p>
            <p v-if="errorMessage" class="footer-dark-form-message is-error">
              {{ errorMessage }}
            </p>
          </div>
        </div>

        <div class="footer-brand-column footer-brand-support">
          <nav class="footer-dark-links" aria-label="Links del sitio">
            <div>
              <h3>Ayuda</h3>
              <a href="/contacto">Contacto</a>
              <a href="/medios-pago">Medios de pago</a>
              <a href="/preguntas-frecuentes">Preguntas frecuentes</a>
              <a href="/gestionar-tickets">Reclamar mis tickets</a>
            </div>

            <div>
              <h3>Producción</h3>
              <a href="/publica-tu-obra">Publicá tu obra</a>
              <!-- <a href="/admin/login">Área de productores</a> -->
            </div>
          </nav>
        </div>

        <div class="footer-bottom">
          <p>© 2026 Ticketera. Todos los derechos reservados.</p>

          <nav class="footer-bottom-links" aria-label="Links legales">
            <a href="/sobre-nosotros">Sobre nosotros</a>
            <a href="/terminos-condiciones">Términos y condiciones</a>
            <a href="/politica-privacidad">Política de privacidad</a>
            <a href="/politica-cookies">Política de cookies</a>
          </nav>
        </div>
      </div>
    </section>
  </footer>
</template>
