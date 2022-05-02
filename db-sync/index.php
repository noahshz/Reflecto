<?php
    require_once 'sync.php';

    $sync = new Sync();

    $sync->setDB('db1', [
        'host' => 'localhost',
        'dbname' => 'sync_1',
        'username' => 'root',
        'password' => 'xampp#local'
    ]);
    $sync->setDB('db2', [
        'host' => 'localhost',
        'dbname' => 'sync_2',
        'username' => 'root',
        'password' => 'xampp#local'
    ]);


    $sync->syncTo("db2");
?>