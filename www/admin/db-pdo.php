<?php
	# test kết nối đến mysql server bằng PDO (using config/database.php)
	require_once __DIR__ . '/../config/database.php';

	try {
		$conn = getDb();
		echo "Kết nối thành công tới database bằng PDO<br><br>";
	} catch(PDOException $e) {
		echo "Kết nối PDO thất bại: " . $e->getMessage();
		die();
	}
?>
