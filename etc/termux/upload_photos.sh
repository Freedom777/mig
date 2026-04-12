#!/data/data/com.termux/files/usr/bin/bash

# 🌐 FTP/SFTP настройки
FTP_HOST="91.98.79.139"
FTP_PORT="2271"
FTP_USER="ftpfreedom"
FTP_PASS="ste4enie"
FTP_PATH="/var/www/photo/storage/app/public/images"

# 🔗 API настройки
API_URL="https://photo.freedomvibe.net/api/images/new-upload"

# 📁 Папка для мониторинга
WATCH_DIR="$HOME/storage/dcim/Camera"

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

# 📶 WiFi настройки
WIFI_WAIT_TIMEOUT=14400     # Время ожидания WiFi (секунды, 4 часа = 14400)
WIFI_CHECK_INTERVAL=600     # Интервал проверки WiFi (секунды, 10 минут = 600)

# ⏳ Задержки
FILE_STABILIZE_DELAY=5      # Ожидание стабилизации файла после создания (секунды)
API_CALL_DELAY=2            # Задержка перед вызовом API после загрузки (секунды)
STARTUP_DELAY=30            # Ожидание инициализации Termux:API после старта (секунды)
API_RETRY_DELAY=5           # Задержка между попытками подключения к API (секунды)
API_RETRY_COUNT=10          # Количество попыток подключения к Termux:API

# 📂 Временная директория
TEMP_DIR="$HOME/tmp"
mkdir -p "$TEMP_DIR"

# 📝 Файл для хранения отложенных загрузок
PENDING_UPLOADS="$TEMP_DIR/pending_uploads.txt"
touch "$PENDING_UPLOADS"

# 🔒 Блокировка пробуждения
termux-wake-lock

# 🔄 Проверка что скрипт уже не запущен (защита от дублирования)
SCRIPT_NAME=$(basename "$0")
RUNNING_COUNT=$(pgrep -fc "$SCRIPT_NAME")

# Если запущено больше 2 процессов (текущий + старый)
if [ "$RUNNING_COUNT" -gt 2 ]; then
    termux-toast "⚠️ Upload script already running ($RUNNING_COUNT instances)"
    exit 0
fi

# Проверка WiFi подключения
is_wifi_connected() {
    local WIFI_INFO
    WIFI_INFO=$(termux-wifi-connectioninfo 2>/dev/null)

    if echo "$WIFI_INFO" | grep -q '"ssid"'; then
        return 0  # WiFi подключен
    else
        return 1  # WiFi не подключен
    fi
}

# Показать диалог выбора действия
ask_user_action() {
    local FILE="$1"
    local BASENAME
    BASENAME=$(basename "$FILE")

    # Захватываем ответ напрямую в переменную
    local RESPONSE
    RESPONSE=$(termux-dialog sheet \
        -t "📸 $BASENAME" \
        -v "Сейчас,WiFi,Позже,Пропустить" 2>&1)

    # Парсим индекс через jq
    local INDEX
    INDEX=$(echo "$RESPONSE" | jq -r '.index' 2>/dev/null)

    case "$INDEX" in
        0) echo "now" ;;      # Сейчас
        1) echo "wifi" ;;     # WiFi
        2) echo "later" ;;    # Позже
        3) echo "skip" ;;     # Пропустить
        *) echo "cancel" ;;   # Отмена или ошибка
    esac
}

# Добавить файл в очередь отложенных
add_to_pending() {
    local FILE="$1"

    # Проверяем что файл ещё не в очереди
    if ! grep -Fxq "$FILE" "$PENDING_UPLOADS"; then
        echo "$FILE" >> "$PENDING_UPLOADS"
        notify "⏸️ Отложено" "$(basename "$FILE") добавлен в очередь"
    fi
}

# Удалить файл из очереди отложенных
remove_from_pending() {
    local FILE="$1"
    local TEMP_FILE="$TEMP_DIR/pending_uploads_tmp.txt"

    grep -Fxv "$FILE" "$PENDING_UPLOADS" > "$TEMP_FILE" 2>/dev/null || true
    mv "$TEMP_FILE" "$PENDING_UPLOADS"
}

# Создать уведомление для отложенного файла
create_pending_notification() {
    local FILE="$1"
    local BASENAME
    BASENAME=$(basename "$FILE")

    # ID уведомления = хеш имени файла
    local NOTIF_ID
    NOTIF_ID=$(echo "$BASENAME" | md5sum | cut -d' ' -f1 | cut -c1-8)

    # Путь к триггер-скрипту
    local TRIGGER_SCRIPT="$TEMP_DIR/upload_trigger.sh"

    # Создаём уведомление с action кнопкой
    termux-notification \
        --id "$NOTIF_ID" \
        --title "⏸️ Отложено: $BASENAME" \
        --content "Нажмите для загрузки" \
        --priority high \
        --ongoing \
        --action "$TRIGGER_SCRIPT '$FILE'" \
        --button1 "Загрузить" \
        --button1-action "$TRIGGER_SCRIPT '$FILE'" 2>/dev/null
}

# Удалить уведомление отложенного файла
remove_pending_notification() {
    local FILE="$1"
    local BASENAME
    BASENAME=$(basename "$FILE")

    local NOTIF_ID
    NOTIF_ID=$(echo "$BASENAME" | md5sum | cut -d' ' -f1 | cut -c1-8)

    termux-notification-remove "$NOTIF_ID" 2>/dev/null
}

# Ожидание WiFi с таймаутом
wait_for_wifi() {
    local FILE="$1"
    local BASENAME
    BASENAME=$(basename "$FILE")

    local ELAPSED=0
    local MAX_WAIT=$WIFI_WAIT_TIMEOUT

    notify "📶 Ожидание WiFi" "$BASENAME - жду WiFi макс ${MAX_WAIT}с"

    while [ $ELAPSED -lt $MAX_WAIT ]; do
        if is_wifi_connected; then
            notify "✅ WiFi найден" "$BASENAME - начинаю загрузку"
            return 0
        fi

        sleep "$WIFI_CHECK_INTERVAL"
        ELAPSED=$((ELAPSED + WIFI_CHECK_INTERVAL))
    done

    notify "⏱️ Таймаут WiFi" "$BASENAME - WiFi не найден, отложено"
    return 1
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

# Обработка файла с выбором действия
process_file_with_choice() {
    local FILE="$1"
    local ACTION

    ACTION=$(ask_user_action "$FILE")

    case "$ACTION" in
        "now")
            # Загрузить сейчас
            if upload_file "$FILE"; then
                remove_pending_notification "$FILE"
            fi
            ;;

        "wifi")
            # Ждать WiFi
            if wait_for_wifi "$FILE"; then
                # WiFi найден - загружаем
                if upload_file "$FILE"; then
                    remove_pending_notification "$FILE"
                fi
            else
                # Таймаут WiFi - отложить
                add_to_pending "$FILE"
                create_pending_notification "$FILE"
            fi
            ;;

        "later")
            # Отложить
            add_to_pending "$FILE"
            create_pending_notification "$FILE"
            ;;

        "skip")
            # Пропустить - не загружать
            notify "⏭️ Пропущено" "$(basename "$FILE") не будет загружен"
            ;;

        "cancel")
            # Отменено (назад в диалоге)
            notify "🚫 Отменено" "$(basename "$FILE") действие отменено"
            ;;
    esac
}

# Создаём скрипт-триггер для обработки нажатий на уведомления
# Используем TEMP_DIR вместо ~/.termux/ (чище и логичнее)
TRIGGER_SCRIPT="$TEMP_DIR/upload_trigger.sh"
cat > "$TRIGGER_SCRIPT" <<'TRIGGER_EOF'
#!/data/data/com.termux/files/usr/bin/bash
FILE="$1"

if [ -z "$FILE" ] || [ ! -f "$FILE" ]; then
    termux-toast "Файл не найден: $FILE"
    exit 1
fi

# Добавляем файл в очередь обработки
TRIGGER_FILE="$HOME/tmp/upload_trigger_queue.txt"
echo "$FILE" >> "$TRIGGER_FILE"

termux-toast "Файл добавлен в очередь: $(basename "$FILE")"
TRIGGER_EOF

chmod +x "$TRIGGER_SCRIPT"

# Основной цикл мониторинга
notify "👁️ Мониторинг" "Отслеживаю папку Camera"

# Фоновый процесс для обработки триггеров
(
    TRIGGER_FILE="$HOME/tmp/upload_trigger_queue.txt"
    touch "$TRIGGER_FILE"

    while true; do
        if [ -s "$TRIGGER_FILE" ]; then
            # Читаем первую строку
            FILE=$(head -n 1 "$TRIGGER_FILE")

            # Удаляем первую строку
            TEMP=$(mktemp)
            tail -n +2 "$TRIGGER_FILE" > "$TEMP"
            mv "$TEMP" "$TRIGGER_FILE"

            if [ -f "$FILE" ]; then
                remove_from_pending "$FILE"
                process_file_with_choice "$FILE"
            fi
        fi

        sleep 5
    done
) &

# Основной цикл inotify
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

        # Показываем диалог с выбором действия
        process_file_with_choice "$FILE"
    fi
done
