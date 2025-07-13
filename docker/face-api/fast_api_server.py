import sys
import time
import logging
import numpy as np
import os
from flask import Flask, request, jsonify
from werkzeug.utils import secure_filename
from PIL import Image, ImageDraw
import face_recognition
import dlib
import cv2  # Добавляем импорт OpenCV

# Настройка логгера
logging.basicConfig(level=logging.INFO, format='%(asctime)s %(levelname)s: %(message)s')
logger = logging.getLogger(__name__)
sys.stdout.reconfigure(line_buffering=True)

app = Flask(__name__)
app.config['UPLOAD_FOLDER'] = '/tmp'
MAX_DIM = 1600  # Ограничение на размер изображения

def resize_image_if_needed(img):
    """Изменяет размер изображения если оно превышает MAX_DIM"""
    # Получаем размеры из PIL Image
    w, h = img.size

    if max(h, w) > MAX_DIM:
        scale = MAX_DIM / max(h, w)
        new_size = (int(w * scale), int(h * scale))
        logger.info(f"Resizing from {w}x{h} to {new_size[0]}x{new_size[1]}")
        return img.resize(new_size, Image.LANCZOS)  # Используем PIL для ресайза
    return img

def save_debug_image(image_array, locations, filename):
    """Сохраняет отладочное изображение с разметкой лиц"""
    # Конвертируем numpy array в PIL Image
    img = Image.fromarray(image_array)
    draw = ImageDraw.Draw(img)

    # Рисуем прямоугольники и подписи
    for i, (top, right, bottom, left) in enumerate(locations):
        # Зелёный прямоугольник толщиной 3px
        draw.rectangle([(left, top), (right, bottom)], outline="green", width=3)

        # Красная подпись с номером лица
        draw.text((left + 5, bottom - 20), f"Face {i}", fill="red")

    # Сохраняем в /tmp
    debug_path = f"/tmp/debug_{filename}"
    img.save(debug_path)
    return debug_path

@app.route('/encode', methods=['POST'])
def encode_faces():
    start = time.time()

    if 'image' not in request.files:
        return jsonify({'error': 'No image file provided'}), 400

    image_file = request.files['image']

    # Проверка наличия файла
    if image_file.filename == '':
        return jsonify({'error': 'No selected file'}), 400

    # Проверка расширения файла
    allowed_extensions = {'jpg', 'jpeg', 'png'}
    filename = secure_filename(image_file.filename)
    file_ext = filename.rsplit('.', 1)[1].lower() if '.' in filename else ''

    if file_ext not in allowed_extensions:
        return jsonify({'error': f'Unsupported file type. Allowed: {allowed_extensions}'}), 400

    filepath = os.path.join(app.config['UPLOAD_FOLDER'], filename)

    try:
        # Сохраняем временный файл
        image_file.save(filepath)

        # Проверяем, является ли файл валидным изображением
        try:
            img = Image.open(filepath)
            img.verify()  # Проверка целостности файла
            img = Image.open(filepath)  # Нужно открыть снова после verify
        except Exception as verify_error:
            logger.error(f"Invalid image file: {str(verify_error)}")
            return jsonify({'error': 'Invalid image file'}), 400

        # Логируем информацию о изображении
        logger.info(f"Processing image: {filename}")
        logger.info(f"Image format: {img.format}, mode: {img.mode}, size: {img.size}")

        # Ресайзим, если необходимо (теперь работает с PIL Image)
        img = resize_image_if_needed(img)

        # Конвертируем в RGB
        if img.mode != 'RGB':
            logger.info(f"Converting image from {img.mode} to RGB")
            img = img.convert('RGB')

        # Конвертируем в numpy array
        image = np.array(img)

        # Проверяем тип данных
        if image.dtype != np.uint8:
            logger.info(f"Converting image from {image.dtype} to uint8")
            if image.dtype == np.float32 or image.dtype == np.float64:
                image = (image * 255).astype(np.uint8)
            else:
                image = image.astype(np.uint8)

        # Дополнительная проверка формы массива
        if len(image.shape) != 3 or image.shape[2] != 3:
            logger.error(f"Invalid image shape: {image.shape}")
            return jsonify({'error': 'Image must be 3-channel RGB'}), 400

        logger.info(f"Final image array shape: {image.shape}, dtype: {image.dtype}")
        logger.info(f"DLIB_USE_CUDA: {dlib.DLIB_USE_CUDA}, CUDA devices: {dlib.cuda.get_num_devices()}")

        # Обработка лиц
        locations = face_recognition.face_locations(image, model='cnn')
        logger.info(f"Found {len(locations)} faces")
        encodings = face_recognition.face_encodings(image, locations)

        # Сохраняем отладочное изображение (добавленная строка)
        debug_path = save_debug_image(image, locations, filename)
        logger.info(f"Debug image saved to: {debug_path}")

        logger.info(f"Encoding took {round(time.time() - start, 2)} seconds")
        # Формируем ответ
        return jsonify({
            'encodings': [e.tolist() for e in encodings],
            # 'faces_locations': locations,
            'debug_image_path': debug_path  # Добавляем путь в ответ
            # 'processing_time': round(time.time() - start, 2)
        })
    except Exception as e:
        logger.error(f"Exception during encoding: {str(e)}", exc_info=True)
        return jsonify({'error': f'Face processing failed: {str(e)}'}), 500
    finally:
        if os.path.exists(filepath):
            os.remove(filepath)

@app.route('/compare', methods=['POST'])
def compare_faces():
    data = request.get_json()
    try:
        encoding = np.array(data.get('encoding'))
        candidates = [np.array(e) for e in data.get('candidates', [])]
        distances = [np.linalg.norm(c - encoding) for c in candidates]
        return jsonify({'distances': distances})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
