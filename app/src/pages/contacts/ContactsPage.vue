<script setup>
/**
 * ContactsPage.vue — "Audiences & Contacts" screen.
 *
 * The contact base lives on the Joinotify platform. This screen lists and
 * edits it through the plugin's REST routes, one tab per resource. The open
 * tab lives in the URL hash (`#contacts`, `#audiences?id=…`), so a reload or a
 * shared link lands on the same place.
 *
 * @since 2.5.0
 */
import { computed, onBeforeUnmount, onMounted, provide, reactive, ref } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import { createContactsApiClient } from '../../services/contactsApi';
import { useToasts } from '../../composables/useToasts';
import { CONTACTS_CONTEXT, errorMessage } from './context';
import PageHeader from '../../components/layout/PageHeader.vue';
import ToastStack from '../../components/toasts/ToastStack.vue';
import { createDefinitions } from './useDefinitions';
import ConnectionState from './components/ConnectionState.vue';
import ContactsTab from './tabs/ContactsTab.vue';
import FieldsTagsTab from './tabs/FieldsTagsTab.vue';

const props = defineProps({
  bootstrap: { type: Object, default: () => ({}) },
});

const state = reactive({ ...props.bootstrap });
const api = createContactsApiClient(props.bootstrap);
const { toasts, toast, dismissToast } = useToasts();
const refreshing = ref(false);
const definitions = createDefinitions(api);

const mode = computed(() => state.capability?.mode || (state.connection?.connected ? 'unreachable' : 'disconnected'));
const usable = computed(() => mode.value === 'full' || mode.value === 'read_only');
const canWrite = computed(() => mode.value === 'full');

/**
 * Tabs of the screen, in display order. Each phase of the screen registers
 * its panel here.
 */
const tabs = [
  { id: 'contacts', label: __('Contacts', textDomain), component: ContactsTab },
  { id: 'fields', label: __('Fields & tags', textDomain), component: FieldsTagsTab },
];

const route = ref(parseHash());
const activeTab = computed(() => {
  const found = tabs.find((tab) => tab.id === route.value.tab);

  return found ? found.id : tabs[0]?.id || '';
});
const panelKey = computed(() => `${activeTab.value}?${new URLSearchParams(route.value.params).toString()}`);
const activePanel = computed(() => tabs.find((tab) => tab.id === activeTab.value)?.component || null);

function parseHash() {
  const raw = window.location.hash.replace(/^#\/?/, '');
  const [tab, query = ''] = raw.split('?');

  return { tab: tab || '', params: Object.fromEntries(new URLSearchParams(query)) };
}

function navigate(tab, params = {}) {
  const query = new URLSearchParams(params).toString();
  const hash = `#${tab}${query ? `?${query}` : ''}`;

  if (window.location.hash !== hash) {
    window.history.replaceState(null, '', hash);
  }

  route.value = { tab, params: { ...params } };
}

function onHashChange() {
  route.value = parseHash();
}

async function retry() {
  refreshing.value = true;

  try {
    Object.assign(state, await api.bootstrap());
  } catch (error) {
    toast(errorMessage(error), 'error', __('Contacts', textDomain));
  } finally {
    refreshing.value = false;
  }
}

function notifyError(error, fallback = '') {
  toast(errorMessage(error, fallback), 'error', __('Error', textDomain));
}

provide(CONTACTS_CONTEXT, {
  api,
  bootstrap: state,
  definitions,
  canWrite,
  toast,
  notifyError,
  navigate,
  route: computed(() => route.value),
});

onMounted(() => window.addEventListener('hashchange', onHashChange));
onBeforeUnmount(() => window.removeEventListener('hashchange', onHashChange));
</script>

<template>
  <div class="joinotify-settings min-h-screen p-4">
    <div class="w-full">
      <PageHeader
        :title="__('Audiences & Contacts', textDomain)"
        :description="__('The contact base of your Joinotify account: people added by hand, by your integrations and by your workflows, the fields and tags that organize them and the audiences built from them.', textDomain)"
      />

      <ConnectionState
        v-if="!usable"
        :action-url="state.capability?.action_url || ''"
        :connection="state.connection || {}"
        :loading="refreshing"
        :message="state.capability?.message || ''"
        :mode="mode"
        @retry="retry"
      />

      <template v-else>
        <div
          v-if="mode === 'read_only'"
          class="mt-6 rounded-[8px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
        >
          {{ state.capability?.message }}
        </div>

        <nav v-if="tabs.length > 1" class="mt-6 flex flex-wrap gap-2" :aria-label="__('Sections', textDomain)">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            class="rounded-full px-4 py-1.5 text-[13px] font-medium transition"
            :class="activeTab === tab.id ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            @click="navigate(tab.id)"
          >
            {{ tab.label }}
          </button>
        </nav>

        <!-- Keyed by the params too: "see the contacts of this audience" reopens the tab filtered. -->
        <component :is="activePanel" v-if="activePanel" :key="panelKey" />
      </template>
    </div>

    <ToastStack :toasts="toasts" @dismiss="dismissToast" />
  </div>
</template>
