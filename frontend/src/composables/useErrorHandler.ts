import { useToast } from './useToast'

/**
 * Standardized error handler for composables.
 *
 * Extracts user-friendly messages from API error responses,
 * shows a toast, and only logs to console in development.
 */
export function useErrorHandler() {
    const { showToast } = useToast()

    /**
     * Handle an error uniformly.
     *
     * @param error           The caught error (usually from axios)
     * @param fallbackMessage Message shown when no API message can be extracted
     * @returns               The extracted/fallback message string
     */
    const handleError = (error: unknown, fallbackMessage = 'An unexpected error occurred'): string => {
        let message = fallbackMessage

        if (error && typeof error === 'object' && 'response' in error) {
            const axiosErr = error as any
            const data = axiosErr.response?.data

            // Try to extract the most useful message from the API response
            if (data?.messages && typeof data.messages === 'object') {
                // Validation errors: { messages: { field: "error text" } }
                message = Object.values(data.messages).join('\n')
            } else if (data?.message) {
                message = data.message
            }
        } else if (error instanceof Error) {
            message = error.message
        }

        // Log full error in development only
        if (import.meta.env.DEV) {
            console.error(`[ErrorHandler] ${fallbackMessage}:`, error)
        }

        showToast(message, 'error')
        return message
    }

    return { handleError }
}
