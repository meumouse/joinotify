<script setup>
/**
 * ConsentModal.vue — records an opt-in, which needs the evidence of how the
 * consent was obtained, or an opt-out, with an optional reason.
 *
 * @since 2.5.0
 */
import { computed, ref, watch } from 'vue';
import { __, textDomain } from '../../../utils/i18n';
import BaseButton from '../../../components/base/BaseButton.vue';
import ModalDialog from '../../../components/modals/ModalDialog.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  mode: { type: String, default: 'opt_in' },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);

const text = ref('');

watch(
  () => props.open,
  (open) => {
    if (open) {
      text.value = '';
    }
  }
);

const isOptIn = computed(() => props.mode === 'opt_in');
const valid = computed(() => !isOptIn.value || text.value.trim().length >= 3);
</script>

<template>
  <!-- Teleported like the drawer, so it opens above it; the class keeps the screen's scoped styles (phone field). -->
  <Teleport to="body">
    <div class="joinotify-settings">
      <ModalDialog
        :description="isOptIn
          ? __('Only record consent the contact actually gave. The evidence is kept with the contact and shown if the consent is ever questioned.', textDomain)
          : __('The contact stops receiving marketing messages and their number goes to the suppression list until they opt in again.', textDomain)"
        :open="open"
        size-class="max-w-lg"
        :title="isOptIn ? __('Record opt-in', textDomain) : __('Record opt-out', textDomain)"
        @close="$emit('cancel')"
      >
        <label class="flex flex-col gap-1">
          <span class="text-[12px] font-medium text-slate-500">
            {{ isOptIn ? __('How was the consent obtained?', textDomain) : __('Reason (optional)', textDomain) }}
          </span>
          <textarea
            v-model="text"
            class="rounded-[8px] border border-slate-200 px-3 py-2 text-[14px] text-slate-700"
            maxlength="500"
            :placeholder="isOptIn ? __('e.g. Agreed by WhatsApp on 29/09/2026', textDomain) : __('e.g. Asked to stop receiving messages', textDomain)"
            rows="3"
          />
        </label>

        <div class="mt-6 flex justify-end gap-3">
          <BaseButton :title="__('Cancel', textDomain)" variant="secondary" @click="$emit('cancel')" />
          <BaseButton
            :disabled="!valid"
            :loading="loading"
            :title="isOptIn ? __('Record opt-in', textDomain) : __('Record opt-out', textDomain)"
            :variant="isOptIn ? 'primary' : 'danger'"
            @click="emit('confirm', text.trim())"
          />
        </div>
      </ModalDialog>
    </div>
  </Teleport>
</template>
