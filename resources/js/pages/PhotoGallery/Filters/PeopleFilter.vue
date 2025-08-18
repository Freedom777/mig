<template>
    <BaseTagFilter
        :items="people"
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', $event)"
        title="Имена"
    />
</template>

<script setup>
import BaseTagFilter from './BaseTagFilter.vue'

defineProps({
    people: { type: Array, required: true },
    modelValue: { type: Array, default: () => [] }
})

defineEmits(['update:modelValue'])
</script>
