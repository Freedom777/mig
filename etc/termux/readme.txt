mkdir -p ~/scripts
nano ~/scripts/upload_photos.sh

After save:
chmod +x ~/scripts/upload_photos.sh

И запустить (постоянно слушает папку):

bash ~/upload_photos.sh

Чтобы скрипт запускался при старте Termux, можно добавить его в ~/.bashrc или создать termux-wake-lock && bash ~/upload_photos.sh &.

2. Что нужно на сервере

Твой Laravel-сервер принимает POST-запрос от скрипта:

Route::post('/api/photo_uploaded', function (Request $request) {
    $filename = $request->input('filename');
    dispatch(new ProcessPhotoJob($filename));
    return response()->json(['status' => 'ok']);
});

Шаг 3. Настрой автозапуск через Termux:Boot

В Termux выполни:

mkdir -p ~/.termux/boot/
cp ~/scripts/upload_photos.sh ~/.termux/boot/


Теперь Termux:Boot при включении телефона:

сам запустит Termux,

активирует wake-lock,

и начнёт следить за /DCIM/Camera.

Шаг 4. Разреши Termux работать в фоне

Очень важно:

Настройки -> Приложения -> Termux -> Батарея -> Без ограничений

В “Недавних приложениях” закрепи Termux (значок замка или свайп вниз)

Если у тебя Android 12+ — включи “Разрешить фоновую работу” и “Автозапуск”

Шаг 5. Проверка

Сделай новое фото

В Termux (или в логах сервера) ты увидишь, что фото ушло.

На сервер прилетит POST с filename, Laravel обработает его.
