<script setup>
  import { computed } from 'vue';
  import { Globe2 } from '@lucide/vue';
  import BrandIcon from '@/site/components/icons/BrandIcon.vue';

  // props
  const props = defineProps({
    showTitle: {
      type: String,
      required: true,
    },
    socialLinks: {
      type: Object,
      default: () => ({}),
    },
  });

  // data
  const socialNetworks = [
    { key: 'instagram', label: 'Instagram', brand: true },
    { key: 'facebook', label: 'Facebook', brand: true },
    { key: 'x', label: 'X', brand: true },
    { key: 'tiktok', label: 'TikTok', brand: true },
    { key: 'youtube', label: 'YouTube', brand: true },
    { key: 'pinterest', label: 'Pinterest', brand: true },
    { key: 'website', label: 'Sitio web', icon: Globe2 },
  ];

  // computed
  const visibleSocialLinks = computed(() => {
    return socialNetworks
      .filter((network) => Boolean(props.socialLinks?.[network.key]))
      .map((network) => ({
        ...network,
        url: props.socialLinks[network.key],
      }));
  });
  const hasSocialLinks = computed(() => visibleSocialLinks.value.length > 0);
</script>

<template>
  <section v-if="hasSocialLinks" id="social-links" class="show-social-section">
    <div class="comments-heading">
      <div>
        <h2>Seguí a {{ showTitle }}</h2>
        <p>Encontrá novedades, contenido y próximas funciones en sus redes.</p>
      </div>
    </div>
    <div class="show-social-card">
      <div class="show-social-links">
        <a
          v-for="socialLink in visibleSocialLinks"
          :key="socialLink.key"
          class="show-social-link"
          :href="socialLink.url"
          target="_blank"
          rel="noreferrer"
        >
          <BrandIcon v-if="socialLink.brand" :name="socialLink.key" />
          <component v-else :is="socialLink.icon" aria-hidden="true" />
          <span>{{ socialLink.label }}</span>
        </a>
      </div>
    </div>
  </section>
</template>
