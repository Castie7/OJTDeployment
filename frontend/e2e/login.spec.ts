import { test, expect } from '@playwright/test'

test.describe('Login Flow', () => {

    test.beforeEach(async ({ page }) => {
        await page.goto('/login')
    })

    test('should display the login form', async ({ page }) => {
        // Verify login page elements are visible
        await expect(page.locator('input[type="email"], input[placeholder*="mail"]')).toBeVisible()
        await expect(page.locator('input[type="password"]')).toBeVisible()
        await expect(page.locator('button[type="submit"], button:has-text("Login"), button:has-text("Sign")')).toBeVisible()
    })

    test('should show error for invalid credentials', async ({ page }) => {
        // Fill in invalid credentials
        await page.fill('input[type="email"], input[placeholder*="mail"]', 'invalid@test.com')
        await page.fill('input[type="password"]', 'wrongpassword')

        // Submit login form
        await page.click('button[type="submit"], button:has-text("Login"), button:has-text("Sign")')

        // Wait for error message to appear
        await expect(page.locator('text=/invalid|error|incorrect|failed/i')).toBeVisible({ timeout: 10000 })
    })

    test('should show validation for empty fields', async ({ page }) => {
        // Try submitting without filling in fields
        await page.click('button[type="submit"], button:has-text("Login"), button:has-text("Sign")')

        // HTML5 validation or custom validation should prevent submission
        // The form should still be on the login page
        await expect(page).toHaveURL(/login/)
    })
})
