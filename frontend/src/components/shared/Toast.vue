<script setup lang="ts">
import { useToast } from '../../composables/useToast'

const { toasts, removeToast } = useToast()

const getTypeClasses = (type: string) => {
  switch (type) {
    case 'success': return 'bg-white border-l-4 border-green-500 text-green-800'
    case 'error': return 'bg-white border-l-4 border-red-500 text-red-800'
    case 'warning': return 'bg-white border-l-4 border-yellow-500 text-yellow-800'
    case 'info': return 'bg-white border-l-4 border-blue-500 text-blue-800'
    default: return 'bg-white border-l-4 border-gray-500 text-gray-800'
  }
}

const getIcon = (type: string) => {
  switch (type) {
    case 'success': return '✅'
    case 'error': return '❌'
    case 'warning': return '⚠️'
    case 'info': return 'ℹ️'
    default: return '🔔'
  }
}
</script>

<template>
  <div class="fixed top-4 right-4 z-[10001] flex flex-col gap-2 w-full max-w-sm pointer-events-none">
    <transition-group name="toast">
      <div 
        v-for="toast in toasts" 
        :key="toast.id" 
        class="pointer-events-auto shadow-lg rounded-md p-4 flex items-start gap-3 transform transition-all duration-300 ease-out"
        :class="getTypeClasses(toast.type)"
      >
        <span class="text-xl mt-0.5">{{ getIcon(toast.type) }}</span>
        <div class="flex-1">
          <p class="font-medium text-sm leading-tight text-gray-900">{{ toast.message }}</p>
        </div>
        <button 
          @click="removeToast(toast.id)"
          class="text-gray-400 hover:text-gray-600 focus:outline-none"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
.toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}
.toast-enter-to {
  opacity: 1;
  transform: translateX(0);
}
.toast-leave-from {
  opacity: 1;
  transform: translateX(0);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
</style>
