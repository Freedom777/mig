<template>
    <BaseTagFilter
        :items="people"
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', $event)"
        title="Имена"
        item-key="id"
        item-label="name"
    />
</template>

<script setup>
import BaseTagFilter from './BaseTagFilter.vue'

defineProps({
    people: { type: Array, required: true },  // [{id: 1, name: 'Олег'}, ...]
    modelValue: { type: Array, default: () => [] }  // [{id: 1, name: 'Олег'}, ...]
})

defineEmits(['update:modelValue'])
</script>
