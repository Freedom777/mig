FROM nvidia/cuda:12.1.1-devel-ubuntu22.04

ENV DEBIAN_FRONTEND=noninteractive

# Установка зависимостей
RUN apt-get update && apt-get install -y \
    python3 python3-dev python3-pip python3-venv \
    cmake build-essential \
    libboost-all-dev libopenblas-dev liblapack-dev \
    libx11-dev libgtk-3-dev \
    git curl wget libglib2.0-0 \
    libsm6 libxext6 libxrender-dev \
    && rm -rf /var/lib/apt/lists/*

# Обновление pip и установка зависимостей
RUN pip3 install --upgrade pip

# ===== Установка cuDNN вручную =====
# Скопировать архив cuDNN (предполагается, что файл рядом с Dockerfile)
COPY cudnn-linux-x86_64-8.9.7.29_cuda12-archive.tar.xz /tmp/

# Распаковать и установить cuDNN
RUN tar -xvf /tmp/cudnn-linux-x86_64-8.9.7.29_cuda12-archive.tar.xz -C /tmp && \
    cp -P /tmp/cudnn-linux-x86_64-8.9.7.29_cuda12-archive/include/* /usr/local/cuda/include/ && \
    cp -P /tmp/cudnn-linux-x86_64-8.9.7.29_cuda12-archive/lib/* /usr/local/cuda/lib64/ && \
    chmod a+r /usr/local/cuda/include/cudnn*.h /usr/local/cuda/lib64/libcudnn* && \
    rm -rf /tmp/cudnn*

# Переменные окружения для cuDNN
ENV LD_LIBRARY_PATH=/usr/local/cuda/lib64:$LD_LIBRARY_PATH
ENV CPATH=/usr/local/cuda/include:$CPATH


# 1. Клонируем dlib и вручную собираем с поддержкой CUDA
WORKDIR /opt
#  --branch v19.24
RUN git clone --recursive https://github.com/davisking/dlib.git && \
    mkdir dlib/build && cd dlib/build && \
    cmake .. -DDLIB_USE_CUDA=1 -DUSE_AVX_INSTRUCTIONS=1 && \
    cat CMakeCache.txt | grep CUDA && \
    cmake --build . --config Release && \
    cd .. && python3 setup.py install

# 2. Ставим face_recognition без его зависимостей (dlib уже установлен вручную!)
COPY requirements.txt /tmp/
RUN pip3 install --no-cache-dir -r /tmp/requirements.txt && \
    pip3 install gunicorn opencv-python-headless && \
    pip3 install --no-deps face_recognition && \
    pip3 install git+https://github.com/ageitgey/face_recognition_models

# Копируем сервер
WORKDIR /app
COPY fast_api_server.py /app/server.py

EXPOSE 5000

# Запуск через Gunicorn (1 worker, 1 thread — можно увеличить при необходимости)
CMD ["gunicorn", "--bind", "0.0.0.0:5000", "server:app", "--workers=1", "--threads=1"]
