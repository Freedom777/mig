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

# CPU-оптимизированные размеры (меньше чем для GPU)
SCALES = [1200, 1600, 2000]

def resize_image_if_needed(img, max_dim=1600):
    """Уменьшаем max_dim для CPU"""
    w, h = img.size
    if max(w, h) > max_dim:
        scale = max_dim / max(w, h)
        new_size = (int(w * scale), int(h * scale))
        logger.info(f"Resizing from {w}x{h} to {new_size[0]}x{new_size[1]}")
        return img.resize(new_size, Image.LANCZOS)
    return img

def detect_faces_multiscale(img_orig):
    """CPU-only: используем только HOG model"""
    last_np = None

    for dim in SCALES:
        img = resize_image_if_needed(img_orig, max_dim=dim)
        image_np = image_to_np_array(img)

        if image_np is False:
            return {"error": "Image must be 3-channel RGB"}

        last_np = image_np

        # Только HOG для CPU (CNN требует GPU)
        logger.info(f"Trying HOG at {dim}px...")
        try:
            locations = face_recognition.face_locations(
                image_np,
                model='hog',
                number_of_times_to_upsample=1  # уменьшаем для скорости
            )

            if locations:
                logger.info(f"Found {len(locations)} faces at {dim}px with HOG")
                return {
                    "locations": locations,
                    "image_np": image_np,
                    "scale": dim,
                    "model": "hog"
                }
        except Exception as e:
            logger.warning(f"HOG failed at {dim}px: {e}")

    # Лиц не найдено
    return {
        "locations": [],
        "image_np": last_np,
        "scale": None,
        "model": None
    }

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

    try:
        font = ImageFont.truetype("DejaVuSans.ttf", 30)
    except:
        font = ImageFont.load_default()

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
        return JSONResponse(
            status_code=400,
            content={'error': f"Unsupported file type. Allowed: {allowed_extensions}"}
        )

    try:
        contents = await image.read()
        img_orig = Image.open(BytesIO(contents))
        img_orig.verify()
        img_orig = Image.open(BytesIO(contents))

        logger.info(f"Processing: {original_path}/{filename}, {img_orig.format}, {img_orig.mode}, {img_orig.size}")

        result = detect_faces_multiscale(img_orig)

        if "error" in result:
            return JSONResponse(status_code=400, content={"error": result["error"]})

        locations = result["locations"]
        image_np = result["image_np"]

        logger.info(f"Detected {len(locations)} faces at scale {result['scale']}")

        encodings = []
        if locations:
            encodings = face_recognition.face_encodings(image_np, locations)
            logger.info(f"Generated {len(encodings)} encodings")

        debug_path = save_debug_image(
            image_np, locations, original_disk, original_path, image_debug_subdir
        )
        logger.info(f"Debug image saved: {debug_path}")

        elapsed = round(time.time() - start, 2)
        logger.info(f"Encoding took {elapsed}s")

        return JSONResponse(content={
            "encodings": [e.tolist() for e in encodings],
            "debug_image_path": debug_path,
        })

    except MemoryError:
        return JSONResponse(status_code=500, content={"error": "MemoryError"})
    except Exception as e:
        logger.error(f"Encoding failed: {str(e)}", exc_info=True)
        return JSONResponse(
            status_code=500,
            content={'error': f"Face processing failed: {str(e)}"}
        )

@app.post("/compare")
async def compare_faces(data: CompareRequest):
    try:
        encoding = np.array(data.encoding)
        candidates = [np.array(e) for e in data.candidates]

        logger.info(f"Comparing {encoding.shape} to {len(candidates)} candidates")

        distances = []
        for i, candidate in enumerate(candidates):
            dist = np.linalg.norm(candidate - encoding)
            distances.append(float(dist))
            logger.info(f"Distance to candidate {i}: {dist:.5f}")

        return JSONResponse(content={'distances': distances})
    except Exception as e:
        logger.error(f"Compare failed: {str(e)}", exc_info=True)
        return JSONResponse(status_code=500, content={'error': str(e)})

@app.get("/health")
async def health_check():
    return {"status": "ok", "mode": "cpu"}
