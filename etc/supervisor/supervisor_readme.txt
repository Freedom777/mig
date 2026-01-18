# Перечитать конфигурацию
sudo supervisorctl reread

# Применить изменения
sudo supervisorctl update

# Должно вывести
photo-workers: added process group

# Проверить статус
sudo supervisorctl status

# Управление группой
sudo supervisorctl start photo-workers:*
sudo supervisorctl stop photo-workers:*
sudo supervisorctl restart photo-workers:*

# Управление отдельным worker'ом
sudo supervisorctl restart photo-metadatas-worker:*

# Запуск worker
sudo supervisorctl start photo-thumbnails-worker:*

# Остановка worker
sudo supervisorctl stop photo-thumbnails-worker:*

# Перезапуск worker
sudo supervisorctl restart photo-thumbnails-worker:*

cd /etc/supervisor/conf.d


# Systemd service
sudo cp /var/www/photo/etc/systemd/face-api.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl restart face-api

# Supervisor
sudo cp /var/www/photo/etc/supervisor/photo-face-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart photo-face-worker:*

# Проверяем
systemctl status face-api
supervisorctl status photo-face-worker:*
