<script setup>
  import { computed, ref } from 'vue';

  // props
  const props = defineProps({
    faqs: {
      type: Array,
      default: () => [],
    },
  });

  // data
  const openFaqIndexes = ref([0]);

  // computed
  const visibleFaqs = computed(() => {
    return props.faqs.filter((faq) => faq.question && faq.answer);
  });

  const hasFaqs = computed(() => visibleFaqs.value.length > 0);

  // methods
  const isOpen = (index) => {
    return openFaqIndexes.value.includes(index);
  };

  const toggleFaq = (index) => {
    if (isOpen(index)) {
      openFaqIndexes.value = openFaqIndexes.value.filter((openIndex) => openIndex !== index);
      return;
    }

    openFaqIndexes.value = [...openFaqIndexes.value, index];
  };
</script>

<template>
  <section v-if="hasFaqs" id="faqs" class="faq-section">
    <h2>Preguntas frecuentes</h2>

    <div class="faq-list">
      <article v-for="(faq, index) in visibleFaqs" :key="`${faq.question}-${index}`" class="faq-item">
        <button
          class="faq-question"
          type="button"
          :aria-expanded="isOpen(index)"
          :aria-controls="`show-faq-${index}`"
          @click="toggleFaq(index)"
        >
          <span>{{ faq.question }}</span>
          <svg
            class="faq-icon"
            :class="{ 'is-open': isOpen(index) }"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <path d="m6 9 6 6 6 -6" />
          </svg>
        </button>

        <div v-show="isOpen(index)" :id="`show-faq-${index}`" class="faq-answer">
          {{ faq.answer }}
        </div>
      </article>
    </div>
  </section>
</template>
