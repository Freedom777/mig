<script setup>
import AppLayout from '@/layouts/AppLayout.vue'
import { ref } from 'vue'

const props = defineProps({
    groups: { type: Object, default: () => ({}) }
})

const output = ref('')
const running = ref(false)
let eventSource = null

const runCommand = (cmd) => {
    if (running.value) return
    output.value = ''
    running.value = true

    if (eventSource) {
        eventSource.close()
    }

    eventSource = new EventSource(`/admin/commands/stream?command=${encodeURIComponent(cmd)}`)

    eventSource.onmessage = (e) => {
        output.value += e.data + '\n'
    }

    eventSource.addEventListener('end', () => {
        running.value = false
        eventSource.close()
    })

    eventSource.onerror = () => {
        running.value = false
        eventSource.close()
    }
}

const buttonClass = (type) => {
    if (type === 'put') return 'bg-green-600 hover:bg-green-700'
    if (type === 'pop') return 'bg-purple-600 hover:bg-purple-700'
    return 'bg-gray-600 hover:bg-gray-700'
}
</script>

<template>
    <AppLayout title="Commands">
        <div class="p-6 space-y-8">
            <h1 class="text-3xl font-extrabold text-white-900">Commands</h1>

            <div v-for="(commands, title) in props.groups" :key="title" class="space-y-3">
                <h2 class="text-2xl font-bold text-white-800 border-b pb-1">{{ title }}</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <button
                        v-for="cmd in commands"
                        :key="cmd.command"
                        class="relative px-4 py-3 rounded-xl text-white shadow-md transition flex flex-col items-start"
                        :class="buttonClass(cmd.type)"
                        @click="runCommand(cmd.command)"
                        :disabled="running"
                    >
                        <span class="text-sm opacity-80">{{ cmd.label }}</span>
                        <span
                            class="absolute top-1 right-2 text-[10px] font-bold uppercase tracking-wider rounded px-1.5 py-0.5"
                            :class="cmd.type === 'put' ? 'bg-white/20 text-white' : 'bg-black/30 text-gray-100'"
                        >
              {{ cmd.type }}
            </span>
                    </button>
                </div>
            </div>

            <div v-if="output" class="mt-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-2">Output</h2>
                <pre class="bg-black text-green-400 p-4 rounded-xl max-h-96 overflow-y-auto whitespace-pre-wrap">
          {{ output }}
        </pre>
            </div>
        </div>
    </AppLayout>
</template>
