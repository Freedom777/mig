<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue';
import FiltersSidebar from './FiltersSidebar.vue'
import PhotoGrid from './PhotoGrid.vue'
import axios from 'axios'

// Состояния
const filters = ref({
    people: [],
    cities: [],
    tags: [],
    dateRange: [2000, new Date().getFullYear()]
})

const selectedFilters = ref({
    people: [],
    cities: [],
    tags: [],
    dateRange: [2000, new Date().getFullYear()]
})

// Фото с пагинацией
const photos = ref({
    data: [],
    current_page: 1,
    last_page: 1
})

// Загрузка фильтров
const fetchFilters = async () => {
    try {
        const res = await axios.get('/api/filters')
        filters.value = res.data
        selectedFilters.value.dateRange = res.data.dateRange
    } catch (error) {
        console.error('Ошибка загрузки фильтров:', error)
    }
}

// Загрузка фото
const fetchPhotos = async (page = 1) => {
    try {
        const res = await axios.get('/api/photos', {
            params: {
                ...selectedFilters.value,
                date_from: selectedFilters.value.dateRange[0],
                date_to: selectedFilters.value.dateRange[1],
                page
            }
        })

        if (page === 1) {
            photos.value = res.data.data
        } else {
            photos.value = {
                ...res.data.data,
                data: [...photos.value.data, ...res.data.data.data]
            }
        }
    } catch (error) {
        console.error('Ошибка загрузки фото:', error)
    }
}

// Реакция на изменения фильтров
const onFiltersChanged = () => {
    fetchPhotos()
}

// При монтировании
onMounted(async () => {
    await fetchFilters()
    await fetchPhotos()
})
</script>

<template>
    <AppLayout title="Фотогалерея">
        <div class="flex">
            <FiltersSidebar
                class="w-1/4"
                :filters="filters"
                v-model:selectedFilters="selectedFilters"
                @filters-changed="onFiltersChanged"
            />
            <PhotoGrid
                class="w-3/4"
                :photos="photos"
                :on-load-more="() => fetchPhotos(photos.current_page + 1)"
            />
        </div>
    </AppLayout>
</template>
