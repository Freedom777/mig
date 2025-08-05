<script setup>
import PhotoCard from './PhotoCard.vue'
import {ref} from "vue";

defineOptions({
    inheritAttrs: false
})

const props = defineProps({
    photos: {
        type: Object,
        default: () => ({ data: [] }),
        required: true
    },
    onLoadMore: {
        type: Function,
        required: true,
        default: null
    }
})

const selectedImage = ref(null)

const openModal = (imageUrl) => {
    selectedImage.value = imageUrl
}

const closeModal = () => {
    selectedImage.value = null
}
</script>

<template>
    <div v-bind="$attrs">
        <!-- Сетка миниатюр -->
        <div v-if="photos.data.length" class="grid grid-cols-4 gap-4">
            <PhotoCard
                v-for="photo in photos.data"
                :key="photo.id"
                :photo="photo"
                @open="openModal"
            />
        </div>

        <!-- Отсутствие фоток -->
        <div v-else class="text-gray-500 text-center py-10">
            Нет фотографий
        </div>

        <!-- Кнопка "Посмотреть ещё" -->
        <div v-if="onLoadMore && photos.current_page < photos.last_page" class="text-center mt-4">
            <button
                @click="onLoadMore"
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
            >
                Посмотреть ещё
            </button>
        </div>

        <!-- Модальное окно -->
        <div
            v-if="selectedImage"
            class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center"
        >
            <!-- Клик по изображению тоже закрывает -->
            <img
                :src="selectedImage"
                class="max-w-full max-h-full cursor-pointer"
                @click="closeModal"
            />
            <!-- Кнопка закрытия -->
            <button
                @click="closeModal"
                class="absolute top-4 right-4 text-white text-2xl"
            >
                ×
            </button>
        </div>
    </div>
</template>
