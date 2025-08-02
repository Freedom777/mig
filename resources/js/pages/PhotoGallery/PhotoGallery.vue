<template>
    <AppLayout>
        <div class="flex">
            <!-- Левая колонка с фильтрами -->
            <FiltersSidebar
                class="w-1/4"
                :filters="filters"
                v-model:selectedFilters="selectedFilters"
                @change="fetchPhotos"
            />

            <!-- Сетка фото -->
            <PhotoGrid
                class="w-3/4"
                :photos="photos"
                @load-more="loadMore"
            />
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import FiltersSidebar from './FiltersSidebar.vue'
import PhotoGrid from './PhotoGrid.vue'
import axios from 'axios';

const photos = ref([])
const filters = ref({ people: [], cities: [], tags: [], dateRange: [] })
const selectedFilters = ref({ people: [], cities: [], tags: [], dateFrom: null, dateTo: null })

const fetchFilters = async () => {
    const res = await axios.get('/api/filters')
    filters.value = res.data
}

const fetchPhotos = async () => {
    const res = await axios.get('/api/photos', { params: selectedFilters.value })
    photos.value = res.data.data
}

const loadMore = async () => {
    // заглушка под пагинацию
}

onMounted(async () => {
    await fetchFilters()
    await fetchPhotos()
})
</script>
