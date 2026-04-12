<template>
  <div>
    <input
      :name="name"
      :value="modelValue"
      :placeholder="field.placeholder || ''"
      type="text"
      class="w-full rounded-[10px] border border-slate-200 bg-white px-4 py-3 text-[14px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100 md:min-w-[330px]"
      @input="$emit('update:modelValue', $event.target.value)"
    />
  </div>
</template>

<script setup>
defineProps({
  modelValue: { type: [String, Number], default: '' },
  field: { type: Object, required: true },
  name: { type: String, required: true },
});

defineEmits(['update:modelValue']);
</script>
