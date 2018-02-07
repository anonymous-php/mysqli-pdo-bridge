<?php

use PHPUnit\Framework\TestCase;
use Anonymous\MysqliPdoBridge\MysqliPDO;


final class MysqliPDOTest extends TestCase
{

    protected $connection = [
        'host' => '127.0.0.1',
        'port' => 3306,
        'dbname' => 'test',
        'username' => 'travis',
        'password' => '',
        'options' => [

        ],
    ];

    /** @var mysqli */
    protected $mysqli;


    protected function getMysqliConnection()
    {
        if ($this->mysqli === null) {
            $this->mysqli = new mysqli(
                $this->connection['host'],
                $this->connection['username'],
                $this->connection['password'],
                $this->connection['dbname'],
                $this->connection['port']
            );
        }

        return $this->mysqli;
    }

    public function testCanConnect()
    {
        $pdo = new Anonymous\MysqliPdoBridge\MysqliPDO(
            "mysql:host={$this->connection['host']};port={$this->connection['port']};dbname={$this->connection['dbname']}",
            $this->connection['username'],
            $this->connection['password'],
            $this->connection['options']
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
        $pdo = new Anonymous\MysqliPdoBridge\MysqliPDO(
            "mysql:host={$this->connection['host']};port={$this->connection['port']};dbname={$this->connection['dbname']}1",
            $this->connection['username'],
            $this->connection['password'],
            $this->connection['options']
        );
    }

    public function testGetAvailableDrivers()
    {
        $this->assertEquals(['mysql'], MysqliPDO::getAvailableDrivers());
    }

}