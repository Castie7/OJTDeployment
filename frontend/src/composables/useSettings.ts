import { ref, reactive, watch, computed } from 'vue'
import api from '../services/api'
import { useToast } from './useToast'
// import type { User } from '../types'


import { useAuthStore } from '../stores/auth'

// 1. Add 'triggerLogout' to arguments
export function useSettings() {
  const authStore = useAuthStore()
  const currentUserRef = computed(() => authStore.currentUser)

  const isProfileLoading = ref(false)
  const isPasswordLoading = ref(false)

  const showCurrentPass = ref(false)
  const showNewPass = ref(false)
  const { showToast } = useToast()

  const profileForm = reactive({
    name: currentUserRef.value?.name || '',
    email: currentUserRef.value?.email || ''
  })

  const passForm = reactive({
    current: '',
    new: '',
    confirm: ''
  })

  watch(currentUserRef, (newUser) => {
    if (newUser) {
      profileForm.name = newUser.name
      profileForm.email = newUser.email || ''
    }
  }, { immediate: true, deep: true })

  // --- ACTION 1: SAVE PROFILE -> REFRESH PAGE ---
  const saveProfile = async () => {
    const user = currentUserRef.value
    if (!user) return

    isProfileLoading.value = true

    try {
      // ✅ Use api.post()
      // Automatically handles Base URL, CSRF Token, and Cookies
      const response = await api.post('/auth/update-profile', {
        user_id: user.id,
        name: profileForm.name,
        email: profileForm.email
      })

      if (response.data.status === 'success') {
        showToast("Profile updated successfully! The page will now refresh.", "success")
        // FORCE PAGE RELOAD to reflect changes in session
        window.location.reload()
      } else {
        showToast((response.data.message || "Failed"), "error")
      }

    } catch (error: any) {
      console.error(error)
      const msg = error.response?.data?.message || "Server Error"
      showToast(msg, "error")
    } finally {
      isProfileLoading.value = false
    }
  }

  // --- ACTION 2: CHANGE PASSWORD -> LOGOUT ---
  const changePassword = async () => {
    const user = currentUserRef.value
    if (!user) return

    if (!passForm.current) { showToast("Enter current password", "warning"); return }
    if (passForm.new.length < 6) { showToast("Password must be 6+ chars", "warning"); return }
    if (passForm.new !== passForm.confirm) { showToast("Passwords do not match", "warning"); return }

    isPasswordLoading.value = true

    try {
      // ✅ Use api.post()
      const response = await api.post('/auth/update-profile', {
        user_id: user.id,
        current_password: passForm.current,
        new_password: passForm.new
      })

      if (response.data.status === 'success') {
        showToast("Password changed successfully! Please login again.", "success")
        // TRIGGER LOGOUT
        authStore.logout()
      } else {
        showToast((response.data.message || "Failed"), "error")
      }

    } catch (error: any) {
      console.error(error)
      const msg = error.response?.data?.message || "Server Error"
      showToast(msg, "error")
    } finally {
      isPasswordLoading.value = false
    }
  }

  return {
    profileForm, passForm,
    isProfileLoading, isPasswordLoading,
    saveProfile, changePassword,
    showCurrentPass, showNewPass
  }
}