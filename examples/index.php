<?php

use SugiPHP\Captcha\Crypt;
use SugiPHP\Captcha\PdoUsedKeys;

include __DIR__."/../vendor/autoload.php";

// Used codes storage. Any PDO connection will do, as well as any PSR-6 cache pool (see CacheUsedKeys)
$db = new PDO("sqlite:" . sys_get_temp_dir() . "/sugiphp-captcha-example.sqlite");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec(file_get_contents(__DIR__."/../schema.sql"));

// Keep the secret out of the code (at least 32 chars long). It must be the same on all servers.
$secret = "change-me-to-a-long-random-string-kept-in-private";

$config = array();
// $config = array("charset" => "абвгдежзийклмнопрстуфхцчшщъьюя1234567890");
$captcha = new Crypt(new PdoUsedKeys($db), $secret, $config);

// No sessions - the form carries the encrypted code (checksum) along with the user input
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
