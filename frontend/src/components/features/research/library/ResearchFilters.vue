<script setup lang="ts">
import BaseInput from '../../../ui/BaseInput.vue'
import BaseSelect from '../../../ui/BaseSelect.vue'

defineProps<{
  searchQuery: string
  selectedType: string
  startDate: string
  endDate: string
  hasActiveFilters: boolean
}>()

const emit = defineEmits<{
  (e: 'update:searchQuery', value: string): void
  (e: 'update:selectedType', value: string): void
  (e: 'update:startDate', value: string): void
  (e: 'update:endDate', value: string): void
  (e: 'clear-filters'): void
}>()

const typeOptions = [
  { label: 'All Types', value: '' },
  { label: 'Research Paper', value: 'Research Paper' },
  { label: 'Book', value: 'Book' },
  { label: 'Journal', value: 'Journal' },
  { label: 'IEC Material', value: 'IEC Material' },
  { label: 'Thesis', value: 'Thesis' }
]
</script>

<template>
  <div class="p-6 space-y-6 sticky top-24 bg-white/80 backdrop-blur-md rounded-2xl shadow-xl border border-white/40">
    <div>
      <label class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 block">Search</label>
      <BaseInput
        :model-value="searchQuery"
        @update:model-value="emit('update:searchQuery', $event as string)"
        placeholder="Title, author, keywords..."
        label=""
        class="w-full"
      />
    </div>

    <div>
      <label class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 block">Type</label>
      <BaseSelect
        :model-value="selectedType"
        @update:model-value="emit('update:selectedType', $event as string)"
        :options="typeOptions"
        label=""
        class="w-full"
      />
    </div>

    <div>
      <label class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 block">Date Range</label>
      <div class="space-y-3">
        <BaseInput
          :model-value="startDate"
          @update:model-value="emit('update:startDate', $event as string)"
          type="date"
          class="w-full"
        />
        <BaseInput
          :model-value="endDate"
          @update:model-value="emit('update:endDate', $event as string)"
          type="date"
          class="w-full"
        />
      </div>
    </div>

    <div v-if="hasActiveFilters" class="pt-4 border-t border-gray-200/50">
      <button
        @click="emit('clear-filters')"
        class="w-full text-sm text-red-600 hover:text-red-700 font-medium flex items-center justify-center gap-1 transition-colors"
      >
        <span>x</span> Clear Filters
      </button>
    </div>
  </div>
</template>
