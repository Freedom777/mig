#!/bin/bash
set -e

echo "=== Face API Installation Script ==="
echo "Installing on CX32 (CPU-only)..."

PROJECT_ROOT="/var/www/photo"
VENV_PATH="$PROJECT_ROOT/face-api-venv"
FACE_API_DIR="$PROJECT_ROOT/etc/face-api"

# 1. System packages
echo "Installing system dependencies..."
sudo apt-get update
sudo apt-get install -y \
    python3 python3-dev python3-pip python3-venv \
    cmake build-essential \
    libopenblas-dev liblapack-dev \
    libx11-dev libgtk-3-dev \
    libsm6 libxext6 libxrender-dev libglib2.0-0 \
    git fonts-dejavu-core

# 2. Create venv
echo "Creating virtual environment..."
cd "$PROJECT_ROOT"
python3 -m venv "$VENV_PATH"
source "$VENV_PATH/bin/activate"
pip install --upgrade pip setuptools wheel

# 3. Build dlib (CPU-only)
echo "Building dlib with CPU optimizations..."
cd /tmp
git clone --depth 1 https://github.com/davisking/dlib.git
cd dlib && mkdir -p build && cd build
cmake .. -DUSE_AVX_INSTRUCTIONS=1
cmake --build . --config Release
cd .. && python3 setup.py install
cd /tmp && rm -rf dlib

# 4. Install Python packages
echo "Installing Python dependencies..."
pip install -r "$FACE_API_DIR/requirements.txt"
pip install --no-deps face_recognition
pip install git+https://github.com/ageitgey/face_recognition_models
pip install opencv-python-headless

# 5. Setup directories and permissions
echo "Setting up directories..."
mkdir -p "$PROJECT_ROOT/storage/logs"
sudo chown -R www-data:www-data "$PROJECT_ROOT/storage/logs"
sudo chmod -R 775 "$PROJECT_ROOT/storage/logs"

# 6. Install systemd service
echo "Installing systemd service..."
sudo cp "$PROJECT_ROOT/etc/systemd/face-api.service" /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable face-api

echo ""
echo "=== Installation Complete! ==="
echo ""
echo "Next steps:"
echo "1. Review config: /etc/systemd/system/face-api.service"
echo "2. Start service: sudo systemctl start face-api"
echo "3. Check status: sudo systemctl status face-api"
echo "4. View logs: tail -f $PROJECT_ROOT/storage/logs/face-api-error.log"
echo ""
