<script setup lang="ts">
defineProps<{
  modelValue: string | number
  label?: string
  options: Array<{ value: string | number, label: string }>
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
    
    <div class="relative">
      <select
        :id="id"
        :value="modelValue"
        @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
        class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg shadow-sm appearance-none
               focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500
               transition-colors duration-200 disabled:bg-gray-50 disabled:text-gray-500"
        :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': error }"
      >
        <option v-if="placeholder" value="" disabled selected>{{ placeholder }}</option>
        <option v-for="option in options" :key="option.value" :value="option.value">
          {{ option.label }}
        </option>
      </select>
      <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none text-gray-500">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </div>
    </div>
    
    <span v-if="error" class="text-xs text-red-500">{{ error }}</span>
  </div>
</template>
