# mysqli-pdo-bridge

This library gives you the possibility to use mysqli connection with the PDO interfaces. No additional wrappers or methods provides.

### Examples

New connection:
 
```php
<?php

use \Anonymous\MysqliPdoBridge\MysqliPDO;
use \Anonymous\MysqliPdoBridge\MysqliPDOStatement;

$pdo = new MysqliPDO('mysql:host=127.0.0.1;dbname=test', 'test', 'test');

/** @var MysqliPDOStatement $stmt */ 
$stmt = $pdo->prepare('SELECT * FROM test WHERE id = :id LIMIT 1');
$stmt->execute(array(':id' => 1));

$result = $stmt->fetch(\PDO::FETCH_ASSOC);
```

Existed connection:

```php
<?php

use \Anonymous\MysqliPdoBridge\MysqliPDO;

/**
 * @var \mysqli $mysqli
 */

$pdo = MysqliPDO::withConnection($mysqli);
// or
$pdo = new MysqliPDO($mysqli);
```

### Installation

```
composer require anonymous-php/mysqli-pdo-bridge  
```

### Implemented fetch modes

* PDO::FETCH_BOTH
* PDO::FETCH_ASSOC
* PDO::FETCH_NUM
* PDO::FETCH_COLUMN
* PDO::FETCH_CLASS
* PDO::FETCH_OBJ
* PDO::FETCH_FUNC
* PDO::FETCH_INTO
* PDO::FETCH_BOUND

### Possible issues

* Stability
* Performance
* Exotic fetching modes
* Cursors

### Todo

* Method debugDumpParams
* Error reporting and exceptions
* Tests
* Documentation