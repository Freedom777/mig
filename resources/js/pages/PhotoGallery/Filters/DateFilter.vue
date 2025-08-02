<template>
    <div>
        <div class="flex justify-between items-center mb-2">
            <h3 class="font-bold">Дата</h3>
            <button
                v-if="dateFrom || dateTo"
                @click="clearAll"
                class="text-sm text-blue-600 hover:underline"
            >Очистить всё</button>
        </div>

        <vue-slider
            v-model="range"
            :min="minYear"
            :max="maxYear"
            :interval="1"
            :tooltip="'always'"
            :lazy="true"
        />
        <p class="mt-2 text-sm text-gray-600">
            {{ range[0] }} - {{ range[1] }}
        </p>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/default.css'

const props = defineProps({
    range: Array,
    dateFrom: Number,
    dateTo: Number
})
const emit = defineEmits(['update:dateFrom', 'update:dateTo'])

const minYear = props.range[0] || 2000
const maxYear = props.range[1] || new Date().getFullYear()
const range = ref([props.dateFrom || minYear, props.dateTo || maxYear])

watch(range, (val) => {
    emit('update:dateFrom', val[0])
    emit('update:dateTo', val[1])
})

const clearAll = () => {
    range.value = [minYear, maxYear]
}
</script>
