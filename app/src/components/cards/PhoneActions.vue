<template>
  <div class="space-y-6">
    <div class="grid items-center gap-6 lg:grid-cols-[minmax(0,420px)_minmax(0,460px)]">
      <div>
        <h3 class="text-[15px] font-semibold text-slate-800">Telefone para testes</h3>
        <p class="mt-1 max-w-xl text-[13px] leading-5 text-slate-500">
          Informe um telefone para receber mensagens de teste para disparo no construtor. Use o formato internacional, informando apenas números.
        </p>
      </div>

      <div class="lg:justify-self-start">
        <input
          :value="modelValue"
          type="text"
          inputmode="numeric"
          placeholder="5541987111527"
          class="w-full rounded-[10px] border border-slate-200 bg-white px-4 py-3 text-[14px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100 md:min-w-[430px]"
          @input="$emit('update:modelValue', $event.target.value)"
        />
      </div>
    </div>

    <div class="flex flex-wrap gap-3">
      <button
        type="button"
        class="rounded-[8px] bg-primary-700 px-5 py-3 text-[14px] font-semibold text-white transition hover:bg-primary-800"
        @click="registerOpen = true"
      >
        Adicionar novo telefone
      </button>
      <button
        type="button"
        class="rounded-[8px] border border-primary-200 bg-white px-5 py-3 text-[14px] font-semibold text-primary-700 transition hover:bg-primary-50 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="!modelValue || !defaultSender"
        @click="$emit('test-message', { sender: defaultSender, receiver: modelValue, message: testMessage })"
      >
        Enviar mensagem teste
      </button>
    </div>

    <ModalDialog
      :open="registerOpen"
      title="Cadastrar novo telefone"
      description="Selecione um telefone disponível, envie o código OTP e valide a conexão."
      eyebrow="Telefones"
      @close="registerOpen = false"
    >
      <div class="space-y-4">
        <SelectField
          v-model="registerPhone"
          :field="candidateField"
          name="register-phone"
        />

        <label class="block">
          <span class="text-sm font-medium text-ink">Código OTP</span>
          <input
            v-model="otpCode"
            type="text"
            inputmode="numeric"
            placeholder="000000"
            class="mt-3 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-ink outline-none transition placeholder:text-slate-400 focus:border-shell-400 focus:ring-4 focus:ring-shell-100"
          />
        </label>

        <div class="flex flex-wrap justify-end gap-3">
          <button
            type="button"
            class="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            @click="registerOpen = false"
          >
            Cancelar
          </button>
          <button
            type="button"
            class="rounded-full bg-shell-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-shell-700 disabled:cursor-not-allowed disabled:bg-slate-300"
            :disabled="!registerPhone"
            @click="sendOtp"
          >
            Enviar OTP
          </button>
          <button
            type="button"
            class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:bg-slate-300"
            :disabled="!registerPhone || !otpCode"
            @click="validate"
          >
            Validar e salvar
          </button>
        </div>
      </div>
    </ModalDialog>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import ModalDialog from '../base/ModalDialog.vue';
import SelectField from '../fields/SelectField.vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  candidates: { type: Array, default: () => [] },
  senders: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'register', 'validate', 'test-message']);

const registerOpen = ref(false);
const registerPhone = ref('');
const otpCode = ref('');
const defaultSender = computed(() => (props.senders[0] ? props.senders[0].phone : ''));
const testMessage = ref('Olá, esta é uma mensagem de teste.');

watch(
  () => props.candidates,
  (value) => {
    if (!registerPhone.value && value.length) {
      registerPhone.value = value[0].phone;
    }
  },
  { immediate: true }
);

const candidateField = computed(() => ({
  label: 'Telefone candidato',
  description: 'Selecione um telefone disponível para cadastro e validação.',
  placeholder: 'Selecione um telefone',
  emptyLabel: 'Nenhum telefone disponível',
  searchable: true,
  options: props.candidates.map((item) => ({
    value: item.phone,
    label: item.formatted || item.phone,
    meta: item.phone,
  })),
}));

function sendOtp() {
  if (!registerPhone.value) {
    return;
  }

  emit('register', registerPhone.value);
}

function validate() {
  if (!registerPhone.value || !otpCode.value) {
    return;
  }

  emit('validate', { phone: registerPhone.value, otp: otpCode.value });
  registerOpen.value = false;
}
</script>
