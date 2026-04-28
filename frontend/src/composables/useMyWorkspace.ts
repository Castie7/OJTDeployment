import { ref, reactive, watch } from 'vue'
import { researchService } from '../services'
import { useToast } from './useToast'
import { useErrorHandler } from './useErrorHandler'
import type { Research, SimilarTitleMatch } from '../types'


export function useMyWorkspace() {

  const activeTab = ref<'pending' | 'approved' | 'rejected' | 'archived'>('pending')
  const isModalOpen = ref(false)
  const isEasyResubmitModalOpen = ref(false)
  const isSubmitting = ref(false)
  const isLoading = ref(false)
  const myResearches = ref<Research[]>([])
  const similarTitleMatches = ref<SimilarTitleMatch[]>([])
  const isCheckingSimilarTitles = ref(false)
  const { showToast } = useToast()
  const { handleError } = useErrorHandler()
  let similarTitleTimer: ReturnType<typeof setTimeout> | null = null
  let similarTitleRequestId = 0

  // VALIDATION STATE
  const errors = reactive({
    title: '',
    author: '',
    knowledge_type: '',
    publication_date: '',
    start_date: '',
    deadline_date: '',
    link: ''
  })

  // FORM STATE
  const form = reactive({
    id: null as number | null,
    title: '',
    author: '',
    crop_variation: '',
    start_date: '',
    deadline_date: '',
    knowledge_type: [] as string[],
    publication_date: '',
    edition: '',
    publisher: '',
    physical_description: '',
    isbn_issn: '',
    subjects: '',
    shelf_location: '',
    item_condition: 'Good',
    link: '',
    pdf_file: null as File | null,
    resubmit_remarks: ''
  })

  const clearSimilarTitleLookup = () => {
    if (similarTitleTimer) {
      clearTimeout(similarTitleTimer)
      similarTitleTimer = null
    }
    similarTitleRequestId += 1
    similarTitleMatches.value = []
    isCheckingSimilarTitles.value = false
  }

  // --- ACTIONS ---

  // VALIDATION FUNCTION
  const validateForm = (): boolean => {
    // Reset errors
    Object.keys(errors).forEach(key => (errors as any)[key] = '')
    let isValid = true

    // 1. Title
    if (!form.title.trim()) {
      errors.title = 'Title is required.'
      isValid = false
    } else if (form.title.length < 3) {
      errors.title = 'Title must be at least 3 characters.'
      isValid = false
    }

    // 2. Author
    if (!form.author.trim()) {
      errors.author = 'Author is required.'
      isValid = false
    } else if (form.author.length < 2) {
      errors.author = 'Author must be at least 2 characters.'
      isValid = false
    }

    // 3. Knowledge Type
    if (form.knowledge_type.length === 0) {
      errors.knowledge_type = 'Please select at least one type.'
      isValid = false
    }

    // 4. Link (URL)
    if (form.link && form.link.trim() !== '') {
      try {
        new URL(form.link)
      } catch (_) {
        errors.link = 'Please enter a valid URL (e.g., https://example.com).'
        isValid = false
      }
    }

    return isValid
  }

  // 1. FETCH DATA
  const fetchMyResearches = async () => {
    isLoading.value = true
    try {
      myResearches.value = activeTab.value === 'archived'
        ? await researchService.getMyArchived()
        : await researchService.getMySubmissions()

    } catch (e) {
      handleError(e, 'Failed to load workspace data')
    } finally {
      isLoading.value = false;
    }
  }

  // Watch for tab changes to reload data
  watch(activeTab, () => {
    fetchMyResearches();
  });

  watch(
    () => [form.title, form.id] as const,
    ([title, id]) => {
      if (similarTitleTimer) {
        clearTimeout(similarTitleTimer)
        similarTitleTimer = null
      }

      const trimmedTitle = title.trim()
      const requestId = ++similarTitleRequestId
      if (trimmedTitle.length < 4) {
        similarTitleMatches.value = []
        isCheckingSimilarTitles.value = false
        return
      }

      similarTitleTimer = setTimeout(async () => {
        isCheckingSimilarTitles.value = true
        try {
          const matches = await researchService.findSimilarTitles(trimmedTitle, id)
          if (requestId === similarTitleRequestId) {
            similarTitleMatches.value = matches
          }
        } catch {
          if (requestId === similarTitleRequestId) {
            similarTitleMatches.value = []
          }
        } finally {
          if (requestId === similarTitleRequestId) {
            isCheckingSimilarTitles.value = false
          }
        }
      }, 400)
    }
  )

  // Helper function to convert date to YYYY-MM-DD format for date inputs
  const toDateInputFormat = (dateStr?: any) => {
    if (!dateStr) return ''
    let dateVal = dateStr
    // Handle DateTime objects from backend
    if (typeof dateStr === 'object' && dateStr.date) dateVal = dateStr.date
    try {
      const d = new Date(dateVal)
      if (isNaN(d.getTime())) return ''
      // Format to YYYY-MM-DD
      const year = d.getFullYear()
      const month = String(d.getMonth() + 1).padStart(2, '0')
      const day = String(d.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    } catch {
      return ''
    }
  }

  // 2. OPEN FOR NEW SUBMISSION
  const openSubmitModal = () => {
    Object.keys(errors).forEach(key => (errors as any)[key] = '') // Clear errors
    clearSimilarTitleLookup()
    Object.assign(form, {
      id: null,
      title: '', author: '', crop_variation: '',
      start_date: '', deadline_date: '',
      knowledge_type: [],
      publication_date: '', edition: '', publisher: '',
      physical_description: '', isbn_issn: '', subjects: '',
      shelf_location: '', item_condition: 'Good', link: '',
      pdf_file: null, resubmit_remarks: ''
    })
    isModalOpen.value = true
  }

  // 3. OPEN FOR EDITING
  const openEditModal = (item: Research) => {
    Object.keys(errors).forEach(key => (errors as any)[key] = '') // Clear errors
    clearSimilarTitleLookup()
    Object.assign(form, {
      id: item.id,
      title: item.title,
      author: item.author,
      crop_variation: item.crop_variation || '',
      start_date: toDateInputFormat(item.start_date),
      deadline_date: toDateInputFormat(item.deadline_date),
      knowledge_type: item.knowledge_type ? item.knowledge_type.split(',').map(s => s.trim()) : [],
      publication_date: toDateInputFormat(item.publication_date),
      edition: item.edition || '',
      publisher: item.publisher || '',
      physical_description: item.physical_description || '',
      isbn_issn: item.isbn_issn || '',
      subjects: item.subjects || '',
      shelf_location: item.shelf_location || '',
      item_condition: item.item_condition || 'Good',
      link: item.link || '',
      pdf_file: null,
      resubmit_remarks: ''
    })
    isModalOpen.value = true
  }

  const openEasyResubmitModal = (item: Research) => {
    openEditModal(item)
    isModalOpen.value = false // hide the big modal
    isEasyResubmitModalOpen.value = true // show the small modal
  }

  const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement
    const file = target.files?.[0]
    if (!file) { form.pdf_file = null; return }

    const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png']
    const fileExtension = file.name.split('.').pop()?.toLowerCase() || ''

    if (!allowedExtensions.includes(fileExtension)) {
      showToast("Invalid File! Please upload a PDF or an Image.", "error")
      target.value = ''
      form.pdf_file = null
      return
    }
    form.pdf_file = file
  }

  const submitResearch = async (): Promise<boolean> => {
    // 🛑 FRONTEND VALIDATION
    if (!validateForm()) {
      // Small delay to ensure modal checks update visually if needed
      return false
    }

    isSubmitting.value = true
    const formData = new FormData()

    // Append Fields
    formData.append('title', form.title)
    formData.append('author', form.author)
    formData.append('crop_variation', form.crop_variation)
    formData.append('start_date', form.start_date)
    formData.append('deadline_date', form.deadline_date)

    // Join array to string for backend
    const kType = Array.isArray(form.knowledge_type) ? form.knowledge_type.join(', ') : form.knowledge_type;
    formData.append('knowledge_type', kType)
    formData.append('publication_date', form.publication_date)
    formData.append('edition', form.edition)
    formData.append('publisher', form.publisher)
    formData.append('physical_description', form.physical_description)
    formData.append('isbn_issn', form.isbn_issn)
    formData.append('subjects', form.subjects)
    formData.append('shelf_location', form.shelf_location)
    formData.append('item_condition', form.item_condition)
    formData.append('link', form.link)
    
    // Pass resubmission remarks if provided for a rejected item edit
    if (form.resubmit_remarks && form.resubmit_remarks.trim() !== '') {
      formData.append('resubmit_remarks', form.resubmit_remarks.trim())
    }

    if (form.pdf_file) formData.append('pdf_file', form.pdf_file)

    try {
      if (form.id) {
        await researchService.update(form.id, formData)
      } else {
        await researchService.create(formData)
      }

      showToast(form.id ? "Success! Research Updated." : "Success! Research Submitted.", "success")
      isModalOpen.value = false
      isEasyResubmitModalOpen.value = false
      return true // Indicate success

    } catch (error: any) {
      if (import.meta.env.DEV) console.error(error)

      // ✅ Improved Error Handling
      let msg = "Action Failed";
      if (error.response?.data?.messages) {
        // Map backend errors to frontend error state if keys match
        const messages = error.response.data.messages;
        Object.keys(messages).forEach(key => {
          if (key in errors) (errors as any)[key] = messages[key];
        });
        msg = Object.values(messages).join('\n');
      } else if (error.response?.data?.message) {
        msg = error.response.data.message;
      }

      showToast("Error: " + msg, "error")
      return false // Indicate failure
    } finally {
      isSubmitting.value = false
    }
  }

  return {
    activeTab,
    myResearches,
    isLoading,
    isModalOpen,
    isEasyResubmitModalOpen,
    isSubmitting,
    similarTitleMatches,
    isCheckingSimilarTitles,
    form,
    errors,
    fetchMyResearches,
    openSubmitModal,
    openEditModal,
    openEasyResubmitModal,
    submitResearch,
    handleFileChange
  }
}
