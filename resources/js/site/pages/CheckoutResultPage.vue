<script setup>
  import { computed } from 'vue';
  import SiteHeader from '@/site/components/layout/SiteHeader.vue';

  // props
  const props = defineProps({
    status: {
      type: String,
      default: 'pending',
    },
  });

  // data
  const params = new URLSearchParams(window.location.search);

  // computed
  const normalizedStatus = computed(() => {
    return ['success', 'failure', 'pending'].includes(props.status) ? props.status : 'pending';
  });

  const orderCode = computed(() => {
    return params.get('order') || params.get('external_reference') || params.get('preference_id') || '';
  });

  const paymentId = computed(() => {
    return params.get('payment_id') || params.get('collection_id') || '';
  });

  const content = computed(() => {
    const options = {
      success: {
        eyebrow: 'Pago aprobado',
        title: 'Tu compra está confirmada',
        description: 'Te enviamos las entradas al email que cargaste. También podés guardar el código de reserva para cualquier consulta.',
        icon: '✓',
        tone: 'success',
        primaryLabel: 'Volver a la obra',
        secondaryLabel: 'Ir al inicio',
      },
      failure: {
        eyebrow: 'Pago rechazado',
        title: 'No pudimos confirmar el pago',
        description: 'No se generaron entradas para esta orden. Podés volver a intentarlo con otro medio de pago.',
        icon: '!',
        tone: 'failure',
        primaryLabel: 'Intentar nuevamente',
        secondaryLabel: 'Ir al inicio',
      },
      pending: {
        eyebrow: 'Pago pendiente',
        title: 'Estamos esperando la confirmación',
        description: 'Mercado Pago todavía no confirmó el resultado final. Si el pago se aprueba, las entradas se enviarán automáticamente.',
        icon: '…',
        tone: 'pending',
        primaryLabel: 'Volver a la obra',
        secondaryLabel: 'Ir al inicio',
      },
    };

    return options[normalizedStatus.value];
  });

  const backUrl = computed(() => {
    return params.get('back_url') || '/';
  });
</script>

<template>
  <div class="checkout-result-page">
    <SiteHeader variant="solid" anchor-prefix="/" />

    <main class="checkout-result-shell">
      <section class="checkout-result-card" :class="`is-${content.tone}`">
        <div class="checkout-result-icon" aria-hidden="true">{{ content.icon }}</div>

        <p class="checkout-result-eyebrow">{{ content.eyebrow }}</p>
        <h1>{{ content.title }}</h1>
        <p class="checkout-result-description">{{ content.description }}</p>

        <dl class="checkout-result-details">
          <div v-if="orderCode">
            <dt>Código de reserva</dt>
            <dd>{{ orderCode }}</dd>
          </div>
          <div v-if="paymentId">
            <dt>ID de pago</dt>
            <dd>{{ paymentId }}</dd>
          </div>
        </dl>

        <div class="checkout-result-actions">
          <a class="primary-button" :href="backUrl">{{ content.primaryLabel }}</a>
          <a class="checkout-result-link" href="/">{{ content.secondaryLabel }}</a>
        </div>
      </section>
    </main>
  </div>
</template>
