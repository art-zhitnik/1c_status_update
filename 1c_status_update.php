<?

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"]."/log/log_1c_status_update.log");


/*
** OPTIONS
*/

$ORDER_ID_PREFIX = 'UGG-';


/*
** CHECK LOGIN & PASSWORD
*/

if (empty($_POST['login']) || empty($_POST['password'])){
    AddMessage2Log('ERROR: Authorization data error', "1c_status_update");
	die('Authorization data error');
}
else {
	$login = trim($_POST['login']);
	$password = trim($_POST['password']);
}


/*
** AUTH USER
*/

global $USER;
if (!is_object($USER)) {
	$USER = new CUser;
}

$arAuthResult = $USER->Login($login, $password, "N");

$APPLICATION->arAuthResult = $arAuthResult;

if (!$USER->IsAuthorized()) {
    AddMessage2Log('ERROR: Authorization failed', "1c_status_update");
    die('Authorization failed');
}


/*
** GET ORDER ID
*/

$orderId = $_POST['id'];
preg_match_all('/' . $ORDER_ID_PREFIX . '([0-9]{1,6})/i', $orderId, $matches);

if (count($matches[1]) != 0){
    $orderId = $matches[1][0];
}
else {
    $USER->Logout();
    AddMessage2Log('ERROR: Order ID mismutch', "1c_status_update");
    die('Order ID mismutch');
}


/*
** GET STATUS CODE
*/

$orderStatus = $_POST['status'];

if (!ereg('^[A-Z]{1,2}$', $orderStatus)){
    $USER->Logout();
    AddMessage2Log('ERROR: Wrong order status', "1c_status_update");
    die('Wrong order status');
}


/*
** INCLUDE MODULE SALE
*/

CModule::IncludeModule('Sale');


/*
** CHECK STATUS CODE
*/

$db_statuses = array();

$arOrder = array('ID','NAME');
$db_sales = CSaleStatus::GetList($arOrder. array(),false, false , array());
while ($existStatus = $db_sales->Fetch())
{
    $db_statuses[] = $existStatus['ID'];
}

$staticStatuses = array('N', 'F', 'DN', 'DF');

$db_statuses = array_merge($db_statuses, $staticStatuses);

if (array_search($orderStatus, $db_statuses) === FALSE){
    $USER->Logout();
    AddMessage2Log('ERROR: Status mismutch', "1c_status_update");
    die('Status mismutch');
}


/*
** UPDATE ORDER STATUS
*/

$totalRes = CSaleOrder::Update( 
  $orderId,
  array(
    'STATUS_ID'=>$orderStatus,
    'DATE_STATUS'=>date("d.m.Y H:i:s"),
    'EMP_STATUS_ID'=>$USER->getID()
    ),
  true
);
if ($totalRes){
    AddMessage2Log('Change success. Order id: ' . $orderId . '. New status code: ' . $orderStatus, "1c_status_update");
    echo "OK";
}
else {
    $USER->Logout();
    AddMessage2Log('ERROR: Order update error ', "1c_status_update");
    die('Order update error ');
}


/*
** LOGOUT USER
*/
$USER->Logout();
