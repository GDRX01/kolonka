# Быстрый запуск проекта «Колонка» (Windows PowerShell)

## 1. Создание базы данных
Откройте консоль MySQL (`mysql -u root`) и выполните:
```sql
CREATE DATABASE kolonka CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 2. Установка и настройка Backend
Откройте терминал **PowerShell** в корневой папке проекта (где лежат `backend` и `frontend`):

```powershell
# 1. Создаём чистый Laravel и ставим Sanctum (на вопрос о миграциях отвечаем: no)
composer create-project laravel/laravel api
cd api
php artisan install:api # migration no
cd ..

# 2. Копируем файлы проекта поверх чистого Laravel (важно использовать /* и -Force)
Copy-Item -Path backend\* -Destination api\ -Recurse -Force
```

## 3. Настройка `.env`
Откройте файл `api/.env` и замените блок подключения к БД на этот:
```ini
APP_LOCALE=ru
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kolonka
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=log
FRONTEND_URL=http://localhost:5173
```

## 4. Миграции, тестовые данные и запуск API
В том же терминале выполните:
```powershell
cd api
composer dump-autoload
php artisan config:clear
php artisan migrate:fresh --seed
php artisan serve
```
Проверка: откройте `http://127.0.0` - должен вернуться JSON.

## 5. Запуск Frontend
Откройте **второй терминал** в корневой папке проекта:
```powershell
cd frontend
npm install
npm run dev
```
Сайт откроется по адресу `http://localhost:5173`. *(Не создавайте файл `frontend/.env` для локальной работы).*

## 6. Тестовые аккаунты (пароль везде: `password`)
* **Администратор:** `un@kolonka.test`
* **Автор (с черновиками):** `anna@kolonka.test`
* **Обычные пользователи:** `ilya@kolonka.test`, `maria@kolonka.test`, `oleg@kolonka.test`