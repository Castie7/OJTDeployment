<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, useRoute, RouterView } from 'vue-router'
import { useDashboard } from '../../composables/useDashboard'
import { useAuthStore } from '../../stores/auth'
import type { User } from '../../types' // Keeping User type if needed for emits or other logic
// import BaseButton from '../ui/BaseButton.vue' // Removed unused

// Props removed (currentUser is in store)
const emit = defineEmits<{
  (e: 'login-click'): void
  (e: 'logout-click'): void
  (e: 'update-user', user: User): void
}>()

// State
const authStore = useAuthStore()
const isMobileSidebarOpen = ref(false)
const isSidebarCollapsed = ref(false)
const router = useRouter()
const route = useRoute()
// const currentUserRef = toRef(props, 'currentUser') // Removed

const toggleSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value
}

// Provide removed (use store in children)

const {
  stats,
  showNotifications,
  notifications,
  unreadCount,
  updateStats,
  toggleNotifications,
  handleNotificationClick,
  formatTimeAgo
} = useDashboard() // No arguments

// Actions
const handleUserUpdate = (updatedUser: User) => {
  emit('update-user', updatedUser)
}

const isActive = (path: string) => route.path === path

const navigateTo = (path: string) => {
  router.push(path)
  isMobileSidebarOpen.value = false
}

const toggleMobileSidebar = () => {
    isMobileSidebarOpen.value = !isMobileSidebarOpen.value
}

// Simple Page Title Logic
const pageTitle = computed(() => {
    if (route.path === '/') return 'Dashboard'
    if (route.path === '/library') return 'Research Library'
    if (route.path === '/assistant') return 'Research Assistant'
    if (route.path === '/workspace') return 'My Workspace'

    if (route.path === '/approval') return 'Approvals'
    if (route.path === '/settings') return 'Settings'
    if (route.path === '/users') return 'User Management'
    if (route.path === '/masterlist') return 'Masterlist'
    if (route.path === '/import') return 'Import Data'
    if (route.path === '/logs') return 'System Logs'
    return 'BSU RootCrops'
})
</script>

<template>
  <div class="flex h-screen bg-gray-100 font-sans text-gray-800 overflow-hidden">
    
    <!-- DESKTOP SIDEBAR -->
    <aside 
        class="hidden md:flex flex-col bg-emerald-900 text-white shadow-xl z-20 transition-all duration-300"
        :class="isSidebarCollapsed ? 'w-20' : 'w-64'"
    >
        <!-- Logo Area -->
        <div class="h-16 flex items-center border-b border-emerald-800/50 bg-emerald-950/20 transition-all duration-300"
             :class="isSidebarCollapsed ? 'justify-center px-0' : 'justify-between px-6'"
        >
            <div class="flex items-center">
                <img src="/logo.png" alt="Logo" class="h-8 w-auto animate-float" :class="isSidebarCollapsed ? '' : 'mr-3'" />
                <span v-if="!isSidebarCollapsed" class="font-bold text-lg tracking-wide text-emerald-50 whitespace-nowrap">BSU RootCrops</span>
            </div>
            
            <button v-if="!isSidebarCollapsed" @click="toggleSidebar" class="text-emerald-400 hover:text-white focus:outline-none">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path></svg>
            </button>
        </div>
        
        <!-- Collapsed Toggle (Centered if collapsed) -->
        <div v-if="isSidebarCollapsed" class="flex justify-center py-2 border-b border-emerald-800/50 bg-emerald-950/30">
             <button @click="toggleSidebar" class="text-emerald-400 hover:text-white focus:outline-none">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path></svg>
             </button>
        </div>

        <!-- Navigation Links -->
        <div class="flex-1 overflow-y-auto py-6 px-3 space-y-1 custom-scrollbar">
            <div v-if="!authStore.currentUser && !isSidebarCollapsed" class="mb-6 px-3">
                 <button @click="$emit('login-click')" class="w-full bg-yellow-400 hover:bg-yellow-500 text-emerald-900 font-bold py-2.5 px-4 rounded-lg flex items-center justify-center gap-2 shadow-md transition-all hover:scale-[1.02]">
                    <span>&#x1F512;</span> Login Area
                </button>
            </div>
            <div v-if="!authStore.currentUser && isSidebarCollapsed" class="mb-6 flex justify-center">
                 <button @click="$emit('login-click')" class="size-10 bg-yellow-400 hover:bg-yellow-500 text-emerald-900 font-bold rounded-lg flex items-center justify-center shadow-md transition-all" title="Login">
                    <span>&#x1F512;</span>
                </button>
            </div>

            <!-- Main Nav -->
            <p v-if="!isSidebarCollapsed" class="px-3 text-xs font-bold text-emerald-400 uppercase tracking-wider mb-2 mt-2">Check</p>
            <div v-else class="h-4"></div>
            
            <button @click="navigateTo('/')" :class="['w-full rounded-lg mb-1 flex items-center transition-all duration-200', isSidebarCollapsed ? 'justify-center p-2' : 'px-3 py-2.5 gap-3 text-left', isActive('/') ? 'bg-emerald-800 text-white font-bold shadow-inner' : 'text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4']" title="Home">
                <span class="text-xl">&#x1F3E0;</span> <span v-if="!isSidebarCollapsed">Home</span>
            </button>
            <button @click="navigateTo('/library')" :class="['w-full rounded-lg mb-1 flex items-center transition-all duration-200', isSidebarCollapsed ? 'justify-center p-2' : 'px-3 py-2.5 gap-3 text-left', isActive('/library') ? 'bg-emerald-800 text-white font-bold shadow-inner' : 'text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4']" title="Research Library">
                <span class="text-xl">&#x1F4DA;</span> <span v-if="!isSidebarCollapsed">Research Library</span>
            </button>
            <button @click="navigateTo('/assistant')" :class="['w-full rounded-lg mb-1 flex items-center transition-all duration-200', isSidebarCollapsed ? 'justify-center p-2' : 'px-3 py-2.5 gap-3 text-left', isActive('/assistant') ? 'bg-emerald-800 text-white font-bold shadow-inner' : 'text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4']" title="Research Assistant">
                <span class="text-xl">&#x1F916;</span> <span v-if="!isSidebarCollapsed">Research Assistant</span>
            </button>

            <template v-if="authStore.currentUser">
                 <p v-if="!isSidebarCollapsed" class="px-3 text-xs font-bold text-emerald-400 uppercase tracking-wider mb-2 mt-6">Workspace</p>
                 <div v-else class="my-2 border-t border-emerald-800/30"></div>
                 
                 <button @click="navigateTo('/workspace')" :class="['w-full rounded-lg mb-1 flex items-center transition-all duration-200', isSidebarCollapsed ? 'justify-center p-2' : 'px-3 py-2.5 gap-3 text-left', isActive('/workspace') ? 'bg-emerald-800 text-white font-bold shadow-inner' : 'text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4']" title="My Workspace">
                    <span class="text-xl">&#x1F4BC;</span> <span v-if="!isSidebarCollapsed">My Workspace</span>
                 </button>


                 <!-- Admin Section -->
                 <template v-if="authStore.currentUser.role === 'admin'">
                    <p v-if="!isSidebarCollapsed" class="px-3 text-xs font-bold text-emerald-400 uppercase tracking-wider mb-2 mt-6">Administration</p>
                    <div v-else class="my-2 border-t border-emerald-800/30"></div>
                    
                    <button @click="navigateTo('/approval')" :class="['w-full rounded-lg mb-1 flex items-center transition-all duration-200', isSidebarCollapsed ? 'justify-center p-2' : 'px-3 py-2.5 gap-3 text-left', isActive('/approval') ? 'bg-emerald-800 text-white font-bold shadow-inner' : 'text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4']" title="Approvals">
                        <span class="text-xl">&#x2705;</span> <span v-if="!isSidebarCollapsed">Approvals</span>
                    </button>
                    <button @click="navigateTo('/import')" :class="['w-full rounded-lg mb-1 flex items-center transition-all duration-200', isSidebarCollapsed ? 'justify-center p-2' : 'px-3 py-2.5 gap-3 text-left', isActive('/import') ? 'bg-emerald-800 text-white font-bold shadow-inner' : 'text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4']" title="Upload Data">
                        <span class="text-xl">&#x1F4C2;</span> <span v-if="!isSidebarCollapsed">Upload Data</span>
                    </button>
                    <button @click="navigateTo('/users')" :class="['w-full rounded-lg mb-1 flex items-center transition-all duration-200', isSidebarCollapsed ? 'justify-center p-2' : 'px-3 py-2.5 gap-3 text-left', isActive('/users') ? 'bg-emerald-800 text-white font-bold shadow-inner' : 'text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4']" title="User Management">
                        <span class="text-xl">&#x1F465;</span> <span v-if="!isSidebarCollapsed">User Management</span>
                    </button>
                    <button @click="navigateTo('/masterlist')" :class="['w-full rounded-lg mb-1 flex items-center transition-all duration-200', isSidebarCollapsed ? 'justify-center p-2' : 'px-3 py-2.5 gap-3 text-left', isActive('/masterlist') ? 'bg-emerald-800 text-white font-bold shadow-inner' : 'text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4']" title="Masterlist">
                        <span class="text-xl">&#x1F4CB;</span> <span v-if="!isSidebarCollapsed">Masterlist</span>
                    </button>
                    <button @click="navigateTo('/logs')" :class="['w-full rounded-lg mb-1 flex items-center transition-all duration-200', isSidebarCollapsed ? 'justify-center p-2' : 'px-3 py-2.5 gap-3 text-left', isActive('/logs') ? 'bg-emerald-800 text-white font-bold shadow-inner' : 'text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4']" title="System Logs">
                        <span class="text-xl">&#x1F4DC;</span> <span v-if="!isSidebarCollapsed">System Logs</span>
                    </button>
                 </template>
            </template>
        </div>

        <!-- User Profile (Bottom) -->
        <div v-if="authStore.currentUser" class="border-t border-emerald-800/50 bg-emerald-950/30 transition-all duration-300" :class="isSidebarCollapsed ? 'p-2' : 'p-4'">
            <div class="flex items-center gap-3 mb-3" :class="isSidebarCollapsed ? 'justify-center' : ''">
                <div class="h-10 w-10 shrink-0 rounded-full bg-emerald-700 flex items-center justify-center text-emerald-100 font-bold border-2 border-emerald-500">
                    {{ authStore.currentUser.name.charAt(0).toUpperCase() }}
                </div>
                <div v-if="!isSidebarCollapsed" class="overflow-hidden">
                    <p class="text-sm font-bold text-white truncate">{{ authStore.currentUser.name }}</p>
                    <p class="text-xs text-emerald-300 truncate capitalize">{{ authStore.currentUser.role }}</p>
                </div>
            </div>
            
            <div v-if="!isSidebarCollapsed" class="flex gap-2">
                 <button @click="navigateTo('/settings')" class="flex-1 bg-emerald-800 hover:bg-emerald-700 text-xs py-1.5 rounded text-emerald-100 transition">Settings</button>
                 <button @click="$emit('logout-click')" class="flex-1 bg-red-900/50 hover:bg-red-700 text-xs py-1.5 rounded text-red-200 transition">Logout</button>
            </div>
            <div v-else class="flex flex-col gap-2 items-center">
                 <button @click="navigateTo('/settings')" class="text-emerald-400 hover:text-white" title="Settings">&#x2699;</button>
                 <button @click="$emit('logout-click')" class="text-red-400 hover:text-red-200" title="Logout">&#x1F6AA;</button>
            </div>
        </div>
    </aside>

    <!-- MOBILE SIDEBAR OVERLAY -->
    <div v-show="isMobileSidebarOpen" class="fixed inset-0 z-50 flex md:hidden">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" @click="isMobileSidebarOpen = false"></div>
        
        <!-- Sidebar Content -->
        <aside class="relative flex-1 flex flex-col max-w-xs w-full bg-emerald-900 text-white shadow-2xl transition-transform duration-300 transform h-full">
            <div class="h-16 flex items-center justify-between px-6 border-b border-emerald-800/50">
                <div class="flex items-center">
                    <img src="/logo.png" alt="Logo" class="h-8 w-auto mr-3" />
                    <span class="font-bold text-lg text-emerald-50">BSU RootCrops</span>
                </div>
                <button @click="isMobileSidebarOpen = false" class="text-emerald-300 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-1">
                 <!-- Same Nav Logic as Desktop (Mobile Optimized) -->
                 <button @click="navigateTo('/')" :class="['w-full text-left px-3 py-2.5 rounded-lg mb-1 flex items-center gap-3 text-sm font-medium transition-all duration-200', isActive('/') ? 'bg-emerald-800 text-white font-bold shadow-inner' : 'text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4']">Home</button>
                 <button @click="navigateTo('/library')" :class="['w-full text-left px-3 py-2.5 rounded-lg mb-1 flex items-center gap-3 text-sm font-medium transition-all duration-200', isActive('/library') ? 'bg-emerald-800 text-white font-bold shadow-inner' : 'text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4']">Research Library</button>
                 <button @click="navigateTo('/assistant')" :class="['w-full text-left px-3 py-2.5 rounded-lg mb-1 flex items-center gap-3 text-sm font-medium transition-all duration-200', isActive('/assistant') ? 'bg-emerald-800 text-white font-bold shadow-inner' : 'text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4']">Research Assistant</button>
                 
                 <template v-if="authStore.currentUser">
                     <div class="my-4 border-t border-emerald-800/50"></div>
                     <button @click="navigateTo('/workspace')" :class="['w-full text-left px-3 py-2.5 rounded-lg mb-1 flex items-center gap-3 text-sm font-medium transition-all duration-200', isActive('/workspace') ? 'bg-emerald-800 text-white font-bold shadow-inner' : 'text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4']">My Workspace</button>

                     
                     <template v-if="authStore.currentUser.role === 'admin'">
                        <div class="my-4 border-t border-emerald-800/50"></div>
                        <p class="px-3 text-xs font-bold text-emerald-400 uppercase tracking-wider mb-2">Admin</p>
                        <button @click="navigateTo('/approval')" :class="['w-full text-left px-3 py-2.5 rounded-lg mb-1 flex items-center gap-3 text-sm font-medium transition-all duration-200', isActive('/approval') ? 'bg-emerald-800 text-white font-bold shadow-inner' : 'text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4']">Approvals</button>
                        <button @click="navigateTo('/import')" :class="['w-full text-left px-3 py-2.5 rounded-lg mb-1 flex items-center gap-3 text-sm font-medium transition-all duration-200', isActive('/import') ? 'bg-emerald-800 text-white font-bold shadow-inner' : 'text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4']">Upload Data</button>
                        <button @click="navigateTo('/users')" :class="['w-full text-left px-3 py-2.5 rounded-lg mb-1 flex items-center gap-3 text-sm font-medium transition-all duration-200', isActive('/users') ? 'bg-emerald-800 text-white font-bold shadow-inner' : 'text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4']">Users</button>
                        <button @click="navigateTo('/masterlist')" :class="['w-full text-left px-3 py-2.5 rounded-lg mb-1 flex items-center gap-3 text-sm font-medium transition-all duration-200', isActive('/masterlist') ? 'bg-emerald-800 text-white font-bold shadow-inner' : 'text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4']">Masterlist</button>
                        <button @click="navigateTo('/logs')" :class="['w-full text-left px-3 py-2.5 rounded-lg mb-1 flex items-center gap-3 text-sm font-medium transition-all duration-200', isActive('/logs') ? 'bg-emerald-800 text-white font-bold shadow-inner' : 'text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4']">System Logs</button>
                     </template>

                     <div class="my-4 border-t border-emerald-800/50"></div>
                     <button @click="navigateTo('/settings')" class="w-full text-left px-3 py-2.5 rounded-lg mb-1 flex items-center gap-3 text-sm font-medium transition-all duration-200 text-emerald-100 hover:bg-emerald-800 hover:text-white hover:pl-4">Settings</button>
                     <button @click="$emit('logout-click'); isMobileSidebarOpen = false" class="w-full text-left px-3 py-2.5 rounded-lg mb-1 flex items-center gap-3 text-sm font-medium transition-all duration-200 text-red-300 hover:bg-red-900/30">Logout</button>
                 </template>
                 <template v-else>
                    <button @click="$emit('login-click'); isMobileSidebarOpen = false" class="w-full bg-yellow-500 text-emerald-900 font-bold p-3 rounded-lg mt-4">Login</button>
                 </template>
            </div>
        </aside>
    </div>

    <!-- MAIN CONTENT AREA -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <!-- TOP HEADER -->
        <header class="h-16 bg-white shadow-sm border-b border-gray-100 flex items-center justify-between px-4 sm:px-6 z-10">
            <div class="flex items-center gap-4">
                <button @click="toggleMobileSidebar" class="md:hidden text-gray-500 hover:text-emerald-700">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <h1 class="text-xl font-bold text-gray-800 hidden sm:block">{{ pageTitle }}</h1>
            </div>

            <!-- Header Actions -->
            <div class="flex items-center gap-4">
                <template v-if="authStore.currentUser">
                     <!-- Notifications -->
                     <div class="relative">
                        <button @click="toggleNotifications" class="p-2 rounded-full hover:bg-gray-100 text-gray-500 hover:text-emerald-600 transition relative">
                            <span class="text-xl">&#x1F514;</span>
                            <span v-if="unreadCount > 0" class="absolute top-1 right-1 h-2 w-2 bg-red-500 rounded-full animate-pulse border border-white"></span>
                        </button>
                         <!-- Notification Dropdown -->
                         <div v-if="showNotifications" class="fixed inset-0 z-40" @click="showNotifications = false"></div>
                         <div v-if="showNotifications" class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-gray-100 z-50 animate-fade-in origin-top-right overflow-hidden">
                             <div class="bg-gray-50 px-4 py-2 border-b border-gray-100 flex justify-between items-center">
                                <span class="text-xs font-bold text-gray-500">NOTIFICATIONS</span>
                             </div>
                             <div class="max-h-64 overflow-y-auto">
                                <div v-if="notifications.length === 0" class="p-4 text-center text-gray-400 text-sm">No new notifications.</div>
                                <div v-for="notif in notifications" :key="notif.id" @click="handleNotificationClick(notif)" class="px-4 py-3 border-b border-gray-50 hover:bg-emerald-50 cursor-pointer flex gap-3" :class="{'bg-blue-50/30': notif.is_read == 0}">
                                    <div class="text-lg">&#x1F4AC;</div>
                                    <div>
                                        <p class="text-sm text-gray-700">{{ notif.message }}</p>
                                        <p class="text-[10px] text-gray-400">{{ formatTimeAgo(notif.created_at) }}</p>
                                    </div>
                                </div>
                             </div>
                         </div>
                     </div>
                </template>
            </div>
        </header>

        <!-- MAIN SCROLLABLE CONTENT -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-4 sm:p-6 lg:p-8 custom-scrollbar">
            <div class="max-w-7xl mx-auto w-full">
                <RouterView 
                    :stats="stats"
                    ref="workspaceRef"
                    @browse-click="router.push('/library')"
                    @view-research="(id: number) => router.push({ path: '/library', query: { open: String(id) } })"
                    @stat-click="(tab: string) => {
                      if (tab === 'home') router.push('/')
                      else if (tab === 'research') router.push('/library')
                      else router.push(`/${tab}`)
                    }"
                    @update-stats="updateStats"
                    @upload-success="router.push('/library')"
                    @update-user="handleUserUpdate"
                    @trigger-logout="$emit('logout-click')"
                />
            </div>
        </main>

    </div>
  </div>
</template>

<style scoped>
.animate-float {
  animation: float 3s ease-in-out infinite;
}

@keyframes float {
  0% { transform: translateY(0px); }
  50% { transform: translateY(-3px); }
  100% { transform: translateY(0px); }
}

.animate-fade-in {
  animation: fadeIn 0.15s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Custom Scrollbar for Sidebar */
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(255,255,255,0.2); 
  border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent; 
}
</style>

