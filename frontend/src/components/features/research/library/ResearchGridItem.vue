<script setup lang="ts">
import type { Research } from '../../../../types'
import { formatDate, getCropImage } from '../../../../utils/formatters'

defineProps<{
    item: Research
}>()

defineEmits<{
    (e: 'click'): void
}>()
</script>

<template>
    <div 
        @click="$emit('click')"
        class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg hover:-translate-y-1 transition-all duration-300 cursor-pointer group flex flex-col h-full"
    >
        <!-- Cover Image Area -->
        <div class="h-32 bg-gray-100 relative overflow-hidden">
                <img :src="getCropImage(item.crop_variation)" class="w-full h-full object-cover opacity-90 group-hover:scale-105 transition-transform duration-500" alt="Cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                
                <div class="absolute bottom-3 left-3 right-3 flex justify-between items-end">
                    <span class="bg-white/90 backdrop-blur-sm text-emerald-800 text-[10px] font-bold px-2 py-0.5 rounded shadow-sm">
                    {{ item.knowledge_type }}
                    </span>
                    <div class="flex items-center gap-1">
                        <span
                          :class="[
                            'text-[10px] font-bold px-2 py-0.5 rounded',
                            item.access_level === 'private'
                              ? 'bg-amber-500/90 text-white'
                              : 'bg-emerald-500/90 text-white'
                          ]"
                        >
                          {{ item.access_level === 'private' ? 'Private' : 'Public' }}
                        </span>
                        <span v-if="item.file_path" class="text-white bg-black/30 p-1 rounded-full text-xs">📎</span>
                    </div>
                </div>
        </div>

        <!-- Content -->
        <div class="p-5 flex-1 flex flex-col">
            <h3 class="font-bold text-gray-900 leading-tight mb-2 line-clamp-2 group-hover:text-emerald-700 transition-colors" :title="item.title">{{ item.title }}</h3>
            <p class="text-xs text-gray-500 mb-3">by {{ item.author }}</p>
            
            <div class="mt-auto pt-3 border-t border-gray-50 flex items-center justify-between text-xs text-gray-400">
                <span>{{ formatDate(item.publication_date) }}</span>
                <span v-if="item.crop_variation" class="text-emerald-600 font-medium">{{ item.crop_variation }}</span>
            </div>
        </div>
    </div>
</template>
