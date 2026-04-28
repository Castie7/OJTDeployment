import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from './router'

import { createPinia } from 'pinia'

// Create the app instance
const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Mount the app to the #app div in your HTML
app.mount('#app')