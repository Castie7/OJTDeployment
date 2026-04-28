<script setup lang="ts">
import { useMasterlist } from '../../../composables/useMasterlist'
import { sanitizeUrl } from '../../../utils/formatters'
import { ref, computed, watch } from 'vue'
import { usePdfViewer } from '../../../composables/usePdfViewer'
import BaseButton from '../../ui/BaseButton.vue'
import BaseCard from '../../ui/BaseCard.vue'
import BaseInput from '../../ui/BaseInput.vue'
import BaseSelect from '../../ui/BaseSelect.vue'

const pdfContainer = ref<HTMLElement | null>(null)
const toggleFullscreen = () => {
  if (!pdfContainer.value) return
  if (!document.fullscreenElement) {
    pdfContainer.value.requestFullscreen().catch(err => console.error(err))
  } else {
    document.exitFullscreen()
  }
}

const {
  allItems,
  isLoading, isRefreshing, searchQuery, statusFilter,
  currentPage, itemsPerPage, filteredItems, paginatedItems, totalPages,
  nextPage, prevPage,
  selectedCount, archivedCount, isArchivedView, allOnPageSelected, bulkAccessLevel, bulkIsProcessing,
  isSelected, toggleSelection, toggleSelectAllOnPage, clearSelection, applyBulkAccessLevel,
  openArchiveBin, openAllItems,
  isEditModalOpen, isSaving, editForm,
  openEdit, handleFileChange, saveEdit,
  getStatusBadge, formatDate, resetFilters,
  confirmModal, requestArchive, requestPermanentDelete, executeArchive,
  selectedItem, viewDetails, closeDetails,
  approveResearch, rejectResearch
} = useMasterlist()

const { pdfBlobUrl, isPdfLoading, pdfError, loadPdf, clearPdf } = usePdfViewer()

watch(() => selectedItem.value, (newVal) => {
  if (newVal && newVal.id) {
    loadPdf(newVal.id)
  } else {
    clearPdf()
  }
})

const hasActiveFilters = computed(() => {
  return searchQuery.value !== '' || statusFilter.value !== 'ALL'
})

const statusTotals = computed(() => ({
  total: allItems.value.length,
  approved: allItems.value.filter(item => item.status === 'approved').length,
  pending: allItems.value.filter(item => item.status === 'pending').length,
  rejected: allItems.value.filter(item => item.status === 'rejected').length,
  archived: allItems.value.filter(item => item.status === 'archived').length
}))

const activeStatusLabel = computed(() => {
  return statusOptions.find(option => option.value === statusFilter.value)?.label ?? 'All Statuses'
})

const resultLabel = computed(() => {
  const count = filteredItems.value.length
  return `${count} ${count === 1 ? 'record' : 'records'}`
})

const tableColumnCount = computed(() => (isArchivedView.value ? 6 : 7))
const tableWidthClass = computed(() => (isArchivedView.value ? 'min-w-[920px]' : 'min-w-[980px]'))

const selectionMessage = computed(() => {
  if (isArchivedView.value) {
    return `${filteredItems.value.length} archived ${filteredItems.value.length === 1 ? 'item' : 'items'} currently in the recycle bin.`
  }

  if (selectedCount.value === 0) {
    return 'Select one or more rows to bulk update visibility.'
  }

  return `${selectedCount.value} ${selectedCount.value === 1 ? 'item is' : 'items are'} ready for a bulk visibility change.`
})



const statusOptions = [
    { value: 'ALL', label: 'All Statuses' },
    { value: 'APPROVED', label: 'Published' },
    { value: 'PENDING', label: 'Pending' },
    { value: 'REJECTED', label: 'Rejected' },
    { value: 'ARCHIVED', label: 'Archived' },
]

const cropOptions = [
  'Sweet Potato', 'Potato', 'Yam Aeroponics', 'Yam Minisetts', 'Taro', 'Cassava', 
  'Yacon', 'Ginger', 'Canna', 'Arrowroot', 'Turmeric', 'Tannia', 'Kinampay', 
  'Zambal', 'Bengueta', 'Immitlog', 'Beniazuma', 'Haponita', 'Ganza', 'Montanosa', 
  'Igorota', 'Solibao', 'Raniag', 'Dalisay', 'Others'
].map(c => ({ value: c, label: c }))

const conditionOptions = ['New', 'Good', 'Fair', 'Poor', 'Damaged'].map(c => ({ value: c, label: c }))
const accessOptions = [
  { value: 'public', label: 'Public' },
  { value: 'private', label: 'Private (Login Required)' }
]
</script>

<template>
  <div class="space-y-6 animate-fade-in">

    <!-- Header -->
    <div class="overflow-hidden rounded-3xl border border-emerald-100 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.14),_transparent_36%),radial-gradient(circle_at_top_right,_rgba(251,191,36,0.14),_transparent_28%),linear-gradient(135deg,_#ffffff,_#f8fafc)] p-6 shadow-sm">
      <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
        <div class="space-y-3">
          <span class="inline-flex items-center rounded-full border border-emerald-200 bg-white/80 px-3 py-1 text-xs font-bold uppercase tracking-[0.2em] text-emerald-700 shadow-sm">
            Catalog Control
          </span>
          <div class="space-y-2">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Masterlist</h1>
            <p class="max-w-2xl text-sm leading-6 text-gray-600 sm:text-base">
              Review every knowledge product in one place, update metadata quickly, and keep archived records separate from the active collection.
            </p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 xl:justify-end">
          <button
            @click="openAllItems"
            :class="[
              'inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-bold transition shadow-sm',
              statusFilter === 'ALL'
                ? 'border-emerald-600 bg-emerald-600 text-white shadow-emerald-100'
                : 'border-white bg-white/85 text-gray-700 hover:bg-white'
            ]"
          >
            <span>All Items</span>
            <span class="rounded-full bg-black/5 px-2 py-0.5 text-xs" :class="statusFilter === 'ALL' ? '!bg-white/15 !text-white' : ''">
              {{ statusTotals.total }}
            </span>
          </button>

          <button
            @click="openArchiveBin"
            :class="[
              'inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-bold transition shadow-sm',
              isArchivedView
                ? 'border-gray-900 bg-gray-900 text-white'
                : 'border-white bg-white/85 text-gray-700 hover:bg-white'
            ]"
          >
            <span>Archive Bin</span>
            <span class="rounded-full bg-black/5 px-2 py-0.5 text-xs" :class="isArchivedView ? '!bg-white/15 !text-white' : ''">
              {{ archivedCount }}
            </span>
          </button>
        </div>
      </div>

      <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl border border-slate-900 bg-slate-950 p-4 text-white shadow-sm">
          <p class="text-xs font-bold uppercase tracking-wide text-slate-300">Total Records</p>
          <p class="mt-3 text-3xl font-bold">{{ statusTotals.total }}</p>
          <p class="mt-1 text-xs text-slate-400">All catalog entries</p>
        </div>

        <div class="rounded-2xl border border-emerald-100 bg-white/90 p-4 shadow-sm">
          <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Published</p>
          <p class="mt-3 text-3xl font-bold text-gray-900">{{ statusTotals.approved }}</p>
          <p class="mt-1 text-xs text-gray-500">Visible according to access settings</p>
        </div>

        <div class="rounded-2xl border border-amber-100 bg-white/90 p-4 shadow-sm">
          <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Pending</p>
          <p class="mt-3 text-3xl font-bold text-gray-900">{{ statusTotals.pending }}</p>
          <p class="mt-1 text-xs text-gray-500">Awaiting review or approval</p>
        </div>

        <div class="rounded-2xl border border-rose-100 bg-white/90 p-4 shadow-sm">
          <p class="text-xs font-bold uppercase tracking-wide text-rose-700">Rejected</p>
          <p class="mt-3 text-3xl font-bold text-gray-900">{{ statusTotals.rejected }}</p>
          <p class="mt-1 text-xs text-gray-500">Returned for revision</p>
        </div>

        <div
          class="rounded-2xl border bg-white/90 p-4 shadow-sm transition"
          :class="isArchivedView ? 'border-gray-900 ring-1 ring-gray-900/10' : 'border-gray-200'"
        >
          <p class="text-xs font-bold uppercase tracking-wide text-gray-600">Archived</p>
          <p class="mt-3 text-3xl font-bold text-gray-900">{{ statusTotals.archived }}</p>
          <p class="mt-1 text-xs text-gray-500">Stored separately from active items</p>
        </div>
      </div>
    </div>

    <!-- Filters Toolbar -->
    <BaseCard class="!p-4 md:!p-5">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div class="grid flex-1 gap-4 lg:grid-cols-[minmax(0,1fr)_220px]">
          <div class="space-y-2">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-gray-400">Search Records</p>
            <BaseInput
              v-model="searchQuery"
              placeholder="Search title, author, crop, or publisher..."
              class="w-full"
            />
          </div>

          <div class="space-y-2">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-gray-400">Filter Status</p>
            <BaseSelect
              v-model="statusFilter"
              :options="statusOptions"
              placeholder="Status"
            />
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 xl:justify-end">
          <span class="inline-flex items-center rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">
            {{ activeStatusLabel }}
          </span>
          <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-bold text-gray-600">
            {{ resultLabel }}
          </span>
          <button
            @click="resetFilters"
            class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-600 shadow-sm transition hover:bg-gray-50"
            title="Refresh & Reset Filters"
            :disabled="isRefreshing"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4 transition-transform"
              :class="{ 'animate-spin-refresh': isRefreshing }"
              fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>{{ hasActiveFilters ? 'Reset & Refresh' : 'Refresh' }}</span>
          </button>
        </div>
      </div>
    </BaseCard>

    <BaseCard
      v-if="isArchivedView"
      class="!p-4 border border-amber-100 bg-amber-50/70"
    >
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="text-sm font-bold text-gray-800">Archive Bin</p>
          <p class="text-sm text-gray-600">Archived items stay out of the active masterlist until you restore them.</p>
        </div>
        <BaseButton @click="openAllItems" variant="ghost">
          Back to All Items
        </BaseButton>
      </div>
    </BaseCard>

    <!-- Bulk Visibility Action -->
    <BaseCard
      v-else-if="selectedCount > 0"
      class="!p-4 border border-emerald-100 bg-emerald-50/50"
    >
      <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div>
          <p class="text-sm font-bold text-gray-800">Bulk Visibility Tools</p>
          <p class="text-sm text-gray-600">{{ selectionMessage }}</p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
          <select
            v-model="bulkAccessLevel"
            class="h-10 rounded-xl border border-emerald-200 bg-white px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-300"
          >
            <option value="public">Set to Public</option>
            <option value="private">Set to Private (Login Required)</option>
          </select>

          <BaseButton
            @click="applyBulkAccessLevel"
            :disabled="selectedCount === 0 || bulkIsProcessing"
            variant="primary"
          >
            {{ bulkIsProcessing ? 'Applying...' : 'Apply to Selected' }}
          </BaseButton>

          <BaseButton
            @click="clearSelection"
            :disabled="selectedCount === 0 || bulkIsProcessing"
            variant="ghost"
          >
            Clear Selection
          </BaseButton>
        </div>
      </div>
    </BaseCard>

    <BaseCard
      v-else
      class="!p-4 border border-dashed border-gray-200 bg-gray-50/70"
    >
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="text-sm font-bold text-gray-800">Bulk Visibility Tools</p>
          <p class="text-sm text-gray-600">{{ selectionMessage }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-gray-500">
          <span class="rounded-full border border-gray-200 bg-white px-3 py-1">Public</span>
          <span class="rounded-full border border-gray-200 bg-white px-3 py-1">Private</span>
          <span class="rounded-full border border-gray-200 bg-white px-3 py-1">Multi-select ready</span>
        </div>
      </div>
    </BaseCard>

    <!-- Table -->
    <BaseCard class="flex min-h-[500px] flex-col overflow-hidden !p-0">
      <div class="border-b border-gray-100 bg-white px-4 py-4 sm:px-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p class="text-sm font-bold text-gray-800">Knowledge Product Records</p>
            <p class="text-sm text-gray-500">Click any row to inspect metadata, digital access, and workflow status.</p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-bold text-gray-600">
              {{ activeStatusLabel }}
            </span>
            <span v-if="!isArchivedView" class="inline-flex items-center rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">
              {{ selectedCount }} selected
            </span>
            <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-bold text-gray-600">
              {{ resultLabel }}
            </span>
          </div>
        </div>
      </div>

      <div class="flex-1 overflow-x-auto custom-scrollbar">
        <table :class="[tableWidthClass, 'w-full table-fixed divide-y divide-gray-100']">
          <thead class="bg-gray-50/90">
            <tr>
              <th v-if="!isArchivedView" class="w-10 px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">
                <input
                  type="checkbox"
                  :checked="allOnPageSelected"
                  @change.stop="toggleSelectAllOnPage"
                  class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                  title="Select all on this page"
                >
              </th>
              <th :class="[isArchivedView ? 'w-[34%]' : 'w-[30%]', 'px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500']">Knowledge Product</th>
              <th class="w-[18%] px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Author</th>
              <th class="w-[12%] px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Status</th>
              <th class="w-[15%] px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Catalog</th>
              <th class="w-[11%] px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Publication</th>
              <th :class="[isArchivedView ? 'w-[20%]' : 'w-[14%]', 'px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-gray-500']">Actions</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-100 bg-white">
            <tr v-if="isLoading">
              <td :colspan="tableColumnCount" class="px-6 py-20 text-center text-gray-400">
                <div class="flex flex-col items-center gap-2">
                  <div class="h-8 w-8 rounded-full border-2 border-emerald-500 border-t-transparent animate-spin"></div>
                  <span>Loading masterlist...</span>
                </div>
              </td>
            </tr>

            <tr v-else-if="filteredItems.length === 0">
              <td :colspan="tableColumnCount" class="px-6 py-20 text-center">
                <div class="mx-auto flex max-w-md flex-col items-center">
                  <div class="mb-4 rounded-full bg-gray-100 p-4 text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                  </div>
                  <h3 class="mb-2 text-lg font-bold text-gray-800">No matching records</h3>
                  <p class="mb-4 text-sm text-gray-600">Adjust the search or status filter to find the item you need.</p>

                  <button
                    v-if="hasActiveFilters"
                    @click="resetFilters"
                    class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-bold text-white shadow-md transition hover:bg-emerald-700"
                  >
                    Clear All Filters
                  </button>
                </div>
              </td>
            </tr>

            <tr
              v-else
              v-for="item in paginatedItems" :key="item.id" v-memo="[item.id, item.status, item.title, item.updated_at, isSelected(item.id)]"
              class="group cursor-pointer border-l-4 align-top transition-colors"
              :class="{
                'bg-emerald-50/80': isSelected(item.id),
                'hover:bg-gray-50/80': !isSelected(item.id),
                'border-l-emerald-500': item.status === 'approved',
                'border-l-yellow-400': item.status === 'pending',
                'border-l-red-500': item.status === 'rejected',
                'border-l-gray-400': item.status === 'archived',
                'border-l-transparent': !['approved','pending','rejected','archived'].includes(item.status)
              }"
              @click="viewDetails(item)"
            >
              <td v-if="!isArchivedView" class="px-3 py-4 align-top" @click.stop>
                <input
                  type="checkbox"
                  :checked="isSelected(item.id)"
                  @change.stop="toggleSelection(item.id)"
                  class="mt-1 h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                  :title="`Select ${item.title}`"
                >
              </td>

              <td class="px-4 py-4 align-top">
                <div class="min-w-0 space-y-2">
                  <div
                    class="line-clamp-2 text-sm font-semibold leading-5 text-gray-900 transition-colors group-hover:text-emerald-700"
                    :title="item.title"
                  >
                    {{ item.title }}
                  </div>

                  <div class="flex flex-wrap gap-1.5 text-[11px]">
                    <span
                      v-if="item.crop_variation"
                      class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-1 font-semibold text-emerald-700"
                    >
                      {{ item.crop_variation }}
                    </span>
                    <span
                      v-if="item.shelf_location"
                      class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 font-medium text-gray-600"
                    >
                      {{ item.shelf_location }}
                    </span>
                    <span
                      v-if="item.subjects"
                      class="inline-flex items-center rounded-full bg-gray-50 px-2 py-1 font-medium text-gray-500"
                    >
                      {{ item.subjects.split(',')[0] }}
                    </span>
                  </div>
                </div>
              </td>

              <td class="px-4 py-4 align-top">
                <div class="min-w-0 space-y-1">
                  <p class="line-clamp-2 text-sm font-semibold text-gray-800">{{ item.author }}</p>
                  <p class="truncate text-xs text-gray-500">{{ item.publisher || 'No publisher listed' }}</p>
                </div>
              </td>

              <td class="px-4 py-4 align-top">
                <span :class="['inline-flex max-w-full rounded-full border px-2 py-1 text-[11px] font-bold shadow-sm', getStatusBadge(item.status).classes]">
                  {{ getStatusBadge(item.status).label }}
                </span>
              </td>

              <td class="px-4 py-4 align-top">
                <div class="space-y-2">
                  <span class="inline-flex max-w-full rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-700">
                    <span class="truncate">{{ item.knowledge_type || 'Not set' }}</span>
                  </span>
                  <span
                    :class="[
                      'inline-flex rounded-full border px-2 py-1 text-[11px] font-bold shadow-sm',
                      item.access_level === 'private'
                        ? 'border-amber-200 bg-amber-100 text-amber-800'
                        : 'border-emerald-200 bg-emerald-100 text-emerald-700'
                    ]"
                  >
                    {{ item.access_level === 'private' ? 'Private' : 'Public' }}
                  </span>
                  <p class="text-[11px] text-gray-500">
                    {{ item.access_level === 'private' ? 'Login required' : 'Open access' }}
                  </p>
                </div>
              </td>

              <td class="px-4 py-4 align-top">
                <div class="space-y-1">
                  <p class="text-sm font-semibold text-gray-800">{{ formatDate(item.publication_date || item.created_at) }}</p>
                  <p class="text-[11px] text-gray-500">Added {{ formatDate(item.created_at) }}</p>
                </div>
              </td>

              <td class="px-4 py-4 text-right align-top">
                <div class="flex flex-wrap justify-end gap-2" @click.stop>
                  <button
                    @click.stop="viewDetails(item)"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-[11px] font-semibold text-blue-700 transition hover:bg-blue-100"
                    :title="item.file_path || item.link ? 'Preview item' : 'View details'"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span>View</span>
                  </button>

                  <button
                    @click.stop="openEdit(item)"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-gray-700 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                    title="Edit details"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                    <span>Edit</span>
                  </button>

                  <button
                    @click.stop="requestArchive(item)"
                    :class="[
                      'inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[11px] font-semibold transition',
                      item.status === 'archived'
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                        : 'border-red-200 bg-red-50 text-red-600 hover:bg-red-100'
                    ]"
                    :title="item.status === 'archived' ? 'Restore item' : 'Archive item'"
                  >
                    <svg v-if="item.status === 'archived'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    <span>{{ item.status === 'archived' ? 'Restore' : 'Archive' }}</span>
                  </button>

                  <button
                    v-if="item.status === 'archived'"
                    @click.stop="requestPermanentDelete(item)"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-[11px] font-semibold text-red-700 transition hover:bg-red-100"
                    title="Delete permanently"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M8 7l1 12a1 1 0 001 1h4a1 1 0 001-1l1-12" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M10 11v5M14 11v5" />
                    </svg>
                    <span>Delete</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination in Footer -->
      <div v-if="filteredItems.length > itemsPerPage" class="mt-auto border-t border-gray-100 bg-gray-50/70 px-4 py-4 sm:px-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <span class="text-sm font-medium text-gray-500">
            Showing {{ ((currentPage - 1) * itemsPerPage) + 1 }} - {{ Math.min(currentPage * itemsPerPage, filteredItems.length) }} of {{ filteredItems.length }}
          </span>

          <div class="flex items-center gap-2">
            <button
              @click="prevPage"
              :disabled="currentPage === 1"
              class="rounded-lg border bg-white px-3 py-1.5 text-xs font-bold shadow-sm transition hover:bg-gray-50 disabled:opacity-50"
            >
              Previous
            </button>
            <span class="rounded-lg border border-emerald-200 bg-emerald-100 px-3 py-1.5 text-xs font-bold text-emerald-700">
              Page {{ currentPage }} of {{ totalPages }}
            </span>
            <button
              @click="nextPage"
              :disabled="currentPage >= totalPages"
              class="rounded-lg border bg-white px-3 py-1.5 text-xs font-bold shadow-sm transition hover:bg-gray-50 disabled:opacity-50"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </BaseCard>

    <!-- Edit Modal -->
    <Transition name="fade">
      <div v-if="isEditModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white rounded-2xl w-full max-w-4xl overflow-hidden shadow-2xl transform transition-all flex flex-col max-h-[90vh] animate-pop">
          
          <div class="bg-emerald-900 text-white p-4 flex justify-between items-center shrink-0">
            <h2 class="font-bold text-lg flex items-center gap-2"><span>✏️</span> Edit Knowledge Product</h2>
            <button @click="isEditModalOpen = false" class="text-white/70 hover:text-white transition w-8 h-8 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 font-bold">&times;</button>
          </div>

          <div class="p-6 overflow-y-auto custom-scrollbar flex-1 bg-gray-50">
            <form @submit.prevent="saveEdit" class="space-y-6">

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <!-- Type -->
                 <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-3">Type <span class="text-red-500">*</span></label>
                    <div class="space-y-2">
                         <label v-for="type in ['Research Paper', 'Book', 'Journal', 'IEC Material', 'Thesis']" :key="type" class="flex items-center gap-3 p-2 rounded hover:bg-emerald-50 cursor-pointer transition">
                            <input type="checkbox" v-model="editForm.knowledge_type" :value="type" class="w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500">
                            <span class="text-sm font-medium text-gray-700">{{ type }}</span>
                         </label>
                    </div>
                 </div>

                 <!-- Basic Info -->
                 <div class="space-y-4">
                    <BaseSelect 
                        v-model="editForm.crop_variation" 
                        :options="cropOptions" 
                        label="Crop Variation" 
                    />
                    
                    <BaseInput 
                        v-model="editForm.title" 
                        label="Title *" 
                        required
                    />

                    <BaseInput 
                        v-model="editForm.author" 
                        label="Author(s) *" 
                        required
                    />
                 </div>
              </div>

              <!-- Dates -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                 <BaseInput v-model="editForm.publication_date" type="date" label="Publication Date" />
                 <BaseInput v-model="editForm.start_date" type="date" label="Date Started" />
                 <BaseInput v-model="editForm.deadline_date" type="date" label="Deadline" />
              </div>

              <!-- Publishing Details -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                 <BaseInput v-model="editForm.publisher" label="Publisher" />
                 <BaseInput v-model="editForm.edition" label="Edition" />
                 <BaseInput v-model="editForm.physical_description" label="Physical Desc" />
                 <BaseInput v-model="editForm.isbn_issn" label="ISBN / ISSN" />
              </div>

              <div class="space-y-1">
                 <label class="block text-xs font-bold text-gray-700 mb-1 ml-1">Subject(s)</label>
                 <textarea v-model="editForm.subjects" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition text-sm shadow-sm" placeholder="Keywords..." rows="2"></textarea>
              </div>

              <!-- Location & Condition -->
              <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                 <BaseInput v-model="editForm.shelf_location" label="Shelf Location" />
                 <BaseSelect v-model="editForm.item_condition" :options="conditionOptions" label="Condition" />
                 <BaseSelect v-model="editForm.access_level" :options="accessOptions" label="Visibility" />
                 <BaseInput v-model="editForm.link" type="url" label="Link" />
              </div>

              <!-- File Upload -->
              <div class="bg-gray-100 p-4 rounded-xl border border-dashed border-gray-300 hover:bg-gray-200/50 transition-colors">
                 <label class="block text-xs font-bold text-gray-500 uppercase mb-2">
                    Replace File (Optional)
                 </label>
                 <input 
                    type="file" 
                    @change="handleFileChange" 
                    accept=".pdf, .jpg, .jpeg, .png" 
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-gray-600 file:text-white hover:file:bg-gray-700 cursor-pointer"
                 />
              </div>

            </form>
          </div>

          <div class="bg-gray-50 p-4 border-t border-gray-100 flex justify-end gap-3 shrink-0">
              <BaseButton 
                @click="isEditModalOpen = false" 
                variant="ghost"
              >
                Cancel
              </BaseButton>

              <BaseButton 
                @click="saveEdit" 
                :disabled="isSaving" 
                variant="primary"
                class="min-w-[120px]"
              >
                  {{ isSaving ? 'Saving...' : 'Update Item' }}
              </BaseButton>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Archive Confirmation Modal (Reused Logic) -->
    <Transition name="pop">
      <div v-if="confirmModal.show" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all p-8 text-center animate-pop">
          <div class="mb-6 flex justify-center">
             <div class="w-20 h-20 rounded-full bg-gray-50 flex items-center justify-center text-5xl shadow-inner">
              {{ 
                confirmModal.action === 'Archive' ? '🗑️' : 
                confirmModal.action === 'Reject' ? '❌' : 
                confirmModal.action === 'Approve' ? '✅' : '♻️' 
              }}
             </div>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ confirmModal.title }}</h3>
          <p class="text-gray-500 text-sm mb-6 leading-relaxed">{{ confirmModal.subtext }}</p>
          <div class="flex gap-3 justify-center">
            <BaseButton @click="confirmModal.show = false" variant="ghost">Cancel</BaseButton>
            <BaseButton 
              @click="executeArchive" 
              :disabled="confirmModal.isProcessing"
              :variant="['Archive', 'Reject', 'Delete'].includes(confirmModal.action) ? 'danger' : 'primary'"
              class="shadow-lg"
            >
              Yes, {{ confirmModal.action }}
            </BaseButton>
          </div>
        </div>
      </div>
    </Transition>

    <!-- View Details Modal -->
    <Transition name="fade">
      <div v-if="selectedItem" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/80 backdrop-blur-sm overflow-y-auto" @click.self="closeDetails">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[95vh] flex flex-col overflow-hidden animate-pop">
          
          <div class="bg-emerald-900 text-white p-6 flex justify-between items-start shrink-0">
            <div>
              <div class="flex gap-2 mb-2">
                 <span class="bg-emerald-800 text-emerald-100 text-[10px] uppercase font-bold px-2 py-1 rounded inline-block border border-emerald-700">{{ selectedItem.knowledge_type }}</span>
                 <span class="bg-white/20 text-white text-[10px] uppercase font-bold px-2 py-1 rounded inline-block border border-white/30">{{ selectedItem.status }}</span>
                 <span class="bg-white/20 text-white text-[10px] uppercase font-bold px-2 py-1 rounded inline-block border border-white/30">
                   {{ selectedItem.access_level === 'private' ? 'Private' : 'Public' }}
                 </span>
              </div>
              <h2 class="text-2xl font-bold leading-tight line-clamp-2 max-w-2xl">{{ selectedItem.title }}</h2>
              <p class="text-emerald-200 text-sm mt-1 font-medium">Author: {{ selectedItem.author }}</p>
            </div>
            <button @click="closeDetails" class="text-white/70 hover:text-white transition w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-2xl font-bold leading-none">&times;</button>
          </div>

          <div class="flex-1 overflow-y-auto p-6 bg-gray-50 custom-scrollbar">
              
             <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Details Card -->
                <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm space-y-4 h-full">
                   <h3 class="font-bold text-gray-800 border-b pb-3 flex items-center gap-2"><span>📖</span> Catalog Details</h3>
                   <div class="grid grid-cols-3 gap-y-3 gap-x-2 text-sm">
                      <span class="text-gray-500 font-medium">Publisher:</span> <span class="col-span-2 text-gray-800">{{ selectedItem.publisher || '-' }}</span>
                      <span class="text-gray-500 font-medium">Edition:</span> <span class="col-span-2 text-gray-800">{{ selectedItem.edition || '-' }}</span>
                      <span class="text-gray-500 font-medium">Date:</span> <span class="col-span-2 text-gray-800">{{ formatDate(selectedItem.publication_date) }}</span>
                      <span class="text-gray-500 font-medium">ISBN/ISSN:</span> <span class="col-span-2 font-mono text-gray-600 bg-gray-50 px-1 rounded w-fit">{{ selectedItem.isbn_issn || '-' }}</span>
                      <span class="text-gray-500 font-medium">Desc:</span> <span class="col-span-2 text-gray-800">{{ selectedItem.physical_description || '-' }}</span>
                   </div>
                </div>

                <!-- Location Card -->
                <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm space-y-4 h-full">
                   <h3 class="font-bold text-gray-800 border-b pb-3 flex items-center gap-2"><span>📍</span> Location & Topic</h3>
                   <div class="grid grid-cols-3 gap-y-3 gap-x-2 text-sm">
                      <span class="text-gray-500 font-medium">Shelf Loc:</span> <span class="col-span-2 font-mono font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded w-fit">{{ selectedItem.shelf_location || 'Unknown' }}</span>
                      <span class="text-gray-500 font-medium">Condition:</span> <span class="col-span-2 text-gray-800">{{ selectedItem.item_condition }}</span>
                      <span class="text-gray-500 font-medium">Crop:</span> <span class="col-span-2 text-amber-700 font-bold bg-amber-50 px-2 py-0.5 rounded w-fit">{{ selectedItem.crop_variation || 'General' }}</span>
                      <span class="text-gray-500 font-medium">Subjects:</span> <span class="col-span-2 italic text-gray-500 text-xs leading-relaxed border-l-2 pl-2 border-gray-200">{{ selectedItem.subjects || 'No keywords' }}</span>
                   </div>
                </div>
             </div>

             <!-- Digital Preview -->
             <div v-if="selectedItem.file_path || selectedItem.link" class="bg-blue-50/50 p-6 rounded-xl border border-blue-100">
                <h3 class="font-bold text-blue-900 mb-4 flex items-center gap-2"><span>🌐</span> Digital Access</h3>
                
                <div class="flex flex-col gap-4">
                  
                  <div v-if="selectedItem.file_path" class="w-full">
                      <div class="flex justify-between items-center mb-2">
                         <p class="text-xs text-blue-600 font-bold uppercase tracking-wide">Attached Document Preview</p>
                         <button @click="toggleFullscreen" class="text-xs flex items-center gap-1 bg-white border border-blue-200 text-blue-600 px-3 py-1.5 rounded-lg hover:bg-blue-50 font-bold transition shadow-sm">
                           ⛶ Full Screen
                         </button>
                      </div>
                      
                      <div ref="pdfContainer" class="w-full bg-gray-900 rounded-xl overflow-hidden shadow-lg h-[600px] border border-gray-200 relative flex flex-col items-center justify-center">
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
                   </div>

                   <div v-if="sanitizeUrl(selectedItem.link)" class="w-full mt-2">
                      <a :href="sanitizeUrl(selectedItem.link)" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center gap-2 w-full bg-blue-600 text-white font-bold py-3 rounded-xl shadow-lg hover:bg-blue-700 hover:scale-[1.01] transition-all">
                         <span>🔗 Open External Link / Website</span>
                      </a>
                   </div>
                </div>
             </div>
             <div v-else class="text-center py-12 text-gray-400 italic bg-white rounded-xl border-dashed border-2 border-gray-200">
                <div class="text-4xl opacity-20 mb-2">📄</div>
                No digital copy available for this item.
             </div>

          </div>

          <!-- Modal Footer with Actions -->
          <div
            v-if="selectedItem.status === 'pending' || selectedItem.status === 'archived'"
            class="shrink-0 border-t border-gray-100 bg-gray-50 p-4"
          >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <p class="text-xs font-medium text-gray-500">
                {{
                  selectedItem.status === 'pending'
                    ? 'This item is awaiting a review decision.'
                    : 'This archived item can be restored to the active library or permanently deleted.'
                }}
              </p>

              <div class="flex justify-end gap-3">
                <template v-if="selectedItem.status === 'pending'">
                  <BaseButton
                    @click="rejectResearch(selectedItem.id)"
                    variant="danger"
                    class="!border !border-red-200 !bg-white !text-red-600 hover:!bg-red-50"
                  >
                    Reject
                  </BaseButton>
                  <BaseButton
                    @click="approveResearch(selectedItem.id)"
                    variant="primary"
                  >
                    Approve
                  </BaseButton>
                </template>

                <template v-else>
                  <BaseButton
                    @click="requestPermanentDelete(selectedItem)"
                    variant="danger"
                    class="!border !border-red-200 !bg-white !text-red-600 hover:!bg-red-50"
                  >
                    Delete Permanently
                  </BaseButton>
                  <BaseButton
                    @click="requestArchive(selectedItem)"
                    variant="primary"
                    class="!bg-emerald-600 hover:!bg-emerald-700"
                  >
                    Restore Item
                  </BaseButton>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>

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

.animate-spin-refresh {
  animation: spin-refresh 0.6s ease-in-out;
}
@keyframes spin-refresh {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
</style>
