<h1 align="center">db-mirror</h1>

db-mirror contains two classes for database sync and backup.

## sync

```php
$sync = new Sync();

$sync->setDB('db1', [
    'host' => 'HOST_ADRESS',
    'dbname' => 'DB_NAME',
    'username' => 'USERNAME',
    'password' => 'PASSWORD'
]);
$sync->setDB('db2', [
    'host' => 'HOST_ADRESS',
    'dbname' => 'DB_NAME',
    'username' => 'USERNAME',
    'password' => 'PASSWORD'
]);

$sync->syncTo('db2');
```

## backup

```php
$backup = new Backup();

$backup->setDB('db1', [
    'host' => 'HOST_ADRESS',
    'dbname' => 'DB_NAME',
    'username' => 'USERNAME',
    'password' => 'PASSWORD'
]);
$backup->setDB('db2', [
    'host' => 'HOST_ADRESS',
    'dbname' => 'DB_NAME',
    'username' => 'USERNAME',
    'password' => 'PASSWORD'
]);

$backup->writeTo('db2');

$backup->restoreFrom('db2', "2022-12-24 08:00:00", ['db1', 'db2']);

```
