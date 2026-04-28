<script setup lang="ts">
import { useLoginForm } from '../../../composables/useLoginForm'
import { ref } from 'vue'

// 1. Define Emits
const emit = defineEmits<{
  (e: 'login-success', data: any): void
  (e: 'back'): void
}>()

// 2. Use Composable
const { 
  email, 
  password, 
  message, 
  isSuccess, 
  isLoading, 
  isLockedOut,
  handleLogin 
} = useLoginForm(emit)

const showPassword = ref(false)
</script>

<template>
  <div class="min-h-screen flex items-center justify-center relative overflow-hidden font-sans bg-slate-900">
    
    <!-- Dynamic Background -->
    <div class="absolute inset-0 bg-[url('/bg-pattern.svg')] opacity-5"></div>
    <div class="absolute inset-0 bg-gradient-to-br from-emerald-900/80 via-slate-900 to-emerald-950/80 z-0"></div>
    
    <!-- Animated Blobs -->
    <div class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] bg-emerald-500/30 rounded-full blur-[100px] animate-blob"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[500px] h-[500px] bg-teal-500/30 rounded-full blur-[100px] animate-blob animation-delay-2000"></div>
    <div class="absolute top-[40%] left-[40%] w-[300px] h-[300px] bg-emerald-400/20 rounded-full blur-[80px] animate-blob animation-delay-4000"></div>

    <!-- Glass Card -->
    <div class="relative z-10 w-full max-w-md p-8 md:p-10 mx-4">
      <div 
        class="bg-white/10 backdrop-blur-xl border border-white/20 rounded-3xl shadow-2xl overflow-hidden relative group animate-slide-up"
      >
        <!-- Success Overlay -->
        <div 
          v-if="isSuccess" 
          class="absolute inset-0 z-20 bg-emerald-500 flex flex-col items-center justify-center text-white animate-success-reveal"
        >
           <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center text-emerald-600 text-4xl mb-4 animate-bounce-in">
             ✓
           </div>
           <h2 class="text-2xl font-bold animate-slide-up-sm">Welcome Back!</h2>
           <p class="text-white/80 mt-2 animate-slide-up-sm delay-100">Redirecting to dashboard...</p>
        </div>

        <!-- content -->
        <div class="relative z-10 p-8">
            
            <!-- Logo Section -->
            <div class="text-center mb-8">
                <div class="inline-block p-3 rounded-2xl bg-white/10 border border-white/10 mb-4 shadow-lg shadow-emerald-500/20 hover:scale-105 transition-transform duration-300">
                    <img src="/logo.svg" alt="Logo" class="w-12 h-12 object-contain drop-shadow-md">
                </div>
                <h1 class="text-3xl font-bold text-white tracking-tight mb-2">Research Portal</h1>
                <p class="text-emerald-200/80 text-sm">Benguet State University</p>
            </div>

            <form @submit.prevent="handleLogin" class="space-y-6" :class="{'animate-shake': message && !isSuccess}">
                
                <div class="space-y-1 group">
                    <label class="text-xs font-bold text-emerald-200/70 uppercase tracking-wider ml-1 group-focus-within:text-emerald-400 transition-colors">Email Address</label>
                    <div class="relative">
                        <input 
                            v-model="email" 
                            type="email" 
                            placeholder="username@bsu.edu.ph"
                            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500/50 transition-all shadow-inner"
                        />
                        <span class="absolute right-3 top-3.5 text-white/30">✉️</span>
                    </div>
                </div>

                <div class="space-y-1 group">
                    <label class="text-xs font-bold text-emerald-200/70 uppercase tracking-wider ml-1 group-focus-within:text-emerald-400 transition-colors">Password</label>
                    <div class="relative">
                        <input 
                            v-model="password" 
                            :type="showPassword ? 'text' : 'password'" 
                            placeholder="••••••••"
                            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500/50 transition-all shadow-inner"
                        />
                        <button 
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-3.5 text-white/30 hover:text-white transition-colors focus:outline-none"
                        >
                            {{ showPassword ? '👁️' : '🔒' }}
                        </button>
                    </div>
                </div>

                <!-- Error Message -->
                <Transition name="fade">
                  <div v-if="message && !isSuccess" class="bg-red-500/10 border border-red-500/20 text-red-200 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                    <span>⚠️</span> {{ message }}
                  </div>
                </Transition>

                <button 
                    type="submit" 
                    :disabled="isLoading || isLockedOut"
                    class="w-full bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-emerald-500/30 transform transition-all duration-300 hover:scale-[1.02] active:scale-[0.98] disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center gap-2 group relative overflow-hidden"
                >
                    <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                    <span v-if="isLoading" class="animate-spin w-5 h-5 border-2 border-white border-t-transparent rounded-full"></span>
                    <span v-else>Sign In</span>
                    <span v-if="!isLoading" class="group-hover:translate-x-1 transition-transform">→</span>
                </button>
            </form>

            <div class="mt-8 text-center">
                 <button @click="$emit('back')" class="text-sm text-emerald-200/60 hover:text-white transition-colors flex items-center justify-center gap-2 mx-auto group">
                    <span class="w-8 h-[1px] bg-emerald-200/20 group-hover:bg-white/50 transition-colors"></span>
                    Return to Website
                    <span class="w-8 h-[1px] bg-emerald-200/20 group-hover:bg-white/50 transition-colors"></span>
                 </button>
            </div>
        </div>
      </div>
      
      <p class="text-center text-white/20 text-[10px] mt-6 tracking-widest uppercase shadow-black drop-shadow-sm">
        &copy; {{ new Date().getFullYear() }} Benguet State University
      </p>
    </div>
  </div>
</template>

<style scoped>
/* Keyframes */
@keyframes blob {
  0% { transform: translate(0px, 0px) scale(1); }
  33% { transform: translate(30px, -50px) scale(1.1); }
  66% { transform: translate(-20px, 20px) scale(0.9); }
  100% { transform: translate(0px, 0px) scale(1); }
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-4px); }
    20%, 40%, 60%, 80% { transform: translateX(4px); }
}

@keyframes bounceIn {
    0% { transform: scale(0); opacity: 0; }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); opacity: 1; }
}

@keyframes successReveal {
    from { clip-path: circle(0% at 50% 50%); }
    to { clip-path: circle(150% at 50% 50%); }
}

/* Base Classes */
.animate-blob {
  animation: blob 15s infinite alternate ease-in-out;
}
.animation-delay-2000 { animation-delay: 2s; }
.animation-delay-4000 { animation-delay: 4s; }

.animate-slide-up {
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.animate-slide-up-sm {
    animation: slideUp 0.4s ease-out forwards;
}

.animate-shake {
    animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
}

.animate-success-reveal {
    animation: successReveal 0.8s cubic-bezier(0.77, 0, 0.175, 1) forwards;
}

.animate-bounce-in {
    animation: bounceIn 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

.delay-100 { animation-delay: 0.1s; }

/* Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>