<?php

require "../../shard.php";
require "../../security.php";

$sharddrive = new ShardDrive;
$token = $_GET['token'] ?? $_POST['token'];
$url = $_GET['url'] ?? $_POST['url'];
$block = $_GET['custom'] ?? $_POST['custom'];

if ($token && filter_var($url, FILTER_VALIDATE_URL) && !$block || ctype_alnum($block))
{
    $link = Array('url' => $url);
    $result = $sharddrive->store(json_encode($link), $block, 'links', $token);
    echo json_encode($result);
}

?>