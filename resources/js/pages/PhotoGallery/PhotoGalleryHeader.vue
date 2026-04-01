<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue'
import { SidebarTrigger } from '@/components/ui/sidebar'
import VueSlider from 'vue-3-slider-component'
import axios from 'axios'

const props = defineProps({
    selectedFilters: {
        type: Object,
        required: true
    }
})

const emit = defineEmits(['update:selectedFilters', 'filters-changed'])

// Mobile detection
const isMobileView = ref(false)
const showFiltersModal = ref(false)

const checkMobile = () => {
    isMobileView.value = window.innerWidth < 768
}

onMounted(() => {
    checkMobile()
    window.addEventListener('resize', checkMobile)
})

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile)
})

// Date range slider
const availableDates = ref([])
const localRange = ref([0, 0])

const loadAvailableDates = async () => {
    try {
        const res = await axios.get('/api/photos/date-available')
        availableDates.value = res.data.map(d => d.date)

        if (availableDates.value.length > 0) {
            localRange.value = [0, availableDates.value.length - 1]
            updateDateRange([0, availableDates.value.length - 1])
        }
    } catch (error) {
        console.error('Error loading available dates:', error)
    }
}

// Маркеры для desktop слайдера (каждый 3-й месяц)
const dateMarks = computed(() => {
    if (!availableDates.value.length) return {}

    const marks = {}
    availableDates.value.forEach((date, i) => {
        if (i === 0 || i === availableDates.value.length - 1 || i % 3 === 0) {
            const [year, month] = date.split('-')
            const monthName = new Date(`${year}-${month}-01`).toLocaleString('ru', { month: 'short' })
            marks[i] = `${monthName} ${year}`
        }
    })
    return marks
})

// Ширина слайдера в зависимости от экрана
const sliderWidth = computed(() => {
    return isMobileView.value ? '150px' : '280px'
})

// Отображение текущего диапазона
const displayRange = computed(() => {
    if (!availableDates.value.length || !localRange.value) return ''

    const startDate = availableDates.value[localRange.value[0]]
    const endDate = availableDates.value[localRange.value[1]]

    const formatDate = (dateStr) => {
        const [year, month] = dateStr.split('-')
        const monthName = new Date(`${year}-${month}-01`).toLocaleString('ru', { month: 'short' })
        return `${monthName} ${year}`
    }

    if (startDate === endDate) {
        return formatDate(startDate)
    }

    return `${formatDate(startDate)} — ${formatDate(endDate)}`
})

// Форматирование тултипа
const formatTooltip = (index) => {
    if (!availableDates.value[index]) return ''
    const [year, month] = availableDates.value[index].split('-')
    const monthName = new Date(`${year}-${month}-01`).toLocaleString('ru', { month: 'short' })
    return `${monthName} ${year}`
}

// Обновление диапазона дат
const updateDateRange = (val) => {
    if (!availableDates.value.length) return

    const startDate = availableDates.value[val[0]]
    const endDate = availableDates.value[val[1]]

    const updated = { ...props.selectedFilters, dateRange: [startDate, endDate] }
    emit('update:selectedFilters', updated)
    emit('filters-changed')
}

// Следим за изменениями dateRange извне
watch(() => props.selectedFilters.dateRange, (newValue) => {
    if (!availableDates.value.length || !newValue || !newValue[0]) return

    const startIndex = availableDates.value.findIndex(date => date === newValue[0])
    const endIndex = availableDates.value.findIndex(date => date === newValue[1])

    if (startIndex !== -1 && endIndex !== -1) {
        localRange.value = [startIndex, endIndex]
    }
}, { deep: true })

// Список активных фильтров для отображения
const activeFiltersList = computed(() => {
    const filters = []

    // People (объекты с id и name)
    if (props.selectedFilters.people?.length) {
        props.selectedFilters.people.forEach(person => {
            filters.push({
                key: `people-${person.id}`,
                label: person.name,
                type: 'people',
                value: person
            })
        })
    }

    // Cities (строки)
    if (props.selectedFilters.cities?.length) {
        props.selectedFilters.cities.forEach(city => {
            filters.push({
                key: `cities-${city}`,
                label: city,
                type: 'cities',
                value: city
            })
        })
    }

    // Tags (строки)
    if (props.selectedFilters.tags?.length) {
        props.selectedFilters.tags.forEach(tag => {
            filters.push({
                key: `tags-${tag}`,
                label: tag,
                type: 'tags',
                value: tag
            })
        })
    }

    return filters
})

const hasActiveFilters = computed(() => {
    // Проверяем чипы фильтров
    const hasChips = activeFiltersList.value.length > 0

    // Проверяем dateRange - активен если не полный диапазон
    const hasDateFilter = localRange.value[0] !== 0 ||
        localRange.value[1] !== (availableDates.value.length - 1)

    return hasChips || hasDateFilter
})

const activeFiltersCount = computed(() => activeFiltersList.value.length)

// Удаление фильтра
const removeFilter = (filter) => {
    const updated = { ...props.selectedFilters }

    if (filter.type === 'people') {
        updated.people = updated.people.filter(p => p.id !== filter.value.id)
    } else if (filter.type === 'cities') {
        updated.cities = updated.cities.filter(c => c !== filter.value)
    } else if (filter.type === 'tags') {
        updated.tags = updated.tags.filter(t => t !== filter.value)
    }

    emit('update:selectedFilters', updated)
    emit('filters-changed')
}

// Очистить все фильтры
const clearAllFilters = () => {
    const updated = {
        people: [],
        cities: [],
        tags: [],
        dateRange: [] // Сбрасываем dateRange
    }
    emit('update:selectedFilters', updated)
    emit('filters-changed')

    // Сбрасываем слайдер на полный диапазон
    if (availableDates.value.length > 0) {
        localRange.value = [0, availableDates.value.length - 1]
    }
}

onMounted(loadAvailableDates)
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <!-- Левая часть: SidebarTrigger + активные фильтры -->
        <div class="flex items-center gap-3 flex-1 min-w-0">
            <SidebarTrigger class="-ml-1" />

            <!-- Кнопка "Сбросить всё" если есть активные фильтры -->
            <button
                v-if="hasActiveFilters"
                @click="clearAllFilters"
                class="text-xs text-muted-foreground hover:text-foreground underline"
            >
                Сбросить всё
            </button>

            <!-- Desktop: активные фильтры чипами -->
            <div v-if="hasActiveFilters && !isMobileView" class="flex flex-wrap gap-2 items-center">
                <span
                    v-for="filter in activeFiltersList"
                    :key="filter.key"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-gradient-to-r from-violet-600 to-purple-600 text-white rounded-full text-xs font-medium"
                >
                    {{ filter.label }}
                    <button
                        @click="removeFilter(filter)"
                        class="flex items-center justify-center w-4 h-4 ml-0.5 bg-white/20 hover:bg-white/30 rounded-full text-white text-sm leading-none transition-colors"
                    >
                        ×
                    </button>
                </span>
            </div>

            <!-- Mobile: кнопка "Фильтры" с счётчиком -->
            <button
                v-if="isMobileView && hasActiveFilters"
                @click="showFiltersModal = true"
                class="inline-flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-violet-600 to-purple-600 text-white rounded-md text-xs font-medium"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                </svg>
                Фильтры ({{ activeFiltersCount }})
            </button>

            <!-- Если нет активных фильтров -->
            <span v-if="!hasActiveFilters && !isMobileView" class="text-sm text-muted-foreground italic">
                Все фотографии
            </span>
        </div>

        <!-- Правая часть: date range slider -->
        <div v-if="availableDates.length" class="flex items-center gap-3 flex-shrink-0">
            <div class="text-sm font-medium text-foreground whitespace-nowrap">
                {{ displayRange }}
            </div>
            <div class="slider-container">
                <VueSlider
                    v-model="localRange"
                    :min="0"
                    :max="availableDates.length - 1"
                    :marks="isMobileView ? {} : dateMarks"
                    :step="1"
                    :lazy="false"
                    :tooltip="'active'"
                    :tooltip-formatter="formatTooltip"
                    :width="sliderWidth"
                    :dot-size="14"
                    :enable-cross="false"
                    :min-range="0"
                    range
                    @change="updateDateRange"
                />
            </div>
        </div>

        <!-- Mobile: Modal с фильтрами -->
        <Teleport to="body">
            <div v-if="showFiltersModal && isMobileView" class="fixed inset-0 bg-black/50 flex items-end justify-center z-50" @click="showFiltersModal = false">
                <div class="bg-background rounded-t-2xl w-full max-h-[70vh] overflow-y-auto" @click.stop>
                    <div class="flex items-center justify-between p-4 border-b border-border">
                        <h3 class="text-lg font-semibold">Активные фильтры</h3>
                        <button @click="showFiltersModal = false" class="w-8 h-8 flex items-center justify-center text-2xl text-muted-foreground">
                            ×
                        </button>
                    </div>
                    <div class="p-4 space-y-2">
                        <div
                            v-for="filter in activeFiltersList"
                            :key="filter.key"
                            class="flex items-center justify-between p-3 bg-muted rounded-lg"
                        >
                            <span class="text-sm">{{ filter.label }}</span>
                            <button
                                @click="removeFilter(filter)"
                                class="w-7 h-7 flex items-center justify-center bg-destructive text-destructive-foreground rounded-full text-lg"
                            >
                                ×
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </header>
</template>

<style scoped>
.slider-container {
    display: flex;
    align-items: center;
}

/* Стилизация слайдера для тёмной темы */
:deep(.vue-slider-process) {
    background: linear-gradient(90deg, rgb(124 58 237) 0%, rgb(147 51 234) 100%);
}

:deep(.vue-slider-rail) {
    background: hsl(var(--border));
    border-radius: 15px;
    height: 5px;
}

:deep(.vue-slider-dot-handle) {
    background: hsl(var(--background));
    box-shadow: 0 2px 8px rgba(124, 58, 237, 0.3);
    border: 2.5px solid rgb(124 58 237);
}

:deep(.vue-slider-dot-handle:hover) {
    border-color: rgb(147 51 234);
    box-shadow: 0 3px 12px rgba(124, 58, 237, 0.4);
}

:deep(.vue-slider-dot-tooltip) {
    background: hsl(var(--popover));
    color: hsl(var(--popover-foreground));
    font-weight: 500;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    border: 1px solid hsl(var(--border));
}

:deep(.vue-slider-mark-label) {
    font-size: 0.7rem;
    color: hsl(var(--muted-foreground));
    margin-top: 0.5rem;
}

:deep(.vue-slider-mark-step) {
    background: hsl(var(--border));
}

/* Responsive */
@media (max-width: 768px) {
    .slider-container {
        max-width: 150px;
    }
}
</style>
