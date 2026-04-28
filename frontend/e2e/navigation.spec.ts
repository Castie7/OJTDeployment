import { test, expect } from '@playwright/test'

test.describe('Navigation', () => {

    test('should load the home page', async ({ page }) => {
        await page.goto('/')
        // Home page should render without errors
        await expect(page).toHaveURL('/')
        // Page should have content (not a blank screen)
        await expect(page.locator('body')).not.toBeEmpty()
    })

    test('should navigate to Research Library', async ({ page }) => {
        await page.goto('/library')
        await expect(page).toHaveURL(/library/)
        // Library page should show research content or a loading indicator
        await expect(page.locator('body')).not.toBeEmpty()
    })

    test('should redirect unauthenticated users from protected pages', async ({ page }) => {
        // Try accessing a protected route
        await page.goto('/workspace')

        // Should be redirected to login
        await page.waitForURL(/login/, { timeout: 10000 })
        await expect(page).toHaveURL(/login/)
    })

    test('should redirect unauthenticated users from admin pages', async ({ page }) => {
        // Try accessing admin-only route
        await page.goto('/masterlist')

        // Should be redirected to login or home
        await page.waitForURL(/login|\/$/, { timeout: 10000 })
    })

    test('should have correct page titles', async ({ page }) => {
        await page.goto('/')
        await expect(page).toHaveTitle(/BSU Research Portal/i)

        await page.goto('/login')
        await expect(page).toHaveTitle(/Login/i)
    })
})
