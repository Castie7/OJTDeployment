import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useToast } from '../composables/useToast'

const routes: Array<RouteRecordRaw> = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('../components/features/authentication/LoginForm.vue'),
    meta: {
      requiresAuth: false,
      title: 'Login - BSU Research Portal'
    }
  },
  {
    path: '/',
    component: () => import('../components/layouts/DashboardLayout.vue'),
    children: [
      {
        path: '',
        name: 'Home',
        component: () => import('../components/features/home/Homeview.vue'),
        meta: {
          requiresAuth: false,
          title: 'BSU Research Portal'
        }
      },
      {
        path: 'library',
        name: 'ResearchLibrary',
        component: () => import('../components/features/research/ResearchLibrary.vue'),
        meta: {
          requiresAuth: false,
          title: 'Research Library - BSU Research Portal'
        }
      },
      {
        path: 'assistant',
        name: 'ResearchAssistant',
        component: () => import('../components/features/assistant/ResearchAssistant.vue'),
        meta: {
          requiresAuth: false,
          title: 'Research Assistant - BSU Research Portal'
        }
      },
      {
        path: 'workspace',
        name: 'MyWorkspace',
        component: () => import('../components/features/research/MyWorkspace.vue'),
        meta: {
          requiresAuth: true,
          title: 'My Workspace - BSU Research Portal'
        }
      },


      {
        path: 'approval',
        name: 'Approval',
        component: () => import('../components/features/research/Approval.vue'),
        meta: {
          requiresAuth: true,
          requiresRole: ['admin'],
          title: 'Approval - BSU Research Portal'
        }
      },
      {
        path: 'settings',
        name: 'Settings',
        component: () => import('../components/features/admin/Settings.vue'),
        meta: {
          requiresAuth: true,
          title: 'Settings - BSU Research Portal'
        }
      },
      {
        path: 'import',
        name: 'ImportCsv',
        component: () => import('../components/features/import/ImportCsv.vue'),
        meta: {
          requiresAuth: true,
          requiresRole: ['admin'],
          title: 'Import Data - BSU Research Portal'
        }
      },
      {
        path: 'users',
        name: 'UserManagement',
        component: () => import('../components/features/admin/UserManagement.vue'),
        meta: {
          requiresAuth: true,
          requiresRole: ['admin'],
          title: 'User Management - BSU Research Portal'
        }
      },
      {
        path: 'masterlist',
        name: 'Masterlist',
        component: () => import('../components/features/research/Masterlist.vue'),
        meta: {
          requiresAuth: true,
          requiresRole: ['admin'],
          title: 'Masterlist - BSU Research Portal'
        }
      },
      {
        path: 'logs',
        name: 'AdminLogs',
        component: () => import('../components/features/admin/AdminLogs.vue'),
        meta: {
          requiresAuth: true,
          requiresRole: ['admin'],
          title: 'System Logs - BSU Research Portal'
        }
      }
    ]
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

// Navigation Guard: Authentication & Authorization
router.beforeEach(async (to, _from, next) => {
  const authStore = useAuthStore()
  const requiresAuth = to.matched.some(record => record.meta.requiresAuth)

  // 1. Initialize Auth State if not yet done
  if (!authStore.isInitialized) {
    await authStore.init()
  }

  // 2. Check Permissions
  if (requiresAuth && !authStore.isAuthenticated) {
    // User is not authenticated, redirect to login
    next({
      path: '/login',
      query: { redirect: to.fullPath }
    })
  } else if (to.path === '/login' && authStore.isAuthenticated) {
    // User is already logged in, redirect to home
    next('/')
  } else {
    // Check role requirements
    const requiredRoles = to.meta.requiresRole as string[] | undefined
    if (requiredRoles && requiredRoles.length > 0) {
      const { showToast } = useToast()

      // 1. Initial Local State Check
      if (!authStore.userRole || !requiredRoles.includes(authStore.userRole)) {
        showToast("Access Denied", "error")
        next('/')
        return
      }

      // 2. Security Enhancement: Prevent Vue DevTools Spoofing
      // If the route is an admin route, enforce strict verification with the backend's source of truth.
      try {
        await authStore.init(true)
        if (!authStore.userRole || !requiredRoles.includes(authStore.userRole)) {
          showToast("Access Denied: Action Logged For Security Review.", "error")
          next('/')
          return
        }
      } catch (err) {
        showToast("Authentication Check Failed", "error")
        next('/')
        return
      }
    }
    next()
  }
})

// Navigation Guard: Update Page Title
router.afterEach((to) => {
  // Set page title from route meta or use default
  const title = (to.meta.title as string) || 'BSU Research Portal'
  document.title = title
})

export default router
