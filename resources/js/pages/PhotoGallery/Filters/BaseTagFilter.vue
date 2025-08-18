<!-- BaseTagFilter.vue - базовый компонент -->
<template>
    <div>
        <div class="flex justify-between items-center mb-3">
            <h3 class="font-bold">{{ title }}</h3>
            <button
                v-if="selected.length && showClearButton"
                @click="clearAll"
                class="text-sm text-blue-600 hover:underline transition-colors duration-200"
            >
                {{ clearButtonText }}
            </button>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="item in items"
                :key="getItemKey(item)"
                @click="toggleSelection(item)"
                :class="[
                    'inline-flex items-center px-2 py-1 rounded-md text-xs transition-all duration-200',
                    isSelected(item)
                        ? selectedClass
                        : unselectedClass
                ]"
            >
                {{ getItemLabel(item) }}
                <span v-if="isSelected(item)" :class="selectedIconClass">{{ selectedIcon }}</span>
            </button>
        </div>

        <!-- Показать количество, если нужно -->
        <div v-if="showCount && selected.length" class="mt-2 text-xs text-gray-500">
            Выбрано: {{ selected.length }}
        </div>
    </div>
</template>

<script setup>
import {computed, ref, watch} from 'vue'

const props = defineProps({
    // Основные параметры
    items: {
        type: Array,
        required: true
    },
    modelValue: {
        type: Array,
        default: () => []
    },
    title: {
        type: String,
        required: true
    },

    // Настройки отображения
    itemKey: {
        type: String,
        default: null
    }, // Для объектов: какое поле использовать как ключ
    itemLabel: {
        type: String,
        default: null
    }, // Для объектов: какое поле показывать как текст

    // Кнопка очистки
    showClearButton: {
        type: Boolean,
        default: true
    },
    clearButtonText: {
        type: String,
        default: 'Очистить всё'
    },

    // Стили
    selectedClass: {
        type: String,
        default: 'bg-blue-100 text-blue-800 font-medium border border-blue-200'
    },
    unselectedClass: {
        type: String,
        default: 'bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-200'
    },
    selectedIcon: {
        type: String,
        default: '✕'
    },
    selectedIconClass: {
        type: String,
        default: 'ml-1 text-blue-600'
    },

    // Дополнительные опции
    showCount: {
        type: Boolean,
        default: false
    },
    multiSelect: {
        type: Boolean,
        default: true
    } // Множественный или одиночный выбор
})

const emit = defineEmits(['update:modelValue'])

// const selected = ref([...props.modelValue])
// Вместо ref используем computed для синхронизации с родителем
const selected = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
})

/*
watch(selected, (val) => {
    emit('update:modelValue', val)
}, { deep: true })*/

// Получение ключа элемента (для простых строк или объектов)
const getItemKey = (item) => {
    if (props.itemKey && typeof item === 'object') {
        return item[props.itemKey]
    }
    return item
}

// Получение отображаемого текста
const getItemLabel = (item) => {
    if (props.itemLabel && typeof item === 'object') {
        return item[props.itemLabel]
    }
    return item
}

// Проверка выбран ли элемент
const isSelected = (item) => {
    const key = getItemKey(item)
    return selected.value.some(selectedItem =>
        getItemKey(selectedItem) === key
    )
}

// Переключение выбора
const toggleSelection = (item) => {
    const key = getItemKey(item)

    if (props.multiSelect) {
        // Множественный выбор
        if (isSelected(item)) {
            selected.value = selected.value.filter(selectedItem =>
                getItemKey(selectedItem) !== key
            )
        } else {
            selected.value = [...selected.value, item]
        }
    } else {
        // Одиночный выбор
        selected.value = isSelected(item) ? [] : [item]
    }
}

// Очистка всех выборов
const clearAll = () => {
    selected.value = []
}
</script>

<!-- Примеры использования: -->

<!-- PeopleFilter.vue -->
<!--
<template>
    <BaseTagFilter
        :items="people"
        v-model="modelValue"
        title="Имена"
        @update:model-value="$emit('update:modelValue', $event)"
    />
</template>

<script setup>
import BaseTagFilter from './BaseTagFilter.vue'

defineProps({
    people: { type: Array, required: true },
    modelValue: { type: Array, default: () => [] }
})

defineEmits(['update:modelValue'])
</script>
-->

<!-- CitiesFilter.vue -->
<!--
<template>
    <BaseTagFilter
        :items="cities"
        v-model="modelValue"
        title="Города"
        selected-class="bg-green-100 text-green-800 font-medium border border-green-200"
        selected-icon="✓"
        selected-icon-class="ml-1 text-green-600"
        @update:model-value="$emit('update:modelValue', $event)"
    />
</template>

<script setup>
import BaseTagFilter from './BaseTagFilter.vue'

defineProps({
    cities: { type: Array, required: true },
    modelValue: { type: Array, default: () => [] }
})

defineEmits(['update:modelValue'])
</script>
-->

<!-- TagsFilter.vue для объектов -->
<!--
<template>
    <BaseTagFilter
        :items="tags"
        v-model="modelValue"
        title="Теги"
        item-key="id"
        item-label="name"
        selected-class="bg-purple-100 text-purple-800 font-medium border border-purple-200"
        selected-icon="🏷️"
        :show-count="true"
        @update:model-value="$emit('update:modelValue', $event)"
    />
</template>

<script setup>
import BaseTagFilter from './BaseTagFilter.vue'

// tags = [{ id: 1, name: 'Природа' }, { id: 2, name: 'Семья' }]
defineProps({
    tags: { type: Array, required: true },
    modelValue: { type: Array, default: () => [] }
})

defineEmits(['update:modelValue'])
</script>
-->

<!-- Фильтр с одиночным выбором -->
<!--
<template>
    <BaseTagFilter
        :items="categories"
        v-model="modelValue"
        title="Категория"
        :multi-select="false"
        clear-button-text="Сбросить"
        @update:model-value="$emit('update:modelValue', $event)"
    />
</template>
-->
