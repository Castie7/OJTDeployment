<script setup lang="ts">
import { ref, watch, nextTick } from 'vue' 
import { useRoute } from 'vue-router'
import { researchService } from '../../../services'
import SubmittedResearches from './SubmittedResearches.vue'
import { useMyWorkspace } from '../../../composables/useMyWorkspace' 
import { useAuthStore } from '../../../stores/auth'
import ResearchDetailsModal from './ResearchDetailsModal.vue'
import BaseButton from '../../ui/BaseButton.vue'
import BaseCard from '../../ui/BaseCard.vue'
import BaseInput from '../../ui/BaseInput.vue'
import BaseSelect from '../../ui/BaseSelect.vue'

// Props removed

const route = useRoute()

const authStore = useAuthStore()


const { 
  activeTab, 
  isModalOpen, 
  isEasyResubmitModalOpen,
  isSubmitting, 
  similarTitleMatches,
  isCheckingSimilarTitles,
  form, 
  errors,
  openSubmitModal, 
  openEditModal,
  openEasyResubmitModal,
  submitResearch,
  handleFileChange
} = useMyWorkspace()

// Reference to the child component (the list of researches)
const submissionsRef = ref<InstanceType<typeof SubmittedResearches> | null>(null)
const selectedResearch = ref(null)

const handleViewResearch = (item: any) => {
  selectedResearch.value = item
}

const handleSubmit = async () => {
  const success = await submitResearch()
  if (success) {
      // Refresh the list after a successful submission
      if (submissionsRef.value) {
        submissionsRef.value.fetchData()
      }
  }
}

// --- HANDLE NOTIFICATION CLICKS ---
const openNotification = async (id: number) => {
    if (!submissionsRef.value) return

    try {
        // 1. Fetch the specific item first to know its status
        const item = await researchService.getById(id)
        if (!item) return

        // 2. Map item status to tab
        let targetTab = 'pending'
        if (item.status === 'approved') targetTab = 'approved'
        else if (item.status === 'rejected') targetTab = 'rejected'
        else if (item.archived_at) targetTab = 'archived' // Handle archived differently if needed
        
        // 3. Switch tab if different
        if (activeTab.value !== targetTab) {
             activeTab.value = targetTab as any
             // Wait for watcher to trigger fetch and fetch to complete
             // Since we don't have a promise for the watcher's fetch, we might need to manually call fetch
             await nextTick()
             if (submissionsRef.value) {
                 await submissionsRef.value.fetchData()
             }
        }

        // 4. Open the notification in the child component
        // Pass the item object if possible to avoid re-finding, but child expects ID
        // The child's openNotification finds it in the list.
        // Since we just fetched data for that tab, it should be in there.
        await nextTick()
        submissionsRef.value.openNotification(id)
        
    } catch (e) {
        console.error("Failed to open notification", e)
    }
}

// Watch for query param changes
watch(() => route.query.open, (newId) => {
    if (newId) {
        // Wait for component to be ready or just call it
        // We might need to wait for fetch if not loaded, but openNotification handles that in SubmittedResearches
        openNotification(Number(newId))
        
        // Clear query param to avoid reopening on refresh (optional but good UX)
        // router.replace({ query: { ...route.query, open: undefined } }) 
        // User asked for "direct" link, maybe keeping it is fine? 
        // If we keep it, it stays open on refresh.
    }
}, { immediate: true })


defineExpose({ openNotification })

const variationOptions = [
  { value: 'Sweet Potato', label: 'Sweet Potato' },
  { value: 'Potato', label: 'Potato' },
  { value: 'Yam Aeroponics', label: 'Yam Aeroponics' },
  { value: 'Yam Minisetts', label: 'Yam Minisetts' },
  { value: 'Taro', label: 'Taro' },
  { value: 'Cassava', label: 'Cassava' },
  { value: 'Yacon', label: 'Yacon' },
  { value: 'Ginger', label: 'Ginger' },
  { value: 'Canna', label: 'Canna' },
  { value: 'Arrowroot', label: 'Arrowroot' },
  { value: 'Turmeric', label: 'Turmeric' },
  { value: 'Tannia', label: 'Tannia' },
  { value: 'Kinampay', label: 'Kinampay' },
  { value: 'Zambal', label: 'Zambal' },
  { value: 'Bengueta', label: 'Bengueta' },
  { value: 'Immitlog', label: 'Immitlog' },
  { value: 'Beniazuma', label: 'Beniazuma' },
  { value: 'Haponita', label: 'Haponita' },
  { value: 'Ganza', label: 'Ganza' },
  { value: 'Montanosa', label: 'Montanosa' },
  { value: 'Igorota', label: 'Igorota' },
  { value: 'Solibao', label: 'Solibao' },
  { value: 'Raniag', label: 'Raniag' },
  { value: 'Dalisay', label: 'Dalisay' },
  { value: 'Others', label: 'Others' },
]

const conditionOptions = [
    { value: 'New', label: 'New' },
    { value: 'Good', label: 'Good' },
    { value: 'Fair', label: 'Fair' },
    { value: 'Poor', label: 'Poor' },
    { value: 'Damaged', label: 'Damaged' },
]
</script>

<template>
  <div class="space-y-6 animate-fade-in">
    
    <div class="flex flex-col md:flex-row justify-between items-center gap-4">
      <div>
        <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <span>👤</span> My Workspace
        </h2>
        <p class="text-sm text-gray-500">Managing uploads for <span class="font-bold text-emerald-700">{{ authStore.currentUser?.name }}</span></p>
      </div>
      <BaseButton 
          @click="openSubmitModal" 
          variant="primary"
          class="shadow-lg hover:shadow-xl hover:scale-105 transition-all"
      >
        <span>➕</span> Submit New Item
      </BaseButton>
    </div>

    <!-- Tabs -->
    <div class="bg-gray-100 p-1 rounded-xl inline-flex gap-1 overflow-x-auto max-w-full">
      <button 
        @click="activeTab = 'pending'" 
        :class="`px-4 py-2 text-sm font-bold rounded-lg transition-all ${activeTab === 'pending' ? 'bg-white text-yellow-700 shadow-sm' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-200/50'}`"
      >
        ⏳ Pending
      </button>
      <button 
        @click="activeTab = 'approved'" 
        :class="`px-4 py-2 text-sm font-bold rounded-lg transition-all ${activeTab === 'approved' ? 'bg-white text-green-700 shadow-sm' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-200/50'}`"
      >
        ✅ Approved
      </button>
      <button 
        @click="activeTab = 'rejected'" 
        :class="`px-4 py-2 text-sm font-bold rounded-lg transition-all ${activeTab === 'rejected' ? 'bg-white text-red-700 shadow-sm' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-200/50'}`"
      >
        ❌ Rejected
      </button>
      <button 
        @click="activeTab = 'archived'" 
        :class="`px-4 py-2 text-sm font-bold rounded-lg transition-all ${activeTab === 'archived' ? 'bg-white text-gray-700 shadow-sm' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-200/50'}`"
      >
        🗑️ Archived
      </button>
    </div>

    <BaseCard class="min-h-[500px] !p-0 overflow-hidden">
        <SubmittedResearches 
            ref="submissionsRef" 
            :currentUser="authStore.currentUser" 
            :statusFilter="activeTab" 
            @edit="openEditModal"
            @easy-resubmit="openEasyResubmitModal"
            @view="handleViewResearch"
        />
    </BaseCard>

    <!-- Modal: Form -->
    <Transition name="fade">
      <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/80 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white rounded-2xl w-full max-w-4xl overflow-hidden shadow-2xl transform transition-all flex flex-col max-h-[90vh] animate-pop" @click.stop>
          
          <div class="bg-emerald-900 text-white p-4 flex justify-between items-center shrink-0">
              <h2 class="font-bold text-lg flex items-center gap-2">
                  <span>{{ form.id ? '✏️' : '📤' }}</span>
                  {{ form.id ? 'Edit Knowledge Product' : 'Submit Knowledge Product' }}
              </h2>
              <button @click="isModalOpen = false" class="text-white/70 hover:text-white transition bg-white/10 hover:bg-white/20 rounded-full w-8 h-8 flex items-center justify-center font-bold">&times;</button>
          </div>
          
          <div class="p-6 overflow-y-auto custom-scrollbar flex-1 bg-gray-50">
            <form @submit.prevent="handleSubmit" class="space-y-6">
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <!-- Type -->
                 <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-3">Type <span class="text-red-500">*</span></label>
                    <div class="space-y-2">
                         <label v-for="type in ['Research Paper', 'Book', 'Journal', 'IEC Material', 'Thesis']" :key="type" class="flex items-center gap-3 p-2 rounded hover:bg-emerald-50 cursor-pointer transition">
                            <input type="checkbox" v-model="form.knowledge_type" :value="type" class="w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500">
                            <span class="text-sm font-medium text-gray-700">{{ type }}</span>
                         </label>
                    </div>
                    <span v-if="errors.knowledge_type" class="text-red-500 text-xs mt-2 block font-medium">{{ errors.knowledge_type }}</span>
                 </div>

                 <!-- Basic Info -->
                 <div class="space-y-4">
                    <BaseSelect 
                        v-model="form.crop_variation" 
                        :options="variationOptions" 
                        label="Crop Variation" 
                        placeholder="Select Variation (Optional)"
                    />
                    
                    <BaseInput 
                        v-model="form.title" 
                        label="Title / Name of Product *" 
                        placeholder="Enter full title..."
                        :error="errors.title"
                    />

                    <p v-if="isCheckingSimilarTitles && !similarTitleMatches.length" class="text-[11px] font-medium text-amber-700">
                        Checking similar titles...
                    </p>

                    <div v-else-if="similarTitleMatches.length" class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                        <p class="text-xs font-bold text-amber-800">Similar title found</p>
                        <div
                          v-for="match in similarTitleMatches"
                          :key="match.id"
                          class="mt-2 rounded border border-amber-100 bg-white px-2 py-1.5 text-xs text-gray-700"
                        >
                          <span class="font-semibold">{{ match.title }}</span>
                          <span class="block text-[10px] text-gray-500">
                            By {{ match.author }}<span v-if="match.edition"> - {{ match.edition }}</span> - {{ match.similarity }}% similar
                          </span>
                        </div>
                    </div>

                    <BaseInput 
                        v-model="form.author" 
                        label="Author(s) *" 
                        placeholder="e.g. Juan Cruz, Maria Santos"
                        :error="errors.author"
                    />
                 </div>
              </div>

              <!-- Dates -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                 <BaseInput v-model="form.publication_date" type="date" label="Publication Date" />
                 <BaseInput v-model="form.start_date" type="date" label="Date Started (Optional)" />
                 <BaseInput v-model="form.deadline_date" type="date" label="Deadline (Optional)" />
              </div>

              <!-- Publishing Details -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                 <BaseInput v-model="form.publisher" label="Publisher / Producer" placeholder="Name of publisher" />
                 <BaseInput v-model="form.edition" label="Edition" placeholder="e.g. 2nd Edition" />
                 <BaseInput v-model="form.physical_description" label="Physical Description" placeholder="e.g. 150 pages, PDF" />
                 <BaseInput v-model="form.isbn_issn" label="ISBN / ISSN" placeholder="Identifier code" />
              </div>

              <div class="space-y-1">
                 <label class="block text-xs font-bold text-gray-700 mb-1 ml-1">Subject(s) / Keywords</label>
                 <textarea v-model="form.subjects" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition text-sm shadow-sm" placeholder="Enter keywords describing the content..." rows="2"></textarea>
              </div>

              <!-- Location & Condition -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                 <BaseInput v-model="form.shelf_location" label="Shelf Location" placeholder="e.g. A-1" />
                 <BaseSelect v-model="form.item_condition" :options="conditionOptions" label="Condition" placeholder="Select Condition" />
                 <BaseInput v-model="form.link" type="url" label="External Link" placeholder="https://..." />
              </div>

              <!-- RESUBMISSION FEEDBACK (Only for rejected items) -->
              <div v-if="activeTab === 'rejected' && form.id" class="bg-amber-50 p-5 rounded-xl border border-amber-200 shadow-sm mt-6">
                 <label class="block text-sm font-bold text-amber-800 uppercase mb-2">Resubmission Remarks</label>
                 <p class="text-xs text-amber-700 mb-3">Explain what you have fixed or changed based on the Admin's feedback.</p>
                 <textarea 
                   v-model="form.resubmit_remarks" 
                   class="w-full border border-amber-300 p-3 rounded-lg focus:ring-2 focus:ring-amber-500 outline-none text-sm resize-none bg-white"
                   rows="3"
                   placeholder="e.g., I have corrected the missing pages in the PDF."
                 ></textarea>
              </div>

              <!-- File Upload -->
              <div class="bg-emerald-50 p-6 rounded-xl border border-dashed border-emerald-300 text-center hover:bg-emerald-100/50 transition-colors">
                 <label class="block text-sm font-bold text-emerald-800 uppercase mb-2">
                    {{ form.id ? 'Replace File (Optional)' : 'Upload File (PDF/Image) (Optional)' }}
                 </label>
                 <input 
                    type="file" 
                    @change="handleFileChange" 
                    accept=".pdf, .jpg, .jpeg, .png" 
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer"
                 />
                 <p class="text-xs text-emerald-600 mt-2">Accepted formats: PDF, JPG, PNG</p>
              </div>

            </form>
          </div>

          <div class="bg-gray-50 p-4 border-t border-gray-100 flex justify-end gap-3 shrink-0">
              <BaseButton 
                @click="isModalOpen = false" 
                variant="ghost"
              >
                Cancel
              </BaseButton>

              <BaseButton 
                @click="handleSubmit" 
                :disabled="isSubmitting" 
                variant="primary"
                class="min-w-[120px]"
              >
                  {{ isSubmitting ? 'Saving...' : (form.id ? 'Update Item' : 'Submit Item') }}
              </BaseButton>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Modal: Easy Resubmit -->
    <Transition name="fade">
      <div v-if="isEasyResubmitModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/80 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl transform transition-all flex flex-col animate-pop" @click.stop>
          
          <div class="bg-amber-500 text-white p-4 flex justify-between items-center shrink-0 rounded-t-2xl">
              <h2 class="font-bold text-lg flex items-center gap-2">
                  <span>⚡</span> Quick Resubmit
              </h2>
              <button @click="isEasyResubmitModalOpen = false" class="text-white/70 hover:text-white transition bg-white/20 hover:bg-white/30 rounded-full w-8 h-8 flex items-center justify-center font-bold">&times;</button>
          </div>
          
          <div class="p-6 bg-amber-50">
            <h3 class="font-bold text-gray-800 text-lg mb-2 line-clamp-1">{{ form.title }}</h3>
            <p class="text-xs text-amber-700 mb-4">You are about to resubmit this rejected item without changing its metadata. Only fill this out if you've resolved the admin's remarks.</p>
            
            <form @submit.prevent="handleSubmit" class="space-y-4">
              
              <!-- RESUBMISSION FEEDBACK -->
              <div>
                 <label class="block text-sm font-bold text-amber-900 uppercase mb-2">Resubmission Remarks <span class="text-red-500">*</span></label>
                 <textarea 
                   v-model="form.resubmit_remarks" 
                   class="w-full border border-amber-300 p-3 rounded-lg focus:ring-2 focus:ring-amber-500 outline-none text-sm resize-none bg-white shadow-sm"
                   rows="4"
                   placeholder="Briefly explain what you fixed (e.g., 'Re-uploaded missing pages', 'Corrected typos')."
                   required
                 ></textarea>
              </div>

              <!-- OPTIONAL FILE REPLACEMENT -->
              <div class="bg-white p-4 rounded-xl border border-dashed border-amber-300 transition-colors">
                 <label class="block text-sm font-bold text-amber-800 uppercase mb-2">
                    Replace File (Optional)
                 </label>
                 <input 
                    type="file" 
                    @change="handleFileChange" 
                    accept=".pdf, .jpg, .jpeg, .png" 
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-amber-600 file:text-white hover:file:bg-amber-700 cursor-pointer"
                 />
                 <p class="text-xs text-amber-600 mt-2">Accepted formats: PDF, JPG, PNG. Leave blank to keep existing file.</p>
              </div>

            </form>
          </div>

          <div class="bg-white p-4 border-t border-gray-100 flex justify-end gap-3 shrink-0 rounded-b-2xl">
              <BaseButton 
                @click="isEasyResubmitModalOpen = false" 
                variant="ghost"
              >
                Cancel
              </BaseButton>

              <button 
                @click="handleSubmit" 
                :disabled="isSubmitting || !form.resubmit_remarks.trim()" 
                class="px-5 py-2 font-bold rounded-lg shadow-md transition-all min-w-[120px]"
                :class="(isSubmitting || !form.resubmit_remarks.trim()) ? 'bg-amber-300 text-white cursor-not-allowed' : 'bg-amber-500 hover:bg-amber-600 text-white hover:shadow-lg'"
              >
                  <span v-if="isSubmitting" class="animate-spin inline-block mr-2">⏳</span>
                  {{ isSubmitting ? 'Resubmitting...' : 'Confirm Resubmit' }}
              </button>
          </div>

        </div>
      </div>
    </Transition>

    <ResearchDetailsModal 
      :research="selectedResearch" 
      @close="selectedResearch = null" 
    />
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.3s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(5px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-pop {
    animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes popIn {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

/* Scrollbar */
.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
</style>
