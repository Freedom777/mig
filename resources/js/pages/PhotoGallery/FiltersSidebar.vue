<script setup>
import PeopleFilter from './Filters/PeopleFilter.vue'
import CitiesFilter from './Filters/CityFilter.vue'
import TagsFilter from './Filters/TagsFilter.vue'
import DateFilter from './Filters/DateFilter.vue'

const props = defineProps({
    filters: {
        type: Object,
        required: true
    },
    selectedFilters: {
        type: Object,
        required: true
    }
})

const emit = defineEmits(['update:selectedFilters', 'filters-changed'])

// Wrapper for filter updates
const updateFilter = (key, value) => {
    emit('update:selectedFilters', { ...props.selectedFilters, [key]: value })
    emit('filters-changed')
}
</script>

<template>
    <div class="filters-sidebar">
        <div class="filters-content">
            <h2 class="sidebar-title">Filters</h2>

            <PeopleFilter
                :people="filters.people"
                :model-value="selectedFilters.people"
                @update:model-value="val => updateFilter('people', val)"
            />

            <CitiesFilter
                :cities="filters.cities"
                :model-value="selectedFilters.cities"
                @update:model-value="val => updateFilter('cities', val)"
            />

            <TagsFilter
                :tags="filters.tags"
                :model-value="selectedFilters.tags"
                @update:model-value="val => updateFilter('tags', val)"
            />

            <DateFilter
                :model-value="selectedFilters.dateRange"
                @update:model-value="val => updateFilter('dateRange', val)"
                @change="$emit('filters-changed')"
            />
        </div>
    </div>
</template>

<style scoped>
.filters-sidebar {
    padding: 1rem;
    /*background-color: #f9fafb;*/
    min-height: 100vh;
}

.filters-content {
    position: sticky;
    top: 1rem; /* Отступ от верха экрана */
    max-height: calc(100vh - 2rem); /* Максимальная высота с учетом отступов */
    overflow-y: auto; /* Прокрутка если содержимое не помещается */
}

.sidebar-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    /*color: #111827;*/
}

/* Опциональные стили для скроллбара */
.filters-content::-webkit-scrollbar {
    width: 6px;
}

.filters-content::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.filters-content::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.filters-content::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}
</style>
