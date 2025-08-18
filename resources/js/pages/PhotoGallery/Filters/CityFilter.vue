<template>
    <BaseTagFilter
        :items="cities"
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', $event)"
        title="Города"
    />
</template>

<script setup>
import BaseTagFilter from "@/pages/PhotoGallery/Filters/BaseTagFilter.vue";

const props = defineProps({
    cities: { type: Array, required: true },
    modelValue: { type: Array, default: () => [] }
})
defineEmits(['update:modelValue'])
</script>
