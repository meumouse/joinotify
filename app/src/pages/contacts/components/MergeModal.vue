<script setup>
/**
 * MergeModal.vue — merges a duplicate into the contact that is open: the
 * admin searches the duplicate, and on confirming the duplicate's
 * conversations, tags, history and campaigns move to this contact and the
 * duplicate is erased. Fields of the duplicate only fill the empty ones, and
 * an opt-out of either wins.
 *
 * @since 2.5.0
 */
import { computed, ref, watch } from 'vue';
import { __, sprintf, textDomain } from '../../../utils/i18n';
import { useContactsContext, errorMessage } from '../context';
import { formatPhone } from '../labels';
import BaseButton from '../../../components/base/BaseButton.vue';
import ModalDialog from '../../../components/modals/ModalDialog.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  contact: { type: Object, default: null },
});

const emit = defineEmits(['close', 'merged']);

const { api, toast } = useContactsContext();

const query = ref('');
const results = ref([]);
const searching = ref(false);
const picked = ref(null);
const merging = ref(false);
const error = ref('');
let timer = 0;

const label = (contact) => contact?.name || [contact?.firstName, contact?.lastName].filter(Boolean).join(' ') || formatPhone(contact?.phone);
const keeping = computed(() => label(props.contact));

watch(
  () => props.open,
  (open) => {
    if (open) {
      query.value = '';
      results.value = [];
      picked.value = null;
      error.value = '';
    }
  }
);

function onSearch(event) {
  query.value = event.target.value;
  window.clearTimeout(timer);
  timer = window.setTimeout(search, 350);
}

async function search() {
  if (query.value.trim().length < 3) {
    results.value = [];
    return;
  }

  searching.value = true;

  try {
    const response = await api.listContacts({ search: query.value, per_page: 10 });

    results.value = (response.items || []).filter((item) => item.id !== props.contact?.id);
  } catch (caught) {
    error.value = errorMessage(caught);
  } finally {
    searching.value = false;
  }
}

async function merge() {
  merging.value = true;
  error.value = '';

  try {
    const response = await api.mergeContacts(props.contact.id, picked.value.id);

    toast(response.message, 'success', __('Contacts', textDomain));
    emit('merged', response.contact);
  } catch (caught) {
    error.value = errorMessage(caught);
  } finally {
    merging.value = false;
  }
}
</script>

<template>
  <Teleport to="body">
    <div class="joinotify-settings">
      <ModalDialog :close-on-backdrop="!merging" :open="open" size-class="max-w-xl" :title="__('Merge a duplicate', textDomain)" @close="$emit('close')">
        <div class="flex flex-col gap-4">
          <p class="text-[14px] leading-6 text-slate-600">
            {{ sprintf(__('%s stays. Search the duplicate that should be merged into it.', textDomain), keeping) }}
          </p>

          <input
            :value="query"
            type="search"
            class="rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[14px] text-slate-700"
            :placeholder="__('Name, e-mail or phone', textDomain)"
            @input="onSearch"
          />

          <ul v-if="results.length" class="max-h-56 divide-y divide-slate-50 overflow-y-auto rounded-[8px] ring-1 ring-slate-100">
            <li v-for="item in results" :key="item.id">
              <button
                type="button"
                class="flex w-full items-center justify-between px-3 py-2 text-left text-[14px] hover:bg-slate-50"
                :class="picked?.id === item.id ? 'bg-primary-50' : ''"
                @click="picked = item"
              >
                <span class="font-medium text-slate-700">{{ label(item) }}</span>
                <span class="text-[12px] text-slate-400">{{ formatPhone(item.phone) }} {{ item.email }}</span>
              </button>
            </li>
          </ul>
          <p v-else-if="searching" class="text-[13px] text-slate-400">{{ __('Searching…', textDomain) }}</p>

          <p v-if="picked" class="rounded-[8px] bg-amber-50 px-4 py-3 text-[13px] text-amber-800">
            {{ sprintf(__('%1$s will be erased after its conversations, tags, history and campaigns move to %2$s. Its fields only fill the empty ones, and an opt-out of either wins. This cannot be undone.', textDomain), label(picked), keeping) }}
          </p>

          <p v-if="error" class="text-[13px] text-danger">{{ error }}</p>

          <div class="flex justify-end gap-3">
            <BaseButton :disabled="merging" :title="__('Cancel', textDomain)" variant="secondary" @click="$emit('close')" />
            <BaseButton :disabled="!picked" :loading="merging" :title="__('Merge', textDomain)" variant="danger" @click="merge" />
          </div>
        </div>
      </ModalDialog>
    </div>
  </Teleport>
</template>
