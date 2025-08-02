<template>
    <div class="border rounded shadow hover:shadow-lg transition">
        <img :src="photo.thumbnail" alt="" class="w-full h-48 object-cover rounded-t">
        <div class="p-2 text-sm text-gray-700">
            <p>{{ formatDate(photo.date) }}</p>
            <p>{{ photo.city }}</p>
        </div>
    </div>
</template>

<script setup>
defineProps({ photo: Object })

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('ru-RU', { year: 'numeric', month: 'long' })
}
</script>
