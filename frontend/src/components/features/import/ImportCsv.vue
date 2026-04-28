<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '../../../services/api'
import { apiCache } from '../../../utils/apiCache'
import { useToast } from '../../../composables/useToast'
import BaseCard from '../../ui/BaseCard.vue'
import BaseButton from '../../ui/BaseButton.vue'
import Papa from 'papaparse'

const emit = defineEmits<{
  (e: 'upload-success'): void
}>()

const { showToast } = useToast()

// ═══════════════════════════════════════════════════════════════
// SECTION 1 — CSV IMPORT
// ═══════════════════════════════════════════════════════════════
const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const isUploading = ref(false)
const uploadStatus = ref<{ message: string, type: 'success' | 'error' | 'warning' | '' }>({ message: '', type: '' })

// --- Preview / Edit state ---
const showPreview = ref(false)
const previewHeaders = ref<string[]>([])
const previewRows = ref<Record<string, string>[]>([])
const csvDuplicates = ref<any[]>([]) // Store duplicate check statuses for each row
const missingOptionalCols = ref<string[]>([])
const editingCell = ref<{ row: number, col: string } | null>(null)

// Template columns
const KNOWN_COLUMNS = new Set([
    'Title', 'Author', 'Authors', 'Type', 'Date',
    'Edition', 'Publication', 'Publisher', 'Pages',
    'ISBN/ISSN', 'ISSN', 'ISBN',
    'Subjects', 'Description',
    'Location', 'Condition', 'Crop',
])

const TEMPLATE_COLUMNS = ['Title', 'Author', 'Type', 'Date', 'Edition', 'Publisher',
    'Pages', 'ISBN/ISSN', 'Subjects', 'Location', 'Condition', 'Crop']

const previewPage = ref(0)
const PREVIEW_PAGE_SIZE = 10
const totalPreviewPages = computed(() => Math.ceil(previewRows.value.length / PREVIEW_PAGE_SIZE))
const paginatedPreviewRows = computed(() => {
    const start = previewPage.value * PREVIEW_PAGE_SIZE
    return previewRows.value.slice(start, start + PREVIEW_PAGE_SIZE)
})
const paginatedStartIndex = computed(() => previewPage.value * PREVIEW_PAGE_SIZE)

// 1. Handle File Selection
const handleFileChange = (event: Event | DragEvent) => {
  let file: File | undefined;

  if (event instanceof DragEvent && event.dataTransfer) {
      if (event.dataTransfer.files && event.dataTransfer.files[0]) {
          file = event.dataTransfer.files[0];
      }
  } else {
      const target = event.target as HTMLInputElement;
      if (target.files && target.files[0]) {
          file = target.files[0];
      }
  }

  if (file) {
    if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
        uploadStatus.value = { message: '❌ Please select a valid .csv file', type: 'error' }
        selectedFile.value = null
        if (event.target instanceof HTMLInputElement) event.target.value = ''
        return
    }
    selectedFile.value = file
    uploadStatus.value = { message: '', type: '' }
    showPreview.value = false
    previewRows.value = []
    previewHeaders.value = []
    missingOptionalCols.value = []
  }
}

// 2. Download Template
const downloadTemplate = () => {
    const headers = [
        'Title', 'Author', 'Type', 'Date', 'Edition', 'Publisher',
        'Pages', 'ISBN/ISSN', 'Subjects', 'Location', 'Condition', 'Crop'
    ];

    const rows = [
        [
            'Golden Roots Issue No. 01', 'Betty T. Gayao et al.', 'Journal', '2004-01-01',
            'Vol. 1', 'NPRCRTC - BSU', '16 Pages', 'ISSN 1656-5444',
            'Sweetpotato processing, Rootcrops', 'Shelf 6b', 'Good', 'Sweetpotato'
        ],
        [
            'Varietal Improvement of Rootcrops', 'Juan Dela Cruz', 'Thesis', '2023-05-15',
            '1st Edition', 'BSU', '120 Leaves', 'N/A',
            'Breeding, Genetics', 'Thesis Section', 'Good', 'Cassava'
        ]
    ];
    const processRow = (row: string[]) => row.map(val => `"${val}"`).join(',');
    const csvContent = [headers.join(','), ...rows.map(processRow)].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'research_upload_template.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// 3. Parse & Preview (Step 1 — Validation + show editable table)
const parseCsv = () => {
    if (!selectedFile.value) return

    uploadStatus.value = { message: 'Parsing CSV...', type: '' }

    Papa.parse(selectedFile.value, {
        header: true,
        skipEmptyLines: true,
        complete: (results) => {
            const rawHeaders: string[] = results.meta.fields ?? []
            // Filter out empty headers (like trailing commas purely from Excel)
            const headers = rawHeaders.filter(h => h && h.trim() !== '')

            // --- GUARD 1: Duplicate headers ---
            const headerCounts: Record<string, number> = {}
            headers.forEach(h => { headerCounts[h] = (headerCounts[h] || 0) + 1 })
            const duplicates = Object.entries(headerCounts)
                .filter(([, count]) => count > 1)
                .map(([h]) => h)
            if (duplicates.length > 0) {
                uploadStatus.value = {
                    message: `Duplicate column header(s) — "${duplicates.join('", "')}". Each column must appear only once.`,
                    type: 'error'
                }
                return
            }

            // --- GUARD 2: Column allowlist ---
            const unknownCols = headers.filter(h => !KNOWN_COLUMNS.has(h))
            if (unknownCols.length > 0) {
                uploadStatus.value = {
                    message: `Unrecognized column(s) — "${unknownCols.join('", "')}". Please use the official template.`,
                    type: 'error'
                }
                return
            }

            // --- GUARD 3: Required columns (Title + Author) — warn but don't block ---
            const missingRequiredCols: string[] = []
            if (!headers.includes('Title')) missingRequiredCols.push('Title')
            if (!headers.includes('Author') && !headers.includes('Authors')) {
                missingRequiredCols.push('Author (or Authors)')
            }

            // --- GUARD 4: At least one row ---
            const rows = results.data as Record<string, string>[]
            if (rows.length === 0) {
                uploadStatus.value = { message: 'CSV has no data rows.', type: 'error' }
                return
            }

            // --- CHECK: Missing optional columns (warn) ---
            const presentHeaders = new Set(headers)
            const missingOpt = TEMPLATE_COLUMNS.filter(c =>
                c !== 'Title' && c !== 'Author' && !presentHeaders.has(c)
            )

            // Merge required and optional missing columns for the warning banner
            missingOptionalCols.value = [...missingRequiredCols, ...missingOpt]

            // ALL good — store and show preview
            previewHeaders.value = headers
            previewRows.value = rows.map(r => ({ ...r })) // deep copy for editing
            
            // Perform async duplicate check
            uploadStatus.value = { message: 'Checking database for duplicates...', type: '' }
            api.post('/research/preview-csv', { rows: previewRows.value })
                .then(response => {
                    if (response.data.status === 'success') {
                        csvDuplicates.value = response.data.preview || []
                    }
                })
                .catch(err => {
                    console.error("Duplicate check failed:", err)
                    csvDuplicates.value = new Array(previewRows.value.length).fill(null)
                })
                .finally(() => {
                    showPreview.value = true
                    previewPage.value = 0
                    uploadStatus.value = { message: '', type: '' }
                })
        },
        error: (error) => {
            console.error(error)
            uploadStatus.value = { message: `CSV Parse Error: ${error.message}`, type: 'error' }
        }
    })
}

// 4. Close preview
const cancelPreview = () => {
    showPreview.value = false
    previewRows.value = []
    csvDuplicates.value = []
    previewHeaders.value = []
    missingOptionalCols.value = []
    editingCell.value = null
}

// 5. Delete a row from preview
const deletePreviewRow = (globalIndex: number) => {
    previewRows.value.splice(globalIndex, 1)
    csvDuplicates.value.splice(globalIndex, 1)
    if (previewRows.value.length === 0) {
        cancelPreview()
        uploadStatus.value = { message: 'All rows removed. Nothing to import.', type: 'error' }
    }
    // Adjust page if last item on page was removed
    if (paginatedPreviewRows.value.length === 0 && previewPage.value > 0) {
        previewPage.value--
    }
}

// 6. Edit cell
const startEdit = (rowIndex: number, col: string) => {
    editingCell.value = { row: rowIndex, col }
}

const finishEdit = () => {
    editingCell.value = null
}

// 7. Confirm & Upload
const confirmUpload = async () => {
    if (previewRows.value.length === 0) return

    showPreview.value = false
    isUploading.value = true
    editingCell.value = null

    const total = previewRows.value.length
    let success = 0
    let skipped = 0
    let errors = 0

    for (let i = 0; i < total; i++) {
        const row = previewRows.value[i]
        uploadStatus.value = { message: `Importing ${i + 1}/${total}...`, type: '' }

        try {
            const response = await api.post('/research/import-single', row)
            if (response.data.status === 'success') {
                success++
            } else if (response.data.status === 'skipped') {
                skipped++
            } else {
                errors++
            }
        } catch (error) {
            console.error("Row import failed", row, error)
            errors++
        }
    }

    isUploading.value = false
    uploadStatus.value = {
        message: `Completed! Imported: ${success}, Skipped (Dup): ${skipped}, Errors: ${errors}.`,
        type: 'success'
    }

    selectedFile.value = null
    previewRows.value = []
    csvDuplicates.value = []
    previewHeaders.value = []
    missingOptionalCols.value = []
    if(fileInput.value) fileInput.value.value = ''
    apiCache.invalidate('research')
    emit('upload-success')
}


// ═══════════════════════════════════════════════════════════════
// SECTION 2 — BULK PDF UPLOAD
// ═══════════════════════════════════════════════════════════════
const pdfInput = ref<HTMLInputElement | null>(null)
const selectedPdfs = ref<File[]>([])
const isPdfUploading = ref(false)
const pdfStatus = ref<{ message: string, type: 'success' | 'error' | '', details?: string[] }>({ message: '', type: '', details: [] })
const showPdfConfirm = ref(false)

const handlePdfChange = (event: Event | DragEvent) => {
    let files: FileList | null = null;

    if (event instanceof DragEvent && event.dataTransfer) {
        files = event.dataTransfer.files;
    } else {
        const target = event.target as HTMLInputElement;
        files = target.files;
    }

    if (files && files.length) {
        if (files.length > 10) {
            showToast("You can only upload a maximum of 10 files at a time.", "warning")
            if (event.target instanceof HTMLInputElement) event.target.value = ''
            selectedPdfs.value = []
            return
        }
        selectedPdfs.value = Array.from(files)
        pdfStatus.value = { message: '', type: '', details: [] }
        showPdfConfirm.value = false
    }
}

// Parsed title info for confirmation display
const pdfFileInfo = computed(() => {
    return selectedPdfs.value.map(file => {
        const basename = file.name.replace(/\.pdf$/i, '')
        let title = basename
        let hint = ''

        // Parse bracket hints
        const m = basename.match(/^(.+?)\s*\[([^\]]+)\]\s*$/)
        if (m) {
            title = m[1]?.trim() ?? ''
            hint = m[2]?.trim() ?? ''
        }
        return { name: file.name, size: (file.size / 1024).toFixed(1) + ' KB', title, hint }
    })
})

// Store matches for display
const pdfPreviewMatches = ref<any[]>([])

// Show confirmation before uploading
const showPdfConfirmation = async () => {
    if (!selectedPdfs.value.length) return
    isPdfUploading.value = true
    pdfStatus.value = { message: '⏳ Checking database for matches...', type: '' }

    try {
        const payload = {
            files: pdfFileInfo.value.map(f => ({
                filename: f.name,
                isbnHint: f.hint.match(/isbn|issn/i) ? f.hint.replace(/isbn|issn/i, '').replace(/[:-]/g, '').trim() : '',
                editionHint: !f.hint.match(/isbn|issn/i) ? f.hint : ''
            }))
        }
        
        const response = await api.post('/research/preview-bulk-pdfs', payload)
        if (response.data.status === 'success') {
            // merge matches with file size info
            pdfPreviewMatches.value = response.data.preview.map((p: any) => {
                const fInfo = pdfFileInfo.value.find(f => f.name === p.filename)
                return { ...p, size: fInfo?.size || '', title: fInfo?.title || '', hint: fInfo?.hint || '' }
            })
            showPdfConfirm.value = true
            pdfStatus.value = { message: '', type: '', details: [] }
        } else {
             pdfStatus.value = { message: `Error checking matches: ${response.data.message}`, type: 'error' }
        }
    } catch (err: any) {
         console.error(err)
         pdfStatus.value = { message: `Match Check Failed: ${err.message || 'Server error'}`, type: 'error' }
    } finally {
         isPdfUploading.value = false
    }
}

const cancelPdfConfirm = () => {
    showPdfConfirm.value = false
    pdfPreviewMatches.value = []
}

const removePdfFile = (index: number) => {
    selectedPdfs.value.splice(index, 1)
    pdfPreviewMatches.value.splice(index, 1)
    if (selectedPdfs.value.length === 0) {
        showPdfConfirm.value = false
        pdfPreviewMatches.value = []
    }
}

const confirmPdfUpload = async () => {
    if (!selectedPdfs.value.length) return

    showPdfConfirm.value = false
    isPdfUploading.value = true
    pdfStatus.value = { message: '⏳ Uploading and linking...', type: '' }

    const formData = new FormData()
    selectedPdfs.value.forEach((file) => {
        formData.append('pdf_files[]', file)
    })

    try {
        const response = await api.post('/research/bulk-upload-pdfs', formData)

        let result = response.data
        if (typeof result === 'string') {
            try { result = JSON.parse(result) } catch (e) { console.error("Failed to parse JSON response", e) }
        }

        if (result.status === 'success' || response.status === 200) {
             let msg = "Upload Complete"
             if (result.message) {
                 msg = result.message
             } else if (result.matched !== undefined) {
                 msg = `Done! Linked: ${result.matched}, Skipped: ${result.skipped}`
             }
             pdfStatus.value = {
                message: msg,
                type: 'success',
                details: result.details || []
            }
            showToast(msg, 'success')
            selectedPdfs.value = []
            if(pdfInput.value) pdfInput.value.value = ''
            apiCache.invalidate('research')
        } else {
            pdfStatus.value = { message: `Error: ${result.message || 'Unknown Error'}`, type: 'error' }
            showToast("Upload Failed", 'error')
        }

    } catch (error: any) {
        console.error(error)
        const msg = error.response?.data?.message || 'Server Connection Failed'
        pdfStatus.value = { message: msg, type: 'error' }
        showToast(msg, 'error')
    } finally {
        isPdfUploading.value = false
    }
}
</script>

<template>
  <div class="space-y-8 animate-fade-in">

    <!-- Header -->
    <div class="flex items-center justify-between">
         <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
            <span class="text-3xl">📂</span> Data Management
         </h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        <!-- ═══ CSV UPLOAD CARD ═══ -->
        <BaseCard class="space-y-6">
            <div class="border-b border-gray-100 pb-4">
                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <span class="bg-emerald-100 text-emerald-600 p-1.5 rounded-lg text-lg">📊</span>
                    Import CSV Data
                </h2>
                <p class="text-sm text-gray-500 mt-1">Bulk upload research records using a CSV file.</p>
            </div>

            <!-- Dropzone -->
            <div
                class="border-2 border-dashed border-gray-200 rounded-xl p-8 flex flex-col items-center justify-center bg-gray-50/50 hover:bg-emerald-50/50 hover:border-emerald-300 transition-all group relative cursor-pointer"
                @click="fileInput?.click()"
                @dragover.prevent
                @drop.prevent="handleFileChange"
            >
                <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <span class="text-3xl">📄</span>
                </div>

                <input
                    type="file"
                    ref="fileInput"
                    accept=".csv"
                    @change="handleFileChange"
                    class="hidden"
                    id="csvUpload"
                />

                <div v-if="!selectedFile">
                    <p class="font-bold text-gray-700 text-center">Click to upload or drag and drop</p>
                    <p class="text-xs text-gray-400 text-center mt-1">CSV files only (max 5MB)</p>
                </div>
                <div v-else class="text-center">
                    <p class="font-bold text-emerald-700 break-all">{{ selectedFile.name }}</p>
                    <p class="text-xs text-emerald-500 mt-1">Ready to preview</p>
                    <button @click.stop="selectedFile = null; showPreview = false; if(fileInput) fileInput.value = ''" class="mt-2 text-xs text-red-400 hover:text-red-600 font-bold hover:underline">Remove</button>
                </div>
            </div>

            <!-- Template Download -->
            <div class="bg-blue-50 px-4 py-3 rounded-lg border border-blue-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-blue-500">ℹ️</span>
                    <span class="text-xs text-blue-800 font-medium">Need the correct format?</span>
                </div>
                <button
                    @click="downloadTemplate"
                    class="text-xs font-bold text-blue-600 hover:text-blue-800 hover:underline whitespace-nowrap"
                >
                    Download Template
                </button>
            </div>

            <!-- Status -->
            <div class="flex flex-col gap-3">
                 <div v-if="uploadStatus.message && !showPreview" :class="[
                    'text-xs font-bold p-3 rounded-lg flex items-center gap-2',
                    uploadStatus.type === 'error' ? 'bg-red-50 text-red-600 border border-red-100' :
                    uploadStatus.type === 'warning' ? 'bg-yellow-50 text-yellow-700 border border-yellow-100' :
                    uploadStatus.type === 'success' ? 'bg-green-50 text-green-700 border border-green-100' :
                    'bg-gray-50 text-gray-600 border border-gray-100'
                 ]">
                    <span>{{ uploadStatus.type === 'error' ? '❌' : uploadStatus.type === 'warning' ? '⚠️' : uploadStatus.type === 'success' ? '✅' : '⏳' }}</span>
                    {{ uploadStatus.message }}
                 </div>

                 <!-- Parse & Preview button (Step 1) -->
                 <BaseButton
                    v-if="!showPreview"
                    @click="parseCsv"
                    :disabled="!selectedFile || isUploading"
                    variant="primary"
                    class="w-full justify-center"
                >
                    <span v-if="isUploading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full mr-2"></span>
                    {{ isUploading ? 'Processing...' : 'Preview CSV Data' }}
                 </BaseButton>
            </div>
        </BaseCard>

        <!-- ═══ PDF BULK UPLOAD CARD ═══ -->
        <BaseCard class="space-y-6">
            <div class="border-b border-gray-100 pb-4">
                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <span class="bg-blue-100 text-blue-600 p-1.5 rounded-lg text-lg">📎</span>
                    Bulk PDF Upload
                </h2>
                <p class="text-sm text-gray-500 mt-1">Auto-link PDFs to existing records by filename.</p>
            </div>

            <!-- Dropzone -->
            <div
                class="border-2 border-dashed border-gray-200 rounded-xl p-8 flex flex-col items-center justify-center bg-gray-50/50 hover:bg-blue-50/50 hover:border-blue-300 transition-all group relative cursor-pointer"
                @click="pdfInput?.click()"
                @dragover.prevent
                @drop.prevent="handlePdfChange"
            >
                <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <span class="text-3xl">📚</span>
                </div>

                <input
                    type="file"
                    ref="pdfInput"
                    accept=".pdf"
                    multiple
                    @change="handlePdfChange"
                    class="hidden"
                    id="pdfUpload"
                />

                <div v-if="!selectedPdfs.length">
                    <p class="font-bold text-gray-700 text-center">Click to upload or drag and drop</p>
                    <p class="text-xs text-gray-400 text-center mt-1">Multiple PDFs allowed (Max 10)</p>
                </div>
                <div v-else class="text-center w-full">
                    <p class="font-bold text-blue-700">{{ selectedPdfs.length }} files selected</p>
                    <div class="mt-2 max-h-20 overflow-y-auto text-xs text-gray-500 space-y-1 custom-scrollbar px-4">
                        <div v-for="file in selectedPdfs" :key="file.name" class="truncate">{{ file.name }}</div>
                    </div>
                    <button @click.stop="selectedPdfs = []; showPdfConfirm = false; if(pdfInput) pdfInput.value = ''" class="mt-3 text-xs text-red-400 hover:text-red-600 font-bold hover:underline">Clear All</button>
                </div>
            </div>

            <div class="bg-gray-50 px-4 py-3 rounded-lg border border-gray-100 space-y-2">
                <p class="text-xs text-gray-700 font-semibold">📌 How PDF matching works:</p>
                <ul class="text-xs text-gray-500 leading-relaxed list-disc pl-4 space-y-1">
                    <li>The PDF filename (without extension) must <strong>match the Title</strong> of an existing record (case-insensitive).</li>
                    <li>If multiple records share the same title, add an <strong>ISBN/ISSN or edition hint</strong> in square brackets to target a specific edition:</li>
                </ul>
                <div class="bg-white border border-gray-200 rounded p-2 text-[10px] font-mono text-gray-600 space-y-1">
                    <div>📄 <span class="text-blue-600">Golden Roots [ISSN 1656-5444].pdf</span></div>
                    <div>📄 <span class="text-blue-600">Golden Roots [Vol. 1].pdf</span></div>
                </div>
                <p class="text-xs text-gray-400">Without hints, the system links to the first matching record that has no file yet.</p>
            </div>

            <!-- Actions -->
             <div class="flex flex-col gap-3">
                 <div v-if="pdfStatus.message" :class="`text-xs font-bold p-3 rounded-lg ${pdfStatus.type === 'error' ? 'bg-red-50 text-red-600 border border-red-100' : 'bg-green-50 text-green-700 border border-green-100'}`">
                    <div class="flex items-center gap-2 mb-1">
                        <span>{{ pdfStatus.type === 'error' ? '❌' : '✅' }}</span>
                        <span>{{ pdfStatus.message }}</span>
                    </div>
                    <ul v-if="pdfStatus.details && pdfStatus.details.length" class="mt-2 pl-4 list-disc text-[10px] max-h-24 overflow-y-auto custom-scrollbar opacity-80">
                        <li v-for="(detail, i) in pdfStatus.details" :key="i">{{ detail }}</li>
                    </ul>
                 </div>

                 <BaseButton
                    @click="showPdfConfirmation"
                    :disabled="!selectedPdfs.length || isPdfUploading"
                    variant="secondary"
                    class="w-full justify-center"
                >
                    <span v-if="isPdfUploading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full mr-2"></span>
                    {{ isPdfUploading ? 'Linking Files...' : 'Review & Upload PDFs' }}
                 </BaseButton>
            </div>
        </BaseCard>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- CSV PREVIEW / EDIT MODAL (Full-screen overlay) -->
    <!-- ═══════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showPreview" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="cancelPreview">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[90vh] flex flex-col overflow-hidden animate-modal-in">

          <!-- Modal Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
            <div>
              <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                  📋 Review CSV Data
              </h2>
              <p class="text-xs text-gray-500 mt-1">
                {{ previewRows.length }} row{{ previewRows.length !== 1 ? 's' : '' }} found · Click any cell to edit · Delete rows with the ✕ button
              </p>
            </div>
            <button @click="cancelPreview" class="text-gray-400 hover:text-gray-600 text-xl font-bold p-1">✕</button>
          </div>

          <!-- Missing Columns Warning -->
          <div v-if="missingOptionalCols.length > 0" class="mx-6 mt-4 bg-yellow-50 border border-yellow-200 rounded-lg px-4 py-3 shrink-0">
            <div class="flex items-start gap-2">
              <span class="text-yellow-500 text-base mt-0.5">⚠️</span>
              <div>
                <p class="text-xs font-bold text-yellow-800">Missing recommended column{{ missingOptionalCols.length > 1 ? 's' : '' }}:
                    <span class="font-normal">{{ missingOptionalCols.join(', ') }}</span>
                </p>
                <p class="text-[11px] text-yellow-600 mt-1">
                    <span v-if="missingOptionalCols.includes('Title') || missingOptionalCols.includes('Author (or Authors)')" class="font-bold text-red-600 mr-2">Note: Rows missing Title or Author will be skipped during import!</span>
                    These fields will be empty for all imported records. You can still proceed — or re-upload with the complete template.
                </p>
              </div>
            </div>
          </div>

          <!-- Scrollable Table -->
          <div class="flex-1 overflow-auto px-6 py-4 custom-scrollbar">
            <table class="w-full text-xs border-collapse min-w-[800px]">
              <thead>
                <tr>
                  <th
                    v-for="col in previewHeaders"
                    :key="col"
                    class="sticky top-0 bg-gray-50 border border-gray-200 px-3 py-2 text-left text-gray-700 font-semibold whitespace-nowrap z-10"
                  >{{ col }}</th>
                  <th class="sticky top-0 bg-gray-50 border border-gray-200 px-3 py-2 text-center text-gray-500 font-semibold min-w-[120px] z-10">Match Status</th>
                  <th class="sticky top-0 bg-gray-50 border border-gray-200 px-3 py-2 text-center text-gray-500 font-semibold w-8 z-10"></th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(row, i) in paginatedPreviewRows"
                  :key="paginatedStartIndex + i"
                  class="transition-colors"
                  :class="{
                     'bg-red-50 hover:bg-red-100/50': csvDuplicates[paginatedStartIndex + i]?.status?.startsWith('duplicate'),
                     'hover:bg-blue-50/30': !csvDuplicates[paginatedStartIndex + i]?.status?.startsWith('duplicate')
                  }"
                >
                  <td class="border border-gray-200 px-3 py-1.5 text-gray-400 text-center">{{ paginatedStartIndex + i + 1 }}</td>
                  <td
                    v-for="col in previewHeaders"
                    :key="col"
                    class="border border-gray-200 px-1 py-0.5 cursor-text group relative"
                    @dblclick="startEdit(paginatedStartIndex + i, col)"
                  >
                    <!-- Editing state -->
                    <input
                      v-if="editingCell?.row === paginatedStartIndex + i && editingCell?.col === col"
                      v-model="previewRows[paginatedStartIndex + i]![col]"
                      class="w-full px-2 py-1 text-xs border border-blue-400 rounded focus:outline-none focus:ring-1 focus:ring-blue-400 bg-blue-50"
                      @blur="finishEdit"
                      @keydown.enter="finishEdit"
                      @keydown.escape="finishEdit"
                      @vue:mounted="($event: any) => $event.el.focus()"
                    />
                    <!-- Display state -->
                    <div v-else class="px-2 py-1 min-h-[24px] flex items-center" :class="{'text-red-800 font-medium': col === 'Title' && csvDuplicates[paginatedStartIndex + i]?.status?.startsWith('duplicate')}">
                      <span v-if="row[col]" class="text-gray-800" :class="{'line-through opacity-70': csvDuplicates[paginatedStartIndex + i]?.status?.startsWith('duplicate')}">{{ row[col] }}</span>
                      <span v-else class="text-gray-300 italic">empty</span>
                      <span class="ml-auto text-gray-300 opacity-0 group-hover:opacity-100 transition-opacity text-[10px]">✏️</span>
                    </div>
                  </td>
                  <td class="border border-gray-200 px-2 py-1.5 text-xs text-center border-l-2" :class="{'border-l-red-200': csvDuplicates[paginatedStartIndex + i]?.status?.startsWith('duplicate')}">
                    <div v-if="csvDuplicates[paginatedStartIndex + i]?.status === 'duplicate_with_pdf'" class="text-red-700 font-bold flex flex-col gap-0.5">
                        <span title="This record will be skipped because it already exists in the database.">⚠️ Duplicate</span>
                        <span class="text-[9px] font-normal bg-red-100 px-1 py-0.5 rounded text-red-800 inline-block">Already has PDF</span>
                    </div>
                    <div v-else-if="csvDuplicates[paginatedStartIndex + i]?.status === 'duplicate_no_pdf'" class="text-yellow-700 font-bold flex flex-col gap-0.5">
                        <span title="This record will be skipped because a record with this Title/Author exists.">⚠️ Duplicate</span>
                        <span class="text-[9px] font-normal opacity-80">(No PDF linked)</span>
                    </div>
                    <span v-else-if="csvDuplicates[paginatedStartIndex + i]?.status === 'new'" class="text-green-600 font-medium whitespace-nowrap">✨ New Record</span>
                    <span v-else class="text-gray-400 animate-pulse">Checking...</span>
                  </td>
                  <td class="border border-gray-200 px-2 py-1.5 text-center">
                    <button
                      @click="deletePreviewRow(paginatedStartIndex + i)"
                      class="text-gray-300 hover:text-red-500 transition-colors font-bold text-sm"
                      title="Remove this row"
                    >✕</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="totalPreviewPages > 1" class="flex items-center justify-center gap-3 px-6 py-2 border-t border-gray-100 shrink-0">
            <button
              @click="previewPage = Math.max(0, previewPage - 1)"
              :disabled="previewPage === 0"
              class="text-xs font-bold text-gray-500 hover:text-gray-800 disabled:opacity-30 disabled:cursor-not-allowed"
            >← Prev</button>
            <span class="text-xs text-gray-400">Page {{ previewPage + 1 }} / {{ totalPreviewPages }}</span>
            <button
              @click="previewPage = Math.min(totalPreviewPages - 1, previewPage + 1)"
              :disabled="previewPage >= totalPreviewPages - 1"
              class="text-xs font-bold text-gray-500 hover:text-gray-800 disabled:opacity-30 disabled:cursor-not-allowed"
            >Next →</button>
          </div>

          <!-- Modal Footer -->
          <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50/50 shrink-0">
            <p class="text-xs text-gray-400">
              {{ previewRows.length }} record{{ previewRows.length !== 1 ? 's' : '' }} will be imported
            </p>
            <div class="flex items-center gap-3">
              <BaseButton variant="ghost" size="sm" @click="cancelPreview">Cancel</BaseButton>
              <BaseButton variant="primary" size="sm" @click="confirmUpload" :disabled="previewRows.length === 0">
                ✅ Confirm & Import {{ previewRows.length }} Record{{ previewRows.length !== 1 ? 's' : '' }}
              </BaseButton>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- PDF CONFIRMATION MODAL                         -->
    <!-- ═══════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showPdfConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="cancelPdfConfirm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[80vh] flex flex-col overflow-hidden animate-modal-in">

          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
            <div>
              <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">📎 Confirm PDF Upload</h2>
              <p class="text-xs text-gray-500 mt-1">Review how each file will be matched before uploading.</p>
            </div>
            <button @click="cancelPdfConfirm" class="text-gray-400 hover:text-gray-600 text-xl font-bold p-1">✕</button>
          </div>

          <!-- File List -->
          <div class="flex-1 overflow-y-auto px-6 py-4 space-y-3 custom-scrollbar">
            <div
              v-for="(match, idx) in pdfPreviewMatches"
              :key="match.filename"
              class="flex items-start gap-3 rounded-lg p-3 border group"
              :class="{
                'bg-gray-50 border-gray-100': match.status === 'linked',
                'bg-red-50 border-red-100': match.status === 'no_match',
                'bg-yellow-50 border-yellow-100': match.status === 'exists'
              }"
            >
              <div class="text-blue-500 text-lg mt-0.5 shrink-0">📄</div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800 truncate">{{ match.filename }} <span class="text-xs text-gray-400 font-normal ml-2">({{ match.size }})</span></p>

                <!-- Match Status UI -->
                <div class="mt-2 text-xs">
                    <div v-if="match.status === 'linked'" class="text-green-700 flex flex-col pt-1 border-t border-green-200/50">
                        <span class="font-medium mb-1">✅ Matches Record:</span>
                        <span class="truncate font-semibold text-gray-800">{{ match.record?.title }}</span>
                        <span class="text-[10px] text-gray-500">By {{ match.record?.author }}</span>
                    </div>

                    <div v-else-if="match.status === 'no_match'" class="text-red-600 flex items-center gap-1.5 font-medium">
                        <span>❌ No Matching Record Found</span>
                    </div>

                    <div v-else-if="match.status === 'exists'" class="text-yellow-700 flex flex-col pt-1 border-t border-yellow-200/50">
                        <span class="font-bold flex items-center gap-1">⚠️ Record Already Has File <span class="text-[10px] font-normal italic">(Will skip)</span></span>
                        <span class="truncate font-medium text-gray-700 mt-0.5">{{ match.record?.title }}</span>
                    </div>
                </div>

                <p v-if="match.hint" class="text-[10px] text-blue-600 mt-1">
                  🔍 Included hint: <span class="font-semibold">{{ match.hint }}</span>
                </p>
              </div>
              <button
                @click="removePdfFile(idx)"
                class="text-gray-300 hover:text-red-500 transition-colors font-bold text-sm opacity-0 group-hover:opacity-100 shrink-0"
                title="Remove this file"
              >✕</button>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50/50 shrink-0">
            <p class="text-xs text-gray-400">{{ selectedPdfs.length }} file{{ selectedPdfs.length !== 1 ? 's' : '' }} ready</p>
            <div class="flex items-center gap-3">
              <BaseButton variant="ghost" size="sm" @click="cancelPdfConfirm">Cancel</BaseButton>
              <BaseButton variant="secondary" size="sm" @click="confirmPdfUpload" :disabled="selectedPdfs.length === 0">
                📎 Upload & Link {{ selectedPdfs.length }} PDF{{ selectedPdfs.length !== 1 ? 's' : '' }}
              </BaseButton>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

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

.animate-modal-in {
  animation: modalIn 0.2s ease-out;
}
@keyframes modalIn {
  from { opacity: 0; transform: scale(0.96) translateY(10px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}

/* Custom Scrollbar */
.custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
</style>
