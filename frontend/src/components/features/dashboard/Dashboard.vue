<script setup lang="ts">
import { ref, onMounted } from 'vue' 
import { useDashboard } from '../../../composables/useDashboard'
import { useAuthStore } from '../../../stores/auth'
import type { User } from '../../../types'

// Import Sub-Components
import HomeView from '../home/Homeview.vue'
import ResearchLibrary from '../research/ResearchLibrary.vue'
import MyWorkspace from '../research/MyWorkspace.vue'
import Approval from '../research/Approval.vue'
import Settings from '../admin/Settings.vue' 
import ImportCsv from '../import/ImportCsv.vue' 
import UserManagement from '../admin/UserManagement.vue'
import AdminLogs from '../admin/AdminLogs.vue'
import Masterlist from '../research/Masterlist.vue'

// Props removed

const emit = defineEmits<{
  (e: 'login-click'): void
  (e: 'logout-click'): void
  (e: 'update-user', user: User): void 
}>()

// Mobile Menu State
const showMobileMenu = ref(false)
const authStore = useAuthStore()

// ... Initialize Dashboard Logic ...
// const currentUserRef = toRef(props, 'currentUser') // Removed

const { 
  // State
  currentTab, 
  stats, 
  workspaceRef, 
  approvalRef, 
  showAdminMenu, 
  showNotifications, 
  notifications, 
  unreadCount,
  
  // Actions
  updateStats, 
  setTab, 
  closeAdminMenu, 
  toggleNotifications, 
  handleNotificationClick, 
  formatTimeAgo
} = useDashboard()

const handleUserUpdate = (updatedUser: User) => {
  emit('update-user', updatedUser)
}

// Silence unused variable warning for template ref
// onMounted moved to top
onMounted(() => {
    // console.log(approvalRef.value) 
})

const triggerApproval = () => {
    if (approvalRef.value) {
        // do nothing
    }
    if (workspaceRef.value) {
        // do nothing
    }
}
onMounted(() => {
    // Silence unused var
    console.log(triggerApproval)
})
</script>


<template>
  <div class="min-h-screen bg-gray-50 font-sans text-gray-800 relative">
    
    <nav class="bg-green-800 text-white shadow-lg sticky top-0 z-40">
      <div class="nav-container">
        <div class="flex items-center justify-between h-16">
          
          <div class="flex items-center gap-3">
            <!-- Mobile Menu Button -->
            <button @click="showMobileMenu = !showMobileMenu" class="md:hidden p-2 rounded-md hover:bg-green-700 focus:outline-none">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path v-if="!showMobileMenu" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>

            <img src="/logo.png" alt="BSU Logo" class="nav-logo-img" />
            <span class="font-bold text-xl tracking-wide hidden sm:block">BSU RootCrops</span>
          </div>

          <!-- Desktop Navigation -->
          <div class="hidden md:flex ml-10 space-x-4">
            <button @click="setTab('home')" :class="['nav-btn', currentTab === 'home' ? 'nav-btn-active' : 'nav-btn-inactive']">
              Home
            </button>
            <button @click="setTab('research')" :class="['nav-btn', currentTab === 'research' ? 'nav-btn-active' : 'nav-btn-inactive']">
              Research Library
            </button>
            
            <template v-if="authStore.currentUser">
              <button @click="setTab('workspace')" :class="['nav-btn', currentTab === 'workspace' ? 'nav-btn-active' : 'nav-btn-inactive']">
                My Workspace
              </button>
              
              <template v-if="authStore.currentUser.role === 'admin'">
                <button 
                  @click="setTab('approval')" 
                  :class="['nav-btn', currentTab === 'approval' ? 'nav-btn-active' : 'nav-btn-inactive']"
                >
                  Approvals
                </button>

                <div class="relative group">
                    <button 
                        @click="showAdminMenu = !showAdminMenu" 
                        @blur="closeAdminMenu"
                        :class="['nav-btn flex items-center gap-1', (currentTab === 'import' || currentTab === 'users' || currentTab === 'masterlist' || showAdminMenu) ? 'nav-btn-active' : 'nav-btn-inactive']"
                    >
                        Admin Tools ▾
                    </button>

                    <div v-if="showAdminMenu" class="absolute top-full left-0 mt-1 w-64 bg-white rounded-lg shadow-xl border border-gray-100 overflow-hidden text-sm z-50 animate-fade-in">
                        <div class="py-1">
                            <button 
                                @click="setTab('import'); showAdminMenu = false"
                                class="w-full text-left px-4 py-3 text-gray-700 hover:bg-green-50 hover:text-green-700 font-bold border-l-4 border-transparent hover:border-green-600 transition flex items-center gap-2"
                            >
                                📂 Upload Data Researches
                            </button>
                            
                            <div class="border-t border-gray-100 my-1"></div>

                            <button 
                                @click="setTab('users'); showAdminMenu = false"
                                class="w-full text-left px-4 py-3 text-gray-700 hover:bg-green-50 hover:text-green-700 font-bold border-l-4 border-transparent hover:border-green-600 transition flex items-center gap-2"
                            >
                                👥 Add/Reset Accounts
                            </button>

                            <div class="border-t border-gray-100 my-1"></div>

                            <button 
                                @click="setTab('masterlist'); showAdminMenu = false"
                                class="w-full text-left px-4 py-3 text-gray-700 hover:bg-green-50 hover:text-green-700 font-bold border-l-4 border-transparent hover:border-green-600 transition flex items-center gap-2"
                            >
                                📋 Masterlist
                            </button>

                            <div class="border-t border-gray-100 my-1"></div>

                            <button 
                                @click="setTab('logs'); showAdminMenu = false"
                                class="w-full text-left px-4 py-3 text-gray-700 hover:bg-green-50 hover:text-green-700 font-bold border-l-4 border-transparent hover:border-green-600 transition flex items-center gap-2"
                            >
                                📜 System Logs
                            </button>
                        </div>
                    </div>
                </div>
              </template>
            </template>
          </div>

          <!-- User Profile & Actions -->
          <div>
            <div v-if="authStore.currentUser" class="flex items-center gap-4">
              <span class="text-sm font-light hidden sm:block">Welcome, {{ authStore.currentUser.name }}</span>
              
              <div class="relative">
                <button 
                  @click="toggleNotifications" 
                  class="p-2 rounded-full hover:bg-green-700 transition relative focus:outline-none"
                  title="Notifications"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                  </svg>
                  
                  <span 
                    v-if="unreadCount > 0" 
                    class="absolute top-1 right-1 h-4 w-4 bg-red-500 rounded-full text-[10px] font-bold flex items-center justify-center border border-green-800"
                  >
                    {{ unreadCount }}
                  </span>
                </button>

                <!-- Invisible backdrop to close notifications on outside click -->
                <div v-if="showNotifications" class="fixed inset-0 z-40" @click="showNotifications = false"></div>

                <div v-if="showNotifications" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl overflow-hidden z-50 border border-gray-100 animate-fade-in">
                  <div class="bg-gray-50 px-4 py-2 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-gray-700">Notifications</h3>
                    <span class="text-xs text-gray-500 cursor-pointer hover:text-green-600">Mark all read</span>
                  </div>
                  
                  <div class="max-h-64 overflow-y-auto custom-scrollbar">
                    <div v-if="notifications.length === 0" class="p-4 text-center text-gray-400 text-sm">
                      No new notifications.
                    </div>
                    
                    <div 
                      v-for="notif in notifications" 
                      :key="notif.id" 
                      @click="handleNotificationClick(notif)"
                      class="px-4 py-3 border-b border-gray-50 hover:bg-green-50 transition cursor-pointer flex gap-3 items-start"
                      :class="{'bg-blue-50/30': notif.is_read == 0}"
                    >
                      <div class="mt-1 text-xl">💬</div>
                      <div>
                        <p class="text-sm text-gray-800 leading-tight">{{ notif.message }}</p>
                        <span class="text-[10px] text-gray-400 font-medium">{{ formatTimeAgo(notif.created_at) }}</span>
                      </div>
                    </div>

                  </div>
                </div>
              </div>

              <!-- Desktop Settings/Logout -->
              <div class="hidden md:flex gap-2">
                <button @click="setTab('settings')" class="btn-settings" title="Settings">
                  ⚙️
                </button>

                <button @click="$emit('logout-click')" class="btn-logout">
                  Logout
                </button>
              </div>
            </div>
            
            <!-- Desktop Login -->
            <button v-else @click="$emit('login-click')" class="btn-login hidden md:flex">
               Login
            </button>
          </div>
        </div>

        <!-- Mobile Menu (Dropdown) -->
        <div v-if="showMobileMenu" class="md:hidden pb-4 pt-2 border-t border-green-700">
            <button @click="setTab('home'); showMobileMenu = false" :class="['w-full text-left px-3 py-2 rounded-md font-medium', currentTab === 'home' ? 'bg-green-900 text-white' : 'text-white hover:bg-green-700']">
              Home
            </button>
            <button @click="setTab('research'); showMobileMenu = false" :class="['w-full text-left px-3 py-2 rounded-md font-medium', currentTab === 'research' ? 'bg-green-900 text-white' : 'text-white hover:bg-green-700']">
              Research Library
            </button>
            
            <template v-if="authStore.currentUser">
              <button @click="setTab('workspace'); showMobileMenu = false" :class="['w-full text-left px-3 py-2 rounded-md font-medium', currentTab === 'workspace' ? 'bg-green-900 text-white' : 'text-white hover:bg-green-700']">
                My Workspace
              </button>
              
              <template v-if="authStore.currentUser.role === 'admin'">
                <button @click="setTab('approval'); showMobileMenu = false" :class="['w-full text-left px-3 py-2 rounded-md font-medium', currentTab === 'approval' ? 'bg-green-900 text-white' : 'text-white hover:bg-green-700']">
                  Approvals
                </button>
                
                <div class="pl-4 mt-2 mb-2 border-l-2 border-green-600">
                    <p class="text-xs text-green-300 px-3 uppercase font-bold mb-1">Admin Tools</p>
                    <button @click="setTab('import'); showMobileMenu = false" :class="['w-full text-left px-3 py-2 rounded-md font-medium text-sm', currentTab === 'import' ? 'bg-green-900 text-white' : 'text-white hover:bg-green-700']">
                        📂 Upload Data
                    </button>
                    <button @click="setTab('users'); showMobileMenu = false" :class="['w-full text-left px-3 py-2 rounded-md font-medium text-sm', currentTab === 'users' ? 'bg-green-900 text-white' : 'text-white hover:bg-green-700']">
                        👥 Users
                    </button>
                    <button @click="setTab('masterlist'); showMobileMenu = false" :class="['w-full text-left px-3 py-2 rounded-md font-medium text-sm', currentTab === 'masterlist' ? 'bg-green-900 text-white' : 'text-white hover:bg-green-700']">
                        📋 Masterlist
                    </button>
                    <button @click="setTab('logs'); showMobileMenu = false" :class="['w-full text-left px-3 py-2 rounded-md font-medium text-sm', currentTab === 'logs' ? 'bg-green-900 text-white' : 'text-white hover:bg-green-700']">
                        📜 Logs
                    </button>
                </div>
              </template>

              <div class="border-t border-green-700 my-2 pt-2">
                 <button @click="setTab('settings'); showMobileMenu = false" :class="['w-full text-left px-3 py-2 rounded-md font-medium', currentTab === 'settings' ? 'bg-green-900 text-white' : 'text-white hover:bg-green-700']">
                  ⚙️ Settings
                 </button>
                 <button @click="$emit('logout-click'); showMobileMenu = false" class="w-full text-left px-3 py-2 rounded-md font-medium text-red-100 hover:bg-red-600 hover:text-white mt-1">
                  Logout
                 </button>
              </div>
            </template>
            <template v-else>
               <button @click="$emit('login-click'); showMobileMenu = false" class="w-full text-left px-3 py-2 rounded-md font-medium bg-yellow-500 text-green-900 hover:bg-yellow-600 mt-2">
                  Login
               </button>
            </template>
        </div>
      </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      
      <HomeView 
        v-if="currentTab === 'home'" 
        :stats="stats" 
        @browse-click="setTab('research')"
        @stat-click="setTab"
      />

      <ResearchLibrary 
        v-if="currentTab === 'research'" 
        @update-stats="updateStats" 
      />

      <MyWorkspace 
        v-if="currentTab === 'workspace'" 
        ref="workspaceRef"
        :currentUser="authStore.currentUser" 
      />

      <Approval 
        v-if="currentTab === 'approval' && authStore.currentUser && authStore.currentUser.role === 'admin'" 
        ref="approvalRef"
        :currentUser="authStore.currentUser" 
      />

      <ImportCsv 
        v-if="currentTab === 'import' && authStore.currentUser && authStore.currentUser.role === 'admin'"
        @upload-success="setTab('research')" 
      />

      <UserManagement 
        v-if="currentTab === 'users' && authStore.currentUser && authStore.currentUser.role === 'admin'"
      />
      
      <Masterlist 
        v-if="currentTab === 'masterlist' && authStore.currentUser && authStore.currentUser.role === 'admin'"
      />

      <AdminLogs 
        v-if="currentTab === 'logs' && authStore.currentUser && authStore.currentUser.role === 'admin'"
      />

      <Settings 
        v-if="currentTab === 'settings'"
        :currentUser="authStore.currentUser"
        @update-user="handleUserUpdate"
        @trigger-logout="$emit('logout-click')" 
      />

    </main>
  </div>
</template>

<style scoped src="../../../assets/styles/Dashboard.css"></style>