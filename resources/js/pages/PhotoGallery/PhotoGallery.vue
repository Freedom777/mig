<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import FiltersSidebar from './FiltersSidebar.vue'
import PhotoGrid from './PhotoGrid.vue'
import axios from 'axios'

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
    dateRange: []
})

// Структура как ожидает PhotoGrid
const photos = ref({
    data: [],
    current_page: 1,
    last_page: 1
})

const isLoading = ref(false)

const fetchFilters = async () => {
    try {
        const res = await axios.get('/api/filters')
        filters.value = res.data
    } catch (error) {
        console.error('Error loading filters:', error)
    }
}

const fetchPhotos = async (page = 1) => {
    if (isLoading.value) return

    isLoading.value = true
    try {
        const params = { page }

        if (selectedFilters.value.people.length) {
            params.person_ids = selectedFilters.value.people.map(p => p.id)
        }

        if (selectedFilters.value.cities.length) {
            params.cities = selectedFilters.value.cities
        }

        if (selectedFilters.value.tags.length) {
            params.tags = selectedFilters.value.tags
        }

        if (selectedFilters.value.dateRange?.length === 2) {
            params.date_from = selectedFilters.value.dateRange[0]
            params.date_to = selectedFilters.value.dateRange[1]
        }

        const res = await axios.get('/api/photos', { params })

        if (page === 1) {
            // Первая страница — заменяем
            photos.value = {
                data: res.data.data,
                current_page: res.data.meta.current_page,
                last_page: res.data.meta.last_page
            }
        } else {
            // Подгрузка — добавляем
            photos.value = {
                data: [...photos.value.data, ...res.data.data],
                current_page: res.data.meta.current_page,
                last_page: res.data.meta.last_page
            }
        }
    } catch (error) {
        console.error('Error loading photos:', error)
    } finally {
        isLoading.value = false
    }
}

const onFiltersChanged = () => {
    fetchPhotos(1)
}

const loadMorePhotos = () => {
    if (photos.value.current_page < photos.value.last_page) {
        fetchPhotos(photos.value.current_page + 1)
    }
}

onMounted(async () => {
    await fetchFilters()
    await fetchPhotos(1)
})
</script>

<template>
    <AppLayout title="Photo Gallery">
        <div class="flex min-h-screen">
            <FiltersSidebar
                class="w-1/4"
                :filters="filters"
                v-model:selectedFilters="selectedFilters"
                @filters-changed="onFiltersChanged"
            />
            <PhotoGrid
                class="w-3/4"
                :photos="photos"
                :on-load-more="loadMorePhotos"
            />
        </div>
    </AppLayout>
</template>
