<script setup lang="ts">
import { computed, ref } from 'vue';
import { useActionRegistry } from '../composables/useActionRegistry';
import ActionLibraryCard from './ActionLibraryCard.vue';

const props = defineProps({
  context: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  title: { type: String, default: 'Add an action' },
});

defineEmits(['select', 'close']);

const registry = useActionRegistry();
const query = ref('');

const availableActions = computed(() => {
  const actions = props.context ? registry.byContext(props.context) : registry.actions.value;
  const term = query.value.trim().toLowerCase();

  if (!term) {
    return actions;
  }

  return actions.filter((action) => {
    const searchable = [action.action, action.title, action.description, ...(action.tags || [])]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return searchable.includes(term);
  });
});
</script>

<template>
  <aside class="flex h-full w-[24rem] flex-col overflow-hidden rounded-l-[28px] border-l border-slate-200 bg-white shadow-[0_18px_50px_rgba(15,23,42,0.12)]">
    <div class="flex items-start justify-between border-b border-slate-200 px-5 py-5">
      <div>
        <h2 class="text-[1.35rem] font-semibold tracking-tight text-slate-900">
          {{ title }}
        </h2>
        <p class="mt-2 max-w-[18rem] text-sm leading-6 text-slate-500">
          Choose a step for the workflow. Actions are loaded from the registry and filtered by context.
        </p>
      </div>

      <button
        type="button"
        class="mt-1 inline-flex h-8 w-8 items-center justify-center rounded-full border border-transparent text-2xl leading-none text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
        aria-label="Close panel"
        @click="$emit('close')"
      >
        x
      </button>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
      <label class="mb-4 block">
        <span class="sr-only">Search actions</span>
        <input
          v-model="query"
          type="search"
          placeholder="Search actions"
          class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-700/10"
        />
      </label>

      <template v-if="loading">
        <div
          v-for="index in 5"
          :key="`action-skeleton-${index}`"
          class="joinotify-skeleton mb-3 flex items-start gap-3 rounded-[14px] border border-slate-200 bg-white p-4"
        >
          <div class="joinotify-skeleton mt-0.5 h-10 w-10 rounded-full bg-slate-200/70" />
          <div class="min-w-0 flex-1">
            <div class="joinotify-skeleton h-4 w-32 rounded-full bg-slate-200/75" />
            <div class="joinotify-skeleton mt-3 h-3 w-40 rounded-full bg-slate-200/60" />
            <div class="joinotify-skeleton mt-2 h-3 w-28 rounded-full bg-slate-200/60" />
          </div>
        </div>
      </template>

      <template v-else>
        <ActionLibraryCard
          v-for="action in availableActions"
          :key="action.action"
          class="mb-3"
          :action="action"
          @click="$emit('select', $event)"
        />

        <div v-if="!availableActions.length" class="rounded-[14px] border border-dashed border-slate-300 px-4 py-8 text-center">
          <p class="text-sm font-medium text-slate-700">No actions available.</p>
          <p class="mt-1 text-sm leading-6 text-slate-500">Check the backend registry, the selected context, or the search term.</p>
        </div>
      </template>
    </div>
  </aside>
</template>
