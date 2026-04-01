<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import PhotoGalleryHeader from './PhotoGalleryHeader.vue'
import FiltersSidebar from './FiltersSidebar.vue'
import PhotoGrid from './PhotoGrid.vue'
import axios from 'axios'

const page = usePage()

// Проверка авторизации
const isAuthenticated = computed(() => !!page.props.auth?.user)

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
    if (isAuthenticated.value) {
        await fetchFilters()
    }
    await fetchPhotos(1)
})
</script>

<template>
    <!-- Для залогиненных пользователей - используем AppLayout с sidebar -->
    <template v-if="isAuthenticated">
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar">
                <!-- Кастомный header с фильтрами вместо стандартного AppSidebarHeader -->
                <PhotoGalleryHeader
                    :selected-filters="selectedFilters"
                    @update:selected-filters="selectedFilters = $event"
                    @filters-changed="onFiltersChanged"
                />
                
                <!-- Основной контент: sidebar + grid -->
                <div class="flex flex-1">
                    <!-- Sidebar с селекторами фильтров -->
                    <FiltersSidebar
                        class="filters-sidebar"
                        :filters="filters"
                        v-model:selectedFilters="selectedFilters"
                        @filters-changed="onFiltersChanged"
                    />
                    
                    <!-- Сетка фотографий -->
                    <PhotoGrid
                        class="photo-grid-authenticated"
                        :photos="photos"
                        :on-load-more="loadMorePhotos"
                    />
                </div>
            </AppContent>
        </AppShell>
    </template>

    <!-- Для незалогиненных пользователей - простой контент без layout -->
    <div v-else class="photo-gallery-public min-h-screen">
        <!-- Только date range в простом header -->
        <PhotoGalleryHeader
            :selected-filters="selectedFilters"
            @update:selected-filters="selectedFilters = $event"
            @filters-changed="onFiltersChanged"
        />
        
        <!-- Сетка фотографий на всю ширину -->
        <PhotoGrid
            class="photo-grid-public"
            :photos="photos"
            :on-load-more="loadMorePhotos"
        />
    </div>
</template>

<script>
// Импорты для AppShell и AppContent (если они не в setup)
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
.filters-sidebar {
    width: 280px;
    flex-shrink: 0;
    border-right: 1px solid hsl(var(--border));
    background: hsl(var(--muted) / 0.3);
}

.photo-grid-authenticated {
    flex: 1;
    padding: 1.5rem;
}

/* Публичная галерея */
.photo-gallery-public {
    background: hsl(var(--background));
}

.photo-grid-public {
    padding: 1.5rem;
    max-width: 1600px;
    margin: 0 auto;
}

/* Responsive */
@media (max-width: 1024px) {
    .filters-sidebar {
        width: 240px;
    }
}

@media (max-width: 768px) {
    .filters-sidebar {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid hsl(var(--border));
    }
    
    .photo-grid-authenticated,
    .photo-grid-public {
        padding: 1rem;
    }
}
</style>
