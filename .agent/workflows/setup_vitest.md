---
description: Set up Vitest locally
---

1. Install Vitest and dependencies
// turbo
```bash
npm install -D vitest jsdom @vue/test-utils
```

2. Create vitest.config.ts
```typescript
/// <reference types="vitest" />
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  test: {
    globals: true,
    environment: 'jsdom',
  },
})
```

3. Update package.json scripts
// turbo
```bash
npm pkg set scripts.test="vitest"
```
