<script setup>
/**
 * ImportModal.vue — imports contacts from a CSV file.
 *
 * The file is read in the browser: the admin maps its columns to the contact
 * (phone, names, e-mail, tags, custom fields), and the rows go to the
 * platform in batches of 500 under one import record, with a running tally
 * and the rows that failed and why. The file itself never reaches the server.
 *
 * @since 2.5.0
 */
import { computed, ref } from 'vue';
import { __, sprintf, textDomain } from '../../../utils/i18n';
import { parseCsv } from '../../../utils/csv';
import { useContactsContext, errorMessage } from '../context';
import BaseButton from '../../../components/base/BaseButton.vue';
import BaseListboxSelect from '../../../components/base/BaseListboxSelect.vue';
import ModalDialog from '../../../components/modals/ModalDialog.vue';

defineProps({
  open: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'imported']);

const { api, definitions } = useContactsContext();

const BATCH = 500;

const inputClass =
  'w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[14px] text-slate-700 focus:border-primary focus:outline focus:outline-1 focus:-outline-offset-2 focus:outline-primary focus:shadow-none';

const step = ref('file');
const filename = ref('');
const header = ref([]);
const rows = ref([]);
const mapping = ref([]);
const onDuplicate = ref('update');
const extraTags = ref('');
const optIn = ref(false);
const evidence = ref('');
const fileError = ref('');
const running = ref(false);
const progress = ref({ done: 0, created: 0, updated: 0, skipped: 0, failed: 0 });
const problems = ref([]);
const fatal = ref('');

function reset() {
  step.value = 'file';
  filename.value = '';
  header.value = [];
  rows.value = [];
  mapping.value = [];
  onDuplicate.value = 'update';
  extraTags.value = '';
  optIn.value = false;
  evidence.value = '';
  fileError.value = '';
  running.value = false;
  progress.value = { done: 0, created: 0, updated: 0, skipped: 0, failed: 0 };
  problems.value = [];
  fatal.value = '';
}

const targetOptions = computed(() => [
  { label: __('— Ignore —', textDomain), value: '' },
  { label: __('Phone', textDomain), value: 'phone' },
  { label: __('First name', textDomain), value: 'firstName' },
  { label: __('Last name', textDomain), value: 'lastName' },
  { label: __('Full name', textDomain), value: 'name' },
  { label: __('E-mail', textDomain), value: 'email' },
  { label: __('Language (e.g. pt-BR)', textDomain), value: 'locale' },
  { label: __('Tags (separated by commas)', textDomain), value: 'tags' },
  ...definitions.activeFields.value.map((field) => ({ label: sprintf(__('Custom field: %s', textDomain), field.label), value: `attr:${field.key}` })),
]);

function normalize(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .trim();
}

function guess(label) {
  const name = normalize(label);
  const field = definitions.activeFields.value.find((item) => normalize(item.key) === name || normalize(item.label) === name);

  if (field) return `attr:${field.key}`;
  if (/phone|telefone|celular|whatsapp|fone|movil/.test(name)) return 'phone';
  if (/mail/.test(name)) return 'email';
  if (/^(sobrenome|last ?name|apellido)/.test(name)) return 'lastName';
  if (/^(primeiro nome|first ?name)/.test(name)) return 'firstName';
  if (/^(nome|name|nombre)/.test(name)) return 'name';
  if (/^(tags?|etiquetas?)$/.test(name)) return 'tags';
  if (/^(idioma|language|locale)$/.test(name)) return 'locale';

  return '';
}

async function onFile(event) {
  const file = event.target.files?.[0];

  fileError.value = '';

  if (!file) {
    return;
  }

  try {
    const parsed = parseCsv(await file.text());

    if (parsed.length < 2) {
      fileError.value = __('The file needs a header line and at least one contact.', textDomain);
      return;
    }

    await definitions.load();
    filename.value = file.name;
    header.value = parsed[0].map((cell) => cell.trim());
    rows.value = parsed.slice(1);
    mapping.value = header.value.map(guess);
    step.value = 'map';
  } catch (caught) {
    fileError.value = __('Could not read this file. Save it as CSV (UTF-8) and try again.', textDomain);
  }
}

const hasPhone = computed(() => mapping.value.includes('phone'));
const sampleRow = computed(() => rows.value[0] || []);

function buildRow(cells) {
  const row = { attributes: {} };
  const tags = [];

  mapping.value.forEach((target, index) => {
    const value = String(cells[index] ?? '').trim();

    if (!target || value === '') {
      return;
    }

    if (target.startsWith('attr:')) {
      row.attributes[target.slice(5)] = value;
    } else if (target === 'tags') {
      tags.push(...value.split(','));
    } else {
      row[target] = value;
    }
  });

  tags.push(...extraTags.value.split(','));
  row.tags = tags.map((tag) => tag.trim()).filter(Boolean);

  return row;
}

const reasonLabels = {
  exists: __('already in the base (skipped)', textDomain),
  contact_limit_reached: __('over the plan\'s contact limit', textDomain),
  conflict_retry: __('created at the same time by another path', textDomain),
  kept_opt_out: __('kept opted out', textDomain),
  no_phone: __('no valid phone number', textDomain),
};

function reasonLabel(reason) {
  return reasonLabels[reason] || reason || __('refused', textDomain);
}

async function run() {
  running.value = true;
  step.value = 'run';
  fatal.value = '';

  let importId = '';

  try {
    const opened = await api.importStep({ step: 'open', filename: filename.value, total: rows.value.length });

    importId = opened.data?.id || '';

    for (let start = 0; start < rows.value.length; start += BATCH) {
      const chunk = rows.value.slice(start, start + BATCH);
      const response = await api.importStep({
        step: 'batch',
        import_id: importId,
        rows: chunk.map(buildRow),
        options: {
          on_duplicate: onDuplicate.value,
          opt_in_evidence: optIn.value ? evidence.value : '',
        },
      });

      const summary = response.data?.summary || {};

      progress.value = {
        done: Math.min(rows.value.length, start + chunk.length),
        created: progress.value.created + (summary.created || 0),
        updated: progress.value.updated + (summary.updated || 0),
        skipped: progress.value.skipped + (summary.skipped || 0),
        failed: progress.value.failed + (summary.failed || 0),
      };

      // Line numbers of the file: the header is line 1.
      (response.dropped || []).forEach((index) => {
        problems.value.push({ line: start + index + 2, reason: reasonLabel('no_phone') });
      });

      (response.data?.results || []).forEach((result) => {
        if (result.status === 'failed' || (result.status === 'skipped' && result.reason && result.reason !== 'exists')) {
          const original = (response.sent || [])[result.index] ?? result.index;

          problems.value.push({ line: start + original + 2, reason: reasonLabel(result.reason) + (result.field ? ` (${result.field})` : '') });
        }
      });
    }

    if (importId) {
      await api.importStep({ step: 'close', import_id: importId, status: 'completed' });
    }
  } catch (caught) {
    fatal.value = errorMessage(caught);

    if (importId) {
      api.importStep({ step: 'close', import_id: importId, status: 'failed' }).catch(() => undefined);
    }
  } finally {
    running.value = false;
    step.value = 'done';
    emit('imported');
  }
}

function close() {
  if (running.value) {
    return;
  }

  reset();
  emit('close');
}

const percent = computed(() => (rows.value.length ? Math.round((progress.value.done / rows.value.length) * 100) : 0));
</script>

<template>
  <Teleport to="body">
    <div class="joinotify-settings">
      <ModalDialog :close-on-backdrop="!running" :open="open" size-class="max-w-4xl" :title="__('Import contacts', textDomain)" @close="close">
        <!-- File -->
        <div v-if="step === 'file'" class="flex flex-col gap-4">
          <p class="text-[14px] leading-6 text-slate-600">
            {{ __('Pick a CSV file with a header line and one contact per line. Every contact needs a phone number; a number without the country code is read in the default country of your settings.', textDomain) }}
          </p>
          <input accept=".csv,text/csv" class="text-[14px]" type="file" @change="onFile" />
          <p v-if="fileError" class="text-[13px] text-danger">{{ fileError }}</p>
        </div>

        <!-- Mapping and options -->
        <div v-else-if="step === 'map'" class="flex flex-col gap-5">
          <p class="text-[14px] text-slate-600">
            {{ sprintf(__('%1$s: %2$d contacts. Tell what each column is.', textDomain), filename, rows.length) }}
          </p>

          <div class="max-h-[40vh] overflow-y-auto rounded-[8px] ring-1 ring-slate-100">
            <table class="min-w-full divide-y divide-slate-100 text-left text-[13px]">
              <thead>
                <tr class="text-[12px] uppercase tracking-wide text-slate-400">
                  <th class="px-3 py-2 font-medium">{{ __('Column', textDomain) }}</th>
                  <th class="px-3 py-2 font-medium">{{ __('First line', textDomain) }}</th>
                  <th class="px-3 py-2 font-medium">{{ __('Is the', textDomain) }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-for="(label, index) in header" :key="index">
                  <td class="px-3 py-2 font-medium text-slate-700">{{ label || `#${index + 1}` }}</td>
                  <td class="max-w-[220px] truncate px-3 py-2 text-slate-500">{{ sampleRow[index] }}</td>
                  <td class="w-64 px-3 py-2"><BaseListboxSelect v-model="mapping[index]" :options="targetOptions" /></td>
                </tr>
              </tbody>
            </table>
          </div>

          <p v-if="!hasPhone" class="text-[13px] text-danger">{{ __('Pick the column that holds the phone number.', textDomain) }}</p>

          <div class="grid gap-4 sm:grid-cols-2">
            <label class="flex flex-col gap-1">
              <span class="text-[12px] font-medium text-slate-500">{{ __('Contacts already in the base', textDomain) }}</span>
              <select v-model="onDuplicate" :class="inputClass">
                <option value="update">{{ __('Update them (filled values replace, tags are added)', textDomain) }}</option>
                <option value="skip">{{ __('Leave them as they are', textDomain) }}</option>
              </select>
            </label>
            <label class="flex flex-col gap-1">
              <span class="text-[12px] font-medium text-slate-500">{{ __('Tags for every imported contact', textDomain) }}</span>
              <input v-model="extraTags" :class="inputClass" :placeholder="__('e.g. Imported, Fair 2026', textDomain)" type="text" />
            </label>
          </div>

          <div class="rounded-[8px] border border-slate-200 p-4">
            <label class="flex items-start gap-3 text-[14px] text-slate-700">
              <input v-model="optIn" class="mt-1" type="checkbox" />
              <span>
                {{ __('These contacts agreed to receive marketing messages', textDomain) }}
                <span class="block text-[12px] text-slate-400">{{ __('New contacts are created opted in, and existing ones only if their consent was unknown. An opt-out is never undone.', textDomain) }}</span>
              </span>
            </label>
            <label v-if="optIn" class="mt-3 flex flex-col gap-1">
              <span class="text-[12px] font-medium text-slate-500">{{ __('How was the consent obtained?', textDomain) }}</span>
              <input v-model="evidence" :class="inputClass" :placeholder="__('e.g. Sign-up sheet of the store event, 09/2026', textDomain)" type="text" />
            </label>
          </div>

          <div class="flex justify-end gap-3">
            <BaseButton :title="__('Back', textDomain)" variant="secondary" @click="reset" />
            <BaseButton
              :disabled="!hasPhone || (optIn && evidence.trim().length < 3)"
              :title="sprintf(__('Import %d contacts', textDomain), rows.length)"
              @click="run"
            />
          </div>
        </div>

        <!-- Progress and results -->
        <div v-else class="flex flex-col gap-4">
          <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
            <div class="h-full bg-primary-600 transition-all" :style="{ width: `${percent}%` }" />
          </div>
          <p class="text-[14px] text-slate-600">
            {{ running ? sprintf(__('Importing… %1$d of %2$d', textDomain), progress.done, rows.length) : __('Import finished.', textDomain) }}
          </p>

          <dl class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <div class="rounded-[8px] bg-slate-50 p-3"><dt class="text-[11px] uppercase text-slate-400">{{ __('Created', textDomain) }}</dt><dd class="text-[18px] font-semibold">{{ progress.created }}</dd></div>
            <div class="rounded-[8px] bg-slate-50 p-3"><dt class="text-[11px] uppercase text-slate-400">{{ __('Updated', textDomain) }}</dt><dd class="text-[18px] font-semibold">{{ progress.updated }}</dd></div>
            <div class="rounded-[8px] bg-slate-50 p-3"><dt class="text-[11px] uppercase text-slate-400">{{ __('Skipped', textDomain) }}</dt><dd class="text-[18px] font-semibold">{{ progress.skipped }}</dd></div>
            <div class="rounded-[8px] bg-slate-50 p-3"><dt class="text-[11px] uppercase text-slate-400">{{ __('Failed', textDomain) }}</dt><dd class="text-[18px] font-semibold">{{ progress.failed }}</dd></div>
          </dl>

          <p v-if="fatal" class="rounded-[8px] border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{{ fatal }}</p>

          <div v-if="problems.length">
            <p class="mb-1 text-[13px] font-semibold text-slate-600">{{ __('Lines not imported', textDomain) }}</p>
            <ul class="max-h-48 space-y-1 overflow-y-auto text-[13px] text-slate-600">
              <li v-for="(problem, index) in problems.slice(0, 200)" :key="index">
                {{ sprintf(__('Line %d', textDomain), problem.line) }}: {{ problem.reason }}
              </li>
            </ul>
          </div>

          <div class="flex justify-end">
            <BaseButton :disabled="running" :title="__('Close', textDomain)" @click="close" />
          </div>
        </div>
      </ModalDialog>
    </div>
  </Teleport>
</template>
