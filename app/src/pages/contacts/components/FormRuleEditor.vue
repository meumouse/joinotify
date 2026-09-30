<script setup>
/**
 * FormRuleEditor.vue — the rule of one form: whether its submissions become
 * contacts, which fields are the phone, e-mail, name and consent, the tags
 * to add and which fields fill custom fields.
 *
 * Edits the rule object it receives in place (it belongs to the Sources tab's
 * reactive config).
 *
 * @since 2.5.0
 */
import { computed } from 'vue';
import { __, textDomain } from '../../../utils/i18n';
import BaseListboxSelect from '../../../components/base/BaseListboxSelect.vue';
import ToggleSwitch from '../../../components/toggles/ToggleSwitch.vue';

const props = defineProps({
  form: { type: Object, required: true },
  rule: { type: Object, required: true },
  customFields: { type: Array, default: () => [] },
});

const inputClass =
  'w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[14px] text-slate-700 focus:border-primary focus:outline focus:outline-1 focus:-outline-offset-2 focus:outline-primary focus:shadow-none';

const fieldOptions = computed(() => [
  { label: __('— Detect automatically —', textDomain), value: '' },
  ...(props.form.fields || []).map((field) => ({ label: field.label ? `${field.label} (${field.id})` : field.id, value: field.id })),
]);

const consentOptions = computed(() => [
  { label: __('— No consent field —', textDomain), value: '' },
  ...(props.form.fields || []).map((field) => ({ label: field.label ? `${field.label} (${field.id})` : field.id, value: field.id })),
]);

const targetOptions = computed(() => [
  { label: __('— Do not send —', textDomain), value: '' },
  ...props.customFields.map((field) => ({ label: field.label, value: field.key })),
]);

const tagsText = computed({
  get: () => (Array.isArray(props.rule.tags) ? props.rule.tags.join(', ') : props.rule.tags || ''),
  set: (value) => {
    props.rule.tags = value;
  },
});

function setAttribute(fieldId, target) {
  const next = { ...(props.rule.attributes || {}) };

  if (target) {
    next[fieldId] = target;
  } else {
    delete next[fieldId];
  }

  props.rule.attributes = next;
}
</script>

<template>
  <div class="flex flex-col gap-4 rounded-[8px] bg-slate-50 p-4">
    <ToggleSwitch v-model="rule.enabled" :label="__('Turn the submissions of this form into contacts', textDomain)" />

    <template v-if="rule.enabled">
      <div class="grid gap-3 sm:grid-cols-2">
        <div class="flex flex-col gap-1">
          <span class="text-[12px] font-medium text-slate-500">{{ __('Phone', textDomain) }}</span>
          <BaseListboxSelect v-model="rule.phone" :options="fieldOptions" />
        </div>
        <div class="flex flex-col gap-1">
          <span class="text-[12px] font-medium text-slate-500">{{ __('E-mail', textDomain) }}</span>
          <BaseListboxSelect v-model="rule.email" :options="fieldOptions" />
        </div>
        <div class="flex flex-col gap-1">
          <span class="text-[12px] font-medium text-slate-500">{{ __('First name (or full name)', textDomain) }}</span>
          <BaseListboxSelect v-model="rule.first_name" :options="fieldOptions" />
        </div>
        <div class="flex flex-col gap-1">
          <span class="text-[12px] font-medium text-slate-500">{{ __('Last name', textDomain) }}</span>
          <BaseListboxSelect v-model="rule.last_name" :options="fieldOptions" />
        </div>
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <div class="flex flex-col gap-1">
          <span class="text-[12px] font-medium text-slate-500">{{ __('Marketing consent field', textDomain) }}</span>
          <BaseListboxSelect v-model="rule.consent" :options="consentOptions" />
          <span class="text-[12px] text-slate-400">{{ __('A checkbox the person ticks to agree to receive offers. Unticked, the contact is sent without consent.', textDomain) }}</span>
        </div>
        <label v-if="rule.consent" class="flex flex-col gap-1">
          <span class="text-[12px] font-medium text-slate-500">{{ __('What the person agreed to', textDomain) }}</span>
          <input v-model="rule.consent_text" :class="inputClass" maxlength="300" :placeholder="__('The checkbox text; its label is used when empty', textDomain)" type="text" />
        </label>
      </div>

      <label class="flex flex-col gap-1">
        <span class="text-[12px] font-medium text-slate-500">{{ __('Tags to add, separated by commas', textDomain) }}</span>
        <input v-model="tagsText" :class="inputClass" :placeholder="__('e.g. Lead, Newsletter', textDomain)" type="text" />
      </label>

      <div v-if="customFields.length && (form.fields || []).length" class="flex flex-col gap-2">
        <span class="text-[12px] font-medium text-slate-500">{{ __('Custom fields filled by this form', textDomain) }}</span>
        <div v-for="field in form.fields" :key="field.id" class="grid items-center gap-2 sm:grid-cols-[1fr_1fr]">
          <span class="truncate text-[13px] text-slate-600" :title="field.label">{{ field.label || field.id }}</span>
          <BaseListboxSelect
            :model-value="(rule.attributes || {})[field.id] || ''"
            :options="targetOptions"
            @update:model-value="setAttribute(field.id, $event)"
          />
        </div>
      </div>
    </template>
  </div>
</template>
