<script setup lang="ts">
import { useSettings } from '../../../composables/useSettings'
import { useAuthStore } from '../../../stores/auth'
import { useRoute } from 'vue-router'
import { computed } from 'vue'
import type { User } from '../../../types'
import BaseButton from '../../ui/BaseButton.vue'
import BaseCard from '../../ui/BaseCard.vue'
import BaseInput from '../../ui/BaseInput.vue'

// 1. Get Global Auth State directly
const authStore = useAuthStore()
const route = useRoute()
const forcePasswordChange = computed(() => route.query.force_password_change === '1')

// 2. Define Emits
const emit = defineEmits<{ 
  (e: 'update-user', user: User): void;
  (e: 'trigger-logout'): void;
}>()

// 3. Use Composable
const { 
  profileForm, 
  passForm, 
  isProfileLoading, 
  isPasswordLoading,
  saveProfile, 
  changePassword,
  showCurrentPass, 
  showNewPass
} = useSettings()
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto space-y-8 animate-fade-in">
    <!-- ⚠️ Forced Password Change Banner -->
    <div v-if="forcePasswordChange" class="bg-red-50 border-2 border-red-300 rounded-xl p-5 flex items-start gap-4 shadow-md">
      <span class="text-3xl">🚨</span>
      <div>
        <h3 class="font-bold text-red-800 text-lg">Password Change Required</h3>
        <p class="text-red-700 text-sm mt-1">Your password was recently reset by an administrator. You <b>must</b> set a new personal password below before you can continue using the portal.</p>
      </div>
    </div>

    <div class="flex items-center gap-4 mb-2">
      <h2 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
        <span>⚙️</span> Settings
      </h2>
    </div>

    <!-- Account Overview & Profile Settings -->
    <BaseCard>
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-8 border-b pb-6">
        <div>
           <h3 class="text-xl font-bold text-gray-800">👤 Account Overview</h3>
           <p class="text-sm text-gray-500 mt-1">View and update your personal details.</p>
        </div>
        
        <div class="flex items-center gap-4 bg-emerald-50 px-6 py-4 rounded-xl border border-emerald-100 shadow-sm w-full md:w-auto">
           <div class="h-14 w-14 bg-emerald-200 text-emerald-800 rounded-full flex items-center justify-center font-bold text-2xl border-4 border-white shadow-sm shrink-0">
             {{ authStore.currentUser?.name?.charAt(0).toUpperCase() || 'U' }}
           </div>
           <div class="flex flex-col">
             <span class="text-[10px] text-emerald-600 uppercase font-bold tracking-wider mb-0.5">Currently Logged In</span>
             <div class="font-bold text-gray-900 text-lg leading-tight">
                {{ authStore.currentUser?.name || 'Unknown User' }}
             </div>
             <div class="text-sm text-gray-600 font-medium">
                {{ authStore.currentUser?.email || 'No Email Found' }}
             </div>
           </div>
        </div>
      </div>
      
      <div>
        <form @submit.prevent="saveProfile">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <BaseInput 
                v-model="profileForm.name" 
                label="Display Name" 
                :placeholder="authStore.currentUser?.name"
            />
            <BaseInput 
                v-model="profileForm.email" 
                type="email" 
                label="Email Address" 
                :placeholder="authStore.currentUser?.email"
            />
          </div>
          <div class="flex justify-end mt-8">
            <BaseButton 
                type="submit" 
                :disabled="isProfileLoading"
                variant="primary"
                class="min-w-[150px]"
            >
              {{ isProfileLoading ? 'Updating...' : 'Save Profile Info' }}
            </BaseButton>
          </div>
        </form>
      </div>
    </BaseCard>

    <!-- Security Zone -->
    <BaseCard class="border-t-4 border-t-amber-500">
      <div class="mb-8 border-b pb-6">
          <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2"><span>🔒</span> Security Zone</h3>
          <p class="text-sm text-gray-500 mt-1">Update your password securely.</p>
      </div>
      
      <div>
        <form @submit.prevent="changePassword">
           <div class="bg-amber-50 p-4 rounded-xl border border-amber-200 mb-8 flex gap-4 items-start">
              <span class="text-2xl mt-1">🔑</span>
              <div>
                <h4 class="font-bold text-amber-900 text-sm">Password Policy</h4>
                <p class="text-xs text-amber-800 mt-1 leading-relaxed">To ensure security, you must enter your <b>Old Password</b> before creating a new one. Make sure your new password is strong and unique.</p>
              </div>
           </div>

           <div class="space-y-6 max-w-2xl">
              <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Old Password <span class="text-red-500">*</span></label>
                <div class="relative">
                  <input 
                    v-model="passForm.current" 
                    :type="showCurrentPass ? 'text' : 'password'" 
                    class="w-full border p-3 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none pr-10 bg-gray-50 focus:bg-white transition-colors" 
                    placeholder="Enter current password"
                  />
                  <button type="button" @click="showCurrentPass = !showCurrentPass" class="absolute inset-y-0 right-0 px-3 text-gray-400 hover:text-gray-600 transition-colors">
                    {{ showCurrentPass ? '🙈' : '👁️' }}
                  </button>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">New Password</label>
                    <div class="relative">
                      <input 
                        v-model="passForm.new" 
                        :type="showNewPass ? 'text' : 'password'" 
                        class="w-full border p-3 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none pr-10 bg-gray-50 focus:bg-white transition-colors" 
                        placeholder="Min. 6 chars"
                      />
                      <button type="button" @click="showNewPass = !showNewPass" class="absolute inset-y-0 right-0 px-3 text-gray-400 hover:text-gray-600 transition-colors">
                        {{ showNewPass ? '🙈' : '👁️' }}
                      </button>
                    </div>
                 </div>
                 <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Confirm New</label>
                    <input 
                        v-model="passForm.confirm" 
                        :type="showNewPass ? 'text' : 'password'" 
                        class="w-full border p-3 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none bg-gray-50 focus:bg-white transition-colors" 
                        placeholder="Repeat password"
                    />
                 </div>
              </div>
           </div>

           <div class="flex justify-end mt-8">
             <BaseButton 
                type="submit" 
                :disabled="isPasswordLoading"
                class="!bg-amber-600 hover:!bg-amber-700 !text-white border-none shadow-md min-w-[160px]"
             >
                {{ isPasswordLoading ? 'Verifying...' : 'Change Password' }}
             </BaseButton>
           </div>
        </form>
      </div>
    </BaseCard>
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.4s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>