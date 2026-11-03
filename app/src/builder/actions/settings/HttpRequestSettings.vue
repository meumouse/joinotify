<script setup lang="ts">
/**
 * HttpRequestSettings.vue
 *
 * Settings panel for the "HTTP request" action. Configures the method and URL,
 * the query parameters and headers, the authentication, the body and the name
 * the response is kept under, so later actions can read it with
 * {{ http:NAME.path }}.
 *
 * The credential comes back from the server sealed (enc:v1:...); it is never
 * shown, and stays as it is until something new is typed over it.
 *
 * @since 2.7.0
 */
import { computed } from 'vue';
import BaseTextField from '../../components/base/BaseTextField.vue';
import BaseTextFieldVariables from '../../components/base/BaseTextFieldVariables.vue';
import BaseSelectField from '../../components/base/BaseSelectField.vue';
import BaseNumberField from '../../components/base/BaseNumberField.vue';
import BaseCodeEditorField from '../../components/base/BaseCodeEditorField.vue';
import BaseAccordion from '../../components/base/BaseAccordion.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import HttpRequestRowsField from './HttpRequestRowsField.vue';
import { useActionSettingsUpdate } from '../../../composables/useActionSettingsUpdate';
import { HTTP_METHODS, SEALED_PREFIX, normalizeRows, responseVarSlug } from '../definitions/httpRequest';
import { __, textDomain } from '../../../utils/i18n';
import type { WorkflowPlaceholderItem } from '../../../types/workflowBuilder';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array as () => Array<WorkflowPlaceholderItem | string>, default: () => [] },
  cronAvailable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'placeholder-selected']);

const { update } = useActionSettingsUpdate(props, emit);

const data = computed(() => props.modelValue as Record<string, unknown>);
const method = computed(() => String(data.value.method || 'GET').toUpperCase());
const authType = computed(() => String(data.value.auth_type || 'none'));
const bodyMode = computed(() => String(data.value.body_mode || 'none'));
const isSealed = computed(() => String(data.value.auth_value || '').startsWith(SEALED_PREFIX));

const methodOptions = HTTP_METHODS.map((value) => ({ label: value, value }));

const authOptions = [
  { label: __('None', textDomain), value: 'none' },
  { label: __('Bearer token', textDomain), value: 'bearer' },
  { label: __('Basic (user and password)', textDomain), value: 'basic' },
  { label: __('API key in a header', textDomain), value: 'api_key' },
];

const bodyOptions = [
  { label: __('No body', textDomain), value: 'none' },
  { label: __('JSON', textDomain), value: 'json' },
  { label: __('Form fields (URL encoded)', textDomain), value: 'form' },
  { label: __('The trigger\'s data (order, customer, user, cart)', textDomain), value: 'trigger' },
  { label: __('Raw text', textDomain), value: 'raw' },
];

const errorOptions = [
  { label: __('Continue with the next actions', textDomain), value: 'continue' },
  { label: __('Stop the workflow', textDomain), value: 'stop' },
];

const secretLabel = computed(() => {
  if (authType.value === 'basic') {
    return __('Password', textDomain);
  }

  return authType.value === 'api_key' ? __('API key', textDomain) : __('Token', textDomain);
});

const responseName = computed(() => responseVarSlug(data.value.response_var));

const usageTokens = computed(() => {
  const name = responseName.value || 'NAME';

  return [
    { token: `{{ http:${name}.status }}`, label: __('HTTP status code, such as 200', textDomain) },
    { token: `{{ http:${name}.ok }}`, label: __('"true" when the status is 2xx', textDomain) },
    { token: `{{ http:${name}.body.FIELD }}`, label: __('A field of the JSON response; use dots for nested fields and list positions (body.items.0.id)', textDomain) },
    { token: `{{ http:${name} }}`, label: __('The whole response body', textDomain) },
    { token: `{{ http:${name}.error }}`, label: __('Why the request failed, empty when it worked', textDomain) },
  ];
});
</script>

<template>
  <div class="space-y-4">
    <FieldGroup :title="__('Request', textDomain)" :description="__('A public address. Local and private network addresses are refused.', textDomain)">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-[8rem_minmax(0,1fr)]">
        <BaseSelectField
          :model-value="method"
          :options="methodOptions"
          :label="__('Method', textDomain)"
          @update:model-value="update('method', $event)"
        />
        <BaseTextFieldVariables
          :model-value="String(modelValue.url || '')"
          :label="__('URL', textDomain)"
          placeholder="https://api.example.com/customers"
          :placeholders="availablePlaceholders"
          @update:model-value="update('url', $event)"
        />
      </div>
    </FieldGroup>

    <FieldGroup :title="__('Query parameters', textDomain)" :description="__('Added to the URL, each value encoded for you.', textDomain)">
      <HttpRequestRowsField
        :model-value="normalizeRows(modelValue.query)"
        name-placeholder="email"
        value-placeholder="{{ wc_billing_email }}"
        :add-label="__('Add parameter', textDomain)"
        :placeholders="availablePlaceholders"
        @update:model-value="update('query', $event)"
      />
    </FieldGroup>

    <FieldGroup :title="__('Headers', textDomain)">
      <HttpRequestRowsField
        :model-value="normalizeRows(modelValue.headers)"
        name-placeholder="X-Store"
        value-placeholder="my-store"
        :add-label="__('Add header', textDomain)"
        :placeholders="availablePlaceholders"
        @update:model-value="update('headers', $event)"
      />
    </FieldGroup>

    <FieldGroup :title="__('Authentication', textDomain)" :description="__('The credential is encrypted when the workflow is saved and is never shown again.', textDomain)">
      <BaseSelectField
        :model-value="authType"
        :options="authOptions"
        :label="__('Authentication', textDomain)"
        @update:model-value="update('auth_type', $event)"
      />

      <template v-if="authType !== 'none'">
        <BaseTextField
          v-if="authType === 'basic'"
          :model-value="String(modelValue.auth_user || '')"
          :label="__('User', textDomain)"
          @update:model-value="update('auth_user', $event)"
        />
        <BaseTextField
          v-if="authType === 'api_key'"
          :model-value="String(modelValue.auth_header || '')"
          :label="__('Header name', textDomain)"
          placeholder="X-API-Key"
          @update:model-value="update('auth_header', $event)"
        />
        <BaseTextField
          :model-value="isSealed ? '' : String(modelValue.auth_value || '')"
          type="password"
          :label="secretLabel"
          :placeholder="isSealed ? __('Saved — type a new one to replace it', textDomain) : ''"
          @update:model-value="update('auth_value', $event || (isSealed ? modelValue.auth_value : ''))"
        />
      </template>
    </FieldGroup>

    <FieldGroup v-if="method !== 'GET'" :title="__('Body', textDomain)">
      <BaseSelectField
        :model-value="bodyMode"
        :options="bodyOptions"
        :label="__('Body', textDomain)"
        @update:model-value="update('body_mode', $event)"
      />

      <template v-if="bodyMode === 'json'">
        <BaseCodeEditorField
          :model-value="String(modelValue.body || '')"
          :label="__('JSON', textDomain)"
          :rows="8"
          placeholder='{ "order": "{{ wc_order_number }}", "phone": "{{ wc_billing_phone }}" }'
          @update:model-value="update('body', $event)"
        />
        <p class="text-xs text-slate-500">
          {{ __('Placeholders inside quotes are replaced safely, even when the value has quotes or line breaks. A placeholder outside quotes, such as a number, must leave valid JSON once replaced.', textDomain) }}
        </p>
      </template>

      <HttpRequestRowsField
        v-else-if="bodyMode === 'form'"
        :model-value="normalizeRows(modelValue.body_fields)"
        name-placeholder="phone"
        value-placeholder="{{ wc_billing_phone }}"
        :add-label="__('Add field', textDomain)"
        :placeholders="availablePlaceholders"
        @update:model-value="update('body_fields', $event)"
      />

      <template v-else-if="bodyMode === 'raw'">
        <BaseTextField
          :model-value="String(modelValue.content_type || '')"
          :label="__('Content type', textDomain)"
          placeholder="text/plain"
          @update:model-value="update('content_type', $event)"
        />
        <BaseCodeEditorField
          :model-value="String(modelValue.body || '')"
          :label="__('Text', textDomain)"
          :rows="6"
          @update:model-value="update('body', $event)"
        />
      </template>
    </FieldGroup>

    <FieldGroup :title="__('Response', textDomain)" :description="__('Give the response a name to use it in the next actions. Lowercase letters, numbers, - and _.', textDomain)">
      <BaseTextField
        :model-value="String(modelValue.response_var || '')"
        :label="__('Response name', textDomain)"
        :placeholder="__('e.g. customer', textDomain)"
        @update:model-value="update('response_var', responseVarSlug($event))"
      />

      <ul class="space-y-1 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
        <li v-for="item in usageTokens" :key="item.token">
          <code class="font-mono font-semibold">{{ item.token }}</code>
          <span class="text-indigo-700"> — {{ item.label }}</span>
        </li>
      </ul>

      <BaseSelectField
        :model-value="String(modelValue.on_error || 'continue')"
        :options="errorOptions"
        :label="__('When the request fails', textDomain)"
        @update:model-value="update('on_error', $event)"
      />
    </FieldGroup>

    <BaseAccordion :title="__('Advanced', textDomain)" :open="false">
      <FieldGroup :title="__('Timeout', textDomain)" :description="__('Seconds to wait for the answer, from 1 to 30.', textDomain)">
        <BaseNumberField
          :model-value="String(modelValue.timeout ?? '10')"
          :label="__('Timeout', textDomain)"
          :min="1"
          :max="30"
          :step="1"
          @update:model-value="update('timeout', $event)"
        />
      </FieldGroup>
    </BaseAccordion>
  </div>
</template>
