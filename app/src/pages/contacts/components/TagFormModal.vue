<script setup>
/**
 * TagFormModal.vue — creates a contact tag or edits one: name, description
 * and a color from the panel's palette.
 *
 * @since 2.5.0
 */
import { computed, reactive, ref, watch } from 'vue';
import { __, textDomain } from '../../../utils/i18n';
import { useContactsContext, errorMessage } from '../context';
import { TAG_COLORS } from '../labels';
import BaseButton from '../../../components/base/BaseButton.vue';
import ModalDialog from '../../../components/modals/ModalDialog.vue';
import TagChip from './TagChip.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  tag: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);

const { api, toast } = useContactsContext();

const inputClass =
  'w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[14px] text-slate-700 focus:border-primary focus:outline focus:outline-1 focus:-outline-offset-2 focus:outline-primary focus:shadow-none';

const form = reactive({ name: '', description: '', color: 'gray' });
const saving = ref(false);
const error = ref('');

const isEdit = computed(() => Boolean(props.tag?.id));
const colors = Object.keys(TAG_COLORS);

watch(
  () => props.open,
  (open) => {
    if (open) {
      form.name = props.tag?.name || '';
      form.description = props.tag?.description || '';
      form.color = props.tag?.color || 'gray';
      error.value = '';
    }
  },
  { immediate: true }
);

async function submit() {
  saving.value = true;
  error.value = '';

  try {
    const response = await api.saveTag(props.tag?.id || '', { ...form });

    toast(response.message, 'success', __('Tags', textDomain));
    emit('saved', response.tag);
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
        :title="isEdit ? __('Edit tag', textDomain) : __('New tag', textDomain)"
        @close="$emit('close')"
      >
        <form class="flex flex-col gap-4" @submit.prevent="submit">
          <label class="flex flex-col gap-1">
            <span class="text-[12px] font-medium text-slate-500">{{ __('Name', textDomain) }}</span>
            <input v-model="form.name" :class="inputClass" maxlength="60" required type="text" />
          </label>

          <label class="flex flex-col gap-1">
            <span class="text-[12px] font-medium text-slate-500">{{ __('Description (optional)', textDomain) }}</span>
            <input v-model="form.description" :class="inputClass" maxlength="200" type="text" />
          </label>

          <div class="flex flex-col gap-2">
            <span class="text-[12px] font-medium text-slate-500">{{ __('Color', textDomain) }}</span>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="color in colors"
                :key="color"
                type="button"
                class="rounded-full ring-offset-2 transition"
                :class="form.color === color ? 'ring-2 ring-primary-600' : ''"
                :aria-label="color"
                :aria-pressed="form.color === color"
                @click="form.color = color"
              >
                <TagChip :color="color" :name="form.name || __('Tag', textDomain)" />
              </button>
            </div>
          </div>

          <p v-if="error" class="rounded-[8px] border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{{ error }}</p>

          <div class="flex justify-end gap-3">
            <BaseButton :disabled="saving" :title="__('Cancel', textDomain)" variant="secondary" @click="$emit('close')" />
            <BaseButton :loading="saving" :title="isEdit ? __('Save changes', textDomain) : __('Create tag', textDomain)" type="submit" />
          </div>
        </form>
      </ModalDialog>
    </div>
  </Teleport>
</template>
