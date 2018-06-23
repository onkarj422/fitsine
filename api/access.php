<?php 
	$post = file_get_contents('php://input');
	if ($post) {
		$data = json_decode($post);
		$db = new DB();
		exit(json_encode($db->getAccess($data)));
	} else {
		$response['result'] = false;
		$response['error'] = "nodata";
		exit(json_encode($response));
	}
?>