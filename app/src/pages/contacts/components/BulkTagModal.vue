<script setup>
/**
 * BulkTagModal.vue — puts a tag on, or takes it off, the selected contacts or
 * every contact the current filters select. Starts no flow and sends no
 * webhook.
 *
 * @since 2.5.0
 */
import { computed, ref, watch } from 'vue';
import { __, _n, sprintf, textDomain } from '../../../utils/i18n';
import { useContactsContext, errorMessage } from '../context';
import BaseButton from '../../../components/base/BaseButton.vue';
import BaseListboxSelect from '../../../components/base/BaseListboxSelect.vue';
import ModalDialog from '../../../components/modals/ModalDialog.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  action: { type: String, default: 'add' },
  contactIds: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  total: { type: Number, default: 0 },
});

const emit = defineEmits(['close', 'applied']);

const { api, definitions, toast } = useContactsContext();

const tagId = ref('');
const scope = ref('selected');
const saving = ref(false);
const error = ref('');

const tagOptions = computed(() => [
  { label: __('— Pick a tag —', textDomain), value: '' },
  ...definitions.sortedTags.value.map((tag) => ({ label: tag.name, value: tag.id })),
]);

const isRemove = computed(() => props.action === 'remove');

watch(
  () => props.open,
  (open) => {
    if (open) {
      tagId.value = '';
      scope.value = props.contactIds.length ? 'selected' : 'filtered';
      error.value = '';
    }
  }
);

async function submit() {
  saving.value = true;
  error.value = '';

  try {
    const response = await api.applyTag({
      tag_id: tagId.value,
      action: props.action,
      contact_ids: scope.value === 'selected' ? props.contactIds : [],
      filters: scope.value === 'filtered' ? props.filters : {},
    });

    toast(response.message, 'success', __('Tags', textDomain));
    await definitions.load(true);
    emit('applied');
  } catch (caught) {
    error.value = errorMessage(caught);
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <Teleport to="body">
    <div class="joinotify-settings">
      <ModalDialog
        :close-on-backdrop="!saving"
        :open="open"
        size-class="max-w-lg"
        :title="isRemove ? __('Remove a tag', textDomain) : __('Add a tag', textDomain)"
        :description="__('Changes the contacts at once, without starting any flow or sending webhooks.', textDomain)"
        @close="$emit('close')"
      >
        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-1">
            <span class="text-[12px] font-medium text-slate-500">{{ __('Tag', textDomain) }}</span>
            <BaseListboxSelect v-model="tagId" :options="tagOptions" />
          </div>

          <!-- Buttons rather than native radios, which the admin's global input styles leave unmarked. -->
          <div class="flex flex-col gap-2" role="radiogroup">
            <button
              v-if="contactIds.length"
              type="button"
              role="radio"
              class="rounded-[8px] px-4 py-2.5 text-left text-[14px] ring-1 transition"
              :class="scope === 'selected' ? 'bg-primary-50 text-primary-800 ring-primary-600' : 'bg-white text-slate-700 ring-slate-200 hover:bg-slate-50'"
              :aria-checked="scope === 'selected'"
              @click="scope = 'selected'"
            >
              {{ sprintf(_n('The %d selected contact', 'The %d selected contacts', contactIds.length, textDomain), contactIds.length) }}
            </button>
            <button
              type="button"
              role="radio"
              class="rounded-[8px] px-4 py-2.5 text-left text-[14px] ring-1 transition"
              :class="scope === 'filtered' ? 'bg-primary-50 text-primary-800 ring-primary-600' : 'bg-white text-slate-700 ring-slate-200 hover:bg-slate-50'"
              :aria-checked="scope === 'filtered'"
              @click="scope = 'filtered'"
            >
              {{ sprintf(_n('The %d contact matching the current filters', 'All %d contacts matching the current filters', total, textDomain), total) }}
            </button>
          </div>

          <p v-if="error" class="rounded-[8px] border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{{ error }}</p>

          <div class="flex justify-end gap-3">
            <BaseButton :disabled="saving" :title="__('Cancel', textDomain)" variant="secondary" @click="$emit('close')" />
            <BaseButton
              :disabled="!tagId"
              :loading="saving"
              :title="isRemove ? __('Remove tag', textDomain) : __('Add tag', textDomain)"
              :variant="isRemove ? 'danger' : 'primary'"
              @click="submit"
            />
          </div>
        </div>
      </ModalDialog>
    </div>
  </Teleport>
</template>
