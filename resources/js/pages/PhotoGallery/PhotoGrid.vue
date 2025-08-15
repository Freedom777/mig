<script setup>
import PhotoCard from './PhotoCard.vue'
import { ref, computed, onMounted, onUnmounted } from "vue";

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
const isLoading = ref(false)
const lastScrollTime = ref(0)
const scrollTimeout = ref(null)

const openModal = (imageUrl) => {
    selectedImage.value = imageUrl
}

const closeModal = () => {
    selectedImage.value = null
}

const loadMore = async () => {
    if (isLoading.value || !hasMorePages.value) return

    isLoading.value = true
    try {
        await props.onLoadMore()
    } catch (error) {
        console.error('Error loading more photos:', error)
    } finally {
        isLoading.value = false
    }
}

const hasMorePages = computed(() => {
    return props.photos.current_page < props.photos.last_page
})

// Throttled scroll handler to prevent multiple API calls
const handleScroll = () => {
    const now = Date.now()

    // Throttle: only check scroll position every 100ms
    if (now - lastScrollTime.value < 100) return
    lastScrollTime.value = now

    if (isLoading.value || !hasMorePages.value) return

    const scrollTop = window.pageYOffset || document.documentElement.scrollTop
    const scrollHeight = document.documentElement.scrollHeight
    const clientHeight = window.innerHeight

    // Load more when user scrolls to within 200px of the bottom
    const threshold = 200
    if (scrollTop + clientHeight >= scrollHeight - threshold) {
        // Clear any existing timeout
        if (scrollTimeout.value) {
            clearTimeout(scrollTimeout.value)
        }

        // Debounce: wait 150ms before actually triggering load
        scrollTimeout.value = setTimeout(() => {
            // Double-check conditions before loading
            if (!isLoading.value && hasMorePages.value) {
                const currentScrollTop = window.pageYOffset || document.documentElement.scrollTop
                const currentScrollHeight = document.documentElement.scrollHeight
                const currentClientHeight = window.innerHeight

                if (currentScrollTop + currentClientHeight >= currentScrollHeight - threshold) {
                    loadMore()
                }
            }
        }, 150)
    }
}

onMounted(() => {
    window.addEventListener('scroll', handleScroll)
})

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll)
    // Clear timeout on unmount
    if (scrollTimeout.value) {
        clearTimeout(scrollTimeout.value)
    }
})
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

        <!-- Индикатор загрузки -->
        <div v-if="isLoading" class="text-center mt-4">
            <div class="inline-flex items-center px-4 py-2 text-gray-600">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Загрузка...
            </div>
        </div>

        <!-- Кнопка "Посмотреть ещё" (показывается только если не загружаем) -->
        <div v-if="!isLoading && hasMorePages" class="text-center mt-4">
            <button
                @click="loadMore"
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
            >
                Посмотреть ещё
            </button>
        </div>

        <!-- Индикатор окончания -->
        <div v-if="!hasMorePages && photos.data.length > 0" class="text-center mt-4 text-gray-500">
            Все фотографии загружены
        </div>

        <!-- Модальное окно -->
        <div
            v-if="selectedImage"
            class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50"
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
                class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300"
            >
                ×
            </button>
        </div>
    </div>
</template>
