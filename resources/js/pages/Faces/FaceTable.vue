<script>

import AppLayout from '@/layouts/AppLayout.vue';
// import { router } from '@inertiajs/vue3';
import axios from 'axios';

export default {
    layout: AppLayout,
    props: {
        initialImageId: {
            type: Number,
            required: true,
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
            noImagesLeft: false,
        };
    },
    computed: {
        imageUrl() {
            return `/api/image/debug/${this.imageId}.jpg`;
        },
        canComplete() {
            return this.faces.every(face => face.status !== 'process');
        },
    },
    async mounted() {
        if (!this.imageId) {
            this.noImagesLeft = true;
            return;
        }

        await this.loadFaces();
        // await this.checkNavigation();
    },
    methods: {
        async checkNavigation() {
            const { data } = await axios.get(`/api/image/${this.imageId}/nearby`);
            this.nextImage = data.next;
            this.prevImage = data.prev;
        },
        async prevPhoto() {
            if (!this.prevImage) {
                // Если нет предыдущего фото, остаёмся на текущем
                return;
            }

            this.imageId = this.prevImage.id;
            await this.loadFaces();
        },
        async nextPhoto() {
            if (!this.nextImage) {
                // Если нет следующего фото, остаёмся на текущем
                return;
            }

            this.imageId = this.nextImage.id;
            await this.loadFaces();
        },
        async loadFaces() {
            this.isLoading = true;
            try {
                const response = await axios.get(`/api/face/list?image_id=${this.imageId}`);
                this.faces = response.data.map(face => ({
                    ...face,
                    saved: false,
                }));

                await this.checkNavigation();

                // Если imageId реально не существует (например, API вернул null)
                this.noImagesLeft = !this.imageId;

            } catch (error) {
                console.error('Failed to load faces:', error);
                this.faces = [];
                this.noImagesLeft = true; // только при ошибке запроса
            } finally {
                this.isLoading = false;
            }
        },
        async saveFace(index, status = null) {
            const face = this.faces[index];
            try {
                await axios.post('/api/face/save', {
                    image_id: this.imageId,
                    face_index: index,
                    name: (status !== 'ok') ? null : face.name,
                    status: status,
                }, {
                    headers: {
                        Accept: 'application/json',
                    }
                });

                // Если статус изменился — обновим в таблице
                if (status !== null && face.status !== status) {
                    this.faces[index].status = status;
                }

                face.saved = true;
                setTimeout(() => { face.saved = false; }, 3000);
            } catch (error) {
                console.error('Save failed:', error);
            }
        },
        async completeImage() {
            try {
                await axios.patch(`/api/image/${this.imageId}/status`, { status: 'ok' }, {
                    headers: { Accept: 'application/json' }
                });

                // Если есть следующее фото — идем к нему
                if (this.nextImage) {
                    this.imageId = this.nextImage.id;
                    await this.loadFaces();
                }
                // Если есть предыдущее фото — идем к нему
                else if (this.prevImage) {
                    this.imageId = this.prevImage.id;
                    await this.loadFaces();
                }
                // Если других фото нет — показываем сообщение
                else {
                    this.noImagesLeft = true;
                }

            } catch (error) {
                console.error('Complete failed:', error);
            }
        },
        async recheckImage() {
            try {
                await axios.patch(`/api/image/${this.imageId}/status`, { status: 'recheck' }, {
                    headers: { Accept: 'application/json' }
                });

                if (this.nextImage) {
                    this.imageId = this.nextImage.id;
                    await this.loadFaces();
                } else if (this.prevImage) {
                    this.imageId = this.prevImage.id;
                    await this.loadFaces();
                } else {
                    this.noImagesLeft = true;
                }

            } catch (error) {
                console.error('Recheck failed:', error);
            }
        },
        async removeImage() {
            try {
                await axios.get(`/api/image/${this.imageId}/remove`);

                if (this.nextImage) {
                    this.imageId = this.nextImage.id;
                    await this.loadFaces();
                } else if (this.prevImage) {
                    this.imageId = this.prevImage.id;
                    await this.loadFaces();
                } else {
                    this.noImagesLeft = true;
                }

            } catch (error) {
                console.error('Remove failed:', error);
            }
        },


        /*
        async removeFace(index) {
            try {
                await axios.delete('/api/face/remove', {
                    data: {
                        image_id: this.imageId,
                        face_index: index,
                    },
                    preserveScroll: true,
                });
                this.faces.splice(index, 1);
            } catch (error) {
                console.error('Remove failed:', error);
            }
        },
        */
        addFace() {
            this.faces.push({
                id: `temp-${Date.now()}-${Math.random()}`,
                name: '',
                saved: false,
            });
        },
        toggleFullscreen() {
            this.isFullscreen = !this.isFullscreen;
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
                <button @click="prevPhoto" :disabled="!prevImage || isLoading" class="nav-button">
                    ← Back <span v-if="isLoading">⌛</span>
                </button>

                <div class="photo-container">
                    <img
                        v-if="!isLoading"
                        :src="imageUrl"
                        :class="{ fullscreen: isFullscreen }"
                        class="photo"
                        @click="toggleFullscreen"
                    />

                    <div v-else class="loading">Loading image...</div>
                </div>

                <button @click="nextPhoto" :disabled="!nextImage || isLoading" class="nav-button">
                    Next → <span v-if="isLoading">⌛</span>
                </button>
            </div>

            <!-- Таблица лиц -->
            <table class="faces-table" v-if="faces.length > 0">
                <thead>
                    <tr>
                        <th>Index</th>
                        <th>Status</th>
                        <th>Name</th>
                        <th></th>
                        <th>Actions</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(face, index) in faces" :key="face.id">
                        <td>{{ index }}</td>
                        <td>{{ face.status}}</td>
                        <td>
                            <input v-model="face.name" type="text" placeholder="Enter name" />
                        </td>
                        <td>
                            <button @click="saveFace(index, 'ok')" class="btn-save">Save</button>
                        </td>
                        <td>
                            <button @click="saveFace(index, 'not_face')" class="btn-remove">Not a face</button>
                            <button @click="saveFace(index, 'unknown')" class="btn-remove">Unknown</button>
                        </td>
                        <td>
                            <span v-if="face.saved" class="saved-message">Saved!</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="no-faces">
                No faces detected in this image.
            </div>

            <!-- Кнопка добавления всегда видна -->
            <div style="padding-bottom: 20px;">
                <button @click="addFace" class="btn-add">Add Face</button>
            </div>
            <div>
                <!-- Кнопка "Complete" -->
                <div class="complete-wrapper" :title="!canComplete ? 'Please mark all faces before completing' : ''">
                    <button
                        @click="completeImage"
                        class="btn-complete"
                        :class="{ 'btn-disabled': !canComplete }"
                        :disabled="!canComplete"
                    >
                        Complete
                    </button>
                </div>
                <button @click="recheckImage" class="btn-remove">Recheck</button>
                <button @click="removeImage" class="btn-remove">Not photo</button>
            </div>
        </template>
    </div>
</template>

<style scoped src="../../../css/FaceTable.css"></style>
