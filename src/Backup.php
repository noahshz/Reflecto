<?php
    class Backup {
        private PDO $db1;
        private PDO $db2;

        private string $errmsg;

        private string $backup_value_tablename = "sn_backup_values";
        private string $backup_structure_tablename = "sn_backup_structure";
        private string $backup_config_tablename = "sn_backup_config";

        private array $typesToQuote = [
            'text',
            'tinytext',
            'mediumtext',
            'longtext',
            'varchar',
            'char',
            'binary',
            'json',
            'datetime',
            'date',
            'timestamp'
        ];

        private array $blacklist = [];

        public function __construct()
        {
            $this->errmsg = "";
        }
        public function setDB(string $db, array $options)
        {
            //combined function of setDB1, setDB2
            if(array_key_exists("host", $options) && array_key_exists("dbname", $options) && array_key_exists("username", $options) && array_key_exists("password", $options)) 
            {
                try 
                {
                    $pdo = new PDO("mysql:host=" . $options['host'] . ";dbname=" . $options['dbname'] . "", $options['username'], $options['password']);
                    /* Variablen DBn werden gesetzt */
                    if($db == "db1" || $db == "db2") {
                        if($db == "db1") {
                            $this->db1 = $pdo;
                        } else {
                            $this->db2 = $pdo;
                        }
                    } else {
                        $this->setError(
                            "Fehlerhafter Parameter für \"db\". Bitte verwenden Sie nur \"db1\" und \"db2\"."
                            . "<br>"
                        );
                        return false;
                    }
                    
                    return true;
                } catch (Exception $e) 
                {
                    $this->setError(
                        "[" . $db . "] : " . $e->getMessage()
                        . "<br>"
                    );
                    return false;
                }
            } else {
                $missing_parameters = "";
                if(!array_key_exists("host", $options)) {
                    $missing_parameters .= "'host', ";
                }
                if(!array_key_exists("dbname", $options)) {
                    $missing_parameters .= "'dbname', ";
                }
                if(!array_key_exists("username", $options)) {
                    $missing_parameters .= "'username', ";
                }
                if(!array_key_exists("password", $options)) {
                    $missing_parameters .= "'password', ";
                }
                $missing_parameters = substr($missing_parameters, 0, strlen($missing_parameters) - 2);
                
                $this->setError(
                    "[" . $db . "] -> " . "Es wurden nicht alle erforderlichen Parameter gesetzt. "
                    . "Fehlende Parameter: " . $missing_parameters . ". "
                    . "<br>"
                );
                return false;
            }
        }
        public function ignoreTables(array $tables) : void
        {
            $this->blacklist = $tables;
        }
        private function isOpen() : bool 
        {
            if(isset($this->db1) && isset($this->db2)) {
                return true;
            }
            
            if(!isset($this->db1) && isset($this->db2)) {
                $this->setError("Fehlende oder Fehlerhafte Verbindung zu \"db1\"!");
            } else if(!isset($this->db2) && isset($this->db1)) {
                $this->setError("Fehlende oder Fehlerhafte Verbindung zu \"db2\"!");
            } else if(!isset($this->db1) && !isset($this->db2)) {
                $this->setError("Fehlende oder Fehlerhafte Verbindung zu \"db1\" und \"db2\"!");
            }
            return false;
        }
        private function getTables(PDO $db) : array
        {
            $tables = array();
            /*
                Checkt, ob beide Datenbankverbindungen gesetzt wurden
            */
            if(!$this->isOpen()) { return []; }

            $stmt = $db->prepare("SHOW TABLES;");
            try {
                $stmt->execute();
            } catch (Exception $e) {
                $this->setError(
                    $e->getMessage()
                    . "<br>"
                );
            }

            foreach($stmt->fetchAll() as $item) {
                $tables[] = $item[0];
            }
            return $tables;
        }
        private function initBackupTables(PDO $db) : bool
        {
            if(!$this->isOpen()) { return false; }
            /*
                1. Prüfe ob beide Backup Tabellen existieren
                2. wenn nicht, erstellen
            */
            $sql = 'CREATE TABLE IF NOT EXISTS `' . $this->backup_value_tablename . '` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `timestamp` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                `tablename` varchar(255) DEFAULT NULL,
                `field` varchar(255) DEFAULT NULL,
                `value` varchar(1000) DEFAULT NULL,
                `type` varchar(255) DEFAULT NULL,
                PRIMARY KEY (`id`)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
              CREATE TABLE IF NOT EXISTS `' . $this->backup_structure_tablename . '` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                `tablename` varchar(255) NOT NULL,
                `createstmt` text NOT NULL,
                `fields` text NOT NULL,
                PRIMARY KEY (`id`)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
              CREATE TABLE IF NOT EXISTS `' . $this->backup_config_tablename . '` (
                `active` tinyint(1) NOT NULL
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
              TRUNCATE TABLE `' . $this->backup_config_tablename . '`;
              INSERT INTO `' . $this->backup_config_tablename . '`(`active`) VALUES (false);
              ';

            $stmt = $db->prepare($sql);

            try {
                $stmt->execute();
                return true;
            } catch (PDOException $e) {
                $this->setError($e->getMessage());
            }

            return false;
        }
        public function writeTo(string $str_to_db) : bool
        {
            if(!$this->isOpen()) { return false; }

            $to_db = null;
            $from_db = null;
            /*
                @var $backup_timestamp
            */
            $backup_timestamp = null;

            $success_switch = true;

            if(!$this->isOpen()) { return false; }

            //'db1' oder 'db2'
            switch($str_to_db)
            {
                case 'db1':
                    $to_db = $this->db1;
                    $from_db = $this->db2;
                    break;
                case 'db2':
                    $to_db = $this->db2;
                    $from_db = $this->db1;
                    break;
                default:
                    $this->setError('Ungültiger Parameter! Bitte nur \'db1\' oder \'db2\' verwenden.');
                    return false;
            }     
        

            //Erstell backup Tabellen in To-DB wenn nicht vorhanden
            $this->initBackupTables($to_db);

            //Prüfe, ob bereits aktiv
            if($this->isActive($to_db)) {
                $this->setError('Backup wird bereits ausgeführt.');
                return false;
            }

            //setzt backup aktiv auf true
            $this->setActive($to_db, true);

            //Erstellt aktuellen Timestamp von Download
            $backup_timestamp = date("Y-m-d H:i:s");

            //tabellen von der from_db holen
            $tables = $this->getTables($from_db);
            
            //schreibe struktur für jede tabelle in sn_backup_structure
            foreach($tables as $table)
            {
                //prüfe ob sn table
                if(!in_array($table, $this->blacklist) && $table != $this->backup_config_tablename && $table != $this->backup_structure_tablename && $table != $this->backup_value_tablename) {
                    $sql = "SHOW CREATE TABLE `" . $table. "`;";
                    $stmt = $from_db->prepare($sql);
                    try {
                        $stmt->execute();
                    } catch (PDOException $e) {
                        $this->setError($e->getMessage());
                        $success_switch = false;
                    }
                    
                    $createstmt = $stmt->fetchAll()[0][1] . ";";

                    $sql = 'DESCRIBE `' . $table . '`;';
                    $stmt = $from_db->prepare($sql);
                    try {
                        $stmt->execute();
                    } catch (PDOException $e) {
                        $this->setError($e->getMessage());
                        $success_switch = false;
                    }
                    $fields = array();
                    $result = $stmt->fetchAll();

                    foreach($result as $item) {
                        $fields[] = $item['Field'];
                    }

                    $fields = implode("<::>" , $fields);

                    $sql = "INSERT INTO `" . $this->backup_structure_tablename . "` (`timestamp`, `tablename`, `createstmt`, `fields`) VALUES (:timestamp, :tablename, :createstmt, :fields);";
                    $stmt = $to_db->prepare($sql);
                    $stmt->bindParam(':timestamp', $backup_timestamp);
                    $stmt->bindParam(':tablename', $table, PDO::PARAM_STR);
                    $stmt->bindParam(':createstmt', $createstmt, PDO::PARAM_STMT);
                    $stmt->bindParam(':fields', $fields, PDO::PARAM_STR);

                    try {
                        $stmt->execute();
                    } catch (PDOException $e) {
                        $this->setError($e->getMessage());
                        $success_switch = false;
                    }
                }
            }

            //schreibe daten für jede tabelle in sn_backup_values
            //TODO
            foreach($tables as $table) 
            {             
                if(!in_array($table, $this->blacklist) && $table != $this->backup_config_tablename && $table != $this->backup_structure_tablename && $table != $this->backup_value_tablename) {
                    $sql = "SELECT * FROM `" . $table . "`;";
                    $stmt = $from_db->prepare($sql);
                    $stmt->execute();

                    if(!empty($stmt->fetchAll())) {
                        $statement = $this->createInsertStatement($from_db, $table, $backup_timestamp);
                        $stmt = $to_db->prepare($statement);

                        try {
                            $stmt->execute();
                        } catch (PDOException $e) {
                            $this->setError($e->getMessage());
                            $success_switch = false;
                        }
                    }
                }

            }

            //setzte aktiv wieder auf false
            $this->setActive($to_db, false);

            if($success_switch) {
                return true;
            } else {
                return false;
            }
            
        }
        public function restoreFrom(string $str_from_db, string $timestamp, array $destination_db) : bool
        {
            /*
                1. Prüfe, ob Backup Tabellen in DB vorhanden [X]
                2. Prüfe, ob Zeitstempel in Backups vorhanden und holt sich Struktur der wiederherzustellenden Tabelle [x]
                3. Baue Statement zusammen mit Daten aus Tabellen [X]
            */
            if(!$this->isOpen()) { return false; }

            $to_db = null;
            $to_db_sync = false;

            $from_db = null;
            $from_db_sync = false;

            $databases = [
                'db1' => null,
                'db2' => null
            ];

            //'db1' oder 'db2'
            switch($str_from_db)
            {
                case 'db1':
                    $to_db = $this->db2;
                    $from_db = $this->db1;

                    $databases['db1'] = 'from_db';
                    $databases['db2'] = 'to_db';
                    break;
                case 'db2':
                    $to_db = $this->db1;
                    $from_db = $this->db2;

                    $databases['db2'] = 'from_db';
                    $databases['db1'] = 'to_db';
                    break;
                default:
                    $this->setError('Ungültiger Parameter! Bitte nur \'db1\' oder \'db2\' verwenden.');
                    return false;
            }

            //abfrage ob db1 oder db2 in destinations drinne
            if(!empty($destination_db)) {
                foreach($destination_db as $item) {
                    if($item != "db1" && $item != "db2") {
                        $this->setError("Bitte nur 'db1' oder 'db2' als Ziel-Datenbank verwenden.");
                        return false;
                    }
                    if($databases[$item] == 'from_db') {
                        $from_db_sync = true;
                    }
                    if($databases[$item] == 'to_db') {
                        $to_db_sync = true;
                    }
                }
            } else {
                $this->setError("Es wurde keine Zieldatenbank übergeben.");
                return false;
            }

            $success_switch = true;

            //1
            $tables = $this->getTables($from_db);

            if(!in_array($this->backup_config_tablename, $tables) && !in_array($this->backup_structure_tablename, $tables) && !in_array($this->backup_value_tablename, $tables)) {
                $this->setError("Auf dieser Datenbank befinden sich keine durch die Klasse erstellten Backups.");
                return false;
            }

            //2
            $sql = "SELECT `tablename`, `createstmt`, `fields` FROM `" . $this->backup_structure_tablename . "` WHERE `timestamp` = :timestamp;";
            $stmt = $from_db->prepare($sql);
            $stmt->bindParam(":timestamp", $timestamp, PDO::PARAM_STR);
            try{
                $stmt->execute();
            } catch (PDOException $e) {
                $this->setError($e->getMessage());
                return false;
            }

            $result = $stmt->fetchAll();

            if(empty($result)) {
                $this->setError("Es wurde kein Backup mit dem Zeitstempel " . $timestamp . " gefunden.");
                return false;
            }


            //setzt fremdschlüssel aus
            //SWITCH FÜR DESTINATION
            $sql = "SET FOREIGN_KEY_CHECKS = 0;";

            if($to_db_sync){
                $stmt = $to_db->prepare($sql);

                try {
                    $stmt->execute();
                } catch (PDOException $e) {
                    $this->setError($e->getMessage());
                    return false;
                }
            }
            if($from_db_sync) {
                $stmt = $from_db->prepare($sql);

                try {
                    $stmt->execute();
                } catch (PDOException $e) {
                    $this->setError($e->getMessage());
                    return false;
                }
            }



            //3
            foreach($result as $item) {
                $tablename = $item['tablename'];
                $createstmt = $item['createstmt'];
                $fields = $item['fields'];

                $fields = explode("<::>", $fields);

                $statement = "";

                $statement .= "DROP TABLE IF EXISTS `" . $tablename . "`;";
                //$statement .= "SET FOREIGN_KEY_CHECKS = 0;";
                $statement .= $createstmt;

                $sql = "SELECT `field`, `value`, `type` FROM `" . $this->backup_value_tablename . "` WHERE `timestamp` = :zeitstempel AND `tablename` = :tablename;";
                $stmt = $from_db->prepare($sql);
                $stmt->bindParam(":zeitstempel", $timestamp, PDO::PARAM_STR);
                $stmt->bindParam(":tablename", $tablename, PDO::PARAM_STR);

                try {
                    $stmt->execute();
                } catch (PDOException $e) {
                    $this->setError($e->getMessage());
                    $success_switch = false;
                }
                

                $result = $stmt->fetchAll();

                $z = 0;

                $insert_values = "(";

                foreach($result as $item) {
                    if($z == count($fields)) {
                        $insert_values = substr($insert_values, 0, strlen($insert_values) - 2);
                        $insert_values .= "), (";
                        $z = 0;
                    }

                    if(is_null($item['value'])) {
                        $insert_values .= "NULL" . ", ";
                    } else {
                        if(in_array($item['type'], $this->typesToQuote)) {
                            $insert_values .= $to_db->quote($item['value']) . ', ';
                        } else {
                            $insert_values .= $item['value'] . ", ";
                        }
                    }

                    $z++;
                }
                
                $insert_values = substr($insert_values, 0, strlen($insert_values) - 2) . ");";

                $columns = implode("`, `", $fields);

                $statement .= "INSERT INTO `" . $tablename . "` (`" . $columns . "`) VALUES " . $insert_values;

                //$statement .= "SET FOREIGN_KEY_CHECKS = 1;";

                /*
                echo "<hr>";
                print_r($statement);
                echo "<hr>";
                */

                //SWITCH FOR DESTINATION
                if($to_db_sync){
                    $stmt = $to_db->prepare($statement);
                    
                    try {
                        $stmt->execute();
                    } catch (PDOException $e) {
                        $this->setError("TABELLE: "  . $tablename . " --> " . $e->getMessage());
                        $success_switch = false;
                    }
                }
                if($from_db_sync) {
                    $stmt = $from_db->prepare($statement);

                    try {
                        $stmt->execute();
                    } catch (PDOException $e) {
                        $this->setError("TABELLE: "  . $tablename . " --> " . $e->getMessage());
                        $success_switch = false;
                    }
                }

            }

            //setzt fremdschlüssel aus
            //SWITCH FOR DESTINATION
            $sql = "SET FOREIGN_KEY_CHECKS = 1;";

            if($to_db_sync){
                $stmt = $to_db->prepare($sql);
                
                try {
                    $stmt->execute();
                } catch (PDOException $e) {
                    $this->setError($e->getMessage());
                    return false;
                }
            }
            if($from_db_sync) {
                $stmt = $from_db->prepare($sql);
                
                try {
                    $stmt->execute();
                } catch (PDOException $e) {
                    $this->setError($e->getMessage());
                    return false;
                }
            }

            if(!$success_switch) {
                return false;
            }
            //todo
            return true;
        }
        private function setActive(PDO $db, bool $value) : void
        {
            $sql = 'UPDATE `' . $this->backup_config_tablename . '` SET `active` = :value;';
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':value', $value, PDO::PARAM_BOOL);
            try{
                $stmt->execute();
            } catch (PDOException $e) {
                $this->setError($e->getMessage());
            }
        }
        private function isActive(PDO $db) : bool
        {
            $sql = 'SELECT `active` FROM `' . $this->backup_config_tablename . '`;';
            $stmt = $db->prepare($sql);

            try{
                $stmt->execute();
            } catch (PDOException $e) {
                $this->setError($e->getMessage());
            }

            if($stmt->fetchAll()[0][0] == 1) {
                return true;
            }
            return false;
        }
        private function createInsertStatement(PDO $from_db, string $table, $timestamp) : string
        {          
            $statement = "";
            $statement .= 'INSERT INTO `' . $this->backup_value_tablename . '` (`timestamp`, `tablename`, `field`, `value`, `type`) VALUES ';


            $sql = "DESCRIBE `" . $table . "`;";
            $stmt = $from_db->prepare($sql);
            try {
                $stmt->execute();
            } catch (PDOException $e) {
                $this->setError($e->getMessage());
            }

            $result = $stmt->fetchAll();

            $fieldname = array();
            $fieldtype = array();
            $field_types = array();

            foreach($result as $item) {
                $fieldname[] = $item['Field'];

                if(strpos($item['Type'], "(")) {
                    $fieldtype[] = substr($item['Type'], 0, strpos($item['Type'], "("));
                } else {
                    $fieldtype[] = $item['Type'];
                } 
               
            }
            $field_types = array_combine($fieldname, $fieldtype);

            /*
            echo "<hr>";
            print_r($field_types);
            echo "<hr>";
            */


            $sql = "SELECT * FROM `" . $table . "`;";
            $stmt = $from_db->prepare($sql);
            $stmt->execute();

            $result = $stmt->fetchAll();

            $temp_stmt = "";

            foreach($result as $item) {
                foreach($item as $key => $value){
                    /*
                        datentyp berücksichtigen
                    */
                    if(!is_int($key)) {
                        //value
                        if(is_null($value)) {
                            $value = "NULL";
                        } else {
                            $value = '' . $from_db->quote($value) . '';
                            //$value = $value;
                        }
                        $temp_stmt .= '("' . $timestamp . '", "' . $table . '", "' . $key . '", ' . $value . ', "' . $field_types[$key] . '"),';
                    }
                }        
            }

            $temp_stmt = substr($temp_stmt, 0, strlen($temp_stmt) - 1) . ";";

            $statement .= $temp_stmt;

            /*
            echo "<hr>";
            print_r($statement);
            echo "<hr>";
            */

            return $statement;
        }
        private function setError($msg) : void
        {
            if($this->errmsg == ""){
                $this->errmsg = "[FEHLER] : " . $msg;
            }
        }
        public function getErrorMessage() : string
        {
            if($this->errmsg == "") {
                return "No Error found.";
            }
            return $this->errmsg;
        }
        public function getRestoreableTimestamps(string $db, string $orderby = null) : array
        {
            switch($db) {
                case 'db1':
                    $pdo = $this->db1;
                    break;
                case 'db2':
                    $pdo = $this->db2;
                    break;
                default:
                    $this->setError('Ungültiger Parameter! Bitte nur \'db1\' oder \'db2\' verwenden.');
                    return [];
            }

            if(!in_array($this->backup_structure_tablename, $this->getTables($pdo))) {
                $this->setError('Es wurden keine Backup Tabellen auf dieser Datenbank gefunden.');
                return [];
            }

            if($orderby != null) {
                $sql = "SELECT DISTINCT `timestamp` FROM `" . $this->backup_structure_tablename . "` ORDER BY `timestamp` " . strtoupper($orderby) . ";";
            } else {
                $sql = "SELECT DISTINCT `timestamp` FROM `" . $this->backup_structure_tablename . "`;";
            }
            
            $stmt = $pdo->prepare($sql);

            try {
                $stmt->execute();
            } catch (PDOException $e) {
                $this->setError($e->getMessage());
                return [];
            }

            $result = $stmt->fetchAll();
            $timestamps = array();
            foreach($result as $item) {
                $timestamps[] = $item['timestamp'];
            }

            return $timestamps;
        }
    }
?>