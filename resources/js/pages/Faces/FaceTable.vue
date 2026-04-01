<script>
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';

export default {
    layout: AppLayout,
    props: {
        initialImageId: {
            type: Number,
            default: null
        },
        FaceStatus: {
            type: Object,
            required: true
        },
    },
    data() {
        return {
            imageId: this.initialImageId,
            isFullscreen: false,
            faces: [],
            prevImage: null,
            nextImage: null,
            isLoading: false,
            isSaving: false,
            noImagesLeft: false
        };
    },
    computed: {
        imageUrl() {
            return this.imageId ? `/api/images/${this.imageId}/debug` : '';
        },
        /**
         * Все faces обработаны (не в статусе process)
         */
        allFacesProcessed() {
            return this.faces.length === 0 || this.faces.every(face => face.status !== this.FaceStatus.Process);
        },
        /**
         * Можно завершить: все обработаны и у ok-статусов есть имена
         */
        canComplete() {
            if (!this.allFacesProcessed) return false;

            // Проверяем что у всех ok-статусов есть имя
            return this.faces.every(face => {
                if (face.status === this.FaceStatus.Ok) {
                    return face.name && face.name.trim().length > 0;
                }
                return true;
            });
        },
        /**
         * Есть ли незаполненные имена для ok-статусов
         */
        hasMissingNames() {
            return this.faces.some(face =>
                face.status === this.FaceStatus.Ok && (!face.name || face.name.trim().length === 0)
            );
        },
    },
    async mounted() {
        if (!this.imageId) {
            this.noImagesLeft = true;
            return;
        }
        await this.loadFaces();
    },
    methods: {
        async checkNavigation() {
            try {
                const { data } = await axios.get(`/api/images/${this.imageId}/nearby`);
                this.nextImage = data.next;
                this.prevImage = data.prev;
            } catch (error) {
                console.error('Failed to check navigation:', error);
            }
        },

        async prevPhoto() {
            if (!this.prevImage || this.isLoading) return;
            this.imageId = this.prevImage.id;
            await this.loadFaces();
        },

        async nextPhoto() {
            if (!this.nextImage || this.isLoading) return;
            this.imageId = this.nextImage.id;
            await this.loadFaces();
        },

        async loadFaces() {
            if (!this.imageId) {
                this.noImagesLeft = true;
                this.faces = [];
                return;
            }

            this.isLoading = true;
            try {
                const response = await axios.get(`/api/images/${this.imageId}/faces`);
                this.faces = response.data.map(face => ({
                    ...face,
                    saved: false,
                    saving: false,
                }));

                await this.checkNavigation();
                this.noImagesLeft = !this.imageId;
            } catch (error) {
                console.error('Failed to load faces:', error);
                this.faces = [];
                this.noImagesLeft = true;
            } finally {
                this.isLoading = false;
            }
        },

        async saveFace(faceIndex, status) {
            const face = this.faces.find(f => f.face_index === faceIndex);
            if (!face) return;

            // Валидация: для ok нужно имя
            if (status === this.FaceStatus.Ok && (!face.name || face.name.trim().length === 0)) {
                alert('Please enter a name for this face');
                return;
            }

            face.saving = true;
            try {
                await axios.put(`/api/images/${this.imageId}/faces/${faceIndex}`, {
                    name: status === this.FaceStatus.Ok ? face.name.trim() : null,
                    status: status,
                }, {
                    headers: { Accept: 'application/json' }
                });

                face.status = status;
                face.saved = true;
                setTimeout(() => { face.saved = false; }, 2000);

                // Автоматически завершаем если все faces обработаны
                await this.autoCompleteIfReady();
            } catch (error) {
                console.error('Save failed:', error);
                alert('Failed to save face: ' + (error.response?.data?.message || error.message));
            } finally {
                face.saving = false;
            }
        },

        /**
         * Автоматически завершить изображение если все лица обработаны
         */
        async autoCompleteIfReady() {
            // Ждём следующий тик чтобы computed обновились
            await this.$nextTick();

            if (this.canComplete) {
                await this.completeImage();
            }
        },

        async completeImage() {
            if (!this.canComplete) return;

            this.isSaving = true;
            try {
                await axios.patch(`/api/images/${this.imageId}/status`, { status: 'ok' }, {
                    headers: { Accept: 'application/json' }
                });
                await this.goToNextImage();
            } catch (error) {
                console.error('Complete failed:', error);
                alert('Failed to complete image');
            } finally {
                this.isSaving = false;
            }
        },

        async recheckImage() {
            this.isSaving = true;
            try {
                await axios.patch(`/api/images/${this.imageId}/status`, { status: 'recheck' }, {
                    headers: { Accept: 'application/json' }
                });
                await this.goToNextImage();
            } catch (error) {
                console.error('Recheck failed:', error);
            } finally {
                this.isSaving = false;
            }
        },

        async removeImage() {
            if (!confirm('Are you sure you want to remove this image?')) return;

            this.isSaving = true;
            try {
                await axios.delete(`/api/images/${this.imageId}`);
                await this.goToNextImage();
            } catch (error) {
                console.error('Remove failed:', error);
            } finally {
                this.isSaving = false;
            }
        },

        async removeFace(faceIndex) {
            if (!confirm('Are you sure you want to remove this face?')) return;

            try {
                await axios.delete(`/api/images/${this.imageId}/faces/${faceIndex}`);
                this.faces = this.faces.filter(f => f.face_index !== faceIndex);
            } catch (error) {
                console.error('Remove failed:', error);
            }
        },

        addFace() {
            const maxIndex = this.faces.length > 0
                ? Math.max(...this.faces.map(f => f.face_index))
                : -1;

            this.faces.push({
                id: `temp-${Date.now()}`,
                face_index: maxIndex + 1,
                name: '',
                status: this.FaceStatus.Process,
                saved: false,
                saving: false,
                isNew: true,
            });
        },

        toggleFullscreen() {
            this.isFullscreen = !this.isFullscreen;
        },

        async goToNextImage() {
            if (this.nextImage) {
                this.imageId = this.nextImage.id;
                await this.loadFaces();
            } else if (this.prevImage) {
                this.imageId = this.prevImage.id;
                await this.loadFaces();
            } else {
                this.noImagesLeft = true;
            }
        },
    },
};
</script>

<template>
    <div class="face-editor">
        <div v-if="noImagesLeft" class="no-photos">
            No available photos to review.
        </div>

        <template v-else>
            <!-- Навигация и фото -->
            <div class="photo-navigation">
                <button
                    @click="prevPhoto"
                    :disabled="!prevImage || isLoading || isSaving"
                    class="nav-button"
                >
                    ← Back
                </button>

                <div class="photo-container">
                    <img
                        v-if="!isLoading"
                        :src="imageUrl"
                        :class="{ fullscreen: isFullscreen }"
                        class="photo"
                        @click="toggleFullscreen"
                        alt="Image for face recognition"
                    />
                    <div v-else class="loading">Loading image...</div>
                </div>

                <button
                    @click="nextPhoto"
                    :disabled="!nextImage || isLoading || isSaving"
                    class="nav-button"
                >
                    Next →
                </button>
            </div>

            <!-- Таблица лиц -->
            <table class="faces-table" v-if="faces.length > 0">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Status</th>
                    <th>Name</th>
                    <th colspan="3">Actions</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="face in faces" :key="face.id">
                    <td>{{ face.face_index }}</td>
                    <td>
                        <span :class="'status-' + face.status">{{ face.status }}</span>
                    </td>
                    <td>
                        <input
                            v-model="face.name"
                            type="text"
                            placeholder="Enter name"
                            :disabled="face.saving"
                            @keyup.enter="saveFace(face.face_index, FaceStatus.Ok)"
                        />
                    </td>
                    <td>
                        <button
                            @click="saveFace(face.face_index, FaceStatus.Ok)"
                            class="btn-save"
                            :disabled="face.saving || !face.name?.trim()"
                        >
                            {{ face.saving ? '...' : 'Save' }}
                        </button>
                    </td>
                    <td>
                        <button
                            @click="saveFace(face.face_index, FaceStatus.NotFace)"
                            class="btn-remove"
                            :disabled="face.saving"
                        >
                            Not a face
                        </button>
                        <button
                            @click="saveFace(face.face_index, FaceStatus.Unknown)"
                            class="btn-remove"
                            :disabled="face.saving"
                        >
                            Unknown
                        </button>
                    </td>
                    <td>
                        <span v-if="face.saved" class="saved-message">✓</span>
                    </td>
                </tr>
                </tbody>
            </table>
            <div v-else class="no-faces">
                No faces detected in this image.
            </div>

            <!-- Кнопки управления -->
            <div class="actions-panel">
                <button @click="addFace" class="btn-add" :disabled="isSaving">
                    + Add Face
                </button>

                <div class="main-actions">
                    <button
                        @click="completeImage"
                        class="btn-complete"
                        :class="{ 'btn-disabled': !canComplete }"
                        :disabled="!canComplete || isSaving"
                        :title="hasMissingNames ? 'Please enter names for all approved faces' : (!allFacesProcessed ? 'Please mark all faces first' : '')"
                    >
                        {{ isSaving ? 'Saving...' : 'Complete' }}
                    </button>
                    <button @click="recheckImage" class="btn-warning" :disabled="isSaving">
                        Recheck
                    </button>
                    <button @click="removeImage" class="btn-danger" :disabled="isSaving">
                        Delete
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped src="../../../css/FaceTable.css"></style>
