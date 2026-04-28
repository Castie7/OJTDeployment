<script setup lang="ts">
import { useUserManagement } from '../../../composables/useUserManagement'
import BaseButton from '../../ui/BaseButton.vue'
import BaseCard from '../../ui/BaseCard.vue'
import BaseInput from '../../ui/BaseInput.vue'
import BaseSelect from '../../ui/BaseSelect.vue'

const { 
  users, isLoading, isSubmitting, showAddForm, form, 
  addUser,
  statusUpdatingId, toggleUserStatus, isCurrentUser,
  showResetModal, resetTarget, resetForm, isResetting, resetError,
  showResetPass, resetPassRules, allResetRulesPassed,
  openResetModal, closeResetModal, submitResetPassword
} = useUserManagement()

const roleOptions = [
  { value: 'admin', label: 'Admin' },
  { value: 'user', label: 'Researcher' }
]
</script>

<template>
  <div class="p-6 space-y-6">
    
    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
      <h2 class="text-2xl font-bold text-gray-800">User Management</h2>
      <BaseButton 
        @click="showAddForm = !showAddForm" 
        variant="primary"
      >
        {{ showAddForm ? '- Close Form' : '+ Add New User' }}
      </BaseButton>
    </div>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <BaseCard v-if="showAddForm" class="border-t-4 border-t-emerald-500">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <BaseInput
            v-model="form.name"
            label="Full Name"
            placeholder="e.g. Juan Dela Cruz"
          />
          
          <BaseInput
            v-model="form.email"
            label="Email Address"
            type="email"
            placeholder="user@school.edu.ph"
          />

          <BaseInput
            v-model="form.password"
            label="Initial Password"
            type="password"
            placeholder="••••••••"
          />

          <BaseSelect
            v-model="form.role"
            label="Role"
            :options="roleOptions"
          />
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
          <BaseButton @click="showAddForm = false" variant="ghost">Cancel</BaseButton>
          <BaseButton 
            @click="addUser" 
            :disabled="isSubmitting" 
            variant="primary"
          >
            {{ isSubmitting ? 'Saving...' : 'Create Account' }}
          </BaseButton>
        </div>
      </BaseCard>
    </transition>

    <BaseCard no-padding class="overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead class="bg-gray-50 text-gray-600 uppercase text-xs font-semibold tracking-wider border-b border-gray-200">
            <tr>
              <th class="px-6 py-4">ID</th>
              <th class="px-6 py-4">Name</th>
              <th class="px-6 py-4">Email</th>
              <th class="px-6 py-4">Role</th>
              <th class="px-6 py-4">Status</th>
              <th class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="isLoading">
              <td colspan="6" class="px-6 py-8 text-center">
                <div class="flex flex-col items-center gap-2">
                  <div class="w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
                  <span class="text-sm text-gray-500 animate-pulse">Loading directory...</span>
                </div>
              </td>
            </tr>

            <tr v-else-if="users.length === 0">
              <td colspan="6" class="px-6 py-8 text-center text-gray-500">No users found.</td>
            </tr>

            <tr v-else v-for="user in users" :key="user.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-6 py-4 text-sm text-gray-500">#{{ user.id }}</td>
              <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ user.name }}</td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ user.email }}</td>
              <td class="px-6 py-4">
                <span 
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                  :class="user.role === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'"
                >
                  {{ user.role === 'user' ? 'Researcher' : 'Admin' }}
                </span>
              </td>
              <td class="px-6 py-4">
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                  :class="user.is_disabled ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800'"
                >
                  {{ user.is_disabled ? 'Disabled' : 'Active' }}
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <BaseButton 
                    @click="openResetModal(user.id, user.name)" 
                    variant="outline" 
                    size="sm"
                  >
                    Reset Password
                  </BaseButton>
                  <BaseButton
                    @click="toggleUserStatus(user)"
                    :variant="user.is_disabled ? 'primary' : 'danger'"
                    size="sm"
                    :disabled="statusUpdatingId === user.id || isCurrentUser(user.id)"
                  >
                    {{
                      isCurrentUser(user.id)
                        ? 'Current Account'
                        : statusUpdatingId === user.id
                          ? (user.is_disabled ? 'Enabling...' : 'Disabling...')
                          : (user.is_disabled ? 'Enable' : 'Disable')
                    }}
                  </BaseButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>

    <!-- 🔐 Reset Password Modal -->
    <Teleport to="body">
      <div v-if="showResetModal" 
           class="fixed inset-0 z-[9998] flex items-center justify-center"
           style="background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);"
      >
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden animate-modal-in">
          <!-- Header -->
          <div class="bg-gradient-to-r from-amber-500 to-amber-600 px-6 py-5 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
              <span class="text-2xl">🔑</span>
              <div>
                <h2 class="text-lg font-bold">Reset Password</h2>
                <p class="text-amber-100 text-sm">for {{ resetTarget.name }}</p>
              </div>
            </div>
            <button @click="closeResetModal" class="text-white/80 hover:text-white text-2xl leading-none">&times;</button>
          </div>

          <!-- Body -->
          <form @submit.prevent="submitResetPassword" class="p-6 space-y-4">
            <!-- Error Banner -->
            <div v-if="resetError" class="bg-red-50 border border-red-300 rounded-lg p-3 text-sm text-red-700 flex items-start gap-2">
              <span class="shrink-0">❌</span>
              <span>{{ resetError }}</span>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
              This will reset <b>{{ resetTarget.name }}'s</b> password. They will be <b>logged out immediately</b> and forced to change it on next login.
            </div>

            <!-- New Password -->
            <div>
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5">New Password</label>
              <div class="relative">
                <input 
                  v-model="resetForm.password" 
                  :type="showResetPass ? 'text' : 'password'"
                  class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none bg-gray-50 focus:bg-white transition-colors"
                  placeholder="Enter new password"
                  autocomplete="new-password"
                />
                <button type="button" @click="showResetPass = !showResetPass" class="absolute inset-y-0 right-0 px-3 text-gray-400 hover:text-gray-600">
                  {{ showResetPass ? '🙈' : '👁️' }}
                </button>
              </div>
            </div>

            <!-- Password Rules -->
            <div class="bg-gray-50 rounded-lg p-3 space-y-1.5 border">
              <p class="text-xs font-bold text-gray-500 uppercase mb-1">Password Requirements</p>
              <div class="grid grid-cols-2 gap-1">
                <div :class="resetPassRules.minLength ? 'text-green-600' : 'text-gray-400'" class="text-xs flex items-center gap-1.5 transition-colors">
                  <span>{{ resetPassRules.minLength ? '✅' : '⬜' }}</span> 10+ characters
                </div>
                <div :class="resetPassRules.hasUpper ? 'text-green-600' : 'text-gray-400'" class="text-xs flex items-center gap-1.5 transition-colors">
                  <span>{{ resetPassRules.hasUpper ? '✅' : '⬜' }}</span> Uppercase (A-Z)
                </div>
                <div :class="resetPassRules.hasLower ? 'text-green-600' : 'text-gray-400'" class="text-xs flex items-center gap-1.5 transition-colors">
                  <span>{{ resetPassRules.hasLower ? '✅' : '⬜' }}</span> Lowercase (a-z)
                </div>
                <div :class="resetPassRules.hasNumber ? 'text-green-600' : 'text-gray-400'" class="text-xs flex items-center gap-1.5 transition-colors">
                  <span>{{ resetPassRules.hasNumber ? '✅' : '⬜' }}</span> Number (0-9)
                </div>
                <div :class="resetPassRules.hasSpecial ? 'text-green-600' : 'text-gray-400'" class="text-xs flex items-center gap-1.5 transition-colors">
                  <span>{{ resetPassRules.hasSpecial ? '✅' : '⬜' }}</span> Special (!@#$)
                </div>
              </div>
            </div>

            <div class="flex gap-3 pt-2">
              <button 
                type="button"
                @click="closeResetModal"
                class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-3 rounded-lg transition-colors"
              >
                Cancel
              </button>
              <button 
                type="submit" 
                :disabled="isResetting || !allResetRulesPassed"
                class="flex-1 bg-amber-600 hover:bg-amber-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold py-3 rounded-lg transition-colors shadow-md"
              >
                {{ isResetting ? '⏳ Resetting...' : '🔑 Reset Password' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<style scoped>
@keyframes modalIn {
  from { opacity: 0; transform: scale(0.9) translateY(20px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
.animate-modal-in {
  animation: modalIn 0.3s ease-out;
}
</style>
