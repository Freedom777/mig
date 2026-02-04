#!/data/data/com.termux/files/usr/bin/bash

# 🌐 FTP/SFTP настройки
FTP_HOST="91.98.79.139"
FTP_PORT="2271"
FTP_USER="ftpfreedom"
FTP_PASS="ste4enie"
FTP_PATH="/www/photo/storage/app/private/images"

# 🔗 API настройки
API_URL="https://photo.freedomvibe.net/api/image/new-upload"

# 📁 Папка для мониторинга
WATCH_DIR="/storage/emulated/0/DCIM/Camera"

# 🔄 Параметры повторов
RETRY_COUNT=10              # Количество попыток загрузки
RETRY_DELAY=1800            # Задержка между попытками (секунды, 30 минут)

# ⏱️ Таймауты (секунды)
TIMEOUT_PING=10             # Таймаут проверки интернета
TIMEOUT_SFTP=300            # Таймаут SFTP загрузки (5 минут)
TIMEOUT_API=30              # Таймаут API запроса
TIMEOUT_NOTIFICATION=5      # Таймаут уведомлений
TIMEOUT_TOAST=2             # Таймаут toast сообщений

# 🔌 Сетевые настройки
PING_TARGET="8.8.8.8"       # DNS сервер для проверки интернета
PING_COUNT=1                # Количество ping пакетов
PING_WAIT=5                 # Время ожидания ответа ping (секунды)
SFTP_CONNECT_TIMEOUT=30     # Таймаут подключения SFTP (секунды)

# ⏳ Задержки
FILE_STABILIZE_DELAY=5      # Ожидание стабилизации файла после создания (секунды)
API_CALL_DELAY=2            # Задержка перед вызовом API после загрузки (секунды)
STARTUP_DELAY=30            # Ожидание инициализации Termux:API после старта (секунды)
API_RETRY_DELAY=5           # Задержка между попытками подключения к API (секунды)
API_RETRY_COUNT=10          # Количество попыток подключения к Termux:API

# 📝 Логирование
LOG_FILE="$HOME/upload_photos.log"

# 📂 Временная директория
TEMP_DIR="$HOME/tmp"
mkdir -p "$TEMP_DIR"

# 🔒 Блокировка пробуждения
termux-wake-lock

log_message() {
    local MESSAGE="[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    echo "$MESSAGE" | tee -a "$LOG_FILE"
}

# Проверка доступности Termux:API с повторными попытками
wait_for_termux_api() {
    log_message "⏳ Waiting for Termux:API to initialize..."

    # Начальная задержка после старта системы
    sleep "$STARTUP_DELAY"

    for i in $(seq 1 $API_RETRY_COUNT); do
        log_message "Checking Termux:API availability (attempt $i/$API_RETRY_COUNT)..."

        # Пробуем простую команду
        if timeout "$TIMEOUT_TOAST" termux-toast "API Test" 2>&1 | grep -q "Connection refused"; then
            log_message "⚠️ Termux:API not ready yet, waiting $API_RETRY_DELAY seconds..."
            sleep "$API_RETRY_DELAY"
        else
            log_message "✅ Termux:API is ready"
            return 0
        fi
    done

    log_message "⚠️ Termux:API still not available after $API_RETRY_COUNT attempts, continuing anyway..."
    return 1
}

# Безопасная отправка уведомлений с проверкой доступности API
notify() {
    local TITLE="$1"
    local TEXT="$2"

    log_message "Sending notification: $TITLE - $TEXT"

    # Пробуем отправить уведомление
    local NOTIFY_OUTPUT=$(timeout "$TIMEOUT_NOTIFICATION" termux-notification \
        --title "$TITLE" \
        --content "$TEXT" \
        --priority high \
        --sound 2>&1)

    local EXIT_CODE=$?

    # Проверяем на ошибку подключения
    if echo "$NOTIFY_OUTPUT" | grep -q "Connection refused"; then
        log_message "⚠️ Termux:API connection refused, skipping notification"
        return 1
    elif [ $EXIT_CODE -eq 124 ]; then
        log_message "⚠️ Notification timeout"
        return 1
    elif [ $EXIT_CODE -ne 0 ]; then
        log_message "⚠️ Notification failed with code: $EXIT_CODE"
        log_message "   Output: $NOTIFY_OUTPUT"
        return 1
    fi

    log_message "✅ Notification sent successfully"

    # Дублируем через toast (быстрее и надёжнее)
    timeout "$TIMEOUT_TOAST" termux-toast "$TITLE: $TEXT" 2>/dev/null

    return 0
}

# Ожидаем инициализацию Termux:API
wait_for_termux_api

log_message "📸 Photo uploader started..."
log_message "⚙️ Configuration:"
log_message "   Watch directory: $WATCH_DIR"
log_message "   FTP host: $FTP_HOST:$FTP_PORT"
log_message "   Retry count: $RETRY_COUNT"
log_message "   Retry delay: $RETRY_DELAY seconds"
log_message "   SFTP timeout: $TIMEOUT_SFTP seconds"
log_message "   Temp directory: $TEMP_DIR"

notify "📸 Запуск" "Photo uploader started"

upload_file() {
    local FILE="$1"
    local BASENAME=$(basename "$FILE")

    log_message "🟡 Starting upload for: $BASENAME"
    notify "🟡 Загрузка" "Начинаю загрузку: $BASENAME"

    # Проверяем, что файл существует и доступен
    if [ ! -f "$FILE" ]; then
        log_message "❌ File not found: $FILE"
        notify "❌ Ошибка" "Файл не найден: $BASENAME"
        return 1
    fi

    if [ ! -r "$FILE" ]; then
        log_message "❌ File not readable: $FILE"
        notify "❌ Ошибка" "Нет доступа к файлу: $BASENAME"
        return 1
    fi

    local FILE_SIZE=$(stat -c%s "$FILE" 2>/dev/null || echo "0")
    log_message "📊 File size: $FILE_SIZE bytes"

    for i in $(seq 1 $RETRY_COUNT); do
        log_message "Attempt $i/$RETRY_COUNT for $BASENAME"

        # Проверка интернета с таймаутом
        log_message "Checking internet connection (ping $PING_TARGET)..."
        if ! timeout "$TIMEOUT_PING" ping -c"$PING_COUNT" -W"$PING_WAIT" "$PING_TARGET" >/dev/null 2>&1; then
            log_message "❌ No internet connection, waiting $RETRY_DELAY seconds... ($i/$RETRY_COUNT)"

            if [ $i -eq 1 ]; then
                notify "⚠️ Нет сети" "Ожидаю подключения для $BASENAME"
            fi

            sleep "$RETRY_DELAY"
            continue
        fi

        log_message "✅ Internet connection OK"

        # Попытка загрузки по SFTP
        log_message "Starting SFTP upload to $FTP_HOST:$FTP_PORT..."

        local SFTP_OUTPUT=$(timeout "$TIMEOUT_SFTP" sshpass -p "$FTP_PASS" sftp \
            -o StrictHostKeyChecking=no \
            -o ConnectTimeout="$SFTP_CONNECT_TIMEOUT" \
            -P "$FTP_PORT" \
            "$FTP_USER@$FTP_HOST" 2>&1 <<EOF
cd "$FTP_PATH"
put "$FILE" "$BASENAME"
ls -l "$BASENAME"
bye
EOF
)

        local SFTP_EXIT=$?

        log_message "SFTP output: $SFTP_OUTPUT"
        log_message "SFTP exit code: $SFTP_EXIT"

        # Проверяем успешность загрузки по наличию файла в выводе ls
        if [ $SFTP_EXIT -eq 0 ] && echo "$SFTP_OUTPUT" | grep -q "$BASENAME"; then
            log_message "✅ SFTP upload verified: $BASENAME found on server"

            # Уведомление API с таймаутом
            log_message "Notifying API at $API_URL..."

            # Небольшая задержка чтобы файл точно записался на диск
            sleep "$API_CALL_DELAY"

            local API_RESPONSE=$(timeout "$TIMEOUT_API" curl -s -w "\nHTTP_CODE:%{http_code}" \
                -X POST "$API_URL" \
                -d "filename=$BASENAME" 2>&1)

            local CURL_EXIT=$?

            if [ $CURL_EXIT -eq 0 ]; then
                local HTTP_CODE=$(echo "$API_RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)
                local API_BODY=$(echo "$API_RESPONSE" | grep -v "HTTP_CODE:")

                log_message "API HTTP code: $HTTP_CODE"
                log_message "API response body: $API_BODY"

                if [[ "$HTTP_CODE" == "200" || "$HTTP_CODE" == "201" ]]; then
                    log_message "✅ API notification successful"
                    notify "✅ Успешно" "$BASENAME загружен и передан API"
                    return 0
                else
                    log_message "⚠️ API notification failed (HTTP $HTTP_CODE)"
                    notify "⚠️ Частичный успех" "$BASENAME загружен, API код $HTTP_CODE"

                    # Если API вернул 500 - файл не найден, значит загрузка не удалась
                    if [[ "$HTTP_CODE" == "500" ]]; then
                        log_message "❌ Server can't find file - upload actually failed!"
                        continue  # Повторяем попытку
                    fi
                    return 0
                fi
            elif [ $CURL_EXIT -eq 124 ]; then
                log_message "⚠️ API call timeout after $TIMEOUT_API seconds"
                notify "⚠️ Частичный успех" "$BASENAME загружен, API timeout"
                return 0
            else
                log_message "⚠️ API call failed with exit code: $CURL_EXIT"
                notify "⚠️ Частичный успех" "$BASENAME загружен, API недоступен"
                return 0
            fi
        else
            if [ $SFTP_EXIT -eq 124 ]; then
                log_message "⚠️ SFTP timeout after $TIMEOUT_SFTP seconds"
            elif [ $SFTP_EXIT -eq 0 ]; then
                log_message "⚠️ SFTP returned success but file not found on server!"
            else
                log_message "⚠️ SFTP failed with exit code: $SFTP_EXIT"
            fi

            log_message "⚠️ Upload failed, retrying in $RETRY_DELAY seconds ($i/$RETRY_COUNT)..."

            if [ $i -eq $RETRY_COUNT ]; then
                notify "❌ Ошибка" "$BASENAME не загружен после $RETRY_COUNT попыток"
            fi
        fi

        sleep "$RETRY_DELAY"
    done

    log_message "🚫 Giving up on $BASENAME after $RETRY_COUNT attempts."
    return 1
}

# Основной цикл мониторинга
log_message "👁️ Starting directory monitoring: $WATCH_DIR"
notify "👁️ Мониторинг" "Отслеживаю папку Camera"

inotifywait -m --event close_write --event moved_to --event create "$WATCH_DIR" --format '%e %w%f' 2>&1 | while read EVENT FILE
do
    BASENAME=$(basename "$FILE")

    log_message "Event detected: $EVENT | File: $FILE"

    # Пропускаем временные файлы
    if [[ "$BASENAME" == *.pending-* ]]; then
        log_message "Skipping pending file: $BASENAME"
        continue
    fi

    # Обрабатываем только изображения
    if [[ "$BASENAME" == *.jpg || "$BASENAME" == *.jpeg || "$BASENAME" == *.png || "$BASENAME" == *.JPG || "$BASENAME" == *.JPEG || "$BASENAME" == *.PNG ]]; then
        log_message "📷 Event: $EVENT | File: $BASENAME"
        notify "📷 Новое фото" "Обнаружен файл: $BASENAME"

        # Ждём завершения записи файла
        log_message "Waiting $FILE_STABILIZE_DELAY seconds for file to stabilize..."
        sleep "$FILE_STABILIZE_DELAY"

        log_message "Calling upload_file function..."
        upload_file "$FILE" &
        log_message "Upload started in background for $BASENAME"
    else
        log_message "Ignoring non-image file: $BASENAME"
    fi
done
