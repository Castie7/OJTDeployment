import { test, expect } from '@playwright/test'

test.describe('Workspace Management', () => {

    test.beforeEach(async ({ page }) => {
        // Log in to access the protected workspace route
        await page.goto('/login')
        await page.fill('input[type="email"], input[placeholder*="mail"]', 'admin@bsu.edu.ph')
        await page.fill('input[type="password"]', 'admin123')
        await page.click('button[type="submit"]')
        
        // Wait until we are redirected away from login
        await page.waitForURL(url => !url.href.includes('/login'), { timeout: 10000 })
        
        // Now explicitly navigate to the workspace
        await page.goto('/workspace')
    })

    test('should allow a user to submit a new research item', async ({ page }) => {
        // 1. Click the "Submit New Item" button
        await page.click('button:has-text("Submit New Item")')

        // Verify that the submit modal has opened
        await expect(page.locator('h2:has-text("Submit Knowledge Product")')).toBeVisible()

        // 2. Fill out the required fields
        // Check "Research Paper" for the type using standard checkbox selection
        await page.locator('input[value="Research Paper"]').check()
        
        // Fill out Title and Author using placeholders for easy selection
        const testTitle = 'E2E Playwright Automated Test Paper - ' + Date.now()
        await page.fill('input[placeholder="Enter full title..."]', testTitle)
        await page.fill('input[placeholder="e.g. Juan Cruz, Maria Santos"]', 'Automated Tester')

        // 3. Submit the newly filled form
        // Targeting the button inside the modal to 'Submit Item' 
        await page.click('button:has-text("Submit Item")')

        // Wait for the modal to close indicating success
        await expect(page.locator('h2:has-text("Submit Knowledge Product")')).toBeHidden({ timeout: 10000 })
        
        // 4. Verify that the new item is now visible in the list (Defaults to 'Pending' tab based on your code)
        await expect(page.locator(`text=${testTitle}`).first()).toBeVisible({ timeout: 10000 })
    })
})
