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
        'dbname' => 'sync_1',
        'username' => 'root',
        'password' => 'xampp#local'
    ]);

    if(!$sync->syncTo("db2")) {
        echo $sync->getErrorMessage();
    } 
?>