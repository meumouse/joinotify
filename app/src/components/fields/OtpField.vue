<script setup>

/**
 * OtpField.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { nextTick, ref } from 'vue';
import { __, sprintf, textDomain } from '../../utils/i18n';

const props = defineProps({
  digits: {
    type: Array,
    required: true,
  },
  length: {
    type: Number,
    required: true,
  },
});

const emit = defineEmits(['update:digits', 'complete']);
const inputRefs = ref([]);

function focusRef(targets, index) {
  nextTick(() => {
    const target = targets[index];

    if (target) {
      target.focus();
      target.select();
    }
  });
}

function onInput(targets, index, event) {
  const value = String(event.target.value || '').replace(/\D+/g, '').slice(-1);
  const nextDigits = [...props.digits];
  nextDigits[index] = value;
  emit('update:digits', nextDigits);
  event.target.value = value;

  if (value && index < props.length - 1) {
    focusRef(targets, index + 1);
  }

  if (nextDigits.every(Boolean)) {
    emit('complete');
  }
}

function onKeydown(targets, index, event) {
  if (event.key === 'Backspace' && !props.digits[index] && index > 0) {
    focusRef(targets, index - 1);
  }
}

function onPaste(targets, index, event) {
  const pasted = String(event.clipboardData?.getData('text') || '').replace(/\D+/g, '').slice(0, props.length);

  if (!pasted) {
    return;
  }

  event.preventDefault();
  const nextDigits = Array.from({ length: props.length }, (_, position) => pasted[position] || '');
  emit('update:digits', nextDigits);

  if (pasted.length === props.length) {
    emit('complete');
    return;
  }

  focusRef(targets, Math.min(index + pasted.length, props.length - 1));
}

function digitLabel(index) {
  return sprintf(__('Code digit %s', textDomain), index + 1);
}
</script>

<template>
  <div class="grid grid-cols-3 gap-2 sm:grid-cols-6 sm:gap-3">
    <input
      v-for="(_, index) in length"
      :key="index"
      :ref="(el) => (inputRefs[index] = el)"
      :value="digits[index]"
      class="h-14 border border-slate-200 bg-slate-50 text-center text-xl font-semibold text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
      type="text"
      inputmode="numeric"
      autocomplete="one-time-code"
      maxlength="1"
      :aria-label="digitLabel(index)"
      @input="onInput(inputRefs, index, $event)"
      @keydown="onKeydown(inputRefs, index, $event)"
      @paste="onPaste(inputRefs, index, $event)"
    />
  </div>
</template>
