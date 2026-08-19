<?php session_start();
/**
 * Created by Joe of ExchangeCore.com
 */
if(isset($_POST['username']) && isset($_POST['password'])){

    $config = require __DIR__ . '/config.php';
    $adServer = $config['auth_ldap_server'];

    $ldap = ldap_connect($adServer);
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($username === '' || $password === '') {
        // Пустой пароль == анонимный LDAP-bind, который AD считает успешным
        // независимо от логина — отклоняем до похода в LDAP.
        header("Location: /login-error");
        exit;
    }

    $ldaprdn = $config['auth_domain_prefix'] . "\\" . $username;

    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

    $bind = @ldap_bind($ldap, $ldaprdn, $password);


    if ($bind) {
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $username;

        // Достаём ФИО из AD для приветствия; если не получилось — покажем логин.
        $_SESSION['fullname'] = $username;
        $selfFilter = '(sAMAccountName=' . ldap_escape($username, '', LDAP_ESCAPE_FILTER) . ')';
        $selfResult = @ldap_search($ldap, $config['dir_base_dn'], $selfFilter, ['cn']);
        if ($selfResult) {
            $selfEntries = ldap_get_entries($ldap, $selfResult);
            if (($selfEntries['count'] ?? 0) > 0 && !empty($selfEntries[0]['cn'][0])) {
                $_SESSION['fullname'] = $selfEntries[0]['cn'][0];
            }
        }

		header("Location: /directory");
        @ldap_close($ldap);
        exit;
    } else {

		header("Location: /login-error");
        @ldap_close($ldap);
        exit;
    }

}else{
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Телефонный справочник</title>
    <link rel="stylesheet" href="login_styles.css">
</head>
<body>

    <div class="logo"><img src="images/logo.png" alt="AlmaWine" style="height:100%; max-width:280px; object-fit:contain;"></div>

    <form action="" method="POST">
        <p>Зайдите под своим аккаунтом, чтобы позвонить сотруднику</p>
        <input id="username" type="text" name="username" required placeholder="Логин" /> 
        <input id="password" type="password" name="password" required placeholder="Пароль" />        
        <input type="submit" name="submit" value="Войти" />
    </form>

</body>
</html>
<?php } ?> 

