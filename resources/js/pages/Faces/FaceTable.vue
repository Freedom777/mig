<script setup>
import axios from 'axios'
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    initialImageId: {
        type: Number,
        required: true,
    },
})

const imageId = ref(props.initialImageId)
const faces = ref([])
const hasPrev = ref(false)
const hasNext = ref(true)
const isLoading = ref(false)

const imageUrl = computed(() => `/api/image/${imageId.value}.jpg`)

onMounted(async () => {
    await loadFaces()
    await checkNavigation()
})

async function checkNavigation() {
    try {
        const response = await axios.get(`/api/image/nearby?id=${imageId.value}`)
        hasNext.value = response.data.hasNext
        hasPrev.value = response.data.hasPrev
    } catch (e) {
        console.error('Navigation check failed', e)
    }
}

async function loadFaces() {
    isLoading.value = true
    try {
        const response = await axios.get(`/api/face/list?image_id=${imageId.value}`)
        faces.value = response.data.map(face => ({
            ...face,
            saved: false,
        }))
    } catch (error) {
        console.error('Failed to load faces:', error)
        faces.value = []
    } finally {
        isLoading.value = false
    }
}

async function prevPhoto() {
    if (!hasPrev.value) return
    imageId.value--
    await loadFaces()
    await checkNavigation()
}

async function nextPhoto() {
    if (!hasNext.value) return
    imageId.value++
    await loadFaces()
    await checkNavigation()
}

async function saveFace(index) {
    const face = faces.value[index]
    try {
        await router.post('/api/face/save', {
            image_id: imageId.value,
            face_index: index,
            name: face.name,
        }, { preserveScroll: true })

        face.saved = true
        setTimeout(() => { face.saved = false }, 3000)
    } catch (error) {
        console.error('Save failed:', error)
    }
}

async function removeFace(index) {
    try {
        await router.delete('/api/face/remove', {
            data: {
                image_id: imageId.value,
                face_index: index,
            },
            preserveScroll: true,
        })
        faces.value.splice(index, 1)
    } catch (error) {
        console.error('Remove failed:', error)
    }
}

function addFace() {
    faces.value.push({
        id: `temp-${Date.now()}-${Math.random()}`,
        name: '',
        saved: false,
    })
}
</script>

<template>
    <div class="face-editor">
        <!-- Навигация и фото -->
        <div class="photo-navigation">
            <button @click="prevPhoto" :disabled="!hasPrev || isLoading" class="nav-button">
                ← Back <span v-if="isLoading">⌛</span>
            </button>

            <div class="photo-container">
                <img v-if="!isLoading" :src="imageUrl" class="photo" />
                <div v-else class="loading">Loading image...</div>
            </div>

            <button @click="nextPhoto" :disabled="!hasNext || isLoading" class="nav-button">
                Next → <span v-if="isLoading">⌛</span>
            </button>
        </div>

        <!-- Таблица лиц или сообщение -->
        <div v-if="faces.length > 0">
            <table class="faces-table">
                <thead>
                <tr>
                    <th>Index</th>
                    <th>Name</th>
                    <th>Actions</th>
                    <th></th> <!-- Для статуса сохранения -->
                </tr>
                </thead>
                <tbody>
                <tr v-for="(face, index) in faces" :key="face.id">
                    <td>{{ index }}</td>
                    <td>
                        <input v-model="face.name" type="text" placeholder="Enter name" />
                    </td>
                    <td>
                        <button @click="saveFace(index)" class="btn-save">Save</button>
                        <button @click="removeFace(index)" class="btn-remove">Remove</button>
                    </td>
                    <td>
                        <span v-if="face.saved" class="saved-message">Saved!</span>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="no-faces">
            No faces detected in this image.
        </div>

        <!-- Кнопка добавления всегда видна -->
        <button @click="addFace" class="btn-add">Add Face</button>
    </div>
</template>

<style scoped src="../../../css/FaceTable.css"></style>
