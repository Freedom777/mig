<template>
    <BaseTagFilter
        :items="tags"
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', $event)"
        title="Тэги"
    />
</template>

<script setup>
import BaseTagFilter from "@/pages/PhotoGallery/Filters/BaseTagFilter.vue";

const props = defineProps({
    tags: { type: Array, required: true },
    modelValue: { type: Array, default: () => [] }
})
defineEmits(['update:modelValue'])
</script>
