<script setup>

/**
 * IntegrationModalBlock.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { computed } from 'vue';
import { __, textDomain } from '../../../../utils/i18n';
import { resolveFieldComponent } from '../../../../components/fields/fieldRegistry';

const props = defineProps({
  block: { type: Object, required: true },
  integration: { type: Object, default: null },
  settings: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update-setting', 'action']);

const blockType = computed(() => String(props.block?.type || props.block?.kind || 'html').trim().toLowerCase());

const resolvedComponent = computed(() => {
  if (blockType.value !== 'component') {
    return null;
  }

  return resolveFieldComponent({
    component: props.block?.component || props.block?.name || '',
    type: props.block?.component || props.block?.name || '',
  });
});

const componentProps = computed(() => {
  return props.block?.props && typeof props.block.props === 'object' ? props.block.props : {};
});

function handleUpdateSetting(key, value) {
  emit('update-setting', { key, value });
}
</script>

<template>
  <div v-if="blockType === 'html'" class="rounded-lg border border-slate-200 bg-slate-50 p-5 text-[14px] leading-6 text-slate-700">
    <div class="prose prose-slate max-w-none" v-html="block.html || block.content || ''" />
  </div>

  <component
    v-else-if="blockType === 'component' && resolvedComponent"
    :is="resolvedComponent"
    v-bind="componentProps"
    :integration="integration"
    :settings="settings"
    :update-setting="handleUpdateSetting"
    @action="$emit('action', $event)"
  />

  <div v-else class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
    {{ __('Custom block not available.', textDomain) }}
  </div>
</template>
