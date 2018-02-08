<?php

use PHPUnit\Framework\TestCase;
use Anonymous\MysqliPdoBridge\MysqliPDO;


final class MysqliPDOTest extends TestCase
{

    protected $options = [
    ];

    /** @var mysqli */
    protected $mysqli;

    /** @var PDO */
    protected $pdo;


    protected function getMysqliConnection()
    {
        if ($this->mysqli === null) {
            $this->mysqli = new mysqli(
                getenv('host'),
                getenv('username'),
                getenv('password'),
                getenv('dbname'),
                getenv('port')
            );
        }

        return $this->mysqli;
    }

    protected function getPdoConnection()
    {
        list($host, $port, $dbname) = array(getenv('host'), getenv('port'), getenv('dbname'));

        if ($this->pdo === null) {
            $this->pdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$dbname}",
                getenv('username'),
                getenv('password'),
                $this->options
            );
        }

        return $this->pdo;
    }

    protected function prepeareDatabase()
    {
        $dropQuery = "DROP TABLE IF EXISTS `test`";
        $createQuery = <<<SQL
CREATE TABLE `test` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `a` int(11) DEFAULT 0,
  `b` double DEFAULT 0.0,
  `c` text DEFAULT NULL,
  `d` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARSET=utf8;
SQL;

        $this->getMysqliConnection()->query($dropQuery);
        $this->getMysqliConnection()->query($createQuery);
    }

    public function setUp()
    {
        parent::setUp();

        $this->prepeareDatabase();
    }

    public function testCanConnect()
    {
        list($host, $port, $dbname) = array(getenv('host'), getenv('port'), getenv('dbname'));

        $pdo = new Anonymous\MysqliPdoBridge\MysqliPDO(
            "mysql:host={$host};port={$port};dbname={$dbname}",
            getenv('username'),
            getenv('password'),
            $this->options
        );

        $this->assertInstanceOf(MysqliPDO::class, $pdo);
        $this->assertInstanceOf(PDO::class, $pdo);
        $this->assertNotNull($pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS));
    }

    public function testCanBeCratedFromExistingConnection()
    {
        $mysqli = $this->getMysqliConnection();

        $this->assertInstanceOf(mysqli::class, $mysqli);
        $this->assertEquals(0, $mysqli->connect_errno);

        $pdo = MysqliPDO::withConnection($mysqli);

        $this->assertInstanceOf(MysqliPDO::class, $pdo);
        $this->assertInstanceOf(PDO::class, $pdo);

        $this->assertSame($mysqli, $pdo->getConnection());
        $this->assertNotNull($pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS));

        $pdo = new MysqliPDO($mysqli);

        $this->assertInstanceOf(MysqliPDO::class, $pdo);
        $this->assertInstanceOf(PDO::class, $pdo);

        $this->assertSame($mysqli, $pdo->getConnection());
        $this->assertNotNull($pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS));
    }

    /**
     * @expectedException \Anonymous\MysqliPdoBridge\MysqliPDOException
     */
    public function testExceptionOnConnectionFail()
    {
        list($host, $port, $dbname) = array(getenv('host'), getenv('port'), getenv('dbname'));

        $pdo = new Anonymous\MysqliPdoBridge\MysqliPDO(
            "mysql:host={$host};port={$port};dbname={$dbname}1",
            getenv('username'),
            getenv('password'),
            $this->options
        );
    }

    public function testGetAvailableDrivers()
    {
        $this->assertEquals(['mysql'], MysqliPDO::getAvailableDrivers());
    }

    public function testExec()
    {
        $dropQuery = "DROP TABLE IF EXISTS `test`";
        $createQuery = <<<SQL
CREATE TABLE `test` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `a` int(11) DEFAULT 0,
  `b` double DEFAULT 0.0,
  `c` text DEFAULT NULL,
  `d` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARSET=utf8;
SQL;

        $pdo = $this->getPdoConnection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $mysqliPdo = new MysqliPDO($this->getMysqliConnection());
        $mysqliPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdoDropResult = $pdo->exec($dropQuery);
        $pdoCreateResult = $pdo->exec($createQuery);

        $mysqliPdoDropResult = $mysqliPdo->exec($dropQuery);
        $mysqliPdoCreateResult = $mysqliPdo->exec($createQuery);

        $this->assertEquals($pdoDropResult, $mysqliPdoDropResult);
        $this->assertEquals($pdoCreateResult, $mysqliPdoCreateResult);

        $pdoInsertResult = $pdo->exec("INSERT INTO `test` VALUES (1, 2, 3.5, 'varchar', 'text')");
        $mysqliPdoInsertResult = $pdo->exec("INSERT INTO `test` VALUES (2, 3, 4.5, 'varchar', 'text')");
        $this->assertEquals($pdoInsertResult, $mysqliPdoInsertResult);

        $pdoUpdateResult = $pdo->exec("UPDATE `test` SET `d` = 'long text'");
        $mysqliPdoUpdateResult = $pdo->exec("UPDATE `test` SET `c` = 'short text'");
        $this->assertEquals($pdoUpdateResult, $mysqliPdoUpdateResult);

        $pdoDeleteResult = $pdo->exec("DELETE FROM `test` WHERE `id` = 2");
        $mysqliPdoDeleteResult = $pdo->exec("DELETE FROM `test` WHERE `id` = 1");
        $this->assertEquals($pdoDeleteResult, $mysqliPdoDeleteResult);
    }

    public function testLastInsertId()
    {
        $pdo = new MysqliPDO($this->getMysqliConnection());

        $pdo->exec("INSERT INTO `test` (`a`, `b`, `c`, `d`) VALUES (2, 3.5, 'varchar', 'text')");
        $this->assertEquals(1, $pdo->lastInsertId());

        $pdo->exec("INSERT INTO `test` (`a`, `b`, `c`, `d`) VALUES (3, 4.5, 'varchar', 'text')");
        $this->assertEquals(2, $pdo->lastInsertId());
    }

    public function testQuote()
    {
        $mysqliPdo = new MysqliPDO($this->getMysqliConnection());
        $pdo = $this->getPdoConnection();

        $this->assertEquals(
            $mysqliPdo->quote("John's coffee"),
            $pdo->quote("John's coffee")
        );

        $this->assertEquals(
            $mysqliPdo->quote(true, PDO::PARAM_BOOL),
            1
        );

        $this->assertEquals(
            $mysqliPdo->quote(null, PDO::PARAM_NULL),
            'NULL'
        );

        $this->assertEquals(
            $mysqliPdo->quote('100.20', PDO::PARAM_INT),
            100
        );
    }

    public function testTransaction()
    {
        $mysqliPdo = new MysqliPDO($this->getMysqliConnection());

        $pdo = $this->getPdoConnection();

        $this->assertTrue($mysqliPdo->beginTransaction());
        $this->assertTrue($mysqliPdo->inTransaction());

        $this->assertEquals(
            $mysqliPdo->exec("INSERT INTO `test` (`a`, `b`, `c`, `d`) VALUES (2, 3.5, 'varchar', 'text')"),
            1
        );

        $this->assertEquals(
            $pdo->query('SELECT count(*) FROM `test`')->fetchColumn(),
            0
        );

        $this->assertTrue($mysqliPdo->rollBack());

        $this->assertEquals(
            $pdo->query('SELECT count(*) FROM `test`')->fetchColumn(),
            0
        );

        $this->assertTrue($mysqliPdo->beginTransaction());
        $this->assertTrue($mysqliPdo->inTransaction());

        $this->assertEquals(
            $mysqliPdo->exec("INSERT INTO `test` (`a`, `b`, `c`, `d`) VALUES (2, 3.5, 'varchar', 'text')"),
            1
        );

        $this->assertTrue($mysqliPdo->commit());

        $this->assertEquals(
            $pdo->query('SELECT count(*) FROM `test`')->fetchColumn(),
            1
        );
    }

    /**
     * @expectedException \Anonymous\MysqliPdoBridge\MysqliPDOException
     */
    public function testExceptionOnNestedTransaction()
    {
        $mysqliPdo = new MysqliPDO($this->getMysqliConnection());
        try {
            $this->assertTrue($mysqliPdo->beginTransaction());
            $mysqliPdo->beginTransaction();
        } catch (\Anonymous\MysqliPdoBridge\MysqliPDOException $e) {
            $mysqliPdo->rollBack();
            throw $e;
        }
    }

}