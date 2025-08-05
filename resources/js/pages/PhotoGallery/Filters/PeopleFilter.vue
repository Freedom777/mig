<template>
    <div>
        <div class="flex justify-between items-center mb-2">
            <h3 class="font-bold">Имена</h3>
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
                v-for="person in people"
                :key="person"
                @click="toggleSelection(person)"
                :class="['text-left', selected.includes(person) ? 'font-bold text-blue-600' : 'text-gray-700']"
            >
                {{ person }}
                <span v-if="selected.includes(person)">✕</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    people: { type: Array, required: true },
    modelValue: { type: Array, default: () => [] }
})
const emit = defineEmits(['update:modelValue'])

const selected = ref([...props.modelValue])

watch(selected, (val) => {
    emit('update:modelValue', val)
})

const toggleSelection = (person) => {
    selected.value = selected.value.includes(person)
        ? selected.value.filter(p => p !== person)
        : [...selected.value, person]
}

const clearAll = () => {
    selected.value = []
}
</script>
