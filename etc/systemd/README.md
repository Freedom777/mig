# Systemd Services

## Face API Service

Face Recognition API service running on CPU.

### Installation
```bash
# Install service
sudo cp /var/www/photo/etc/systemd/face-api.service /etc/systemd/system/

# Reload systemd
sudo systemctl daemon-reload

# Enable auto-start
sudo systemctl enable face-api

# Start service
sudo systemctl start face-api

# Check status
sudo systemctl status face-api
```

### Management
```bash
# Start
sudo systemctl start face-api

# Stop
sudo systemctl stop face-api

# Restart
sudo systemctl restart face-api

# Reload config (after editing service file)
sudo systemctl daemon-reload
sudo systemctl restart face-api

# View logs
sudo journalctl -u face-api -f

# Application logs
tail -f /var/www/photo/storage/logs/face-api-error.log
tail -f /var/www/photo/storage/logs/face-api-access.log
```

### Troubleshooting
```bash
# Check if service is running
systemctl is-active face-api

# Check if service is enabled
systemctl is-enabled face-api

# View recent errors
sudo journalctl -u face-api -n 50 --no-pager

# Test API endpoint
curl http://127.0.0.1:5000/health
```

### Configuration

- Workers: 4 (adjust based on CPU cores)
- Threads per worker: 2
- Timeout: 300 seconds
- Memory limit: 12GB
- CPU quota: 800% (8 cores)

Edit `/etc/systemd/system/face-api.service` to modify settings.
