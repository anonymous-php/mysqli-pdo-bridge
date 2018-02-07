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

Get connection:

```php
<?php

use \Anonymous\MysqliPdoBridge\MysqliPDO;

/**
 * @var MysqliPDO $pdo
 */

$mysqli = $pdo->getConnection();
```

### Installation

```
composer require anonymous-php/mysqli-pdo-bridge  
```

### Error reporting

Error reporting modes implemented as in PDO but error codes and messages belong to Mysqli.

### Implemented PDO fetch modes

* PDO::FETCH_BOTH
* PDO::FETCH_ASSOC
* PDO::FETCH_NUM
* PDO::FETCH_COLUMN
* PDO::FETCH_CLASS
* PDO::FETCH_OBJ
* PDO::FETCH_FUNC
* PDO::FETCH_INTO
* PDO::FETCH_BOUND

### Implemented PDO options (attributes)

* PDO::ATTR_ERRMODE (PDO::ERRMODE_SILENT or PDO::ERRMODE_EXCEPTION, PDO::ERRMODE_SILENT by default)
* PDO::ATTR_AUTOCOMMIT (true or false, true by default)
* PDO::MYSQL_ATTR_USE_BUFFERED_QUERY (true or false, true by default)
* PDO::ATTR_DEFAULT_FETCH_MODE (see implemented fetch modes)
* PDO::MYSQL_ATTR_INIT_COMMAND (only for new connections)
* PDO::ATTR_PERSISTENT (true or false, false by default)

### Possible issues

* Stability
* Performance
* Exotic fetching modes
* Cursors

### Todo

* Map "duplicate record" error on insert to PDO code
* Method debugDumpParams
* Tests
* Documentation

### Why?

We have a huge legacy project with mysqli which we want to refactor and we still have PHP 5.5 on several nodes.