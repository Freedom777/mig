<template>
    <div class="cursor-pointer" @click="$emit('open', photo.image)">
        <img :src="photo.thumbnail" class="w-full h-auto" />
        <div class="text-xs mt-1">
            <span>{{ photo.date }}</span>
            <span v-if="photo.city"> — {{ photo.city }}</span>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    photo: { type: Object, required: true }
})

defineEmits(['open'])
</script>
