<?php
include 'db_helper.php';
include 'ChromePhp.php';
include 'error_report.php';
include 'resources.php';
$rs = new Resources();
$db = new DB();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	if ($_GET['action'] == 'order_task_job') {
		include 'order_task.php';
		$rep['result'] = 'unknown';
		exit(json_encode($rep));
	}
	if (isset($_GET['param'])) {
		exit(json_encode($db->selectFromWhere($_GET['action'], $_GET['param_name'], $_GET['value'])));
	} else {
		exit(json_encode($db->selectFrom($_GET['action'])));
	}
} else {
	switch ($_GET['action']) {
		case 'register':
			include 'register.php';
			break;
		case 'login':
			include 'login.php';
			break;
		case 'access':
			include 'access.php';
			break;
		case 'set_status':
			$post = file_get_contents('php://input');
			$data = json_decode($post);
			exit(json_encode($db->setStatus($data)));
			break;
		case 'create_order':
			$post = file_get_contents('php://input');
			$data = json_decode($post);
			exit(json_encode($db->createOrder($data)));
			break;
		case 'add_address':
			$post = file_get_contents('php://input');
			$data = json_decode($post);
			exit(json_encode($db->addCustomerAddress($data)));
			break;
		default:
			echo "<h1>ERROR 404: Not Found</h1>";
			break;
	}
}
?>