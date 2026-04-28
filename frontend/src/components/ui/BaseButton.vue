<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost' | 'outline',
  size?: 'sm' | 'md' | 'lg',
  disabled?: boolean,
  type?: 'button' | 'submit' | 'reset',
  block?: boolean
}>()

const baseClasses = 'inline-flex items-center justify-center rounded-lg font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed transform active:scale-95'

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'secondary':
      return 'bg-teal-500 text-white hover:bg-teal-600 focus:ring-teal-500 shadow-sm hover:shadow'
    case 'danger':
      return 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 shadow-sm hover:shadow'
    case 'ghost':
      return 'bg-transparent text-gray-600 hover:bg-gray-100 focus:ring-gray-400'
    case 'outline':
      return 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 focus:ring-emerald-500'
    case 'primary':
    default:
      return 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500 shadow-sm hover:shadow-md'
  }
})

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'px-3 py-1.5 text-xs'
    case 'lg':
      return 'px-6 py-3 text-base'
    case 'md':
    default:
      return 'px-4 py-2 text-sm'
  }
})
</script>

<template>
  <button
    :type="type || 'button'"
    :class="[
      baseClasses,
      variantClasses,
      sizeClasses,
      block ? 'w-full' : ''
    ]"
    :disabled="disabled"
  >
    <slot />
  </button>
</template>
