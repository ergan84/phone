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

    $ldaprdn = $config['auth_domain_prefix'] . "\\" . $username;

    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

    $bind = @ldap_bind($ldap, $ldaprdn, $password);
	

    if ($bind) {
        # header("Location :".$_SERVER['HTTP_REFERER']);
		header("Location: phone.php");
        @ldap_close($ldap);
    } else {
     
		header("Location: error.php");
        @ldap_close($ldap);
    }

}else{
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="login_styles.css">
</head>
<body>

    <form action="" method="POST">
        <p>Зайдите под своим аккаунтом, чтобы позвонить сотруднику</p>
        <input id="username" type="text" name="username" required placeholder="Логин" /> 
        <input id="password" type="password" name="password" required placeholder="Пароль" />        
        <input type="submit" name="submit" value="Войти" />
    </form>

</body>
</html>
<?php } ?> 

