<?php

require __DIR__ .'/vendor/autoload.php';

$options = array(
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
);

$pdo = new \Anonymous\MysqliPdoBridge\MysqliPDO('mysql:host=127.0.0.1;port=33068;dbname=letscount', 'letscount', 'letscount', $options);

$stmt = $pdo->prepare('update test set name = :name, name_long = :name_long where id = :id');
$stmt->execute(['id' => 1, 'name' => 'name', 'name_long' => 'name_long']);
$result = $stmt->fetch(\PDO::FETCH_ASSOC);
var_dump($result);
