<template>
    <div class="grid grid-cols-4 gap-4 p-4">
        <PhotoCard
            v-for="photo in photos"
            :key="photo.id"
            :photo="photo"
        />
    </div>
    <div class="text-center mt-4">
        <button @click="$emit('load-more')" class="px-4 py-2 bg-blue-600 text-white rounded">Показать ещё</button>
    </div>
</template>

<script setup>
import PhotoCard from './PhotoCard.vue'

defineProps({
    photos: Array
})
</script>
