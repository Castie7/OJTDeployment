import { ref, computed } from 'vue'

/**
 * Global loading state that tracks in-flight API requests.
 *
 * Wire this into axios interceptors (see api.ts) so any component
 * can show a global loading indicator via `isLoading`.
 */

const activeRequests = ref(0)
const isLoading = computed(() => activeRequests.value > 0)

export function useGlobalLoading() {
    const startLoading = () => { activeRequests.value++ }
    const stopLoading = () => { activeRequests.value = Math.max(0, activeRequests.value - 1) }

    return {
        /** True when at least one API request is in-flight */
        isLoading,
        /** Raw count of active requests */
        activeRequests,
        startLoading,
        stopLoading,
    }
}
