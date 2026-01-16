<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

# 🚀 SIPELITA MAINTENANCE GUIDE
*Dokumen maintenance untuk aplikasi SIPELITA*

## 📊 STATUS CHECK
```bash
pm2 status                                # Cek status semua service
sudo lsof -i :3000                        # Cek port frontend
sudo lsof -i :8081                        # Cek port backend
curl http://localhost:3000/api/health     # Test API kesehatan
```
## 📝 LOGS MONITORING
```bash
pm2 logs                                  # Semua logs realtime
pm2 logs sipelita-frontend --lines 50     # Logs frontend
pm2 logs sipelita-backend --lines 50      # Logs backend
tail -f /var/www/sipelita/backend/storage/logs/laravel.log  # Laravel logs
```
## 🔄 RESTART & RELOAD
```bash
pm2 restart all                           # Restart semua
pm2 restart sipelita-frontend             # Restart frontend saja
pm2 restart sipelita-backend              # Restart backend saja
pm2 reload all                            # Reload tanpa downtime
```
## 🚨 FULL RESET (Emergency)
```bash
pm2 stop all
pm2 delete all
cd /var/www/sipelita/backend && pm2 start ./start.sh --name sipelita-backend
cd /var/www/sipelita/frontend && pm2 start "pnpm run start" --name sipelita-frontend
pm2 save
```
## 🔄 UPDATE APLIKASI
### Frontend (Next.js)
```bash
cd /var/www/sipelita/frontend
git pull origin main
pnpm install
pnpm run build
pm2 restart sipelita-frontend
```
### Backend (Laravel)
```bash
cd /var/www/sipelita/backend
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
pm2 restart sipelita-backend
```
## 🛠️ TROUBLESHOOTING
### 1. Port Conflict
```bash
sudo lsof -i :3000                        # Cek apa yang pakai port 3000
sudo kill -9 $(sudo lsof -t -i:3000)      # Kill process di port 3000
sudo lsof -i :8081                        # Cek apa yang pakai port 8081  
sudo kill -9 $(sudo lsof -t -i:8081)      # Kill process di port 8081
```
### 2. Permission Issues
```bash
sudo chown -R nirwasita:www-data /var/www/sipelita
sudo chmod -R 775 /var/www/sipelita/backend/storage
sudo chmod -R 775 /var/www/sipelita/backend/bootstrap/cache
```
### 3. Disk Space
```bash
df -h                                      # Cek disk usage
du -sh /var/www/sipelita/frontend/.next/  # Cek Next.js build size
du -sh /var/www/sipelita/backend/storage/ # Cek Laravel storage
```
### 4. Network Check
```bash
curl -I http://localhost:3000/            # Test frontend lokal
curl -I http://localhost:8081/api         # Test backend lokal
curl -I https://nirwasita.kemenlh.go.id   # Test dari luar
```
## 📍 ARSITEKTUR APLIKASI
```bash
Frontend: Next.js (port 3000) → http://localhost:3000
Backend:  Laravel (port 8081) → http://localhost:8081
Proxy:    /api/* → Laravel backend
Domain:   https://nirwasita.kemenlh.go.id
```