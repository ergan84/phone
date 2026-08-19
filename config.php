<?php
/**
 * Конфигурация LDAP/AD.
 * Неcекретные значения по умолчанию — текущие продакшн-настройки.
 * Пароль сервисного аккаунта обязателен через переменную окружения
 * PHONEBOOK_DIR_PASSWORD (см. DEPLOY.md / docker-compose.yml) —
 * в коде он больше не хранится.
 */
$dirPassword = getenv('PHONEBOOK_DIR_PASSWORD');
if ($dirPassword === false || $dirPassword === '') {
    throw new RuntimeException('Переменная окружения PHONEBOOK_DIR_PASSWORD не задана');
}

return [
    // Логин: контроллер домена, к которому биндится сам пользователь
    'auth_ldap_server'   => getenv('PHONEBOOK_AUTH_LDAP') ?: 'ldap://10.10.10.2',
    'auth_domain_prefix' => getenv('PHONEBOOK_DOMAIN_PREFIX') ?: 'ALMATYTRADE',

    // Справочник: сервисный аккаунт для выгрузки сотрудников из AD
    'dir_ldap_server' => getenv('PHONEBOOK_DIR_LDAP') ?: '172.16.65.2',
    'dir_base_dn'     => getenv('PHONEBOOK_DIR_BASE_DN') ?: 'dc=almatytrade,dc=kz',
    'dir_login'       => getenv('PHONEBOOK_DIR_LOGIN') ?: 'yerzhan.abduhaimov@almatytrade.kz',
    'dir_password'    => $dirPassword,
];
