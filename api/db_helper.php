<?php
	class DB {

		private $host = "mysql.hostinger.in";
		private $user = "u900755815_onkar";
		private $password = "fitsine@mysql";
		private $database = "u900755815_ftsn";
		private $connection = false;
		public $mysqli;
		private $salt = "$6056#";

	  public function __construct(){
	  	$this->connect();
	  }

	  public function connect() {

	  	if (!$this->connection) {
	  		$this->mysqli = new mysqli($this->host, $this->user, $this->password, $this->database);
	  		if ($this->mysqli) {
	  			$this->connection = true;
	  			return true;
	  		} else {
	  			$this->connection = false;
	  			return false;
	  		}
	  	} else {
	  		return true;
	  	}
	  }

	  public function registerCustomer($data) {

	  	$flag = true;
	  	$this->mysqli->autocommit(false);
	  	$this->mysqli->query("SET FOREIGN_KEY_CHECKS=0");
	  	$this->mysqli->begin_transaction();

	  	while ($flag == true) {
	  	  if ($this->mysqli->query("INSERT INTO `customers` ( firstname, lastname, email, password, mobileNumber ) VALUES ('$data->firstname', '$data->lastname', '$data->email', '".crypt($data->password, $this->salt)."', '$data->mobileNumber')") == FALSE) {
	  				$flag = false;
	  				ChromePhp::log($this->mysqli->error);
	  				break;
	  		}
	  		$customer_id = intval($this->mysqli->insert_id);
	  		if ($this->mysqli->query("INSERT INTO `c_address` ( customerId, address, area, mobileNumber) VALUES ( $customer_id , '$data->address', '$data->area', '$data->mobileNumber')") == FALSE) {
	  			$flag = false;
	  			ChromePhp::log($this->mysqli->error);
	  			break;
	  		}
	  		if ($this->mysqli->query("UPDATE `customers` SET `addressId` = LAST_INSERT_ID() WHERE `id` = $customer_id") == FALSE) {
	  			$flag = false;
	  			ChromePhp::log($this->mysqli->error);
	  			break;
	  		}
	  		break;
	  	}

	  	if ($flag) {
	  		$this->mysqli->commit();
	  		ChromePhp::log("Commited!");
	  		$accessData['email'] = $data->email;
	  		$accessData['userRoleId'] = $data->userRoleId;
	  		$accessData['userId'] = $customer_id;
	  		$this->setAccess($accessData);
	  		$response = $accessData;
	  		$accessData['result'] = true;
	  		return $accessData;
	  	} else {
	  		$this->mysqli->rollback();
	  		ChromePhp::log("Rolled back!");
		  	if ($this->mysqli->errno == 1062) {
		  		$response['result'] = false;
					$response['error'] = "exists";
					return $response;
		  	} else {
		  		$re = new ReportError();
		  		$re->sendMail( $this->mysqli->error, $this->mysqli->errno, "register" );
		  		$response['result'] = false;
					$response['error'] = "unknown";
					return $response;
		  	}
	  	}
	  }

	  public function createOrder($data) {
			$flag = true;
	  	$this->mysqli->autocommit(false);
	  	$this->mysqli->query("SET FOREIGN_KEY_CHECKS=0");
	  	$this->mysqli->begin_transaction();

	  	while ($flag) {
	  		if ($this->mysqli->query("INSERT INTO `orders` (`date`, customerId, statusId, paymentId) 
	  			VALUES ( CURRENT_TIMESTAMP, $data->customerId, $data->statusId, $data->paymentId)") == false) {
	  			$flag = false;
	  			ChromePhp::log($this->mysqli->error);
	  			ChromePhp::log("100");
	  			break;
	  		}
	  		$orderId = intval($this->mysqli->insert_id);
	  		if ($this->mysqli->query("INSERT INTO `o_details` (taskCount, deliveryAddressId, mealTypeId, mealId, timeSlotId, morningTimeSlot, eveningTimeSlot, foodTypeId) 
	  			VALUES ($data->taskCount, $data->deliveryAddressId, $data->mealTypeId, $data->mealId, $data->timeSlotId, '$data->morningTimeSlot', '$data->eveningTimeSlot', $data->foodTypeId)") == false) {
	  			$flag = false;
	  			ChromePhp::log($this->mysqli->error);
	  			ChromePhp::log("108");
	  			break;
	  		}
	  		$orderDetailsId = intval($this->mysqli->insert_id);
	  		if ($this->mysqli->query("UPDATE `orders` SET `orderDetailsId` = $orderDetailsId WHERE `id` = $orderId") == false) {
	  			$flag = false;
	  			ChromePhp::log($this->mysqli->error);
	  			ChromePhp::log("129");
	  			break;	
	  		}
	  		$selectedSalads = $data->selectedSalads;
	  		$array = json_decode(json_encode($selectedSalads), True);
	  		$sql = array();
	  		foreach ($array as $row) {
	  			$sql[] = '('.$orderId.', '.$row['saladId'].', '.$row['foodTypeId'].', '.$row['isTaken'].')';
	  		}
				if ($this->mysqli->query("INSERT INTO `o_selected_salads` (`orderId`, `saladId`, `foodTypeId`, `isTaken`) VALUES ".implode(',', $sql)."") == FALSE) {
					$flag = false;
					ChromePhp::log($this->mysqli->error);
					ChromePhp::log("selectedSaladsError");
					break;
				}
	  		break;
	  	}

	  	if ($flag) {
	  		$this->mysqli->commit();
	  		ChromePhp::log("Commited!");
	  		return $orderId;
	  	} else {
	  		$this->mysqli->rollback();
	  		ChromePhp::log("Rolled Back!");
	  		return null;
	  	}
		}

	  public function setAccess($data) {
	  	$roleId = intval($data['userRoleId']);
			$insert = "INSERT INTO `access` ( `email`, `lastAccess`, `roleId` ) VALUES ('".$data['email']."', CURRENT_TIMESTAMP, $roleId)";
	  	$result = $this->mysqli->query($insert);
	  	$this->mysqli->commit();
		}

	  public function login($data) {
	  	$role = $this->mysqli->query("SELECT `access_roles`.`id` FROM `access` INNER JOIN `access_roles` ON `access`.`roleId` = `access_roles`.`id` WHERE `email` = '$data->email'");
	  	if (!$role) {
	  		ChromePhp::log($this->mysqli->error);
	  		ChromePhp::log($this->mysqli->errno);
	  	} else {
	  		if ($row = $role->fetch_assoc()) {
	  			$roleId = intval($row['id']);
	  			$response['userRoleId'] = $roleId;
	  			$response['result'] = $this->matchPassword($data, $roleId);
	  			$response['userId'] = $this->getId($roleId, $data->email);
	  			$response['email'] = $data->email;
	  			return $response;
	  		} else {
	  			ChromePhp::log("User does not exists!");
	  			$response['error'] = "noexists";
	  			$response['result'] = false;
	  			return $response;
	  		}
	  	}
	  }

	  public function getAccess($data) {
	  	$access = "UPDATE `access` SET `lastAccess` = CURRENT_TIMESTAMP WHERE `email` = '".$data->email."'";
			if (!$this->mysqli->query($access)) {
				ChromePhp::log("LastAccess update failed!");
			} else {
				return $this->getAllData(intval($data->userRoleId), intval($data->userId));
			}
		}

		public function getAllData($roleId, $id) {
			$table = $this->getRoleTable($roleId);
			$sql = "SELECT * FROM $table WHERE `id` = '$id'";
			$result = $this->mysqli->query($sql);
			$row = $result->fetch_assoc();
			$row['userRoleId'] = $roleId;
			unset($row['password']);
			return $row;
		}

		public function getId($roleId, $email) {
			$table = $this->getRoleTable($roleId);
			$sql = "SELECT `id` FROM $table WHERE `email` = '$email'";
			$result = $this->mysqli->query($sql);
			$row = $result->fetch_assoc();
			return intval($row['id']);
		}

		public function getRoleDescription($id) {
			$roleId = intval($id);
			$sql = "SELECT `description` FROM `access_roles` WHERE `id` = $roleId";
			$result = $this->mysqli->query($sql);
			$row = $result->fetch_assoc();
			return $row['description'];
		}

		public function matchPassword($data, $roleId) {
			$table = $this->getRoleTable($roleId);
			$sql = "SELECT `password` FROM $table WHERE `email` = '$data->email'";
			$result = $this->mysqli->query($sql);
			$row = $result->fetch_assoc();
			return (crypt($data->password, $this->salt) == $row['password']) ? true : false;
		}

		public function changePassword($data, $role) {
			$crypt = crypt($data->password, $this->salt);
			$this->mysqli->query("UPDATE $table SET `password` = '$crypt' WHERE `email` = '$data->email'");
		}

		public function getRoleTable($roleId) {
			switch (intval($roleId)) {
				case 1:
					$table = "admin";
					break;
				case 2:
					$table = "customers";
					break;
				case 3:
					$table = "deliverymen";
					break;
				case 4:
					$table = "chiefs";
					break;
			}
			return $table;
		}

		public function getCustomers() {
			$sql = "SELECT * FROM `customers`";
			$result = $this->mysqli->query($sql);
			while ($row = $result->fetch_assoc()) {
				unset($row['password']);
				$customerId = $row['id'];
				$res = $this->mysqli->query("SELECT `id`, `address`, `area`, `mobileNumber` FROM `c_address` WHERE `customerId` = $customerId");
				$addresses = [];
				while($addressRow = $res->fetch_assoc()) {
					$addresses[] = $addressRow;
				}
				$row['addresses'] = $addresses;
			 	$rows[] = $row;
			}
			return $rows;
		}

		public function getOrders() {
			$result = $this->mysqli->query("SELECT `orders`.`id`, `orders`.`date`, `orders`.`customerId`, `od`.`taskCount` AS `remainingDel`, `mt`.`description` AS `mealSize`, `m`.`name` AS `mealName`, `ft`.`value` AS `foodType`, `st`.`description` AS `orderStatus`, `ost`.`description` AS `paymentStatus` FROM `orders` INNER JOIN `o_details` AS `od` ON `orders`.`id`=`od`.`id` INNER JOIN `m_types` AS `mt` ON `od`.`mealTypeId`=`mt`.`id` INNER JOIN `meals` AS `m` ON `od`.`mealId`=`m`.`id` INNER JOIN `veg_nonveg` AS `ft` ON `od`.`foodTypeId`=`ft`.`id` INNER JOIN `o_statuses` AS `st` ON `orders`.`statusId`=`st`.`id` INNER JOIN `o_statuses` AS `ost` ON `orders`.`paymentId`=`ost`.`id`");
			ChromePhp::log($this->mysqli->error);
			while ($row = $result->fetch_assoc()) {
			  $rows[] = $row;
			}
			if (isset($rows)) {
				return $rows;
			} else {
				$rows['result'] = null;
				return $rows;
			}
		}

		public function getCurrentFoodTasks() {
			$result = $this->mysqli->query("SELECT `o_tasks`.`id`, `o_tasks`.`orderId`, `o_tasks`.`dateAdded`, `ts`.`description` AS `taskStatus`, `d`.`id` AS `dId`, `d`.`name` AS `dName`, `d`.`mobileNumber` AS `dMobNo`, `c`.`id` AS `chiefId`, `c`.`name` AS `chiefName`, `c`.`mobileNumber` AS `chiefMobNo`, `sal`.`name` AS `saladName`, `ft`.`value` AS `foodType`, `mt`.`description` AS `mealSize` FROM `o_tasks` INNER JOIN `o_tasks_statuses` AS `ts` ON `o_tasks`.`taskStatusId`=`ts`.`id` INNER JOIN `deliverymen` AS `d` ON `o_tasks`.`deliveryManId`=`d`.`id` INNER JOIN `chiefs` AS `c` ON `o_tasks`.`chiefId`=`c`.`id` INNER JOIN `o_selected_salads` AS `ss` ON `o_tasks`.`selectedSaladId`=`ss`.`id` INNER JOIN `salads` AS `sal` ON `ss`.`saladId`=`sal`.`id` INNER JOIN `veg_nonveg` AS `ft` ON `ss`.`foodTypeId`= `ft`.`id` INNER JOIN `o_details` AS `od` ON `o_tasks`.`orderId`=`od`.`id` INNER JOIN `m_types` AS `mt` ON `od`.`mealTypeId`=`mt`.`id` WHERE (`o_tasks`.`taskStatusId`=1 )");
			ChromePhp::log($this->mysqli->error);
			while ($row = $result->fetch_assoc()) {
			  $rows[] = $row;
			}
			if (isset($rows)) {
				return $rows;
			} else {
				$rows['result'] = null;
				return $rows;
			}
		}

		public function getCurrentFoodTasksByCId($id, $value) {
			$result = $this->mysqli->query("SELECT `o_tasks`.`id`, `o_tasks`.`orderId`, `ts`.`description` AS `taskStatus`, `sal`.`name` AS `saladName`, `ft`.`value` AS `foodType`, `mt`.`description` AS `mealSize` FROM `o_tasks` INNER JOIN `o_tasks_statuses` AS `ts` ON `o_tasks`.`taskStatusId`=`ts`.`id` INNER JOIN `o_selected_salads` AS `ss` ON `o_tasks`.`selectedSaladId`=`ss`.`id` INNER JOIN `salads` AS `sal` ON `ss`.`saladId`=`sal`.`id` INNER JOIN `veg_nonveg` AS `ft` ON `ss`.`foodTypeId`= `ft`.`id` INNER JOIN `o_details` AS `od` ON `o_tasks`.`orderId`=`od`.`id` INNER JOIN `m_types` AS `mt` ON `od`.`mealTypeId`=`mt`.`id` WHERE (`o_tasks`.`taskStatusId`=1 AND `o_tasks`.$id=$value )");
			ChromePhp::log($this->mysqli->error);
			while ($row = $result->fetch_assoc()) {
			  $rows[] = $row;
			}
			if (isset($rows)) {
				return $rows;
			} else {
				$rows['result'] = null;
				return $rows;
			}
		}

		public function getCurrentDeliveryTasks() {
			$result = $this->mysqli->query("SELECT `o_tasks`.`id`, `o_tasks`.`orderId`, `o_tasks`.`dateAdded`, `ts`.`description` AS `taskStatus`, `d`.`id` AS `dId`, `d`.`name` AS `dName`, `d`.`mobileNumber` AS `dMobNo`, `c`.`id` AS `chiefId`, `c`.`name` AS `chiefName`, `c`.`mobileNumber` AS `chiefMobNo`, `sal`.`name` AS `saladName`, `ft`.`value` AS `foodType`, `mt`.`description` AS `mealSize`, `odt`.`time` AS `timeSlot`, `ca`.`address` AS `address`, `ca`.`area` AS `area`, `ca`.`mobileNumber` AS `mobileNumber`, `cus`.`firstname` AS `firstname`, `cus`.`lastname` AS `lastname` FROM `o_tasks` INNER JOIN `o_tasks_statuses` AS `ts` ON `o_tasks`.`taskStatusId`=`ts`.`id` INNER JOIN `deliverymen` AS `d` ON `o_tasks`.`deliveryManId`=`d`.`id` INNER JOIN `chiefs` AS `c` ON `o_tasks`.`chiefId`=`c`.`id` INNER JOIN `o_selected_salads` AS `ss` ON `o_tasks`.`selectedSaladId`=`ss`.`id` INNER JOIN `salads` AS `sal` ON `ss`.`saladId`=`sal`.`id` INNER JOIN `veg_nonveg` AS `ft` ON `ss`.`foodTypeId`= `ft`.`id` INNER JOIN `o_details` AS `od` ON `o_tasks`.`orderId`=`od`.`id` INNER JOIN `m_types` AS `mt` ON `od`.`mealTypeId`=`mt`.`id` INNER JOIN `c_address` AS `ca` ON `od`.`deliveryAddressId`=`ca`.`id` INNER JOIN `o_d_timeslot` AS `odt` ON `od`.`timeSlotId`=`odt`.`id` INNER JOIN `orders` AS `o` ON `o_tasks`.`orderId`=`o`.`id` INNER JOIN `customers` AS `cus` ON `o`.`customerId`=`cus`.`id` WHERE (`o_tasks`.`taskStatusId`=2)");
			ChromePhp::log($this->mysqli->error);
			while ($row = $result->fetch_assoc()) {
			  $rows[] = $row;
			}
			if (isset($rows)) {
				return $rows;
			} else {
				$rows['result'] = null;
				return $rows;
			}
		}

		public function getCurrentDeliveryTasksByDId($id, $value) {
			$result = $this->mysqli->query("SELECT `o_tasks`.`id`, `o_tasks`.`orderId`, `o_tasks`.`dateAdded`, `ts`.`description` AS `taskStatus`, `d`.`id` AS `dId`, `d`.`name` AS `dName`, `d`.`mobileNumber` AS `dMobNo`, `c`.`id` AS `chiefId`, `c`.`name` AS `chiefName`, `c`.`mobileNumber` AS `chiefMobNo`, `sal`.`name` AS `saladName`, `ft`.`value` AS `foodType`, `mt`.`description` AS `mealSize`, `odt`.`time` AS `timeSlot`, `ca`.`address` AS `address`, `ca`.`area` AS `area`, `ca`.`mobileNumber` AS `mobileNumber`, `cus`.`firstname` AS `firstname`, `cus`.`lastname` AS `lastname`, `od`.`morningTimeSlot` AS `morningTimeSlot`, `od`.`eveningTimeSlot` AS `eveningTimeSlot` FROM `o_tasks` INNER JOIN `o_tasks_statuses` AS `ts` ON `o_tasks`.`taskStatusId`=`ts`.`id` INNER JOIN `deliverymen` AS `d` ON `o_tasks`.`deliveryManId`=`d`.`id` INNER JOIN `chiefs` AS `c` ON `o_tasks`.`chiefId`=`c`.`id` INNER JOIN `o_selected_salads` AS `ss` ON `o_tasks`.`selectedSaladId`=`ss`.`id` INNER JOIN `salads` AS `sal` ON `ss`.`saladId`=`sal`.`id` INNER JOIN `veg_nonveg` AS `ft` ON `ss`.`foodTypeId`= `ft`.`id` INNER JOIN `o_details` AS `od` ON `o_tasks`.`orderId`=`od`.`id` INNER JOIN `m_types` AS `mt` ON `od`.`mealTypeId`=`mt`.`id` INNER JOIN `c_address` AS `ca` ON `od`.`deliveryAddressId`=`ca`.`id` INNER JOIN `o_d_timeslot` AS `odt` ON `od`.`timeSlotId`=`odt`.`id` INNER JOIN `orders` AS `o` ON `o_tasks`.`orderId`=`o`.`id` INNER JOIN `customers` AS `cus` ON `o`.`customerId`=`cus`.`id` WHERE (`o_tasks`.`taskStatusId`=2 AND `o_tasks`.$id=$value)");
			ChromePhp::log($this->mysqli->error);
			while ($row = $result->fetch_assoc()) {
			  $rows[] = $row;
			}
			return $rows;
		}

		public function getAllTasks() {
			$result = $this->mysqli->query("SELECT `o_tasks`.`id`, `o_tasks`.`orderId`, `o_tasks`.`dateAdded`, `ts`.`description` AS `taskStatus`, `d`.`id` AS `dId`, `d`.`name` AS `dName`, `d`.`mobileNumber` AS `dMobNo`, `c`.`id` AS `chiefId`, `c`.`name` AS `chiefName`, `c`.`mobileNumber` AS `chiefMobNo`, `sal`.`name` AS `saladName`, `ft`.`value` AS `foodType`, `mt`.`description` AS `mealSize`, `odt`.`time` AS `timeSlot`, `ca`.`address` AS `address`, `ca`.`area` AS `area`, `ca`.`mobileNumber` AS `mobileNumber`, `cus`.`firstname` AS `firstname`, `cus`.`lastname` AS `lastname` FROM `o_tasks` INNER JOIN `o_tasks_statuses` AS `ts` ON `o_tasks`.`taskStatusId`=`ts`.`id` INNER JOIN `deliverymen` AS `d` ON `o_tasks`.`deliveryManId`=`d`.`id` INNER JOIN `chiefs` AS `c` ON `o_tasks`.`chiefId`=`c`.`id` INNER JOIN `o_selected_salads` AS `ss` ON `o_tasks`.`selectedSaladId`=`ss`.`id` INNER JOIN `salads` AS `sal` ON `ss`.`saladId`=`sal`.`id` INNER JOIN `veg_nonveg` AS `ft` ON `ss`.`foodTypeId`= `ft`.`id` INNER JOIN `o_details` AS `od` ON `o_tasks`.`orderId`=`od`.`id` INNER JOIN `m_types` AS `mt` ON `od`.`mealTypeId`=`mt`.`id` INNER JOIN `c_address` AS `ca` ON `od`.`deliveryAddressId`=`ca`.`id` INNER JOIN `o_d_timeslot` AS `odt` ON `od`.`timeSlotId`=`odt`.`id` INNER JOIN `orders` AS `o` ON `o_tasks`.`orderId`=`o`.`id` INNER JOIN `customers` AS `cus` ON `o`.`customerId`=`cus`.`id`");
			ChromePhp::log($this->mysqli->error);
			while ($row = $result->fetch_assoc()) {
			  $rows[] = $row;
			}
			if (isset($rows)) {
				return $rows;
			} else {
				$rows['result'] = null;
				return $rows;
			}
		}

		public function getSalads() {
			$result = $this->mysqli->query("SELECT `salads`.`id`, `salads`.`name`, `salads`.`description`, `foodJoin`.`value` AS `type` FROM `salads` INNER JOIN `veg_nonveg` AS `foodJoin` ON `foodJoin`.`id`=`salads`.`typeId`");
			if (!$result) {
				ChromePhp::log($this->mysqli->error);
			} else {
				while ($row = $result->fetch_assoc()) {
			  	$rows[] = $row;
				}
				return $rows;
			}
		}

		public function selectFrom($table) {
			switch ($table) {
				case 'customers':
					return $this->getCustomers();
					break;
				case 'orders':
					return $this->getOrders();
					break;
				case 'salads':
					return $this->getSalads();
					break;
				case 'food_tasks':
					return $this->getCurrentFoodTasks();
					break;
				case 'delivery_tasks':
					return $this->getCurrentDeliveryTasks();
					break;
				case 'all_tasks':
					return $this->getAllTasks();
					break;
				default:
					$result = $this->mysqli->query("SELECT * FROM $table");
					while ($row = $result->fetch_assoc()) {
			    	$rows[] = $row;
					}
					return $rows;
					break;
			}
		}

		public function selectFromWhere($table, $where, $equalTo) {
			switch ($table) {
				case 'customers':
					return $this->getCustomerWhere($where, $equalTo);
					break;
				case 'food_tasks_where':
					return $this->getCurrentFoodTasksByCId($where, $equalTo);
					break;
				case 'delivery_tasks_where':
					return $this->getCurrentDeliveryTasksByDId($where, $equalTo);
					break;
				default:
					$result = $this->mysqli->query("SELECT * FROM $table WHERE $where = $equalTo");
					if (!$result) {
						ChromePhp::log($this->mysqli->error);
					}
					if ($result->num_rows > 1) {
						while ($row = $result->fetch_assoc()) {
			  			$rows[] = $row;
						}
						return $rows;
					} else {
						return $result->fetch_assoc();
					}
					break;
			}
		}

		public function getCustomerWhere($whichIs, $equalTo) {
			$result = $this->mysqli->query("SELECT `customers`.*, `c`.`address` AS `address`, `c`.`area` AS `area`, `c`.`mobileNumber` AS `mobileNumber` FROM `customers` INNER JOIN `c_address` AS `c` ON `c`.`id`=`customers`.`addressId` WHERE `customers`.$whichIs=$equalTo");
			if (!$result) {
				ChromePhp::log($this->mysqli->error);
			} else {
				if ($result->num_rows > 1) {
					while ($row = $result->fetch_assoc()) {
			 			$rows[] = $row;
					}
					return $rows;
				} else {
					return $result->fetch_assoc();
				}
			}
		}

		public function addCustomerAddress($data) {
			$sql = "INSERT INTO `c_address` ( customerId, address, area, mobileNumber ) VALUES ( $data->customerId, $data->address, $data->area, $data->mobileNumber)";
			if ($this->mysqli->query($sql) == false) {
				ChromePhp::log($this->mysqli->error);
				return null;
			} else {
				return intval($this->mysqli->insert_id);
			}
		}

		public function setStatus($data) {
			$sql = "UPDATE `o_tasks` SET `taskStatusId`=$data->statusId WHERE `id`=$data->taskId";
			if ($this->mysqli->query($sql) == false) {
				ChromePhp::log($this->mysqli->error);
				$data->result = false;
				return null;
			} else {
				$data->result = true;
				return $data;
			}
		}
	}
?>
