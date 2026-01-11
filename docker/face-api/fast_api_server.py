import sys
import time
import logging
import numpy as np
import os
from fastapi import FastAPI, File, UploadFile, Form
from fastapi.responses import JSONResponse
from PIL import Image, ImageDraw, ImageFont
from io import BytesIO
from pydantic import BaseModel
from typing import List
import face_recognition
import dlib

class CompareRequest(BaseModel):
    encoding: List[float]
    candidates: List[List[float]]

# Настройка логгера
logging.basicConfig(level=logging.INFO, format='%(asctime)s %(levelname)s: %(message)s')
logger = logging.getLogger(__name__)
sys.stdout.reconfigure(line_buffering=True)

app = FastAPI(
    docs_url=None,
    redoc_url=None,
    openapi_url=None
)
SCALES = [1600, 2000, 2600]  # Ограничение на размер изображения

def resize_image_if_needed(img, max_dim=2000):
    w, h = img.size
    if max(w, h) > max_dim:
        scale = max_dim / max(w, h)
        new_size = (int(w * scale), int(h * scale))
        logger.info(f"Resizing from {w}x{h} to {new_size[0]}x{new_size[1]}")
        return img.resize(new_size, Image.LANCZOS)
    return img

def detect_faces_multiscale(img_orig):
    last_np = None
    for dim in SCALES:
        img = resize_image_if_needed(img_orig, max_dim=dim)
        image_np = image_to_np_array(img)
        if image_np is False:
            return {"error": "Image must be 3-channel RGB"}

        last_np = image_np  # будем использовать для дебага, если лиц не найдём

        # CNN
        logger.info(f"Trying CNN at {dim}px...")
        try:
            locations = face_recognition.face_locations(image_np, model='cnn')
        except RuntimeError as e:
            if "CUDA out of memory" in str(e):
                return {"error": "CUDA_OOM"}
            logger.warning(f"CNN failed at {dim}px: {e}")
            locations = []

        if locations:
            return {"locations": locations, "image_np": image_np, "scale": dim, "model": "cnn"}

        # HOG fallback
        logger.info(f"Trying HOG at {dim}px...")
        locations = face_recognition.face_locations(image_np, model='hog')
        if locations:
            return {"locations": locations, "image_np": image_np, "scale": dim, "model": "hog"}

    # лиц нет на всех масштабах — это НЕ ошибка
    return {"locations": [], "image_np": last_np, "scale": None, "model": None}


def image_to_np_array(img):
    if img.mode != 'RGB':
        logger.info(f"Converting image from {img.mode} to RGB")
        img = img.convert('RGB')

    image = np.array(img)

    if image.dtype != np.uint8:
        logger.info(f"Converting image from {image.dtype} to uint8")
        if image.dtype in (np.float32, np.float64):
            image = (image * 255).astype(np.uint8)
        else:
            image = image.astype(np.uint8)

    if len(image.shape) != 3 or image.shape[2] != 3:
        logger.error(f"Invalid image shape: {image.shape}")
        return False
    else:
        return image

def save_debug_image(image_array, locations, original_disk, original_filename, image_debug_subdir):
    original_path = os.path.normpath(original_filename)
    relative_path = os.path.relpath(original_path, start=original_disk)
    image_dir = os.path.dirname(relative_path)
    debug_dir = os.path.join(original_disk, image_dir, image_debug_subdir)
    os.makedirs(debug_dir, exist_ok=True)

    base_name = os.path.basename(original_filename)
    debug_path = os.path.join(debug_dir, f"debug_{base_name}")

    img = Image.fromarray(image_array)
    draw = ImageDraw.Draw(img)
    font = ImageFont.truetype("DejaVuSans.ttf", 30)
    for i, (top, right, bottom, left) in enumerate(locations):
        draw.rectangle([(left, top), (right, bottom)], outline="green", width=3)
        draw.text((left + 5, bottom - 40), f"Face {i}", fill="red", font=font)
    img.save(debug_path, quality=90)

    return debug_path

@app.post("/encode")
async def encode_faces(
    image: UploadFile = File(...),
    original_path: str = Form(...),
    original_disk: str = Form(...),
    image_debug_subdir: str = Form("debug")
):
    start = time.time()
    allowed_extensions = {'jpg', 'jpeg', 'png'}
    filename = image.filename
    file_ext = filename.rsplit('.', 1)[-1].lower() if '.' in filename else ''

    if file_ext not in allowed_extensions:
        return JSONResponse(status_code=400, content={'error': f"Unsupported file type. Allowed: {allowed_extensions}"})

    try:
        contents = await image.read()

        img_orig = Image.open(BytesIO(contents))
        img_orig.verify()
        img_orig = Image.open(BytesIO(contents))

        logger.info(f"Processing image: {original_path}/{filename}, format: {img_orig.format}, mode: {img_orig.mode}, size: {img_orig.size}")
        result = detect_faces_multiscale(img_orig)

        if "error" in result:
            # Фатальная ошибка только для non-RGB (или CUDA_OOM и т.п.)
            return JSONResponse(status_code=400, content={"error": result["error"]})

        locations = result["locations"]
        image_np  = result["image_np"]
        logger.info(f"Detected {len(locations)} faces at scale {result['scale']}")

        encodings = []
        if locations:
            encodings = face_recognition.face_encodings(image_np, locations)
            logger.info(f"Got {len(encodings)} encodings")
            # for idx, e in enumerate(encodings):
            #    logger.info(f"Encoding {idx} shape: {e.shape}, first values: {e[:5].tolist()}")
        else:
            logger.info("No faces found → encodings skipped")

        # ✅ сохраняем дебаг всегда, даже когда лиц нет
        # debug_path = None
        # if locations:
        debug_path = save_debug_image(image_np, locations, original_disk, original_path, image_debug_subdir)
        logger.info(f"Saved debug image to {debug_path}")

        logger.info(f"Encoding took {round(time.time() - start, 2)} seconds")
        return JSONResponse(
            content={
                "encodings": [e.tolist() for e in encodings],  # [] если лиц нет
                # "locations": locations,                        # [] если лиц нет
                "debug_image_path": debug_path,
                # "scale": result["scale"],                      # None если лиц нет
                # "model": result["model"]                       # None если лиц нет
            }
        )

    except RuntimeError as e:
        #if "CUDA out of memory" in str(e):
        #    return {"error": "CUDA_OOM"}
        if "reason: out of memory" in str(e):
            return {"error": "CUDA_OOM"}
        raise
    except MemoryError:
        return {"error": "MemoryError"}
    except Exception as e:
        logger.error(f"Exception during encoding: {str(e)}", exc_info=True)
        return JSONResponse(status_code=500, content={'error': f"Face processing failed: {str(e)}"})
    finally:
        try:
            os.remove(f"/tmp/{filename}")
        except:
            pass

@app.post("/compare")
async def compare_faces(data: CompareRequest):
    try:
        encoding = np.array(data.encoding)
        candidates = [np.array(e) for e in data.candidates]

        logger.info(f"Comparing encoding of shape {encoding.shape} to {len(candidates)} candidate(s)")

        distances = []
        for i, candidate in enumerate(candidates):
            dist = np.linalg.norm(candidate - encoding)
            distances.append(dist)
            logger.info(f"Distance to candidate {i}: {dist:.5f}")

        return JSONResponse(content={'distances': distances})
    except Exception as e:
        logger.error(f"Error in compare_faces: {str(e)}", exc_info=True)
        return JSONResponse(status_code=500, content={'error': str(e)})

# curl -F "file=@image1.jpg" "http://localhost:8000/hash?hash_size=16"
#  {
#    "hash": "abcd5678...",
#    "hash_size": 16,
#    "bits": 256,
#  }
# curl -F "file1=@image1.jpg" -F "file2=@image2.jpg" "http://localhost:8000/compare-images?hash_size=16"
# {
#    "hash1": "abcd1234...",
#    "hash2": "abcd5678...",
#    "hash_size": 16,
#    "bits": 256,
#    "distance": 5
#  }
@app.post("/hash")
async def get_hash(
    file: UploadFile = File(...),
    hash_size: int = Query(8, ge=4, le=32, description="Размер хэша (по умолчанию 8)")
):
    """Вычислить pHash для одного изображения"""
    try:
        img = Image.open(file.file)
        hash_val = str(imagehash.phash(img, hash_size=hash_size))
        return JSONResponse(content={
            "hash": hash_val,
            "hash_size": hash_size,
            "bits": hash_size * hash_size
        })
    except Exception as e:
        return JSONResponse(content={"error": str(e)}, status_code=500)


@app.post("/compare-images")
async def compare_images(
    file1: UploadFile = File(...),
    file2: UploadFile = File(...),
    hash_size: int = Query(8, ge=4, le=32, description="Размер хэша (по умолчанию 8)")
):
    """Сравнить два изображения по pHash"""
    try:
        img1 = Image.open(file1.file)
        img2 = Image.open(file2.file)

        hash1 = imagehash.phash(img1, hash_size=hash_size)
        hash2 = imagehash.phash(img2, hash_size=hash_size)

        distance = hash1 - hash2  # встроенный Hamming distance

        return JSONResponse(content={
            "hash1": str(hash1),
            "hash2": str(hash2),
            "hash_size": hash_size,
            "bits": hash_size * hash_size,
            "distance": distance
        })
    except Exception as e:
        return JSONResponse(content={"error": str(e)}, status_code=500)
