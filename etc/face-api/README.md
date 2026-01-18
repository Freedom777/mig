# Face Recognition API

CPU-only face recognition service using face_recognition library with dlib backend.

## Installation

Run the installation script:
```bash
cd /var/www/photo
sudo ./etc/face-api/install.sh
```

This will:
1. Install system dependencies
2. Create Python virtual environment at `/var/www/photo/face-api-venv`
3. Build dlib with CPU optimizations (AVX)
4. Install Python packages
5. Setup systemd service

## Manual Installation

If you prefer manual installation:
```bash
# 1. Install system packages
sudo apt-get install python3 python3-dev python3-venv cmake build-essential \
    libopenblas-dev liblapack-dev fonts-dejavu-core

# 2. Create venv
python3 -m venv /var/www/photo/face-api-venv
source /var/www/photo/face-api-venv/bin/activate

# 3. Build dlib
cd /tmp
git clone --depth 1 https://github.com/davisking/dlib.git
cd dlib && mkdir build && cd build
cmake .. -DUSE_AVX_INSTRUCTIONS=1
cmake --build . --config Release
cd .. && python3 setup.py install

# 4. Install packages
pip install -r /var/www/photo/etc/face-api/requirements.txt
pip install --no-deps face_recognition
pip install git+https://github.com/ageitgey/face_recognition_models
pip install opencv-python-headless

# 5. Install systemd service (see etc/systemd/README.md)
```

## API Endpoints

### POST /encode
Detect and encode faces in an image.

**Request:**
- `image` (file): Image file (jpg, jpeg, png)
- `original_path` (form): Original file path
- `original_disk` (form): Storage disk
- `image_debug_subdir` (form): Debug subdirectory (default: "debug")

**Response:**
```json
{
  "encodings": [[128 floats], ...],
  "debug_image_path": "/path/to/debug.jpg"
}
```

### POST /compare
Compare face encodings.

**Request:**
```json
{
  "encoding": [128 floats],
  "candidates": [[128 floats], ...]
}
```

**Response:**
```json
{
  "distances": [0.45, 0.67, ...]
}
```

### GET /health
Health check endpoint.

**Response:**
```json
{
  "status": "ok",
  "mode": "cpu"
}
```

## Testing
```bash
# Health check
curl http://127.0.0.1:5000/health

# Test encoding (from project root)
curl -X POST http://127.0.0.1:5000/encode \
  -F "image=@storage/app/private/images/test.jpg" \
  -F "original_path=/var/www/photo/storage/app/private/images/test.jpg" \
  -F "original_disk=/var/www/photo/storage/app/private" \
  -F "image_debug_subdir=debug"
```

## Configuration

### Service Configuration
Edit `/etc/systemd/system/face-api.service`:
- Workers: Adjust `--workers` based on CPU cores (default: 4)
- Memory: Adjust `MemoryMax` (default: 12G)
- CPU: Adjust `CPUQuota` (default: 800% = 8 cores)

### Application Configuration
Edit `server.py`:
- `SCALES`: Image sizes for processing (default: [1200, 1600, 2000])
- Model: HOG only (CNN requires GPU)

## Monitoring
```bash
# Service status
sudo systemctl status face-api

# Application logs
tail -f /var/www/photo/storage/logs/face-api-error.log
tail -f /var/www/photo/storage/logs/face-api-access.log

# System logs
sudo journalctl -u face-api -f

# Resource usage
htop  # look for gunicorn/uvicorn processes
```

## Performance Tuning

For CX32 (32 cores, 64GB RAM):
```ini
# Increase workers in systemd service
--workers 8  # or up to 12-16
```

## Troubleshooting

### Service won't start
```bash
# Check logs
sudo journalctl -u face-api -n 50

# Check permissions
ls -la /var/www/photo/storage/logs/
sudo chown -R www-data:www-data /var/www/photo/storage/logs

# Test manually
source /var/www/photo/face-api-venv/bin/activate
cd /var/www/photo/etc/face-api
python server.py
```

### Slow processing
- Reduce image sizes in `SCALES` array
- Reduce `number_of_times_to_upsample` in `face_locations()`
- Increase workers if CPU usage is low

### Memory issues
- Reduce workers count
- Reduce image sizes
- Add swap space if needed

## Dependencies

- Python 3.10+
- dlib (CPU-only, built with AVX)
- face_recognition
- FastAPI + Uvicorn + Gunicorn
- numpy, Pillow, opencv-python-headless
