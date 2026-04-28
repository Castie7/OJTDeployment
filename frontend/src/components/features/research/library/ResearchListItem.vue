<script setup lang="ts">
import { computed } from 'vue'
import type { Research } from '../../../../types'
import { formatDate } from '../../../../utils/formatters'
import { useAuthStore } from '../../../../stores/auth'

const props = defineProps<{
  item: Research
}>()

const emit = defineEmits<{
    (e: 'click'): void
    (e: 'archive', item: Research): void
}>()

const authStore = useAuthStore()
const isArchived = computed(() => props.item.status === 'archived')
</script>

<template>
    <tr @click="$emit('click')" class="hover:bg-emerald-50/50 cursor-pointer transition-colors">
        <td class="px-6 py-4">
            <div class="font-bold text-gray-900 text-sm line-clamp-1">{{ item.title }}</div>
            <div class="text-xs text-gray-500">{{ item.author }}</div>
        </td>
        <td class="px-6 py-4">
            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 mb-1">
                {{ item.knowledge_type }}
            </span>
            <span
              :class="[
                'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ml-1',
                item.access_level === 'private'
                  ? 'bg-amber-100 text-amber-800'
                  : 'bg-emerald-100 text-emerald-700'
              ]"
            >
              {{ item.access_level === 'private' ? 'Private' : 'Public' }}
            </span>
        </td>
        <td class="px-6 py-4">
            <span class="text-xs text-gray-500">{{ formatDate(item.publication_date) }}</span>
        </td>
        <td class="px-6 py-4">
            <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs font-mono font-bold">{{ item.shelf_location || 'N/A' }}</span>
        </td>
        <td class="px-6 py-4 text-right">
            <button 
                v-if="authStore.currentUser && authStore.currentUser.role === 'admin'" 
                @click.stop="$emit('archive', item)" 
                :class="[
                  'text-xs font-medium px-2 py-1 rounded transition-colors',
                  isArchived ? 'text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50' : 'text-red-500 hover:text-red-700 hover:bg-red-50'
                ]"
                :title="isArchived ? 'Restore' : 'Archive'"
            >
                {{ isArchived ? 'Restore' : 'Archive' }}
            </button>
        </td>
    </tr>
</template>
