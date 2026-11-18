<?php

use SugiPHP\Captcha\Session;

include __DIR__."/../vendor/autoload.php";

// The code is kept in the session - no secret key and no database are needed
session_start();

$config = array();
// $config = array("charset" => "абвгдежзийклмнопрстуфхцчшщъьюя1234567890");
$captcha = new Session($config);

$msg = "";
if (isset($_POST["captcha"])) {
	$checksum = isset($_POST["checksum"]) ? $_POST["checksum"] : "";
	if ($captcha->verify($_POST["captcha"], $checksum)) {
		die("<h1>Code accepted</h1><a href=''>Try again</a>");
	}
	$msg = $captcha->getLastErrorMessage();
}

$captcha->create();

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" />
</head>
<body>
	<h1><?=htmlspecialchars($msg);?></h1>
	<img src="data:image/jpeg;base64,<?=base64_encode($captcha->getImage());?>" />
	<form method="post" action="">
		<input type="hidden" name="checksum" value="<?=htmlspecialchars($captcha->getChecksum());?>" />
		<input type="text" name="captcha" value="" autocomplete="off" />
		<input type="submit" value="Submit" />
	</form>
</body>
</html>
