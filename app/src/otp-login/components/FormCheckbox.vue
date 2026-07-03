<script setup>
/**
 * Styled checkbox with v-model support for the "remember me" option.
 *
 * @since 1.0.0
 * @type {Object}
 */
const props = defineProps({
  id: {
    type: String,
    required: true,
  },
  label: {
    type: String,
    required: true,
  },
  modelValue: {
    type: Boolean,
    default: false,
  },
  name: {
    type: String,
    default: 'remember',
  },
  value: {
    type: String,
    default: '1',
  },
});

const emit = defineEmits(['update:modelValue']);

/**
 * Synchronize the checkbox state with the parent v-model.
 *
 * @since 1.0.0
 * @param {Event} event Native change event.
 * @return {void}
 */
function onChange(event) {
  emit('update:modelValue', event.target.checked);
}
</script>

<template>
  <label
    class="joinotify-otp-login__checkbox group flex cursor-pointer items-center gap-3 select-none aria-disabled:cursor-not-allowed"
    :for="props.id"
  >
    <div>
      <input
        :id="props.id"
        :checked="props.modelValue"
        :name="props.name"
        :value="props.value"
        class="peer sr-only"
        type="checkbox"
        @change="onChange"
      />
      <div
        class="joinotify-otp-login__checkbox-box grid size-5 place-items-center rounded-md border bg-white transition
               [&>svg]:hidden [&>svg]:size-3.5 [&>svg]:text-white peer-checked:[&>svg]:block"
      >
        <svg
          aria-hidden="true"
          fill="none"
          viewBox="0 0 14 14"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path
            d="M11.667 3.5L5.25 9.917 2.333 7"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="1.94437"
          />
        </svg>
      </div>
    </div>
    <span class="text-sm text-slate-600">{{ props.label }}</span>
  </label>
</template>
