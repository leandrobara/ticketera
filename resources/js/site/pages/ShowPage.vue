<script setup>
  import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
  import CheckoutModal from '@/site/components/checkout/CheckoutModal.vue';
  import CommentsSection from '@/site/components/show/CommentsSection.vue';
  import CreditsSection from '@/site/components/show/CreditsSection.vue';
  import EventFinishedPanel from '@/site/components/show/EventFinishedPanel.vue';
  import FaqSection from '@/site/components/show/FaqSection.vue';
  import SiteFooter from '@/site/components/show/SiteFooter.vue';
  import ShowHero from '@/site/components/show/ShowHero.vue';
  import ShowInfo from '@/site/components/show/ShowInfo.vue';
  import ShowLinks from '@/site/components/show/ShowLinks.vue';
  import ShowPerformanceHistory from '@/site/components/show/ShowPerformanceHistory.vue';
  import ShowSocialLinks from '@/site/components/show/ShowSocialLinks.vue';
  import SiteHeader from '@/site/components/layout/SiteHeader.vue';
  import TicketPanel from '@/site/components/show/TicketPanel.vue';
  import { calculatePricePreview } from '@/site/helpers/PricePreviewHelper';
  import CommentService from '@/site/services/CommentService';
  import PresentationService from '@/site/services/PresentationService';
  import ShowService from '@/site/services/ShowService';
  import VenueService from '@/site/services/VenueService';

  // props
  const props = defineProps({
    showId: {
      type: [Number, String],
      required: true,
    },
    seasonId: {
      type: [Number, String],
      required: true,
    },
  });

  // data
  const show = ref(null);
  const venue = ref(null);
  const presentationList = ref([]);
  const comments = ref([]);
  const commentsSummary = ref({
    count: 0,
    average_rating: null,
  });
  const commentsPagination = ref({
    page: 1,
    limit: 5,
    total: 0,
    last_page: 1,
    has_more: false,
  });
  const errorMessage = ref('');
  const isLoading = ref(true);
  const selectedPresentationId = ref(null);
  const selectedTicketTypeId = ref(null);
  const quantity = ref(0);
  const promoCode = ref('');
  const pricePreview = ref(null);
  const isCheckoutOpen = ref(false);
  const isMobileTicketBarVisible = ref(false);
  const hiddenZones = {
    hero: true,
    availability: true,
    summary: true,
  };
  let visibilityObserver = null;
  let scrollRafId = null;

  // computed
  const imageUrl = computed(() => show.value?.main_image_url ?? null);
  const presentations = computed(() => presentationList.value);
  const selectedPresentation = computed(() => presentations.value.find((presentation) => {
    return Number(presentation.id) === Number(selectedPresentationId.value);
  }) ?? null);
  const selectedTicketType = computed(() => {
    return selectedPresentation.value?.tickets?.find((ticketType) => {
      return Number(ticketType.id) === Number(selectedTicketTypeId.value);
    }) ?? null;
  });
  const hasShowPremiered = computed(() => {
    const hasPublishedComments = Number(commentsSummary.value.count ?? 0) > 0;
    const hasFinishedPresentation = presentations.value.some((presentation) => presentation.is_finished);

    return hasPublishedComments || hasFinishedPresentation;
  });
  const lastPresentationStartsAt = computed(() => {
    const presentationDates = presentations.value
      .map((presentation) => new Date(presentation.starts_at))
      .filter((date) => !Number.isNaN(date.getTime()));

    if (!presentationDates.length) {
      return null;
    }

    return new Date(Math.max(...presentationDates.map((date) => date.getTime())));
  });
  const isEventFinished = computed(() => {
    return lastPresentationStartsAt.value !== null
      && new Date() > lastPresentationStartsAt.value;
  });
  const hasSocialLinks = computed(() => {
    return Object.values(show.value?.social_links ?? {}).some(Boolean);
  });
  const minimumVisibleTicketPrice = computed(() => {
    const availablePrices = presentations.value.flatMap((presentation) => {
      return (presentation.tickets ?? [])
        .filter((ticketType) => ticketType.has_stock)
        .map((ticketType) => ticketTypeEffectivePrice(ticketType));
    });

    return availablePrices.length ? Math.min(...availablePrices) : 0;
  });

  // methods
  const getPromoCodeFromUrl = () => {
    const code = new URLSearchParams(window.location.search).get('promo_code') ?? '';

    return code.trim().toLowerCase();
  };

  const ticketTypeEffectivePrice = (ticketType) => {
    if (ticketType.promotion?.promotional_price !== null && ticketType.promotion?.promotional_price !== undefined) {
      return Number(ticketType.promotion.promotional_price);
    }

    if (
      ticketType.promotion?.type === 'buy_x_get_y'
      && Number(ticketType.promotion.bundle_quantity) > 0
      && ticketType.promotion.bundle_effective_amount !== null
    ) {
      return Number(ticketType.promotion.bundle_effective_amount)
        / Number(ticketType.promotion.bundle_quantity);
    }

    return Number(ticketType.price ?? 0);
  };

  const findAppliedPromoTicketType = () => {
    for (const presentation of presentations.value) {
      const ticketType = (presentation.tickets ?? []).find((item) => {
        return item.promotion?.promo_code_applied && item.has_stock;
      });

      if (ticketType) {
        return { presentation, ticketType };
      }
    }

    return null;
  };

  const applyPromoCodeSelection = () => {
    const appliedPromo = findAppliedPromoTicketType();

    if (!appliedPromo) {
      return;
    }

    selectedPresentationId.value = Number(appliedPromo.presentation.id);
    selectedTicketTypeId.value = Number(appliedPromo.ticketType.id);
    quantity.value = 1;
  };

  const loadShow = async () => {
    isLoading.value = true;
    errorMessage.value = '';
    promoCode.value = getPromoCodeFromUrl();

    try {
      const showResponse = await ShowService.getInstance().getShow({
        showId: props.showId,
      });
      show.value = showResponse.data.data;

      const [presentationsResult, venueResult, commentsResult] = await Promise.allSettled([
        PresentationService.getInstance().getPresentations(props.seasonId, promoCode.value),
        VenueService.getInstance().getVenueBySeason(props.seasonId),
        CommentService.getInstance().getComments(show.value.id, {
          page: 1,
          limit: 5,
          sort: 'desc',
        }),
      ]);

      if (presentationsResult.status === 'fulfilled') {
        presentationList.value = presentationsResult.value.data.data;
        applyPromoCodeSelection();
      }

      if (venueResult.status === 'fulfilled') {
        venue.value = venueResult.value.data.data;
      }

      if (commentsResult.status === 'fulfilled') {
        comments.value = commentsResult.value.data.data.items;
        commentsSummary.value = commentsResult.value.data.data.comments_summary;
        commentsPagination.value = commentsResult.value.data.data.pagination;
      }
    } catch (error) {
      errorMessage.value = 'No se pudo cargar la ficha de la obra.';
    } finally {
      isLoading.value = false;
    }

    if (show.value && !isEventFinished.value) {
      await nextTick();
      setupMobileTicketBarObserver();
    }
  };

  const scrollToTickets = () => {
    document.querySelector('#tickets')?.scrollIntoView({
      behavior: 'smooth',
      block: 'start',
    });
  };

  const formatMoney = (amount) => {
    return new Intl.NumberFormat('es-AR', {
      style: 'currency',
      currency: 'ARS',
      maximumFractionDigits: 0,
    }).format(Number(amount ?? 0));
  };

  const isNearPageEnd = () => {
    return window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 16;
  };

  const refreshMobileTicketBarVisibility = () => {
    isMobileTicketBarVisible.value = !Object.values(hiddenZones).some(Boolean) || isNearPageEnd();
  };

  const handleScroll = () => {
    if (scrollRafId) {
      return;
    }

    scrollRafId = window.requestAnimationFrame(() => {
      refreshMobileTicketBarVisibility();
      scrollRafId = null;
    });
  };

  const setupMobileTicketBarObserver = () => {
    visibilityObserver?.disconnect();
    window.removeEventListener('scroll', handleScroll);

    if (!('IntersectionObserver' in window)) {
      window.addEventListener('scroll', handleScroll, { passive: true });
      return;
    }

    const observedElements = [
      ['hero', document.querySelector('.hero')],
      ['availability', document.querySelector('.ticket-availability')],
      ['summary', document.querySelector('.order-summary')],
    ].filter(([, element]) => Boolean(element));

    visibilityObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        hiddenZones[entry.target.dataset.stickyHiddenZone] = entry.isIntersecting;
      });

      refreshMobileTicketBarVisibility();
    }, {
      threshold: 0.05,
    });

    observedElements.forEach(([zone, element]) => {
      hiddenZones[zone] = true;
      element.dataset.stickyHiddenZone = zone;
      visibilityObserver.observe(element);
    });

    refreshMobileTicketBarVisibility();
    window.addEventListener('scroll', handleScroll, { passive: true });
  };

  const selectPresentation = (presentationId) => {
    selectedPresentationId.value = Number(presentationId) || null;
    selectedTicketTypeId.value = null;
    quantity.value = 0;
    pricePreview.value = null;
  };

  const selectTicketType = (ticketTypeId) => {
    selectedTicketTypeId.value = Number(ticketTypeId) || null;
    quantity.value = selectedTicketType.value ? Math.max(1, quantity.value) : 0;
  };

  const updateQuantity = (nextQuantity) => {
    quantity.value = nextQuantity;

    if (quantity.value === 0) {
      selectedTicketTypeId.value = null;
      pricePreview.value = null;
    }
  };

  const clearSelection = () => {
    quantity.value = 0;
    selectedTicketTypeId.value = null;
    pricePreview.value = null;
  };

  const loadPricePreview = () => {
    if (!selectedTicketType.value || quantity.value <= 0 || !show.value) {
      pricePreview.value = null;
      return;
    }

    pricePreview.value = calculatePricePreview(
      selectedTicketType.value,
      quantity.value,
      show.value,
    );
  };

  const openCheckout = () => {
    if (!selectedTicketType.value || !pricePreview.value) {
      return;
    }

    isCheckoutOpen.value = true;
  };

  // lifecycle
  onMounted(loadShow);
  onBeforeUnmount(() => {
    visibilityObserver?.disconnect();
    window.removeEventListener('scroll', handleScroll);

    if (scrollRafId) {
      window.cancelAnimationFrame(scrollRafId);
    }
  });

  watch([selectedTicketTypeId, quantity], loadPricePreview);
</script>

<template>
  <div v-if="isLoading" class="site-loading">
    Cargando...
  </div>

  <div v-else-if="errorMessage" class="site-error">
    {{ errorMessage }}
  </div>

  <div v-else-if="show">
    <ShowHero
      :show="show"
      :image-url="imageUrl"
      :show-comments="hasShowPremiered"
      :comments-summary="commentsSummary"
      :is-event-finished="isEventFinished"
      @buy="scrollToTickets"
    >
      <template #default>
        <SiteHeader variant="transparent" />
      </template>
    </ShowHero>

    <main class="content">
      <div>
        <ShowInfo
          :show="show"
          :venue="venue"
          :presentations="presentations"
          :primary-presentation="selectedPresentation || presentations[0]"
          :selected-presentation="selectedPresentation"
        >
          <template #after-show-info>
            <CommentsSection
              v-if="hasShowPremiered"
              :show="show"
              :initial-comments="comments"
              :initial-comments-summary="commentsSummary"
              :initial-pagination="commentsPagination"
            />
          </template>
        </ShowInfo>
        <CreditsSection :credits="show.credits" />
        <ShowSocialLinks
          :show-title="show.title"
          :social-links="show.social_links"
        />
        <ShowPerformanceHistory :histories="show.performance_history" />
        <ShowLinks :links="show.links" />
        <FaqSection :faqs="show.faqs" />
      </div>

      <EventFinishedPanel
        v-if="isEventFinished"
        :has-social-links="hasSocialLinks"
      />

      <TicketPanel
        v-else
        :show="show"
        :presentations="presentations"
        :selected-presentation-id="selectedPresentationId"
        :selected-ticket-type-id="selectedTicketTypeId"
        :quantity="quantity"
        :price-preview="pricePreview"
        @select-presentation="selectPresentation"
        @select-ticket-type="selectTicketType"
        @update-quantity="updateQuantity"
        @clear="clearSelection"
        @checkout="openCheckout"
      />
    </main>

    <SiteFooter :show="show" :has-ticket-bar="!isEventFinished" />

    <CheckoutModal
      :is-open="isCheckoutOpen"
      :show="show"
      :presentation="selectedPresentation"
      :ticket-type="selectedTicketType"
      :quantity="quantity"
      :price-preview="pricePreview"
      :promo-code="selectedTicketType?.promotion?.promo_code_applied ? promoCode : null"
      @close="isCheckoutOpen = false"
    />

    <div
      v-if="!isEventFinished"
      class="mobile-ticket-bar"
      :class="{ 'is-visible': isMobileTicketBarVisible }"
    >
      <div class="price">
        Entradas desde
        <strong>{{ formatMoney(minimumVisibleTicketPrice) }}</strong>
      </div>
      <button class="primary-button" type="button" @click="scrollToTickets">
        Comprar
      </button>
    </div>
  </div>
</template>
