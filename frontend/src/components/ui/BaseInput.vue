<script setup lang="ts">
defineProps<{
  modelValue: string | number
  label?: string
  type?: string
  placeholder?: string
  error?: string
  id?: string
}>()

defineEmits<{
  (e: 'update:modelValue', value: string | number): void
}>()
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label v-if="label" :for="id" class="text-sm font-medium text-gray-700">
      {{ label }}
    </label>
    
    <input
      :id="id"
      :type="type || 'text'"
      :value="modelValue"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      :placeholder="placeholder"
      class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400
             focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500
             transition-colors duration-200 disabled:bg-gray-50 disabled:text-gray-500"
      :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': error }"
    />
    
    <span v-if="error" class="text-xs text-red-500">{{ error }}</span>
  </div>
</template>
