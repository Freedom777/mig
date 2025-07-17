<template>
  <div class="image-explorer">
    <!-- Миниатюры -->
      <div class="thumbnails-container">
          <div
              v-for="(image, index) in images"
              :key="index"
              class="thumbnail"
              @click="openFullscreen(index)"
          >
              <img :src="image.thumbnail" :alt="'Thumbnail ' + index">
          </div>
      </div>

      <!-- Полноэкранный просмотр -->
    <div v-if="fullscreenVisible" class="fullscreen-view">
      <button class="close-btn" @click="closeFullscreen">×</button>
      <button class="nav-btn left" @click="prevImage">←</button>

      <div class="fullscreen-image-container">
        <img :src="images[currentIndex].full" :alt="'Image ' + currentIndex">
      </div>

      <button class="nav-btn right" @click="nextImage">→</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

defineProps<{
    images: Array<{
        thumbnail: string;
        full: string;
    }>;
}>();

// Пример данных - замените на свои изображения
/*
const images = ref([
  { thumbnail: 'https://picsum.photos/200/150?random=1', full: 'https://picsum.photos/1920/1080?random=1' },
  { thumbnail: 'https://picsum.photos/200/150?random=2', full: 'https://picsum.photos/1920/1080?random=2' },
  { thumbnail: 'https://picsum.photos/200/150?random=3', full: 'https://picsum.photos/1920/1080?random=3' },
  { thumbnail: 'https://picsum.photos/200/150?random=4', full: 'https://picsum.photos/1920/1080?random=4' },
  { thumbnail: 'https://picsum.photos/200/150?random=5', full: 'https://picsum.photos/1920/1080?random=5' },
  { thumbnail: 'https://picsum.photos/200/150?random=6', full: 'https://picsum.photos/1920/1080?random=6' },
]);
*/
const fullscreenVisible = ref(false);
const currentIndex = ref(0);

const openFullscreen = (index) => {
  currentIndex.value = index;
  fullscreenVisible.value = true;
  document.body.style.overflow = 'hidden';
};

const closeFullscreen = () => {
  fullscreenVisible.value = false;
  document.body.style.overflow = '';
};

const nextImage = () => {
  if (currentIndex.value < images.value.length - 1) {
    currentIndex.value++;
  } else {
    currentIndex.value = 0;
  }
};

const prevImage = () => {
  if (currentIndex.value > 0) {
    currentIndex.value--;
  } else {
    currentIndex.value = images.value.length - 1;
  }
};

const handleKeyDown = (e) => {
  if (!fullscreenVisible.value) return;

  switch(e.key) {
    case 'ArrowLeft':
      prevImage();
      break;
    case 'ArrowRight':
      nextImage();
      break;
    case 'Escape':
      closeFullscreen();
      break;
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
});
</script>

<style scoped>
.image-explorer {
  font-family: Arial, sans-serif;
}

.thumbnails-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 15px;
  padding: 20px;
}

.thumbnail {
  cursor: pointer;
  transition: transform 0.2s;
  border: 1px solid #ddd;
  padding: 5px;
  background: white;
}

.thumbnail:hover {
  transform: scale(1.05);
  box-shadow: 0 0 10px rgba(0,0,0,0.2);
}

.thumbnail img {
  width: 100%;
  height: auto;
  display: block;
}

.fullscreen-view {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.fullscreen-image-container {
  max-width: 90%;
  max-height: 90%;
}

.fullscreen-image-container img {
  max-width: 100%;
  max-height: 90vh;
  object-fit: contain;
}

.close-btn {
  position: absolute;
  top: 20px;
  right: 30px;
  font-size: 40px;
  color: white;
  background: none;
  border: none;
  cursor: pointer;
}

.nav-btn {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  font-size: 30px;
  color: white;
  background: rgba(0,0,0,0.5);
  border: none;
  padding: 20px;
  cursor: pointer;
  border-radius: 50%;
}

.nav-btn.left {
  left: 30px;
}

.nav-btn.right {
  right: 30px;
}

.nav-btn:hover {
  background: rgba(255,255,255,0.3);
}
</style>
