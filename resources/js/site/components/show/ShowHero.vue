<script setup>
  import { computed } from 'vue';
  import { CalendarCheck, Star } from '@lucide/vue';
  import BrandIcon from '@/site/components/icons/BrandIcon.vue';

  // props
  const props = defineProps({
    show: {
      type: Object,
      required: true,
    },
    imageUrl: {
      type: String,
      default: null,
    },
    showComments: {
      type: Boolean,
      default: false,
    },
    commentsSummary: {
      type: Object,
      default: () => ({
        count: 0,
        average_rating: null,
      }),
    },
    isEventFinished: {
      type: Boolean,
      default: false,
    },
  });

  // emits
  const emit = defineEmits(['buy']);

  // computed
  const showGenre = computed(() => {
    if (props.show.genre) {
      return props.show.genre;
    }

    if (props.show.format) {
      return props.show.format;
    }

    return 'Teatro';
  });
  const commentCount = computed(() => Number(props.commentsSummary?.count ?? 0));
  const hasRatingSummary = computed(() => {
    return props.showComments && commentCount.value >= 10;
  });
  const averageRating = computed(() => {
    const average = props.commentsSummary?.average_rating;

    if (average === null || average === undefined) {
      return '0,0';
    }

    return Number(average).toFixed(1).replace('.', ',');
  });
  const socialNetworks = [
    { key: 'instagram', label: 'Instagram' },
    { key: 'facebook', label: 'Facebook' },
    { key: 'x', label: 'X' },
    { key: 'tiktok', label: 'TikTok' },
    { key: 'youtube', label: 'YouTube' },
    { key: 'pinterest', label: 'Pinterest' },
  ];
  const visibleSocialLinks = computed(() => {
    return socialNetworks
      .filter((network) => Boolean(props.show.social_links?.[network.key]))
      .map((network) => ({
        ...network,
        url: props.show.social_links[network.key],
      }));
  });

  // methods
  const shareOnWhatsApp = () => {
    const showUrl = new URL(window.location.href);
    showUrl.search = '';
    showUrl.hash = '';

    const message = `Mirá ${props.show.title} en EntradaTix: ${showUrl.toString()}`;
    const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(message)}`;

    window.open(whatsappUrl, '_blank', 'noopener,noreferrer');
  };
</script>

<template>
  <section
    class="hero"
    :class="{ 'has-image': imageUrl }"
    :style="imageUrl ? { '--hero-bg': `url('${imageUrl}')` } : null"
  >
    <div v-if="imageUrl" class="hero-backdrop" aria-hidden="true"></div>
    <slot></slot>
    <div class="hero-grid">
      <div class="hero-copy">
        <div class="eyebrow">{{ showGenre }}</div>
        <h1>{{ show.title }}</h1>
        <p v-if="show.subtitle" class="subtitle">{{ show.subtitle }}</p>
        <div class="cta-row">
          <div v-if="isEventFinished" class="hero-event-finished">
            <CalendarCheck aria-hidden="true" />
            <span>Evento finalizado</span>
          </div>
          <button v-else class="primary-button" type="button" @click="emit('buy')">
            Comprar entradas
          </button>
        </div>
        <a
          v-if="hasRatingSummary"
          class="hero-rating-summary"
          href="#comments"
          :aria-label="`${averageRating} de 5, según ${commentCount} comentarios`"
        >
          <Star class="hero-rating-star" aria-hidden="true" />
          <span><strong>{{ averageRating }} de 5 estrellas</strong> ({{ commentCount }} comentarios)</span>
        </a>
        <div class="hero-social-actions">
          <button
            class="hero-share-button"
            type="button"
            aria-label="Compartir por WhatsApp"
            title="Compartir por WhatsApp"
            @click="shareOnWhatsApp"
          >
            <span class="hero-share-icon-frame" aria-hidden="true">
              <BrandIcon name="whatsapp" class="hero-share-icon" />
            </span>
            <span>Compartir</span>
          </button>
          <div v-if="visibleSocialLinks.length" class="hero-social-links" aria-label="Redes sociales de la obra">
            <a
              v-for="socialLink in visibleSocialLinks"
              :key="socialLink.key"
              class="hero-social-link"
              :href="socialLink.url"
              target="_blank"
              rel="noreferrer"
              :aria-label="socialLink.label"
              :title="socialLink.label"
            >
              <BrandIcon :name="socialLink.key" />
            </a>
          </div>
        </div>
      </div>

      <div v-if="imageUrl" class="hero-media">
        <div class="hero-image">
          <img :src="imageUrl" :alt="show.title">
        </div>
      </div>
    </div>
  </section>
</template>
