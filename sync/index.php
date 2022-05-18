<?php
    require_once 'Sync.php';
    require_once 'Backup.php';

/*
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

    $tables = [
        'adv_img',
        'config',
        'label',
        'preisaenderung',                                 
        'sperrliste_gas',                                   
        'sperrliste_strom',                                  
        'table 22',                                        
        'tarif_bak',                                        
        'tarif_beschreibung',                                
        'tarif_beschreibung_detail',                          
        'tarife',                                            
        'tarife_tarif_beschreibung',                         
        'tarife_tarif_beschreibung_detail',                   
        'tarife_tarifoptionen',                              
        'tarifgruppen',                                       
        'tarifgruppen_tarife',                                
        'tarifoption_beschreibungen',                         
        'tarifoption_preisaenderungen',                       
        'tarifoptionen',                                     
        'tarifoptionen_tarifoption_beschreibungen',           
        'tarifoptionen_tarifoption_beschreibungen_detail',
        'tarifwechsel',                                      
        'vertragspartner'
    ];
    
    if(!$sync->syncTo("db2", $tables)) {
        echo $sync->getErrorMessage();
    }
*/
    

    $backup = new Backup();

    $backup->setDB('db1', [
        'host' => 'localhost',
        'dbname' => 'sync_1',
        'username' => 'root',
        'password' => 'xampp#local'
    ]);
    $backup->setDB('db2', [
        'host' => 'localhost',
        'dbname' => 'sync_2',
        'username' => 'root',
        'password' => 'xampp#local'
    ]);

    //$backup->writeTo('db1');

    $backup->restoreFrom('db1', "2022-05-16 08:29:47", ['db1', 'db2']);

    echo $backup->getErrorMessage();

    print_r($backup->getRestoreableTimestamps('db1'));


    
?>