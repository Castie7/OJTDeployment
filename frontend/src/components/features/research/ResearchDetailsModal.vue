<script setup lang="ts">
import { ref } from 'vue'
import { useToast } from '../../../composables/useToast'

import { sanitizeUrl } from '../../../utils/formatters'
import { watch } from 'vue'
import { usePdfViewer } from '../../../composables/usePdfViewer'

// // Ideally, import your shared 'Research' interface here. 
// // For now, I'm using 'any', but you should replace it with your actual type.
// defineProps<{
//   research: any | null 
// }>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

// Helper to handle both string dates and Backend-returned DateTime objects
const formatDate = (date: any) => {
  if (!date) return '-'
  
  let dateStr = date
  // If it's a DateTime object produced by PHP/CodeIgniter
  if (typeof date === 'object' && date.date) {
    dateStr = date.date
  }
  
  try {
    const d = new Date(dateStr)
    // Check if valid date
    if (isNaN(d.getTime())) return dateStr

    return d.toLocaleDateString('en-US', { 
      year: 'numeric', 
      month: 'short', 
      day: 'numeric' 
    })
  } catch (e) {
    return dateStr
  }
}

const { showToast } = useToast()
const pdfContainer = ref<HTMLElement | null>(null)

const { pdfBlobUrl, isPdfLoading, pdfError, loadPdf, clearPdf } = usePdfViewer()

const propsDef = defineProps<{
  research: any | null 
}>()

watch(() => propsDef.research, (newVal) => {
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
  <Transition name="fade">
    <div v-if="research" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-75 backdrop-blur-sm overflow-y-auto">
      
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden" @click.stop>
                <div class="bg-emerald-900 text-white p-6 flex justify-between items-start shrink-0">
            <div class="pr-8">
              <div class="flex gap-2 mb-3">
                  <span class="bg-white/20 text-white backdrop-blur-md text-[10px] uppercase font-bold px-2 py-1 rounded">{{ research.knowledge_type }}</span>
                  <span v-if="research.crop_variation" class="bg-teal-500 text-emerald-900 text-[10px] uppercase font-bold px-2 py-1 rounded">{{ research.crop_variation }}</span>
              </div>
              <h2 class="text-2xl font-bold leading-tight">{{ research.title }}</h2>
              <p class="text-emerald-200 text-sm mt-2 font-medium">By {{ research.author }}</p>
            </div>
          <button @click="$emit('close')" class="text-white hover:text-gray-300 text-3xl font-bold leading-none">&times;</button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 bg-gray-50 custom-scrollbar">
           
           <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
             
             <div class="bg-white p-5 rounded-lg border shadow-sm space-y-3">
                 <h3 class="font-bold text-gray-800 border-b pb-2 mb-2">📖 Catalog Details</h3>
                 <div class="grid grid-cols-3 gap-2 text-sm">
                    <span class="text-gray-500">Publisher:</span> <span class="col-span-2 font-medium">{{ research.publisher || '-' }}</span>
                    <span class="text-gray-500">Edition:</span> <span class="col-span-2">{{ research.edition || '-' }}</span>
                    <span class="text-gray-500">Date:</span> <span class="col-span-2">{{ formatDate(research.publication_date) }}</span>
                    <span class="text-gray-500">ISBN/ISSN:</span> <span class="col-span-2 font-mono text-gray-600">{{ research.isbn_issn || '-' }}</span>
                    <span class="text-gray-500">Description:</span> <span class="col-span-2">{{ research.physical_description || '-' }}</span>
                 </div>
             </div>

             <div class="bg-white p-5 rounded-lg border shadow-sm space-y-3">
                   <h3 class="font-bold text-gray-800 border-b pb-2 flex items-center gap-2"><span>📍</span> Location & Topic</h3>
                   <div class="grid grid-cols-3 gap-y-3 text-sm">
                      <span class="text-gray-500">Shelf Loc:</span> <span class="col-span-2 font-mono font-bold text-emerald-700 text-lg">{{ research.shelf_location || 'Unknown' }}</span>
                      <span class="text-gray-500">Condition:</span> <span class="col-span-2">{{ research.item_condition }}</span>
                    <span class="text-gray-500">Crop:</span> <span class="col-span-2 text-amber-600 font-medium">{{ research.crop_variation || 'General' }}</span>
                    <span class="text-gray-500">Subjects:</span> <span class="col-span-2 italic text-gray-600">{{ research.subjects || 'No keywords' }}</span>
                 </div>
             </div>
           </div>

           <div v-if="research.file_path || research.link" class="bg-blue-50 p-4 rounded-lg border border-blue-100">
              <h3 class="font-bold text-blue-900 mb-3 flex items-center gap-2">🌐 Digital Access</h3>
              
              <div class="flex flex-wrap gap-4">
                
                <div v-if="research.file_path" class="w-full">
                   <div class="flex justify-between items-center mb-2">
                      <p class="text-xs text-blue-600 font-bold uppercase">Attached Document:</p>
                      <button @click="toggleFullscreen" class="text-xs flex items-center gap-1 bg-white border border-blue-200 text-blue-600 px-2 py-1 rounded hover:bg-blue-50 font-bold transition">
                        ⛶ Full Screen
                      </button>
                   </div>
                   
                   <div ref="pdfContainer" class="w-full bg-black rounded overflow-hidden shadow-lg h-[500px] flex flex-col items-center justify-center relative">
                      <div v-if="isPdfLoading" class="flex flex-col items-center justify-center text-gray-500 space-y-4 h-full bg-gray-100 w-full absolute inset-0 z-10">
                        <div class="w-12 h-12 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
                        <span class="font-medium animate-pulse">Decrypting and loading document securely...</span>
                      </div>
                      
                      <div v-else-if="pdfError" class="text-red-500 font-bold p-6 bg-red-50 rounded-xl text-center border border-red-200 h-full w-full flex flex-col items-center justify-center absolute inset-0 z-10">
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
                </div>

                <div v-if="sanitizeUrl(research.link)" class="w-full mt-2">
                   <a :href="sanitizeUrl(research.link)" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center gap-2 w-full bg-blue-600 text-white font-bold py-3 rounded-lg shadow hover:bg-blue-700 transition">
                      <span>🔗 Open External Link / Website</span>
                   </a>
                </div>
              </div>
           </div>
           
           <div v-else class="text-center py-8 text-gray-400 italic bg-white rounded border border-dashed">
              No digital copy available for this item.
           </div>

        </div>
      </div>
    </div>
  </Transition>
</template>

