<template>
    <div>
        <div class="flex justify-between items-center mb-2">
            <h3 class="font-bold">Дата</h3>
            <button
                v-if="range[0] !== minYear || range[1] !== maxYear"
                @click="clearAll"
                class="text-sm text-blue-600 hover:underline"
            >
                Очистить всё
            </button>
        </div>

        <Slider
            v-model="range"
            :min="minYear"
            :max="maxYear"
            :step="1"
            :lazy="true"
            :tooltips="true"
            class="w-full"
        />
        <div class="flex justify-between text-sm mt-2">
            <span>{{ range[0] }}</span>
            <span>{{ range[1] }}</span>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import Slider from '@vueform/slider'
import '@vueform/slider/themes/default.css'

// Принимаем минимальный/максимальный год и массив дат
const props = defineProps({
    minYear: { type: Number, required: true },
    maxYear: { type: Number, required: true },
    modelValue: { type: Array, default: () => [] }
})

// Эмитим обновление массива
const emit = defineEmits(['update:modelValue'])

// Локальное состояние слайдера
const range = ref(
    props.modelValue.length
        ? props.modelValue
        : [props.minYear, props.maxYear]
)

// Следим за изменениями и эмитим их наружу
watch(range, (val) => {
    emit('update:modelValue', val)
})

// Кнопка "Очистить всё" сбрасывает диапазон к полному
const clearAll = () => {
    range.value = [props.minYear, props.maxYear]
}
</script>

<style scoped>
.date-filter {
    padding: 10px;
}
</style>
