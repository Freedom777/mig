<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import FiltersSidebar from './FiltersSidebar.vue'
import PhotoGrid from './PhotoGrid.vue'
import axios from 'axios'

// Filter states
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

// Photos with pagination
const photos = ref({
    data: [],
    current_page: 1,
    last_page: 1
})

// Load filter options
const fetchFilters = async () => {
    try {
        const res = await axios.get('/api/filters')
        filters.value = res.data

        // Initialize selected filters if not already set
        if (!selectedFilters.value.people.length && res.data.people) {
            selectedFilters.value.people = []
        }
        if (!selectedFilters.value.cities.length && res.data.cities) {
            selectedFilters.value.cities = []
        }
        if (!selectedFilters.value.tags.length && res.data.tags) {
            selectedFilters.value.tags = []
        }
        // DateFilter will set its own initial range
    } catch (error) {
        console.error('Error loading filters:', error)
    }
}

// Load photos with current filters
const fetchPhotos = async (page = 1) => {
    try {
        // Prepare parameters
        const params = {
            people: selectedFilters.value.people,
            cities: selectedFilters.value.cities,
            tags: selectedFilters.value.tags,
            page
        }

        // Add date range if set (DateFilter will provide date strings like "2024-01")
        if (selectedFilters.value.dateRange && selectedFilters.value.dateRange.length === 2) {
            params.date_from = selectedFilters.value.dateRange[0]
            params.date_to = selectedFilters.value.dateRange[1]
        }

        const res = await axios.get('/api/photos', { params })

        if (page === 1) {
            photos.value = res.data.data
        } else {
            photos.value = {
                ...res.data.data,
                data: [...photos.value.data, ...res.data.data.data]
            }
        }
    } catch (error) {
        console.error('Error loading photos:', error)
    }
}

// Handle filter changes
const onFiltersChanged = () => {
    fetchPhotos(1) // Always start from page 1 when filters change
}

// Load more photos for pagination
const loadMorePhotos = () => {
    if (photos.value.current_page < photos.value.last_page) {
        fetchPhotos(photos.value.current_page + 1)
    }
}

// Initialize on component mount
onMounted(async () => {
    await fetchFilters()
    // fetchPhotos will be called automatically when DateFilter sets initial range
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
