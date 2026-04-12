<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center px-4">
    <button class="absolute inset-0 bg-slate-950/60" type="button" aria-label="Close dialog" @click="$emit('close')" />
    <div class="relative z-10 w-full max-w-2xl rounded-lg border border-white/20 bg-white p-6 shadow-soft">
      <div class="mb-4 flex items-start justify-between gap-4">
        <div>
          <p v-if="eyebrow" class="text-xs font-semibold uppercase tracking-[0.2em] text-shell-500">
            {{ eyebrow }}
          </p>
          <h3 class="mt-1 text-xl font-semibold text-ink">{{ title }}</h3>
          <p v-if="description" class="mt-2 text-sm leading-6 text-muted">{{ description }}</p>
        </div>

        <button
          type="button"
          class="btn-close box-content flex h-4 w-4 shrink-0 items-center justify-center rounded border-0 bg-transparent p-[0.25rem] opacity-50 transition hover:opacity-75 focus:outline-none focus:opacity-100 disabled:pointer-events-none disabled:select-none disabled:opacity-25"
          :style="closeButtonStyle"
          aria-label="Fechar dialog"
          @click="$emit('close')"
        >
          <span class="sr-only">Fechar</span>
        </button>
      </div>

      <slot />
    </div>
  </div>
</template>

<script setup>
const closeButtonStyle = {
  backgroundImage:
    'url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\' fill=\'%23000\'%3e%3cpath d=\'M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 1 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 1 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z\'/%3e%3c/svg%3e")',
  backgroundPosition: 'center',
  backgroundRepeat: 'no-repeat',
  backgroundSize: '0.75rem auto',
};

defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  eyebrow: { type: String, default: '' },
});

defineEmits(['close']);
</script>
