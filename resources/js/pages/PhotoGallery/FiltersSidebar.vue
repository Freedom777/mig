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

// Обёртка для обновлений
const updateFilter = (key, value) => {
    emit('update:selectedFilters', { ...props.selectedFilters, [key]: value })
    emit('filters-changed')
}
</script>

<template>
    <div>
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
            :min-year="filters.dateRange[0]"
            :max-year="filters.dateRange[1]"
            :model-value="selectedFilters.dateRange"
            @update:model-value="val => updateFilter('dateRange', val)"
        />
    </div>
</template>
