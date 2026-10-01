<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es.js';
import 'flatpickr/dist/themes/dark.css';

const props = defineProps({
  id: String, label: String, modelValue: String, hint: String,
  mode: { type: String, default: 'datetime' }, earliestAfter: String, earliestOnOrAfter: String,
});
const emit = defineEmits(['update:modelValue']);
const input = ref(null);
let picker;
const format = computed(() => props.mode === 'date' ? 'Y-m-d' : props.mode === 'time' ? 'H:i' : 'Y-m-d H:i');
const displayFormat = computed(() => props.mode === 'date' ? 'd/m/Y' : props.mode === 'time' ? 'H:i' : 'd/m/Y H:i');
const placeholder = computed(() => props.mode === 'date' ? 'dd/mm/aaaa' : props.mode === 'time' ? 'hh:mm' : 'dd/mm/aaaa hh:mm');

function startingDate() {
  const value = props.earliestAfter || props.earliestOnOrAfter;
  if (!value) return null;
  return flatpickr.parseDate(value, props.earliestAfter ? 'Y-m-d H:i' : 'Y-m-d');
}

function minimumDate() {
  const start = startingDate();
  if (!start) return 'today';
  if (props.earliestAfter) start.setMinutes(start.getMinutes() + 1);
  return start;
}

onMounted(() => {
  picker = flatpickr(input.value, {
    locale: Spanish,
    enableTime: props.mode !== 'date',
    noCalendar: props.mode === 'time',
    time_24hr: true,
    disableMobile: true,
    ...(props.mode === 'datetime' ? { minuteIncrement: 15 } : {}),
    ...(props.mode !== 'time' ? { minDate: minimumDate() } : {}),
    dateFormat: format.value,
    altInput: true,
    altInputClass: 'form-control',
    altFormat: displayFormat.value,
    allowInput: false,
    onOpen: () => {
      const start = startingDate();
      if (!start || picker.selectedDates.length) return;
      picker.jumpToDate(start);
      if (props.mode === 'datetime') {
        picker.hourElement.value = String(start.getHours()).padStart(2, '0');
        picker.minuteElement.value = String(start.getMinutes()).padStart(2, '0');
      }
    },
    onChange: (_dates, value) => emit('update:modelValue', value),
  });
  picker.input.removeAttribute('id');
  picker.altInput.id = props.id;
});

watch(() => props.modelValue, value => {
  if (picker && picker.input.value !== (value || '')) picker.setDate(value || '', false, format.value);
});

watch(() => [props.earliestAfter, props.earliestOnOrAfter], () => {
  picker?.set('minDate', minimumDate());
});

onBeforeUnmount(() => picker?.destroy());
</script>

<template>
  <div class="mb-3">
    <label class="form-label" :for="id">{{ label }}</label>
    <input :id="id" ref="input" type="text" class="form-control" :value="modelValue" :placeholder="placeholder">
    <small v-if="hint" class="form-hint">{{ hint }}</small>
  </div>
</template>
