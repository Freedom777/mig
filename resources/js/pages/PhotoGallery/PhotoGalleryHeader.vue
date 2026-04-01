<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue'
import { SidebarTrigger } from '@/components/ui/sidebar'
import VueSlider from 'vue-3-slider-component'
import PeopleFilter from './Filters/PeopleFilter.vue'
import CityFilter from './Filters/CityFilter.vue'
import TagsFilter from './Filters/TagsFilter.vue'
import axios from 'axios'

const props = defineProps({
    selectedFilters: {
        type: Object,
        required: true
    },
    filters: {
        type: Object,
        required: true
    },
    showSidebarToggle: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:selectedFilters', 'filters-changed'])

// Dropdown states
const showPeopleDropdown = ref(false)
const showCitiesDropdown = ref(false)
const showTagsDropdown = ref(false)

// Mobile detection
const isMobileView = ref(false)

const checkMobile = () => {
    isMobileView.value = window.innerWidth < 768
}

onMounted(() => {
    checkMobile()
    window.addEventListener('resize', checkMobile)
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile)
    document.removeEventListener('click', handleClickOutside)
})

// Refs для dropdown контейнеров
const peopleDropdownRef = ref(null)
const citiesDropdownRef = ref(null)
const tagsDropdownRef = ref(null)

const handleClickOutside = (event) => {
    // Закрываем dropdown если клик вне их
    if (peopleDropdownRef.value && !peopleDropdownRef.value.contains(event.target)) {
        showPeopleDropdown.value = false
    }
    if (citiesDropdownRef.value && !citiesDropdownRef.value.contains(event.target)) {
        showCitiesDropdown.value = false
    }
    if (tagsDropdownRef.value && !tagsDropdownRef.value.contains(event.target)) {
        showTagsDropdown.value = false
    }
}

const toggleDropdown = (dropdown) => {
    if (dropdown === 'people') {
        showPeopleDropdown.value = !showPeopleDropdown.value
        showCitiesDropdown.value = false
        showTagsDropdown.value = false
    } else if (dropdown === 'cities') {
        showCitiesDropdown.value = !showCitiesDropdown.value
        showPeopleDropdown.value = false
        showTagsDropdown.value = false
    } else if (dropdown === 'tags') {
        showTagsDropdown.value = !showTagsDropdown.value
        showPeopleDropdown.value = false
        showCitiesDropdown.value = false
    }
}

// Update filter wrapper
const updateFilter = (key, value) => {
    const updated = { ...props.selectedFilters, [key]: value }
    emit('update:selectedFilters', updated)
    emit('filters-changed')
}

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

const sliderWidth = computed(() => {
    return isMobileView.value ? '150px' : '280px'
})

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

const formatTooltip = (index) => {
    if (!availableDates.value[index]) return ''
    const [year, month] = availableDates.value[index].split('-')
    const monthName = new Date(`${year}-${month}-01`).toLocaleString('ru', { month: 'short' })
    return `${monthName} ${year}`
}

const updateDateRange = (val) => {
    if (!availableDates.value.length) return

    const startDate = availableDates.value[val[0]]
    const endDate = availableDates.value[val[1]]

    const updated = { ...props.selectedFilters, dateRange: [startDate, endDate] }
    emit('update:selectedFilters', updated)
    emit('filters-changed')
}

watch(() => props.selectedFilters.dateRange, (newValue) => {
    if (!availableDates.value.length || !newValue || !newValue[0]) return

    const startIndex = availableDates.value.findIndex(date => date === newValue[0])
    const endIndex = availableDates.value.findIndex(date => date === newValue[1])

    if (startIndex !== -1 && endIndex !== -1) {
        localRange.value = [startIndex, endIndex]
    }
}, { deep: true })

// Активные фильтры
const hasActiveFilters = computed(() => {
    const hasChips = props.selectedFilters.people?.length > 0 || 
                     props.selectedFilters.cities?.length > 0 || 
                     props.selectedFilters.tags?.length > 0
    
    const hasDateFilter = localRange.value[0] !== 0 || 
                          localRange.value[1] !== (availableDates.value.length - 1)
    
    return hasChips || hasDateFilter
})

// Очистить все фильтры
const clearAllFilters = () => {
    const updated = {
        people: [],
        cities: [],
        tags: [],
        dateRange: []
    }
    emit('update:selectedFilters', updated)
    emit('filters-changed')

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
        <!-- Левая часть: Toggle + Фильтры -->
        <div class="flex items-center gap-3 flex-1 min-w-0">
            <!-- SidebarTrigger только для залогиненных -->
            <SidebarTrigger v-if="showSidebarToggle" class="-ml-1" />

            <!-- Кнопка "Сбросить всё" -->
            <button
                v-if="hasActiveFilters"
                @click="clearAllFilters"
                class="text-xs text-muted-foreground hover:text-foreground underline transition-colors whitespace-nowrap"
            >
                Сбросить всё
            </button>

            <!-- Dropdown фильтры -->
            <div class="flex items-center gap-2 flex-wrap">
                <!-- People Filter Dropdown -->
                <div class="relative" ref="peopleDropdownRef">
                    <button
                        @click.stop="toggleDropdown('people')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                        :class="props.selectedFilters.people?.length > 0 
                            ? 'bg-gradient-to-r from-violet-600 to-purple-600 text-white' 
                            : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                    >
                        Имена
                        <span v-if="props.selectedFilters.people?.length > 0" class="ml-0.5">({{ props.selectedFilters.people.length }})</span>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    
                    <div v-if="showPeopleDropdown" class="dropdown-menu">
                        <PeopleFilter
                            :people="filters.people"
                            :model-value="selectedFilters.people"
                            @update:model-value="val => updateFilter('people', val)"
                        />
                    </div>
                </div>

                <!-- Cities Filter Dropdown -->
                <div class="relative" ref="citiesDropdownRef">
                    <button
                        @click.stop="toggleDropdown('cities')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                        :class="props.selectedFilters.cities?.length > 0 
                            ? 'bg-gradient-to-r from-violet-600 to-purple-600 text-white' 
                            : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                    >
                        Города
                        <span v-if="props.selectedFilters.cities?.length > 0" class="ml-0.5">({{ props.selectedFilters.cities.length }})</span>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    
                    <div v-if="showCitiesDropdown" class="dropdown-menu">
                        <CityFilter
                            :cities="filters.cities"
                            :model-value="selectedFilters.cities"
                            @update:model-value="val => updateFilter('cities', val)"
                        />
                    </div>
                </div>

                <!-- Tags Filter Dropdown -->
                <div class="relative" ref="tagsDropdownRef">
                    <button
                        @click.stop="toggleDropdown('tags')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                        :class="props.selectedFilters.tags?.length > 0 
                            ? 'bg-gradient-to-r from-violet-600 to-purple-600 text-white' 
                            : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                    >
                        Тэги
                        <span v-if="props.selectedFilters.tags?.length > 0" class="ml-0.5">({{ props.selectedFilters.tags.length }})</span>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    
                    <div v-if="showTagsDropdown" class="dropdown-menu">
                        <TagsFilter
                            :tags="filters.tags"
                            :model-value="selectedFilters.tags"
                            @update:model-value="val => updateFilter('tags', val)"
                        />
                    </div>
                </div>
            </div>
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
    </header>
</template>

<style scoped>
.slider-container {
    display: flex;
    align-items: center;
}

/* Dropdown menu */
.dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    margin-top: 0.5rem;
    background: hsl(var(--popover));
    border: 1px solid hsl(var(--border));
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    z-index: 50;
    min-width: 250px;
    max-height: 400px;
    overflow-y: auto;
    padding: 0.5rem;
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
