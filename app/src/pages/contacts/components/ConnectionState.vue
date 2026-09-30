<script setup>
/**
 * ConnectionState.vue — what the contacts screen shows when it cannot reach
 * the contact base: the site is not connected, the key was revoked, the
 * account is blocked by billing, the key has no access, or the platform is
 * out of reach.
 *
 * @since 2.5.0
 */
import { computed } from 'vue';
import { __, textDomain } from '../../../utils/i18n';
import BaseButton from '../../../components/base/BaseButton.vue';

const props = defineProps({
  mode: { type: String, required: true },
  message: { type: String, default: '' },
  actionUrl: { type: String, default: '' },
  connection: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
});

defineEmits(['retry']);

const copy = computed(() => {
  switch (props.mode) {
    case 'disconnected':
      return {
        title: __('Connect this site to Joinotify', textDomain),
        text: __('Your contacts and audiences live on the Joinotify platform. Connect the site to list them, add contacts by hand and sync the ones your integrations collect.', textDomain),
      };
    case 'unauthorized':
      return {
        title: __('The connection to Joinotify was lost', textDomain),
        text: props.message || __('The API key of this site was revoked or is invalid. Connect the site to Joinotify again.', textDomain),
      };
    case 'blocked':
      return {
        title: __('Your Joinotify account needs attention', textDomain),
        text: props.message || __('Your Joinotify account needs attention before it can be used. Open the panel to resolve the billing.', textDomain),
      };
    case 'forbidden':
      return {
        title: __('No access to the contact base', textDomain),
        text: props.message || __('The API key of this site is not allowed to read the contact base.', textDomain),
      };
    default:
      return {
        title: __('Could not reach the Joinotify platform', textDomain),
        text: props.message || __('Check your connection and try again.', textDomain),
      };
  }
});

const panelUrl = computed(() => props.actionUrl || props.connection?.panel_url || '');
</script>

<template>
  <div class="mt-6 rounded-[8px] bg-white px-6 py-16 text-center shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100">
    <p class="text-[17px] font-semibold text-ink">{{ copy.title }}</p>
    <p class="mx-auto mt-2 max-w-xl text-[14px] leading-6 text-slate-500">{{ copy.text }}</p>

    <div class="mt-6 flex flex-wrap justify-center gap-3">
      <template v-if="mode === 'disconnected' || mode === 'unauthorized'">
        <BaseButton :href="connection.onboarding_url" :title="__('Connect with Joinotify', textDomain)" />
        <BaseButton :href="connection.settings_url" :title="__('Open settings', textDomain)" variant="secondary" />
      </template>

      <BaseButton
        v-else-if="mode === 'blocked' && panelUrl"
        :href="panelUrl"
        :title="__('Open the Joinotify panel', textDomain)"
        rel="noopener noreferrer"
        target="_blank"
      />

      <BaseButton
        v-if="mode !== 'disconnected'"
        :loading="loading"
        :title="__('Try again', textDomain)"
        variant="secondary"
        @click="$emit('retry')"
      />
    </div>
  </div>
</template>
