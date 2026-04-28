import { ref } from 'vue'
import api from '../services/api' // using the standard api instance

export function usePdfViewer() {
  const pdfBlobUrl = ref<string | null>(null)
  const isPdfLoading = ref(false)
  const pdfError = ref<string | null>(null)

  const loadPdf = async (researchId: number) => {
    isPdfLoading.value = true
    pdfError.value = null
    
    // Revoke previous URL to free memory
    if (pdfBlobUrl.value) {
      const urlToRevoke = pdfBlobUrl.value.split('#')[0]
      if (urlToRevoke) {
        URL.revokeObjectURL(urlToRevoke)
      }
      pdfBlobUrl.value = null
    }

    try {
      const response = await api.get(`research/view-pdf/${researchId}?xhr=1`, {
        responseType: 'blob',
        // bypass browser cache for secure documents if needed, but the server handles Cache-Control
      })
      
      const blob = new Blob([response.data], { type: 'application/pdf' })
      // Adding specific hash parameters to disable the built-in PDF viewer's toolbar, 
      // preventing casual downloads and print actions by users.
      const url = URL.createObjectURL(blob)
      pdfBlobUrl.value = url + '#toolbar=0&navpanes=0&scrollbar=0&statusbar=0&messages=0'
    } catch (e) {
      pdfError.value = 'Failed to load secure document. It may be unavailable.'
      console.error('PDF Secure Load Error', e)
    } finally {
      isPdfLoading.value = false
    }
  }

  const clearPdf = () => {
    if (pdfBlobUrl.value) {
      const urlToRevoke = pdfBlobUrl.value.split('#')[0]
      if (urlToRevoke) {
        URL.revokeObjectURL(urlToRevoke)
      }
      pdfBlobUrl.value = null
    }
    pdfError.value = null
  }

  return {
    pdfBlobUrl,
    isPdfLoading,
    pdfError,
    loadPdf,
    clearPdf
  }
}
