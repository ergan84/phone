# Перенос на Ubuntu 22.04

## 1. Пакеты

Ubuntu 22.04 ставит PHP 8.1 из стандартного репозитория.

```bash
sudo apt update
sudo apt install -y apache2 php8.1 libapache2-mod-php8.1 php8.1-ldap php8.1-mbstring
sudo a2enmod headers rewrite
```

- `php8.1-ldap` — обязателен, без него упадут все `ldap_*` вызовы в `index.php`/`phone.php`.
- `php8.1-mbstring` — обязателен для `mb_substr()` в `phone.php`.
- `mod_headers` — нужен для директив `Header set/unset` в `.htaccess`.

## 2. Сетевой доступ

Сервер должен иметь доступ по сети до обоих контроллеров домена:
- `10.10.10.2` (LDAP, аутентификация пользователей в `index.php`)
- `172.16.65.2` (LDAP, сервисная выгрузка сотрудников в `phone.php`)

Проверить после переноса: `nc -zv 10.10.10.2 389` и `nc -zv 172.16.65.2 389`.

## 3. Файлы проекта

Скопировать содержимое каталога в `/var/www/phonebook` (или свой путь):

```bash
sudo mkdir -p /var/www/phonebook
sudo rsync -a --exclude='.claude' --exclude='*.bak' ./ /var/www/phonebook/
sudo chown -R www-data:www-data /var/www/phonebook
sudo find /var/www/phonebook -type f -exec chmod 640 {} \;
sudo find /var/www/phonebook -type d -exec chmod 750 {} \;
```

`*.php.bak` в перенос не берём — это старые копии с теми же LDAP-кредами внутри, они не нужны в проде.

## 4. Секреты (config.php)

Креды LDAP вынесены в `config.php` (не отдаётся напрямую — заблокирован в `.htaccess`).
Несекретные параметры (IP серверов, base DN, логин) имеют значения по умолчанию —
текущие продакшн-настройки, их можно переопределить переменными окружения Apache,
не редактируя файл на сервере. Пароль сервисной учётки **обязателен** через переменную
окружения — без неё `config.php` бросает исключение и сайт не запустится:

```apache
# /etc/apache2/sites-available/phonebook.conf, внутри <VirtualHost>
SetEnv PHONEBOOK_DIR_LOGIN "yerzhan.abduhaimov@almatytrade.kz"
SetEnv PHONEBOOK_DIR_PASSWORD "новый_пароль_после_ротации"
```

**Важно:** пароль сервисной учётки (`ZaqwsX1@`) был захардкожен в веб-доступном
`phone.php` до этого переноса — обязательно смените его в AD, старый считается
скомпрометированным.

## 5. Apache vhost

```apache
<VirtualHost *:80>
    ServerName phonebook.almatytrade.kz
    DocumentRoot /var/www/phonebook

    <Directory /var/www/phonebook>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/phonebook_error.log
    CustomLog ${APACHE_LOG_DIR}/phonebook_access.log combined
</VirtualHost>
```

`AllowOverride All` обязателен — иначе `.htaccess` (защита `config.php`/`*.bak`,
заголовки безопасности) игнорируется.

```bash
sudo a2ensite phonebook.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

## 6. Проверка после переноса

- `index.php` — логин под доменной учёткой, успешный редирект на `phone.php`.
- Логин с неверным паролем — редирект на `error.php` (страница-заглушка, ранее
  отсутствовала в проекте — файл был добавлен).
- `phone.php` — таблица сотрудников подгружается, поиск и модалки работают.
- `curl -I https://.../config.php` и `.../index.php.bak` — должны вернуть 403.
- Проверить `php -l index.php` и `php -l phone.php` перед первым запуском на новом сервере.
