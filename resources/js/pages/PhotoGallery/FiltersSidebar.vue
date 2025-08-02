<template>
    <div class="p-4 space-y-6">
        <PeopleFilter
            :items="filters.people"
            v-model:selected="selectedFilters.people"
        />
        <CityFilter
            :items="filters.cities"
            v-model:selected="selectedFilters.cities"
        />
        <TagsFilter
            :items="filters.tags"
            v-model:selected="selectedFilters.tags"
        />
        <DateFilter
            :range="filters.dateRange"
            v-model:dateFrom="selectedFilters.dateFrom"
            v-model:dateTo="selectedFilters.dateTo"
        />
    </div>
</template>

<script setup>
defineProps({
    filters: Object,
    selectedFilters: Object
})

defineEmits(['change'])
</script>
