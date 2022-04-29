<?php
    require_once 'sync.php';

    $sync = new Sync();

    $sync->setDB1([
        'host' => 'localhost',
        'dbname' => 'sync_1',
        'username' => 'root',
        'password' => 'xampp#local'
    ]);
    $sync->setDB2([
        'host' => 'localhost',
        'dbname' => 'sync_2',
        'username' => 'root',
        'password' => 'xampp#local'
    ]);


    //$sync->sync("db1", "db2");
?>