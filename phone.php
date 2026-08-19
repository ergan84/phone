<?php
session_start();
if (empty($_SESSION['authenticated'])) {
    header('Location: index.php');
    exit;
}
?>
<!doctype html>
<html lang="ru">
<head><title>Телефонный справочник</title>
<!-- Semantic -->
<link rel="stylesheet" type="text/css" href="semantic/semantic.min.css">
<style>
body, .ui, h1, h2, h3, h4, h5 {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
}
</style>
<script src="semantic/jquery-3.1.1.min.js"></script>
<script src="semantic/semantic.js"></script>

<!-- Data tables-->
<link rel="stylesheet" href="datatables/datatables.min.css" type="text/css">
<script type="text/javascript" language="javascript" src="datatables/datatables.min.js"></script>

<!--Datatables для отображения таблицы-->
<script type="text/javascript" charset="utf-8">
$(document).ready(function() {
    $('.example_phone').DataTable( {
        "language": {
            "url": "semantic/Russian.json"
        },
        "pageLength": 50,
        "order": [],
    } );
} );
		</script>
<!-- Modal windows: используем модуль modal из общего бандла semantic.js выше,
     отдельные components/modal.js и modal.css не подключаем, чтобы не
     переопределять его версией из другого файла -->
<script type="text/javascript" charset="utf-8">
$(document).ready(function() {
// Делегирование через document (а не прямая привязка на '.show-modal') —
// устойчивее к тому, как DataTables управляет строками при пагинации.
$(document).on('click', '.show-modal', function (e) {
  e.preventDefault();
  var modal_id = $(this).attr('data-modal');
  // На случай, если предыдущая карточка осталась открытой — закрываем её
  // перед показом новой, иначе общий диммер Semantic UI путается.
  $('.ui.modal.active').not(modal_id).modal('hide');
  $(modal_id).modal('show');
});

// QR — отдельное общее модальное окно (одно на всех, не по одному на
// каждого сотрудника), картинка каждый раз подставляется заново.
$(document).on('click', '.show-qr', function (e) {
  e.preventDefault();
  $('.ui.modal.active').not('#qr-modal').modal('hide');
  $('#qr-modal-name').text($(this).attr('data-name'));
  $('#qr-modal-img').attr('src', $(this).attr('data-qr-src'));
  $('#qr-modal').modal('show');
});

// Клик по должности/отделу/компании — фильтр по точному совпадению
// в своей колонке (номер колонки — в data-col). Повторный клик снимает.
$(document).on('click', 'td.filter-col', function () {
  var table = $('.example_phone').DataTable();
  var col = parseInt($(this).attr('data-col'), 10);
  var value = $(this).text().trim();
  var escaped = value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  var pattern = '^' + escaped + '$';

  if (table.column(col).search() === pattern) {
    table.column(col).search('').draw();
  } else {
    table.column(col).search(pattern, true, false).draw();
  }
});
} );
</script>	
</head>
<body>

<div class="ui segment" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;">
<div style="display:flex; align-items:center; gap:16px;">
    <img src="images/logo.png" alt="AlmaWine" style="height:40px; width:auto;">
    <h1 class="ui header" style="margin:0;">Телефонный справочник</h1>
</div>
<div style="display:flex; align-items:center; gap:12px;">
    <span>Здравствуйте, <b><?php echo htmlentities($_SESSION['fullname'] ?? $_SESSION['username'] ?? ''); ?></b></span>
    <a href="logout.php" class="ui button" style="background-color:#4d604d; color:white;">Выйти</a>
</div>
</div>

<div class="ui small modal" id="qr-modal">
    <i class="close icon"></i>
    <div class="header">QR-визитка</div>
    <div class="content" style="text-align:center;">
        <div id="qr-modal-name" style="font-weight:bold; font-size:1.1em; margin-bottom:14px;"></div>
        <img id="qr-modal-img" src="" alt="QR-визитка" style="width:320px; height:320px; max-width:100%;">
        <div style="font-size:12px; color:rgba(0,0,0,.6); margin-top:12px;">Отсканируйте, чтобы сохранить контакт</div>
    </div>
</div>


<?php
    
	

$config = require __DIR__ . '/config.php';
$srv = $config['dir_ldap_server'];
$srv_login = $config['dir_login'];
$srv_password = $config['dir_password'];
$dn = $config['dir_base_dn'];

// Фильтр для выгрузки из AD, здесь указываем группу, в которую входят нужные нам пользователи, например, memberof=CN=Spravochnik,OU=Company,DC=example,DC=com
$filter = "(&(objectCategory=user)(!(userAccountControl:1.2.840.113556.1.4.803:=2))(memberof=CN=TEL,OU=GROUPS,DC=almatytrade,DC=kz))";
$attr = array("cn","mail","title","department","company","telephonenumber","thumbnailphoto","jpegphoto","samaccountname","info","manager","pager","mobile");

require_once __DIR__ . '/includes/vcard.php';

// Кэш выгрузки в Redis — LDAP не дёргаем на каждый заход, если недоступен,
// просто работаем как раньше, напрямую через LDAP.
$redisCacheKey = 'phonebook:directory';
$redis = null;
try {
    $r = new Redis();
    if ($r->connect($config['redis_host'], $config['redis_port'], 1.0)) {
        $redis = $r;
    }
} catch (\Throwable $e) {
    $redis = null;
}

$result_entries = null;
if ($redis) {
    $cached = $redis->get($redisCacheKey);
    if ($cached !== false) {
        $result_entries = unserialize($cached, ['allow_classes' => false]);
    }
}

if ($result_entries === null) {
    $dc = ldap_connect($srv);
    ldap_set_option($dc, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($dc, LDAP_OPT_REFERRALS, 0);

    if ($dc) {
        ldap_bind($dc,$srv_login,$srv_password);
        $result = ldap_search($dc,$dn,$filter,$attr);
        $result_entries = ldap_get_entries($dc,$result);
        ldap_unbind($dc);
    }

    if ($redis && $result_entries) {
        $redis->setex($redisCacheKey, $config['redis_ttl'], serialize($result_entries));
    }
}

function isLeadership($title) {
    return (strpos($title, 'уководитель') !== false)
        || (strpos($title, 'иректор') !== false)
        || (strpos($title, 'ачальник') !== false);
}

// Кайрат Молдабаев и Абзал Молдабаев всегда идут первыми, в этом порядке.
function pinnedPriority($cn) {
    $pinned = ['Кайрат Молдабаев' => 0, 'Абзал Молдабаев' => 1];
    return $pinned[$cn] ?? PHP_INT_MAX;
}

// Сортировка по умолчанию: сначала руководство, затем остальные;
// у кого нет мобильного — в самый конец, независимо от должности.
$order = range(0, $result_entries['count'] - 1);
usort($order, function($aIdx, $bIdx) use ($result_entries) {
    $aPinned = pinnedPriority($result_entries[$aIdx]['cn'][0] ?? '');
    $bPinned = pinnedPriority($result_entries[$bIdx]['cn'][0] ?? '');
    if ($aPinned !== $bPinned) {
        return $aPinned <=> $bPinned;
    }

    $aHasPhone = !empty($result_entries[$aIdx]['mobile'][0] ?? '');
    $bHasPhone = !empty($result_entries[$bIdx]['mobile'][0] ?? '');
    if ($aHasPhone !== $bHasPhone) {
        return $aHasPhone ? -1 : 1;
    }

    $aLead = isLeadership($result_entries[$aIdx]['title'][0] ?? '');
    $bLead = isLeadership($result_entries[$bIdx]['title'][0] ?? '');
    if ($aLead !== $bLead) {
        return $aLead ? -1 : 1;
    }

    return strcmp($result_entries[$aIdx]['cn'][0] ?? '', $result_entries[$bIdx]['cn'][0] ?? '');
});

echo ("<table cellpadding='0' cellspacing='0' border='0' class='ui celled striped table example_phone' >
            <thead>
                <tr>
                    <th>ФИО</th>
                    <th>Email</th>
					<th>Мобильный</th>
                    <th>Тел</th>
                    <th>Должность</th>
                    <th>Отдел</th>
                    <th>Компания</th>
                    <th>QR</th>

                </tr>
            </thead>
            <tbody>");


//Фильтруем данные из AD и выгружаем только тех, у кого есть телефон
 foreach ($order as $i) {
    
        
		$cn = $result_entries[$i]['cn'][0] ?? '';
        $mail = htmlentities($result_entries[$i]['mail'][0] ?? '');
        $title = htmlentities($result_entries[$i]['title'][0] ?? '');
        $department = $result_entries[$i]['department'][0] ?? '';
        $company = $result_entries[$i]['company'][0] ?? '';
		$pager = $result_entries[$i]['pager'][0] ?? '';
        $telephonenumber = formatMobilePhone($result_entries[$i]['mobile'][0] ?? '');
        $telephonenumberDigits = preg_replace('/\D/', '', $telephonenumber);
        $thumbnailphoto = $result_entries[$i]['thumbnailphoto'][0] ?? '';
        $jpegphoto = $result_entries[$i]['jpegphoto'][0] ?? '';
        $samaccountname = $result_entries[$i]['samaccountname'][0] ?? '';
        $info = $result_entries[$i]['info'][0] ?? '';
        $manager = $result_entries[$i]['manager'][0] ?? '';

    // Выделяем жирным текстом руководителей, директоров, начальников
    echo ("<tr style='"); if (isLeadership($title)) {echo ("font-weight: bold;'>");} else{ echo ("font-weight: normal;'>");}

    // В таблице фото всего 28px — берём маленькую миниатюру, чтобы не тянуть
    // в HTML полноразмерное фото (jpegphoto) там, где оно всё равно сожмётся.
    // В карточке, наоборот, приоритет полноразмерному.
    if (!empty($thumbnailphoto)) {
        $photo_thumb_src = "data:image/jpeg;base64,".base64_encode($thumbnailphoto);
    } elseif (!empty($jpegphoto)) {
        $photo_thumb_src = "data:image/jpeg;base64,".base64_encode($jpegphoto);
    } else {
        $photo_thumb_src = "images/no_photo.png";
    }

    if (!empty($jpegphoto)) {
        $photo_full_src = "data:image/jpeg;base64,".base64_encode($jpegphoto);
    } elseif (!empty($thumbnailphoto)) {
        $photo_full_src = "data:image/jpeg;base64,".base64_encode($thumbnailphoto);
    } else {
        $photo_full_src = "images/no_photo.png";
    }

    echo ("
            <td>
            <div class='ui modal' id='item-modal-".$i."'>
            <i class='close icon'></i>
            <div class='header' style='margin-bottom: 20px;'>Информация о сотруднике</div>
            <div class='image content '>
            <img class='ui medium rounded image fluid' src='".$photo_full_src."'/>
            </div>
            <div class='description fluid'>
            <div class='ui message fluid'>
            <div class='header'>
                <h4>".$cn."</h4>
            </div>
            <div class='ui segments fluid'>
                <div class='ui segment'><i class='map marker alternate icon'></i>".$department."</div>
                <div class='ui segment'><i class='address card outline icon'></i>".$title."</div>
                <div class='ui segment'><a href='mailto:".$mail."'><i class='mail icon'></i>".$mail."</a></div>
                <div class='ui segment'><i class='mobile alternate icon'></i>".$telephonenumber."</div>
                <div class='ui segment'><i class='street view icon'></i>Непосредственный руководитель:<br><span style='margin-left: 20px; font-weight: bold;'>");
        $oldmanager = $manager; // получаем  манагера
        preg_match_all('#CN=(.+?),OU#is', $oldmanager, $arr); // обрезаем лишнее
        $newmanager = implode('', $arr[1]); // преобразуем в строку
        $newmanager1 = mb_substr("$newmanager", 0, 1); //разбиваем фамилию, чтобы не искало по руководителю
        $newmanager2 = mb_substr("$newmanager", 1);
        
        echo ("
            ".$newmanager1." ".$newmanager2."</span></div>
            </div>
            </div>
            
            </div>
            </div>
            
            </div>
            
            <a class='ui show-modal' data-modal='#item-modal-".$i."' style='cursor:pointer;'>
                <img src='".$photo_thumb_src."' style='width:28px;height:28px;object-fit:cover;border-radius:50%;vertical-align:middle;margin-right:8px;'/>".$cn."
            </a></td>
            <td><i class='envelope outline icon' style='color: green;'></i> <a href='mailto:".$mail."'>".$mail."</a></td>
			<td><i class='mobile alternate icon' style='color: green;'></i> ".$telephonenumber."<span style='display:none;'> ".$telephonenumberDigits."</span></td>
            <td><i class='phone icon' style='color: green;'></i> ".$pager."</td>
            <td class='filter-col' data-col='4' style='cursor:pointer;' title='Показать только эту должность'>".$title."</td>
            <td class='filter-col' data-col='5' style='cursor:pointer;' title='Показать только этот отдел'>".$department."</td>
            <td class='filter-col' data-col='6' style='cursor:pointer;' title='Показать только эту компанию'>".$company."</td>
            <td style='text-align:center;'><a class='show-qr' data-qr-src='qr.php?i=".$i."' data-name='".htmlspecialchars($cn, ENT_QUOTES)."' style='cursor:pointer;' title='Показать QR-визитку'><i class='qrcode icon' style='color: green; font-size:1.3em;'></i></a></td>
            </tr>
            
    "); 
 
        
    }  
	

echo ("</tbody>
        </table>");
?>
</body>
</html>