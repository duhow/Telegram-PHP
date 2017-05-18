<?php

$dir = dirname(__FILE__) .'/';
$files = [
	$dir .'User.php',
	$dir .'Chat.php',
	$dir .'Bot.php',

	$dir .'Keyboards/Keyboard.php',
	$dir .'Keyboards/InlineKeyboard.php',

	$dir .'Payments/Stripe.php',

	$dir .'Elements/Base.php',
	$dir .'Elements/Voice.php', // REQUIRED for priority
];

foreach(scandir($dir .'Elements/') as $file){
	if(substr($file, -4) != ".php"){ continue; }
	$files[] = $dir .'Elements/' .$file;
}

$files[] = $dir .'Receiver.php';
$files[] = $dir .'Sender.php';

foreach($files as $file){
	require_once $file;
}

unset($dir);
unset($files);

?>
