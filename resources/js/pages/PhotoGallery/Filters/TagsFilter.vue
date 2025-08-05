<template>
    <div>
        <div class="flex justify-between items-center mb-2">
            <h3 class="font-bold">Теги</h3>
            <button
                v-if="selected.length"
                @click="clearAll"
                class="text-sm text-blue-600 hover:underline"
            >
                Очистить всё
            </button>
        </div>

        <div class="flex flex-col gap-1">
            <button
                v-for="tag in tags"
                :key="tag"
                @click="toggleSelection(tag)"
                :class="['text-left', selected.includes(tag) ? 'font-bold text-blue-600' : 'text-gray-700']"
            >
                {{ tag }}
                <span v-if="selected.includes(tag)">✕</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    tags: { type: Array, required: true },
    modelValue: { type: Array, default: () => [] }
})
const emit = defineEmits(['update:modelValue'])

const selected = ref([...props.modelValue])

watch(selected, (val) => {
    emit('update:modelValue', val)
})

const toggleSelection = (tag) => {
    selected.value = selected.value.includes(tag)
        ? selected.value.filter(t => t !== tag)
        : [...selected.value, tag]
}

const clearAll = () => {
    selected.value = []
}
</script>
