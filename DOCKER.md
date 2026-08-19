# Развёртывание через Docker Compose

Альтернатива ручной установке Apache/PHP из `DEPLOY.md` — контейнер на базе
`php:8.1-apache` с уже собранными расширениями `ldap` и `mbstring`.

## 1. Требования к хосту

Docker и Docker Compose plugin на Ubuntu 22.04:

```bash
sudo apt update
sudo apt install -y docker.io docker-compose-plugin
sudo systemctl enable --now docker
```

Хост, на котором будет работать контейнер, должен иметь сетевой доступ к контроллерам
домена — `10.10.10.2` и `172.16.65.2` (стандартный Docker bridge не мешает исходящим
соединениям по IP, дополнительная настройка сети обычно не требуется).

## 2. Секреты

```bash
cp .env.example .env
# открыть .env и вписать реальный пароль сервисной LDAP-учётки
```

`docker-compose.yml` требует `PHONEBOOK_DIR_PASSWORD` — без него `config.php` бросает
исключение и контейнер не поднимет приложение. Остальные параметры (IP серверов,
базовый DN, логин) заданы в `docker-compose.yml` с текущими продакшн-значениями и
их тоже можно переопределить через `.env` при необходимости.

## 3. Запуск

```bash
docker compose up -d --build
```

Приложение будет на `http://<host>:8080/`. Порт задан в `docker-compose.yml`
(`8080:80`) — поменять при конфликте.

## 4. Проверка

```bash
docker compose logs -f
curl -I http://localhost:8080/config.php   # ожидаем 403
curl -I http://localhost:8080/index.php.bak # 403 (файла и так нет в образе — .bak не копируются)
```

Функционально — как в `DEPLOY.md`: логин через AD, редирект на `phone.php`,
таблица сотрудников с поиском и карточками.

## 5. Обновление

```bash
git pull   # или синхронизация файлов другим способом
docker compose up -d --build
```

Пересборка образа нужна, т.к. код копируется в образ на этапе `COPY` (не volume-mount),
это осознанный выбор для воспроизводимости — на каждом деплое собирается фиксированный образ.
