/**
 * Creates a debounced version of a function.
 * The function will only execute after `wait` milliseconds of inactivity.
 *
 * @param func  The function to debounce
 * @param wait  Delay in ms (default 300)
 */
export function debounce<T extends (...args: any[]) => any>(
    func: T,
    wait = 300
): (...args: Parameters<T>) => void {
    let timeout: ReturnType<typeof setTimeout> | null = null

    return function (this: any, ...args: Parameters<T>) {
        if (timeout) clearTimeout(timeout)
        timeout = setTimeout(() => func.apply(this, args), wait)
    }
}
