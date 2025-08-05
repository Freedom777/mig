<template>
    <div>
        <div class="flex justify-between items-center mb-2">
            <h3 class="font-bold">Города</h3>
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
                v-for="city in cities"
                :key="city"
                @click="toggleSelection(city)"
                :class="['text-left', selected.includes(city) ? 'font-bold text-blue-600' : 'text-gray-700']"
            >
                {{ city }}
                <span v-if="selected.includes(city)">✕</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    cities: { type: Array, required: true },
    modelValue: { type: Array, default: () => [] }
})
const emit = defineEmits(['update:modelValue'])

const selected = ref([...props.modelValue])

watch(selected, (val) => {
    emit('update:modelValue', val)
})

const toggleSelection = (city) => {
    selected.value = selected.value.includes(city)
        ? selected.value.filter(c => c !== city)
        : [...selected.value, city]
}

const clearAll = () => {
    selected.value = []
}
</script>
