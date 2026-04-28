/**
 * Lightweight in-memory API cache with TTL and request deduplication.
 *
 * Usage:
 *   const data = await apiCache.get('research:all', () => api.get('/research'))
 *   apiCache.invalidate('research')   // clear all keys containing "research"
 *   apiCache.invalidate()             // clear everything
 */

interface CacheEntry<T> {
    data: T
    timestamp: number
    /** Pending promise — prevents duplicate concurrent requests for the same key */
    pending?: Promise<T>
}

class ApiCache {
    private cache = new Map<string, CacheEntry<any>>()
    private defaultTtl: number

    /** @param ttlMs  Time-to-live in milliseconds (default 5 min) */
    constructor(ttlMs = 5 * 60 * 1000) {
        this.defaultTtl = ttlMs
    }

    /**
     * Return cached data if fresh, otherwise call `fetcher` and cache the result.
     * Concurrent calls with the same key share a single in-flight request.
     */
    async get<T>(key: string, fetcher: () => Promise<T>, ttlMs?: number): Promise<T> {
        const ttl = ttlMs ?? this.defaultTtl
        const cached = this.cache.get(key)

        // 1. Fresh cache hit
        if (cached && Date.now() - cached.timestamp < ttl) {
            return cached.data as T
        }

        // 2. Request already in-flight → piggyback on it
        if (cached?.pending) {
            return cached.pending as Promise<T>
        }

        // 3. Fetch new data
        const pending = fetcher()
        this.cache.set(key, { data: null as any, timestamp: 0, pending })

        try {
            const data = await pending
            this.cache.set(key, { data, timestamp: Date.now() })
            return data
        } catch (error) {
            this.cache.delete(key) // don't cache failures
            throw error
        }
    }

    /**
     * Invalidate cached entries.
     * @param pattern  If provided, only keys containing this substring are removed.
     *                 If omitted, the entire cache is cleared.
     */
    invalidate(pattern?: string): void {
        if (!pattern) {
            this.cache.clear()
            return
        }
        for (const key of this.cache.keys()) {
            if (key.includes(pattern)) {
                this.cache.delete(key)
            }
        }
    }
}

export const apiCache = new ApiCache()
