<?php
$post = file_get_contents('php://input');
if ($post) {
	$data = json_decode($post);
	$db = new DB();
	ChromePhp::log($data);
	switch (intval($data->userRoleId)) {
		case 1:
			exit(json_encode($db->registerAdmin($data)));
			break;
		case 2:
			exit(json_encode($db->registerCustomer($data)));
			break;
		case 3:
			exit(json_encode($db->registerDeliveryman($data)));
			break;
		case 4:
			exit(json_encode($db->registerChief($data)));
			break;
		default:
			ChromePhp::log("No role id recieved");
			break;
	}
} else {
	$response['result'] = false;
	$response['error'] = "nodata";
	exit(json_encode($response));
}
?>