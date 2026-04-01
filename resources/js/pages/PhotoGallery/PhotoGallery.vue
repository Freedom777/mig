<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import PhotoGalleryHeader from './PhotoGalleryHeader.vue'
import PhotoGrid from './PhotoGrid.vue'
import axios from 'axios'

const page = usePage()

// Проверка авторизации
const isAuthenticated = computed(() => !!page.props.auth?.user)

const filters = ref({
    people: [],
    cities: [],
    tags: []
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
    // Фильтры загружаем для всех (и залогиненных, и незалогиненных)
    await fetchFilters()
    await fetchPhotos(1)
})
</script>

<template>
    <!-- Для залогиненных пользователей - AppLayout с sidebar -->
    <template v-if="isAuthenticated">
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar">
                <!-- Header с фильтрами -->
                <PhotoGalleryHeader
                    :selected-filters="selectedFilters"
                    :filters="filters"
                    :show-sidebar-toggle="true"
                    @update:selected-filters="selectedFilters = $event"
                    @filters-changed="onFiltersChanged"
                />
                
                <!-- Сетка фотографий на всю ширину -->
                <div class="photo-content">
                    <PhotoGrid
                        :photos="photos"
                        :on-load-more="loadMorePhotos"
                    />
                </div>
            </AppContent>
        </AppShell>
    </template>

    <!-- Для незалогиненных пользователей - простой layout -->
    <div v-else class="photo-gallery-public min-h-screen">
        <!-- Header с фильтрами (БЕЗ кнопки toggle sidebar) -->
        <PhotoGalleryHeader
            :selected-filters="selectedFilters"
            :filters="filters"
            :show-sidebar-toggle="false"
            @update:selected-filters="selectedFilters = $event"
            @filters-changed="onFiltersChanged"
        />
        
        <!-- Сетка фотографий на всю ширину -->
        <div class="photo-content-public">
            <PhotoGrid
                :photos="photos"
                :on-load-more="loadMorePhotos"
            />
        </div>
    </div>
</template>

<script>
// Импорты компонентов layout
import AppShell from '@/components/AppShell.vue'
import AppSidebar from '@/components/AppSidebar.vue'
import AppContent from '@/components/AppContent.vue'

export default {
    components: {
        AppShell,
        AppSidebar,
        AppContent
    }
}
</script>

<style scoped>
.photo-content {
    padding: 1.5rem;
}

.photo-gallery-public {
    background: hsl(var(--background));
}

.photo-content-public {
    padding: 1.5rem;
    max-width: 1600px;
    margin: 0 auto;
}

/* Responsive */
@media (max-width: 768px) {
    .photo-content,
    .photo-content-public {
        padding: 1rem;
    }
}
</style>
