<script setup lang="ts">
import { ref, watch } from 'vue'
import type { Research } from '../../../../types'
import { formatDate, getCropImage, sanitizeUrl } from '../../../../utils/formatters'
import { useToast } from '../../../../composables/useToast'
import { usePdfViewer } from '../../../../composables/usePdfViewer'

const props = defineProps<{
    research: Research
    assetUrl: string
}>()

const emit = defineEmits<{
    (e: 'close'): void
}>()

const { showToast } = useToast()
const pdfContainer = ref<HTMLElement | null>(null)

const { pdfBlobUrl, isPdfLoading, pdfError, loadPdf, clearPdf } = usePdfViewer()

watch(() => props.research, (newVal) => {
  if (newVal && newVal.id) {
    loadPdf(newVal.id)
  } else {
    clearPdf()
  }
}, { immediate: true })

const toggleFullscreen = () => {
  if (!pdfContainer.value) return

  if (!document.fullscreenElement) {
    pdfContainer.value.requestFullscreen().catch((err: any) => {
      showToast(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`, 'error');
    });
  } else {
    document.exitFullscreen();
  }
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/80 backdrop-blur-sm overflow-y-auto" @click="emit('close')">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden animate-pop" @click.stop>
            
            <div class="relative h-48 md:h-64 bg-gray-900 shrink-0">
                <img :src="getCropImage(research.crop_variation)" class="w-full h-full object-cover opacity-40">
                <div class="absolute inset-0 bg-gradient-to-t from-gray-900 to-transparent"></div>
                
                <div class="absolute bottom-0 left-0 p-6 md:p-8 w-full">
                    <div class="flex gap-2 mb-3">
                        <span class="bg-emerald-500 text-white text-[10px] uppercase font-bold px-2 py-1 rounded shadow-sm">{{ research.knowledge_type }}</span>
                        <span v-if="research.crop_variation" class="bg-white/20 text-white backdrop-blur-md text-[10px] uppercase font-bold px-2 py-1 rounded border border-white/20">{{ research.crop_variation }}</span>
                        <span class="bg-white/20 text-white backdrop-blur-md text-[10px] uppercase font-bold px-2 py-1 rounded border border-white/20">
                          {{ research.access_level === 'private' ? 'Private' : 'Public' }}
                        </span>
                    </div>
                    <h2 class="text-2xl md:text-4xl font-bold text-white leading-tight dropshadow-md">{{ research.title }}</h2>
                    <p class="text-emerald-200 text-sm md:text-base mt-2 font-medium">By {{ research.author }}</p>
                </div>

                <button @click="emit('close')" class="absolute top-4 right-4 bg-black/20 hover:bg-black/40 text-white rounded-full p-2 backdrop-blur-sm transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-6 bg-gray-50 custom-scrollbar">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <!-- Metadata Side -->
                    <div class="md:col-span-1 space-y-4">
                        <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Catalog Info</h3>
                                <dl class="space-y-3 text-sm">
                                    <div class="flex flex-col">
                                        <dt class="text-gray-400 text-xs">Publisher</dt>
                                        <dd class="font-medium text-gray-900">{{ research.publisher || 'N/A' }}</dd>
                                    </div>
                                    <div class="flex flex-col">
                                        <dt class="text-gray-400 text-xs">Date</dt>
                                        <dd class="font-medium text-gray-900">{{ formatDate(research.publication_date) }}</dd>
                                    </div>
                                    <div class="flex flex-col">
                                        <dt class="text-gray-400 text-xs">ISBN/ISSN</dt>
                                        <dd class="font-mono text-gray-600">{{ research.isbn_issn || 'N/A' }}</dd>
                                    </div>
                                    <div class="flex flex-col">
                                        <dt class="text-gray-400 text-xs">Shelf Location</dt>
                                        <dd class="font-mono font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded w-fit">{{ research.shelf_location || 'Unknown' }}</dd>
                                    </div>
                                    <div class="flex flex-col">
                                        <dt class="text-gray-400 text-xs">Condition</dt>
                                        <dd :class="`font-bold ${research.item_condition === 'Good' ? 'text-green-600' : 'text-red-500'}`">{{ research.item_condition }}</dd>
                                    </div>
                                </dl>
                        </div>
                    </div>

                    <!-- Abstract / Access -->
                    <div class="md:col-span-2 space-y-6">
                        <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                            <h3 class="font-bold text-gray-800 mb-2">Description / Abstract</h3>
                            <p class="text-gray-600 text-sm leading-relaxed">{{ research.physical_description || 'No description provided.' }}</p>
                        </div>

                        <div v-if="research.file_path || research.link" class="bg-blue-50/50 p-6 rounded-xl border border-blue-100">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="font-bold text-blue-900 flex items-center gap-2"><span>🌐</span> Digital Access</h3>
                                <button v-if="research.file_path" @click="toggleFullscreen" class="text-xs font-bold text-blue-600 hover:text-blue-800 bg-white border border-blue-200 px-3 py-1 rounded shadow-sm">
                                Full Screen
                                </button>
                            </div>
                            
                            <div v-if="research.file_path" ref="pdfContainer" class="w-full bg-gray-900 rounded-lg overflow-hidden shadow-lg h-[500px] border border-gray-200 relative flex flex-col items-center justify-center">
                                <div v-if="isPdfLoading" class="flex flex-col items-center justify-center text-gray-400 space-y-4 h-full bg-gray-900 w-full absolute inset-0 z-10">
                                  <div class="w-12 h-12 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                                  <span class="font-medium animate-pulse">Decrypting and loading document securely...</span>
                                </div>
                                
                                <div v-else-if="pdfError" class="text-red-400 font-bold p-6 bg-red-900/20 text-center h-full w-full flex flex-col items-center justify-center absolute inset-0 z-10">
                                  <div class="text-4xl mb-2">🔒</div>
                                  {{ pdfError }}
                                </div>

                                <iframe 
                                    v-else-if="pdfBlobUrl"
                                    :src="pdfBlobUrl" 
                                    class="w-full h-full border-none bg-white" 
                                    title="Secure PDF Preview">
                                </iframe>
                            </div>

                            <div v-if="sanitizeUrl(research.link)" class="mt-4">
                                <a :href="sanitizeUrl(research.link)" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center gap-2 w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700 shadow-md hover:shadow-lg transition">
                                    <span>🔗 Open External Link</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-pop {
    animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes popIn {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
</style>
