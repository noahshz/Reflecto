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


    $sync->sync("db1", "db2");


    /*
    $dbuser = 'root';
    $dbpass = 'xampp#local';

    $pdo1 = new PDO('mysql:host=localhost;dbname=test', $dbuser, $dbpass);
    
    $stmt1 = $pdo1->prepare("SELECT * FROM user;");
    $stmt1->execute();
    echo "<hr>";
 

    $pdo2 = new PDO('mysql:host=localhost;dbname=test', $dbuser, $dbpass);
    $stmt2 = "";


    foreach($stmt1->fetchAll() as $item){
        $columns = array();
        $values = array();
        $data = array();

        foreach(array_keys($item) as $index => $value) {
            if($index % 2 == 0) {
                $columns[] = $value;
            }
        }
        for($i = 0; $i < count($item)/2; $i++) {
            $values[] = $item[$i];
        }
        $data = array_combine($columns, $values);

        print_r($data);

        foreach($data as $column => $value) {
            print_r($column);
            $query = "INSERT INTO user2 (`" . $column . "`) VALUES (:insert_value);";
            $stmt2 = $pdo2->prepare($query);
            $stmt2->bindParam(":insert_value", $value);
            $stmt2->execute();
        }

    }
    */

?>