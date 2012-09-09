<?php
require '../src/Stitt.php';


$stitt = new Stitt();
$stitt->setSwfPath(__DIR__,'gacha.swf');

$imageInfos = $stitt->perseSWFImageInfo();
var_dump($imageInfos);

$stitt->replaceImageId();

$imageInfos = $stitt->perseSWFImageInfo();
var_dump($imageInfos);