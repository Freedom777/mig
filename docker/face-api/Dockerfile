FROM nvidia/cuda:12.1.1-devel-ubuntu22.04

ENV DEBIAN_FRONTEND=noninteractive
ENV PYTHONUNBUFFERED=1

# Установка базовых пакетов
RUN apt-get update && apt-get install -y --no-install-recommends \
    python3 python3-dev python3-pip python3-venv \
    cmake build-essential \
    libboost-all-dev libopenblas-dev liblapack-dev \
    libx11-dev libgtk-3-dev \
    libsm6 libxext6 libxrender-dev libglib2.0-0 \
    git curl wget pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Обновление pip
RUN python3 -m pip install --upgrade pip

# Установка cuDNN вручную
COPY cudnn-linux-x86_64-8.9.7.29_cuda12-archive.tar.xz /tmp/

RUN cd /tmp && \
    tar -xvf cudnn-linux-x86_64-8.9.7.29_cuda12-archive.tar.xz && \
    cp -P cudnn-linux-x86_64-8.9.7.29_cuda12-archive/include/* /usr/local/cuda/include/ && \
    cp -P cudnn-linux-x86_64-8.9.7.29_cuda12-archive/lib/* /usr/local/cuda/lib64/ && \
    chmod a+r /usr/local/cuda/include/cudnn*.h /usr/local/cuda/lib64/libcudnn* && \
    rm -rf /tmp/cudnn*

ENV LD_LIBRARY_PATH=/usr/local/cuda/lib64:$LD_LIBRARY_PATH
ENV CPATH=/usr/local/cuda/include:$CPATH

# Сборка dlib с CUDA
WORKDIR /opt

RUN git clone --recursive https://github.com/davisking/dlib.git && \
    mkdir -p dlib/build && cd dlib/build && \
    cmake .. -DDLIB_USE_CUDA=1 -DUSE_AVX_INSTRUCTIONS=1 && \
    cmake --build . --config Release && \
    cd .. && python3 setup.py install

# Установка Python зависимостей
COPY requirements.txt /tmp/

RUN pip3 install --no-cache-dir -r /tmp/requirements.txt && \
    pip3 install --no-deps face_recognition && \
    pip3 install git+https://github.com/ageitgey/face_recognition_models && \
    pip3 install gunicorn uvicorn opencv-python-headless && \
    rm -f /tmp/requirements.txt

# Копирование FastAPI-сервера
WORKDIR /app

COPY fast_api_server.py server.py

EXPOSE 5000

# Запуск через Gunicorn + Uvicorn
CMD ["gunicorn", "server:app", "-k", "uvicorn.workers.UvicornWorker", "--bind", "0.0.0.0:5000", "--workers=1", "--threads=1", "--timeout=120"]

