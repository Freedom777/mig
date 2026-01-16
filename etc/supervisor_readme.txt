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
