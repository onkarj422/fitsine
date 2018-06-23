<?php
$status = $db->mysqli->query("SELECT `status` FROM `cron_jobs` WHERE `job`='order_tasks.php'");
if ($status == FALSE) {
	ChromePhp::log("Error getting status");
	exit();
}
$statusRow = $status->fetch_assoc();
if ($statusRow['status'] == 1) {
	ChromePhp::log("Running cron job..");
	$tasks = getTasks($db, $rs);
	ChromePhp::log($tasks);
	$flag = true;
	$db->mysqli->autocommit(false);
	$db->mysqli->begin_transaction();
	$flag = insertTasks($tasks, $db);
	$flag = updateTaskCount($tasks, $db);
	$flag = updateSaladPicks($tasks, $db);
	if ($flag) {
		$db->mysqli->commit();
		ChromePhp::log("Commited!");
	} else {
		$db->mysqli->rollback();
		ChromePhp::log("Rolled Back");
	}
} else {
	exit();
}

//---------------------------------------------------------

function getTasks($db, $rs) {
	$sql_m = "SELECT `orders`.`id` AS `orderId`, `od`.`taskCount` AS `taskCount`, `od`.`timeSlotId` AS `timeSlotId` FROM `orders` INNER JOIN `o_details` AS `od` ON `od`.`id`=`orders`.`id` WHERE (`orders`.`statusId`=2) AND (`od`.`taskCount`>0) AND (`od`.`timeSlotId`=1 OR `od`.`timeSlotId`=3)";//only morning tasks
	$sql_e = "SELECT `orders`.`id` AS `orderId`, `od`.`taskCount` AS `taskCount`, `od`.`timeSlotId` AS `timeSlotId` FROM `orders` INNER JOIN `o_details` AS `od` ON `od`.`id`=`orders`.`id` WHERE (`orders`.`statusId`=2) AND (`od`.`taskCount`>0) AND (`od`.`timeSlotId`=2 OR `od`.`timeSlotId`=3)";//only evening tasks

	$currTime = intval(date('H'));
	if ($currTime < 13) {
		$taskq = $db->mysqli->query($sql_m);	
		ChromePhp::log("Getting morning tasks..");
	} else if ($currTime >= 13) {
		ChromePhp::log("Getting evening tasks..");
		$taskq = $db->mysqli->query($sql_e);
	}

	if ($taskq->num_rows == 0) {
		ChromePhp::log("No tasks to perform");
		exit();
	}

	$tasks = [];
	while ($taskRow = $taskq->fetch_assoc()) {
		$tasks[] = $taskRow;
	}
	foreach ($tasks as &$task) {
		$sq = $db->mysqli->query("SELECT `id` FROM `o_selected_salads` WHERE (`orderId`=".intval($task['orderId'])." AND `isTaken`=0) ORDER BY `id` LIMIT 1");
		$selRow = $sq->fetch_assoc();
		$task['selectedSaladId'] = intval($selRow['id']);
		$task['deliveryManId'] = $rs->getAvailableDeliveryManId();
		$task['chiefId'] = $rs->getAvailableChiefId();
		$task['orderId'] = intval($task['orderId']);
		$task['taskCount'] = intval($task['taskCount']);
		$task['taskStatusId'] = 1;
	}
	unset($task);
	return json_decode(json_encode($tasks), True);
}

function insertTasks($tasks, $db) {
	foreach ($tasks as $task) {
		$sql[] = '('.$task['orderId'].', '.$task['deliveryManId'].', '.$task['chiefId'].', '.$task['taskStatusId'].', '.$task['selectedSaladId'].')';
	}
	
	if ($db->mysqli->query("INSERT INTO `o_tasks` (`orderId`, `deliveryManId`, `chiefId`, `taskStatusId`, `selectedSaladId`) VALUES ".implode(',', $sql)."") == FALSE) {
		ChromePhp::log("Error while inserting tasks..");
		ChromePhp::log($db->mysqli->error);
		return false;
	} else {
		return true;
	}
}

function updateTaskCount($tasks, $db) {
	foreach ($tasks as $task) {
		$cases[] = ''.$task['orderId'].' THEN (`taskCount` - 1)';
		$orderIds[] = $task['orderId'];
	}
	$query = "UPDATE `o_details`\n"
         . "   SET `taskCount` = CASE `id`\n"
         . "                     WHEN "

         . implode("\n                     WHEN ", $cases) 
         . "                   END"
         . " WHERE `id` IN (".implode(', ', $orderIds).")";

	if ($db->mysqli->query($query) == FALSE) {
		return false;
	} else {
		return true;
	}
}

function updateSaladPicks($tasks, $db) {
	foreach ($tasks as $task) {
		$cases[] = ''.$task['selectedSaladId'].' THEN 1';
		$pickedSaladsId[] = $task['selectedSaladId'];
	}
	$query = "UPDATE `o_selected_salads`\n"
         . "   SET `isTaken` = CASE `id`\n"
         . "                     WHEN "

         . implode("\n                     WHEN ", $cases) 
         . "                   END"
         . " WHERE `id` IN (".implode(', ', $pickedSaladsId).")";
	if ($db->mysqli->query($query) == FALSE) {
		return false;
	} else {
		return true;
	}
}

?>