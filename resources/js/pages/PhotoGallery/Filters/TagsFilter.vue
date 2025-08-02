<template>
    <div>
        <div class="flex justify-between items-center mb-2">
            <h3 class="font-bold">Теги</h3>
            <button
                v-if="selected.length"
                @click="clearAll"
                class="text-sm text-blue-600 hover:underline"
            >Очистить всё</button>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="item in inactiveItems"
                :key="item"
                @click="activate(item)"
                class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300"
            >{{ item }}</button>
        </div>

        <div v-if="selected.length" class="flex flex-wrap gap-2 mt-2">
            <div
                v-for="item in selected"
                :key="item"
                class="flex items-center bg-yellow-200 rounded px-2 py-1"
            >
                {{ item }}
                <button
                    @click="deactivate(item)"
                    class="ml-1 text-red-500 hover:text-red-700"
                >&times;</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    items: Array,
    selected: Array
})
const emit = defineEmits(['update:selected'])

const inactiveItems = computed(() => props.items.filter(i => !props.selected.includes(i)))

const activate = (item) => emit('update:selected', [...props.selected, item])
const deactivate = (item) => emit('update:selected', props.selected.filter(i => i !== item))
const clearAll = () => emit('update:selected', [])
</script>
