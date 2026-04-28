/**
 * Runtime type guards for domain objects.
 *
 * Use these when receiving data from external sources (API responses,
 * localStorage, postMessage, etc.) where TypeScript's compile-time
 * types cannot guarantee the shape at runtime.
 */

import type { Research, User, Comment } from '../types'

/** Narrow an unknown value to `User` */
export function isUser(obj: unknown): obj is User {
    return (
        typeof obj === 'object' &&
        obj !== null &&
        'id' in obj &&
        'name' in obj &&
        'role' in obj &&
        typeof (obj as any).id === 'number' &&
        typeof (obj as any).name === 'string' &&
        typeof (obj as any).role === 'string'
    )
}

/** Narrow an unknown value to `Research` */
export function isResearch(obj: unknown): obj is Research {
    return (
        typeof obj === 'object' &&
        obj !== null &&
        'id' in obj &&
        'title' in obj &&
        'author' in obj &&
        'status' in obj &&
        typeof (obj as any).id === 'number' &&
        typeof (obj as any).title === 'string' &&
        typeof (obj as any).author === 'string'
    )
}

/** Narrow an unknown value to `Comment` */
export function isComment(obj: unknown): obj is Comment {
    return (
        typeof obj === 'object' &&
        obj !== null &&
        'id' in obj &&
        'user_name' in obj &&
        'comment' in obj &&
        typeof (obj as any).id === 'number' &&
        typeof (obj as any).comment === 'string'
    )
}

/** Check if an error is an Axios-style error with a `response` property */
export function isApiError(error: unknown): error is { response: { status: number; data: any } } {
    return (
        typeof error === 'object' &&
        error !== null &&
        'response' in error &&
        typeof (error as any).response?.status === 'number'
    )
}
