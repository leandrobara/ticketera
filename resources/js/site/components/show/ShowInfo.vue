<script setup>
  import { computed, ref } from 'vue';
  import {
    Accessibility,
    BadgeCheck,
    Building2,
    CalendarDays,
    CalendarPlus,
    CalendarX,
    Car,
    CircleDollarSign,
    Clock,
    Map as MapIcon,
    MapPin,
    MapPinned,
    Tags,
    Ticket,
    TriangleAlert,
    Utensils,
  } from '@lucide/vue';
  import {
    formatDateTime,
    formatLongDate,
    formatWeekdayTime,
  } from '@/site/helpers/DateTimeFormatHelper';

  // props
  const props = defineProps({
    show: {
      type: Object,
      required: true,
    },
    primaryPresentation: {
      type: Object,
      default: null,
    },
    selectedPresentation: {
      type: Object,
      default: null,
    },
    presentations: {
      type: Array,
      default: () => [],
    },
  });

  // data
  const isExpanded = ref(false);

  // computed
  const venue = computed(() => props.show.venue ?? null);
  const venueAddress = computed(() => {
    return [venue.value?.address, venue.value?.neighborhood, venue.value?.city]
      .filter(Boolean)
      .join(', ');
  });
  const firstDate = computed(() => props.presentations[0]?.starts_at ?? null);
  const lastDate = computed(() => props.presentations.at(-1)?.starts_at ?? null);
  const hasLongSynopsis = computed(() => (props.show.synopsis || '').length > 200);
  const ticketPriceRange = computed(() => {
    const prices = props.presentations.flatMap((presentation) => {
      return (presentation.tickets ?? [])
        .filter((ticketType) => ticketType.has_stock)
        .map((ticketType) => ticketTypeEffectivePrice(ticketType));
    }).filter((price) => Number.isFinite(price));

    if (!prices.length) {
      return '-';
    }

    const minimumPrice = Math.min(...prices);
    const maximumPrice = Math.max(...prices);

    if (minimumPrice === maximumPrice) {
      return formatMoney(minimumPrice);
    }

    return `${formatMoney(minimumPrice)} hasta ${formatMoney(maximumPrice)}`;
  });
  const performanceDays = computed(() => {
    const dayTimes = props.presentations
      .map((presentation) => presentation.starts_at)
      .filter(Boolean)
      .map((startsAt) => formatWeekdayTime(startsAt));

    return [...new Set(dayTimes)].join(' - ') || '-';
  });

  // methods
  const formatMoney = (amount) => {
    return new Intl.NumberFormat('es-AR', {
      style: 'currency',
      currency: 'ARS',
      maximumFractionDigits: 0,
    }).format(Number(amount ?? 0));
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
</script>

<template>
  <article id="about" class="main-card">
    <div class="about-head">
      <div>
        <h2>Sobre {{ show.title }}</h2>
        <div class="copy-block" :class="{ 'is-collapsed': hasLongSynopsis && !isExpanded }">
          <p class="copy">{{ show.synopsis || 'Próximamente agregaremos más información sobre esta obra.' }}</p>
        </div>
        <button v-if="hasLongSynopsis" class="read-more" type="button" @click="isExpanded = !isExpanded">
          {{ isExpanded ? 'Ver menos' : 'Ver más' }}
        </button>
      </div>
    </div>

    <section class="details-grid show-details">
      <div class="detail">
        <h3><MapPin class="detail-icon" />Teatro</h3>
        <p>
          <a v-if="venue" href="#venue">{{ venue.name }}</a>
          <span v-else>-</span>
        </p>
      </div>
      <div class="detail">
        <h3><CircleDollarSign class="detail-icon" />Precios</h3>
        <p>{{ ticketPriceRange }}</p>
      </div>
      <div class="detail">
        <h3><Clock class="detail-icon" />Duración</h3>
        <p>{{ show.duration_minutes ? `${show.duration_minutes} minutos` : '-' }}</p>
      </div>
      <div class="detail">
        <h3><Tags class="detail-icon" />Categoría</h3>
        <p>{{ show.genre || '-' }}</p>
      </div>
      <div class="detail">
        <h3><BadgeCheck class="detail-icon" />Clasificación</h3>
        <p>{{ show.age_rating || '-' }}</p>
      </div>
      <div class="detail">
        <h3><CalendarDays class="detail-icon" />Días de función</h3>
        <p>{{ performanceDays }}</p>
      </div>
      <div class="detail">
        <h3><CalendarPlus class="detail-icon" />Fecha de estreno</h3>
        <p>{{ formatLongDate(firstDate) }}</p>
      </div>
      <div class="detail">
        <h3><CalendarX class="detail-icon" />Última función</h3>
        <p>{{ formatLongDate(lastDate) }}</p>
      </div>
      <div class="detail">
        <h3><Ticket class="detail-icon" />Formato</h3>
        <p>{{ show.format || 'Teatro' }}</p>
      </div>
    </section>

    <section v-if="show.additional_information" class="show-additional-information">
      <p>{{ show.additional_information }}</p>
    </section>

    <section v-if="show.production_note">
      <div class="notice">
        <div class="notice-title">
          <TriangleAlert class="notice-icon" />
          <strong>Nota de producción</strong>
        </div>
        <div>{{ show.production_note }}</div>
      </div>
    </section>

    <section v-if="selectedPresentation?.notes">
      <div class="notice notice-presentation" :class="{ 'has-production-note': show.production_note }">
        <div class="notice-title">
          <CalendarDays class="notice-icon" />
          <strong>Nota para la función del {{ formatDateTime(selectedPresentation.starts_at) }}</strong>
        </div>
        <div>{{ selectedPresentation.notes }}</div>
      </div>
    </section>
  </article>

  <slot name="after-show-info" />

  <section id="venue" v-if="venue" class="venue-info">
    <h2>Información sobre {{ venue.name }}</h2>

    <div class="details-grid venue-details">
      <div class="detail">
        <h3><Building2 class="detail-icon" />Teatro</h3>
        <p>{{ venue.name }}</p>
      </div>
      <div class="detail">
        <h3><MapIcon class="detail-icon" />Dirección</h3>
        <p>{{ venueAddress || '-' }}</p>
      </div>
      <div v-if="venue.google_maps_url" class="venue-map-action">
        <a
          class="venue-map-link"
          :href="venue.google_maps_url"
          target="_blank"
          rel="noreferrer"
        >
          <MapPinned />
          Ver en el mapa
        </a>
      </div>
      <div v-if="venue.has_bar" class="detail venue-amenity">
        <h3><Utensils class="detail-icon" />Bar</h3>
      </div>
      <div v-if="venue.has_parking" class="detail venue-amenity">
        <h3><Car class="detail-icon" />Estacionamiento</h3>
      </div>
      <div v-if="venue.is_accessible" class="detail venue-amenity">
        <h3><Accessibility class="detail-icon" />Accesibilidad</h3>
      </div>
    </div>
  </section>
</template>
