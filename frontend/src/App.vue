<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import { RouterView, useRouter } from 'vue-router'
import Toast from './components/shared/Toast.vue'
import { useAuthStore } from './stores/auth'
import { useToast } from './composables/useToast'
import api from './services/api'

const authStore = useAuthStore()
const isLoading = ref(!authStore.isInitialized) 
const router = useRouter()
const { showToast } = useToast()

/* Watch for initialization */
watch(() => authStore.isInitialized, (newVal) => {
    if (newVal) isLoading.value = false
}, { immediate: true })

// --- Forced Password Change Modal State ---
const forcePassForm = reactive({ current: '', newPass: '', confirm: '' })
const isChangingPassword = ref(false)
const showCurrentPass = ref(false)
const showNewPass = ref(false)
const passError = ref('')

// Real-time password validation rules
const passRules = computed(() => ({
  minLength: forcePassForm.newPass.length >= 10,
  hasUpper: /[A-Z]/.test(forcePassForm.newPass),
  hasLower: /[a-z]/.test(forcePassForm.newPass),
  hasNumber: /\d/.test(forcePassForm.newPass),
  hasSpecial: /[^a-zA-Z0-9]/.test(forcePassForm.newPass),
  matches: forcePassForm.newPass !== '' && forcePassForm.newPass === forcePassForm.confirm
}))

const allRulesPassed = computed(() => 
  passRules.value.minLength && passRules.value.hasUpper && passRules.value.hasLower && 
  passRules.value.hasNumber && passRules.value.hasSpecial && passRules.value.matches
)

const submitForcedPasswordChange = async () => {
  passError.value = ''

  if (!forcePassForm.current) { passError.value = 'Enter your current password.'; return }
  if (!allRulesPassed.value) { passError.value = 'Please meet all password requirements below.'; return }

  isChangingPassword.value = true
  try {
    const response = await api.post('/auth/update-profile', {
      user_id: authStore.currentUser?.id,
      current_password: forcePassForm.current,
      new_password: forcePassForm.newPass
    })

    if (response.data.status === 'success') {
      showToast('Password changed successfully! Please login again.', 'success')
      authStore.mustChangePassword = false
      await authStore.logout()
      window.location.href = '/login'
    }
  } catch (error: any) {
    // DON'T logout on error — just show the error message
    passError.value = error.response?.data?.message || 'Failed to change password. Please try again.'
  } finally {
    isChangingPassword.value = false
  }
}

// --- Event Handlers ---
const onLoginSuccess = (data: any) => {
  authStore.setUser(data.user)

  if (data.must_change_password) {
    authStore.mustChangePassword = true
    router.push('/')
  } else {
    router.push('/') 
  }
}

const handleLogout = async () => {
    await authStore.logout()
    window.location.href = '/login'; 
}

const goToLogin = () => {
  router.push('/login')
}
</script>

<template>
  <div v-if="isLoading" class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="flex flex-col items-center gap-4">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-700"></div>
      <p class="text-green-800 font-medium animate-pulse">Initializing Portal...</p>
    </div>
  </div>

  <template v-else>
    <RouterView 
      @login-success="onLoginSuccess"
      @login-click="goToLogin"
      @logout-click="handleLogout"
      @update-user="(u: any) => authStore.setUser(u)"
      @back="router.push('/')"
    />
    <Toast />

    <!-- 🔒 UNCLOSEABLE Forced Password Change Modal -->
    <Teleport to="body">
      <div v-if="authStore.mustChangePassword && authStore.isAuthenticated" 
           class="fixed inset-0 z-[9999] flex items-center justify-center"
           style="background: rgba(0,0,0,0.7); backdrop-filter: blur(6px);"
      >
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden animate-modal-in">
          <!-- Header -->
          <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-5 text-white">
            <div class="flex items-center gap-3">
              <span class="text-3xl">🔐</span>
              <div>
                <h2 class="text-xl font-bold">Password Change Required</h2>
                <p class="text-red-100 text-sm mt-0.5">Your password was reset by an administrator</p>
              </div>
            </div>
          </div>

          <!-- Body -->
          <form @submit.prevent="submitForcedPasswordChange" class="p-6 space-y-4">
            <!-- Error Banner -->
            <div v-if="passError" class="bg-red-50 border border-red-300 rounded-lg p-3 text-sm text-red-700 flex items-start gap-2">
              <span class="shrink-0">❌</span>
              <span>{{ passError }}</span>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800">
              <b>⚠️ You must set a new personal password</b> before you can use the portal.
            </div>

            <!-- Current Password -->
            <div>
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5">Current Password <span class="text-red-500">*</span></label>
              <div class="relative">
                <input 
                  v-model="forcePassForm.current" 
                  :type="showCurrentPass ? 'text' : 'password'"
                  class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none bg-gray-50 focus:bg-white transition-colors"
                  placeholder="Enter current password"
                  autocomplete="current-password"
                />
                <button type="button" @click="showCurrentPass = !showCurrentPass" class="absolute inset-y-0 right-0 px-3 text-gray-400 hover:text-gray-600">
                  {{ showCurrentPass ? '🙈' : '👁️' }}
                </button>
              </div>
            </div>

            <!-- New Password -->
            <div>
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5">New Password</label>
              <div class="relative">
                <input 
                  v-model="forcePassForm.newPass" 
                  :type="showNewPass ? 'text' : 'password'"
                  class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none bg-gray-50 focus:bg-white transition-colors"
                  placeholder="Enter new password"
                  autocomplete="new-password"
                />
                <button type="button" @click="showNewPass = !showNewPass" class="absolute inset-y-0 right-0 px-3 text-gray-400 hover:text-gray-600">
                  {{ showNewPass ? '🙈' : '👁️' }}
                </button>
              </div>
            </div>

            <!-- Confirm -->
            <div>
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5">Confirm New Password</label>
              <input 
                v-model="forcePassForm.confirm" 
                :type="showNewPass ? 'text' : 'password'"
                class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none bg-gray-50 focus:bg-white transition-colors"
                placeholder="Repeat new password"
                autocomplete="new-password"
              />
            </div>

            <!-- 🔒 Real-time Password Rules Checklist -->
            <div class="bg-gray-50 rounded-lg p-3 space-y-1.5 border">
              <p class="text-xs font-bold text-gray-500 uppercase mb-1">Password Requirements</p>
              <div class="grid grid-cols-2 gap-1">
                <div :class="passRules.minLength ? 'text-green-600' : 'text-gray-400'" class="text-xs flex items-center gap-1.5 transition-colors">
                  <span>{{ passRules.minLength ? '✅' : '⬜' }}</span> 10+ characters
                </div>
                <div :class="passRules.hasUpper ? 'text-green-600' : 'text-gray-400'" class="text-xs flex items-center gap-1.5 transition-colors">
                  <span>{{ passRules.hasUpper ? '✅' : '⬜' }}</span> Uppercase (A-Z)
                </div>
                <div :class="passRules.hasLower ? 'text-green-600' : 'text-gray-400'" class="text-xs flex items-center gap-1.5 transition-colors">
                  <span>{{ passRules.hasLower ? '✅' : '⬜' }}</span> Lowercase (a-z)
                </div>
                <div :class="passRules.hasNumber ? 'text-green-600' : 'text-gray-400'" class="text-xs flex items-center gap-1.5 transition-colors">
                  <span>{{ passRules.hasNumber ? '✅' : '⬜' }}</span> Number (0-9)
                </div>
                <div :class="passRules.hasSpecial ? 'text-green-600' : 'text-gray-400'" class="text-xs flex items-center gap-1.5 transition-colors">
                  <span>{{ passRules.hasSpecial ? '✅' : '⬜' }}</span> Special (!@#$)
                </div>
                <div :class="passRules.matches ? 'text-green-600' : 'text-gray-400'" class="text-xs flex items-center gap-1.5 transition-colors">
                  <span>{{ passRules.matches ? '✅' : '⬜' }}</span> Passwords match
                </div>
              </div>
            </div>

            <button 
              type="submit" 
              :disabled="isChangingPassword || !allRulesPassed"
              class="w-full bg-red-600 hover:bg-red-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold py-3 rounded-lg transition-colors shadow-md"
            >
              {{ isChangingPassword ? '⏳ Changing...' : '🔒 Change Password & Continue' }}
            </button>
          </form>
        </div>
      </div>
    </Teleport>
  </template>
</template>

<style>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

body {
  margin: 0;
  background-color: #f9fafb;
}

@keyframes modalIn {
  from { opacity: 0; transform: scale(0.9) translateY(20px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
.animate-modal-in {
  animation: modalIn 0.3s ease-out;
}
</style>