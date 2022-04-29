<?php
    class Sync {
        private PDO $db1;
        private PDO $db2;

        public function __construct()
        {
            
        }
        public function setDB1($options) : bool
        {
            if(array_key_exists("host", $options) && array_key_exists("dbname", $options) && array_key_exists("username", $options) && array_key_exists("password", $options)) 
            {
                try 
                {
                    $pdo = new PDO("mysql:host=" . $options['host'] . ";dbname=" . $options['dbname'] . "", $options['username'], $options['password']);
                    /* Variable DB1 wird gesetzt */
                    $this->db1 = $pdo;
                    return true;
                } catch (Exception $e) 
                {
                    echo $e->getMessage();
                    return false;
                }
            } else {
                echo("Es wurden nicht alle erforderlichen Parameter gesetzt.");
                return false;
            }
        }
        public function setDB2($options) : bool
        {
            if(array_key_exists("host", $options) && array_key_exists("dbname", $options) && array_key_exists("username", $options) && array_key_exists("password", $options)) 
            {
                try 
                {
                    $pdo = new PDO("mysql:host=" . $options['host'] . ";dbname=" . $options['dbname'] . "", $options['username'], $options['password']);
                    /* Variable DB2 wird gesetzt */
                    $this->db2 = $pdo;
                    return true;
                } catch (Exception $e) 
                {
                    echo $e->getMessage();
                    return false;
                }
            } else {
                echo("Es wurden nicht alle erforderlichen Parameter gesetzt.");
                return false;
            }
        }
        private function isOpen() : bool 
        {
            if(isset($this->db1) && isset($this->db2)) {
                return true;
            }
            return false;
        }
        private function getTables(PDO $db) : array
        {
            $tables = array();
            /*
                Checkt, ob beide Datenbankverbindungen gesetzt wurden
            */
            if(!$this->isOpen()) {
                die("Fehlende Datenbankverbindung. Bitte DB1 und/oder DB2 überprüfen.");
                return [];
            }

            $stmt = $db->prepare("SHOW TABLES;");
            try {
                $stmt->execute();
            } catch (Exception $e) {
                die($e->getMessage());
            }

            foreach($stmt->fetchAll() as $item) {
                $tables[] = $item[0];
            }
            return $tables;
        }
        private function tableExists(PDO $db ,string $tablename) : bool
        {
            $result = $this->getTables($db);

            if(in_array($tablename, $result)) {
                return true;
            }
            return false;
        }
        public function sync(string $from, string $to, array $tables = null) : void
        {
            if(!$this->isOpen()) {
                die("Fehlende Datenbankverbindung. Bitte DB1 und/oder DB2 überprüfen.");
            }

            $from_db = null;
            $to_db = null;

            $tablesToSync = array();

            switch($from) {
                case 'db1':
                    $from_db = $this->db1;
                    $to_db = $this->db2;
                    break;
                case 'db2':
                    $from_db = $this->db2;
                    $to_db = $this->db1;
                    break;
            }

            if($tables == null) {
                $tablesToSync = $this->getTables($from_db);
            } else {
                $tablesToSync = $tables;
            }
            
            foreach($tablesToSync as $table) {
                /*
                    Prüft, ob Tabelle bei der FROM Db vorhanden ist, wenn nein dann fehler
                */
                if($this->tableExists($from_db, $table)) {
                    $sql = $this->createStatement($from_db, $table);
                    $stmt = $to_db->prepare($sql);
                    try{
                        $stmt->execute();
                    } catch (PDOException $e) {
                        echo $e->getMessage();
                    }
                    
                }
                
            }            
        }
        private function createStatement(PDO $from_db, string $table) : string
        {
            $active_table = $table;

            $query = $from_db->prepare("SELECT * FROM `" . $active_table . "`;");
            $query->execute();

            $stmt = $from_db->prepare("DESCRIBE `" . $active_table . "`;");
            try {
                $stmt->execute();
            } catch (PDOException $e) {
                die($e->getMessage());
            }


            $statement = "";

            $statement .= "/* Autor: N. Scholz | Topic: Generator for export code for a table | Version: 1.1 */";


            $statement .= 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";';
            $statement .= 'SET time_zone = "+00:00";';

            /*
                Löscht alte Tabelle
            */
            $statement .= "DROP TABLE IF EXISTS `" . $active_table . "`;";


            /*
                Erstellt Tabelle
            */
            $statement .= "CREATE TABLE IF NOT EXISTS `" . $active_table . "` (";

            $columns = array();
            $primary_keys = array();
            $mul_keys = array();
            $auto_increments = array();
            $table_data = $query->fetchAll();

            $temp_stmt = "";

            foreach($stmt->fetchAll() as $item) {
                //print_r($item);
                //echo "<br>";               
                /*
                    [0] : feldname
                    [1] : typ
                    [2] : NULL
                    [3] : KEY
                    [4] : default
                    [5] : Extra
                */

                //Checkt ob NULL oder Nicht
                $null = $item[2] == "NO" ? $null = "NOT NULL" : $null = "DEFAULT NULL";

                //Ergänzt die Arrays Primar und Andere Schlüssel
                if($item[3] == "PRI") {
                    $primary_keys[] = $item[0];
                } elseif($item[3] == "MUL") {
                    $mul_keys[] = $item[0];
                }
               
                //Lädt die autoincrement befehle in ein array
                if($item[5] == "auto_increment") {
                    $auto_increments[] = "MODIFY `" . $item[0] . "` " . $item[1] . " " . $null . " AUTO_INCREMENT,";
                }

                //checkt den default
                if($item[4] != "") {
                    if(!is_int($item[4])) {
                        $default = 'DEFAULT "' . $item[4] . '"';
                    } else {
                        $default = 'DEFAULT ' . $item[4];
                    }
                } else {
                    $default = "";
                }

                //temp statement wird zusammengebaut
                $columns[] = $item[0];
                $temp_stmt .= "`" . $item[0] . "`" . " " . $item[1] . " " . $null . " " . $default . ",";
            }
            $temp_stmt = substr($temp_stmt, 0, strlen($temp_stmt) - 1);

            $statement .= $temp_stmt;
            $statement .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";


            //Füge Daten ein ???WAS WENN LEER?
            //!!!
            $statement .= "/*INSERT VALUES HERE*/";
            
            if(!empty($table_data)) {
                $col = "";
                $col = implode("` ,`" , $columns);
                $col = "`" . $col . "`";
                $statement .= "INSERT INTO `" . $active_table . "` (" . $col . ") VALUES";

                $temp_stmt = "";

                
                foreach($table_data as $item) {
                    $row = "(";
                    for($i = 0; $i < count($item)/2; $i++) {
                        $row .= "'" . $item[$i] . "'" . ", ";
                    }
                    $row = substr($row, 0, strlen($row) - 2) . "),";
                    $temp_stmt .= $row;
                }
                $temp_stmt = substr($temp_stmt, 0 , strlen($temp_stmt) - 1);
                $temp_stmt .= ";";
                $statement .= $temp_stmt;
            }
    
            //Füge Primärschlüssel hinzu
            $statement .= "/* Indizies für die Tabelle `" . $active_table . "` */";
            $statement .= "ALTER TABLE `" . $active_table . "` ";

            $temp_stmt = "";

            for($i = 0; $i < count($primary_keys); $i++) {
                $temp_stmt .= "ADD PRIMARY KEY (`" . $primary_keys[$i] . "`),"; 
            }
            for($i = 0; $i < count($mul_keys); $i++) {
                $temp_stmt .= "ADD KEY `" . $mul_keys[$i] . "`(`" . $mul_keys[$i] . "`),"; 
            }
            $temp_stmt = substr($temp_stmt, 0, strlen($temp_stmt) - 1);
            $statement .= $temp_stmt . ";";



            //Füge AutoIncrement Hinzu
            $statement .= "/* AUTO_INCREMENT für die Tabelle `" . $active_table . "` */";
            $statement .= "ALTER TABLE `" . $active_table . "` ";
            $temp_stmt = "";

            for($i = 0; $i < count($auto_increments); $i++) {
                $temp_stmt .= $auto_increments[$i];
            }
            $temp_stmt = substr($temp_stmt, 0, strlen($temp_stmt) - 1);
            $statement .= $temp_stmt . ";";
            return $statement;
        }

    }
?>