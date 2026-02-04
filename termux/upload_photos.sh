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
TIMEOUT_CONFIRM=30          # Таймаут ожидания подтверждения пользователя

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

# 📂 Временная директория
TEMP_DIR="$HOME/tmp"
mkdir -p "$TEMP_DIR"

# 🔒 Блокировка пробуждения
termux-wake-lock

ask_user_notification() {
    local FILE="$1"
    local BASENAME
    BASENAME=$(basename "$FILE")

    # Показываем диалог
    termux-dialog confirm \
        -t "📸 Новое фото" \
        -i "Загрузить $BASENAME на сервер?" 2>&1 | grep -q 'yes'

    # grep вернёт 0 если нашёл 'yes', иначе 1
    return $?
}

# Проверка доступности Termux:API с повторными попытками
wait_for_termux_api() {
    sleep "$STARTUP_DELAY"

    for i in $(seq 1 $API_RETRY_COUNT); do
        if timeout "$TIMEOUT_TOAST" termux-toast "API Test" 2>&1 | grep -q "Connection refused"; then
            sleep "$API_RETRY_DELAY"
        else
            return 0
        fi
    done

    return 1
}

# Безопасная отправка уведомлений с проверкой доступности API
notify() {
    local TITLE="$1"
    local TEXT="$2"

    timeout "$TIMEOUT_NOTIFICATION" termux-notification \
        --title "$TITLE" \
        --content "$TEXT" \
        --priority high \
        --sound 2>/dev/null

    timeout "$TIMEOUT_TOAST" termux-toast "$TITLE: $TEXT" 2>/dev/null

    return 0
}

# Ожидаем инициализацию Termux:API
wait_for_termux_api

notify "📸 Запуск" "Photo uploader started"

upload_file() {
    local FILE="$1"
    local API_RESPONSE
    local BASENAME
    local SFTP_OUTPUT

    BASENAME=$(basename "$FILE")

    notify "🟡 Загрузка" "Начинаю загрузку: $BASENAME"

    # Проверяем, что файл существует и доступен
    if [ ! -f "$FILE" ] || [ ! -r "$FILE" ]; then
        notify "❌ Ошибка" "Файл недоступен: $BASENAME"
        return 1
    fi

    for i in $(seq 1 $RETRY_COUNT); do
        # Проверка интернета с таймаутом
        if ! timeout "$TIMEOUT_PING" ping -c"$PING_COUNT" -W"$PING_WAIT" "$PING_TARGET" >/dev/null 2>&1; then
            if [ $i -eq 1 ]; then
                notify "⚠️ Нет сети" "Ожидаю подключения для $BASENAME"
            fi
            sleep "$RETRY_DELAY"
            continue
        fi

        # Попытка загрузки по SFTP
        SFTP_OUTPUT=$(timeout "$TIMEOUT_SFTP" sshpass -p "$FTP_PASS" sftp \
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

        # Проверяем успешность загрузки по наличию файла в выводе ls
        if [ $SFTP_EXIT -eq 0 ] && echo "$SFTP_OUTPUT" | grep -q "$BASENAME"; then
            sleep "$API_CALL_DELAY"

            API_RESPONSE=$(timeout "$TIMEOUT_API" curl -s -w "\nHTTP_CODE:%{http_code}" \
                -X POST "$API_URL" \
                -d "filename=$BASENAME" 2>&1)

            local CURL_EXIT=$?

            if [ $CURL_EXIT -eq 0 ]; then
                local HTTP_CODE=$(echo "$API_RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)

                if [[ "$HTTP_CODE" == "200" || "$HTTP_CODE" == "201" ]]; then
                    notify "✅ Успешно" "$BASENAME загружен и передан API"
                    return 0
                else
                    notify "⚠️ Частичный успех" "$BASENAME загружен, API код $HTTP_CODE"

                    # Если API вернул 500 - файл не найден, значит загрузка не удалась
                    if [[ "$HTTP_CODE" == "500" ]]; then
                        continue  # Повторяем попытку
                    fi
                    return 0
                fi
            elif [ $CURL_EXIT -eq 124 ]; then
                notify "⚠️ Частичный успех" "$BASENAME загружен, API timeout"
                return 0
            else
                notify "⚠️ Частичный успех" "$BASENAME загружен, API недоступен"
                return 0
            fi
        else
            if [ $i -eq $RETRY_COUNT ]; then
                notify "❌ Ошибка" "$BASENAME не загружен после $RETRY_COUNT попыток"
            fi
        fi

        sleep "$RETRY_DELAY"
    done

    return 1
}

# Основной цикл мониторинга
notify "👁️ Мониторинг" "Отслеживаю папку Camera"

inotifywait -m --event close_write --event moved_to --event create "$WATCH_DIR" --format '%e %w%f' 2>&1 | while read EVENT FILE
do
    BASENAME=$(basename "$FILE")

    # Пропускаем временные файлы
    if [[ "$BASENAME" == *.pending-* ]]; then
        continue
    fi

    # Обрабатываем только изображения
    if [[ "$BASENAME" == *.jpg || "$BASENAME" == *.jpeg || "$BASENAME" == *.png || "$BASENAME" == *.JPG || "$BASENAME" == *.JPEG || "$BASENAME" == *.PNG ]]; then
        notify "📷 Новое фото" "Обнаружен файл: $BASENAME"

        # Ждём завершения записи файла
        sleep "$FILE_STABILIZE_DELAY"

        # Спрашиваем через уведомление
        if ask_user_notification "$FILE"; then
            upload_file "$FILE"
        else
            notify "⏭️ Пропущено" "$BASENAME не загружен"
        fi
    fi
done
