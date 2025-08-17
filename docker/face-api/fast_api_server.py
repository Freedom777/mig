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
MAX_DIM = 2000  # Ограничение на размер изображения

def resize_image_if_needed(img):
    w, h = img.size
    if max(h, w) > MAX_DIM:
        scale = MAX_DIM / max(h, w)
        new_size = (int(w * scale), int(h * scale))
        logger.info(f"Resizing from {w}x{h} to {new_size[0]}x{new_size[1]}")
        return img.resize(new_size, Image.LANCZOS)
    return img

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
        img = resize_image_if_needed(img_orig)
        image_np = image_to_np_array(img)

        if image_np is False:
            return JSONResponse(status_code=400, content={'error': 'Image must be 3-channel RGB'})

        logger.info(f"DLIB_USE_CUDA: {dlib.DLIB_USE_CUDA}, CUDA devices: {dlib.cuda.get_num_devices()}")
        locations = face_recognition.face_locations(image_np, model='cnn')
        logger.info(f"CNN model found {len(locations)} faces at: {locations}")

        if not locations:
            image_np = image_to_np_array(img_orig)

            if image_np is False:
                return JSONResponse(status_code=400, content={'error': 'Image must be 3-channel RGB'})
            locations = face_recognition.face_locations(image_np, model='cnn')
            logger.info(f"CNN model fallback (original image) found {len(locations)} faces at: {locations}")

            if not locations:
                locations = face_recognition.face_locations(image_np, model='hog')
                logger.info(f"HOG model fallback (original image) found {len(locations)} faces at: {locations}")

        encodings = face_recognition.face_encodings(image_np, locations)
        logger.info(f"Got {len(encodings)} encodings")
        for idx, e in enumerate(encodings):
            logger.info(f"Encoding {idx} shape: {e.shape}, first values: {e[:5].tolist()}")

        # debug_path = None
        # if locations:
        debug_path = save_debug_image(image_np, locations, original_disk, original_path, image_debug_subdir)
        logger.info(f"Debug image saved to: {debug_path}")

        logger.info(f"Encoding took {round(time.time() - start, 2)} seconds")
        return JSONResponse(content={
            'encodings': [e.tolist() for e in encodings],
            'debug_image_path': debug_path
        })

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
