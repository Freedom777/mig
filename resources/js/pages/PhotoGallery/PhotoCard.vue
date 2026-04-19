<template>
    <div class="relative cursor-pointer group" @click="$emit('open', photo.image)">
        <img :src="photo.thumbnail" class="w-full h-auto" />

        <div class="text-xs mt-1">
            <span>{{ photo.date }}</span>
            <span v-if="photo.city"> — {{ photo.city }}</span>
        </div>

        <!-- Кнопка удаления — только для администратора -->
        <button
            v-if="isAdmin"
            class="
                absolute top-1 right-1
                w-7 h-7 flex items-center justify-center
                bg-red-600 hover:bg-red-700 text-white
                rounded-full text-lg leading-none
                opacity-0 group-hover:opacity-100
                transition-opacity duration-200
                z-10
            "
            title="Удалить фото"
            :disabled="isDeleting"
            @click.stop="handleDelete"
        >
            <span v-if="isDeleting" class="text-xs">…</span>
            <span v-else>&times;</span>
        </button>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'

const props = defineProps({
    photo: { type: Object, required: true },
    isAdmin: { type: Boolean, default: false },
})

const emit = defineEmits(['open', 'deleted'])

const isDeleting = ref(false)

const handleDelete = async () => {
    if (!confirm('Удалить это фото? Действие необратимо.')) return

    isDeleting.value = true
    try {
        await axios.delete(`/api/images/${props.photo.id}`)
        emit('deleted', props.photo.id)
    } catch (error) {
        console.error('Ошибка при удалении:', error)
        alert('Не удалось удалить фото. Попробуйте ещё раз.')
    } finally {
        isDeleting.value = false
    }
}
</script>
