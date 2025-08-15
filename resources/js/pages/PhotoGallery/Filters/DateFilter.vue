<template>
    <div class="date-filter">
        <h3 class="filter-title">Date Range</h3>
        <div class="flex gap-4 items-center" v-if="availableDates.length">
            <div class="flex flex-col items-center w-full">
                <div class="date-display mb-2">
                    {{ displayRange }}
                </div>
                <VueSlider
                    v-model="localRange"
                    direction="ttb"
                    :min="0"
                    :max="availableDates.length - 1"
                    :marks="dateMarks"
                    :step="1"
                    :lazy="false"
                    :tooltip="'always'"
                    :tooltip-formatter="formatTooltip"
                    height="400px"
                    :dot-size="16"
                    :enable-cross="false"
                    :min-range="0"
                    range
                    @change="updateRange"
                />
            </div>
        </div>
        <div v-else class="loading">
            Loading dates...
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import VueSlider from 'vue-3-slider-component'
import axios from 'axios'

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [2000, new Date().getFullYear()]
    }
})

const emit = defineEmits(['update:modelValue', 'change'])

// Available dates from API
const availableDates = ref([])
const localRange = ref([0, 0])

// Load available dates from API
const loadAvailableDates = async () => {
    try {
        const res = await axios.get('/api/photos/date-available')
        availableDates.value = res.data.map(d => d.date)

        // Set initial range to full range
        if (availableDates.value.length > 0) {
            localRange.value = [0, availableDates.value.length - 1]
            // Emit initial values
            updateRange([0, availableDates.value.length - 1])
        }
    } catch (error) {
        console.error('Error loading available dates:', error)
    }
}

// Create marks for the slider
const dateMarks = computed(() => {
    if (!availableDates.value.length) return {}

    return Object.fromEntries(
        availableDates.value.map((date, i) => {
            const [year, month] = date.split('-')
            const monthName = new Date(`${year}-${month}-01`).toLocaleString('default', { month: 'short' })
            return [
                i,
                {
                    label: `${monthName} ${year}`,
                    style: {
                        position: 'absolute',
                        transform: 'translateY(-50%)',
                        whiteSpace: 'nowrap',
                        left: '110%'
                    }
                }
            ]
        })
    )
})

// Display current range
const displayRange = computed(() => {
    if (!availableDates.value.length || !localRange.value) return ''

    const startDate = availableDates.value[localRange.value[0]]
    const endDate = availableDates.value[localRange.value[1]]

    const formatDate = (dateStr) => {
        const [year, month] = dateStr.split('-')
        const monthName = new Date(`${year}-${month}-01`).toLocaleString('default', { month: 'short' })
        return `${monthName} ${year}`
    }

    return `${formatDate(startDate)} — ${formatDate(endDate)}`
})

// Tooltip formatter
const formatTooltip = (index) => {
    if (!availableDates.value[index]) return ''
    const [year, month] = availableDates.value[index].split('-')
    const monthName = new Date(`${year}-${month}-01`).toLocaleString('default', { month: 'short' })
    return `${monthName} ${year}`
}

// Update range and emit changes
const updateRange = (val) => {
    if (!availableDates.value.length) return

    const startDate = availableDates.value[val[0]]
    const endDate = availableDates.value[val[1]]

    emit('update:modelValue', [startDate, endDate])
    emit('change')
}

// Watch for external changes to modelValue
watch(() => props.modelValue, (newValue) => {
    if (!availableDates.value.length || !newValue) return

    // Find indices for the new values
    const startIndex = availableDates.value.findIndex(date => date === newValue[0])
    const endIndex = availableDates.value.findIndex(date => date === newValue[1])

    if (startIndex !== -1 && endIndex !== -1) {
        localRange.value = [startIndex, endIndex]
    }
})

onMounted(loadAvailableDates)
</script>

<style scoped>
.date-filter {
    padding: 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.filter-title {
    font-weight: 600;
    margin-bottom: 1rem;
    color: #374151;
}

.date-display {
    font-weight: 500;
    color: #4b5563;
    text-align: center;
    min-height: 1.5rem;
}

.loading {
    text-align: center;
    color: #6b7280;
    padding: 2rem;
}

.vue-slider {
    height: 400px;
}

:deep(.vue-slider-vertical .vue-slider-mark) {
    display: flex;
    align-items: center;
}
</style>
