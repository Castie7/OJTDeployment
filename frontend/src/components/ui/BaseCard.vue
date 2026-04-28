<script setup lang="ts">
defineProps<{
  noPadding?: boolean
}>()
</script>

<template>
  <div
    class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300 overflow-hidden border border-gray-100"
  >
    <div v-if="$slots.header" class="px-6 py-4 border-b border-gray-50">
      <slot name="header"></slot>
    </div>

    <div :class="noPadding ? '' : 'p-6'">
      <slot></slot>
    </div>

    <div v-if="$slots.footer" class="px-6 py-4 bg-gray-50 border-t border-gray-50">
      <slot name="footer"></slot>
    </div>
  </div>
</template>
