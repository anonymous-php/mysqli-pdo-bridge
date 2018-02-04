<?php

namespace Anonymous\MysqliPdoBridge;


/**
 * Class MysqliPDO
 * @author Anonymous PHP Developer
 * @package Anonymous\MysqliPdoBridge
 */
class MysqliPDO extends \PDO
{

    protected $mysqli;
    protected $mysqliTransaction = 0;

    protected $options = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT,
        \PDO::ATTR_AUTOCOMMIT => true,
        \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_BOTH,
        \PDO::MYSQL_ATTR_INIT_COMMAND => '',
    ];


    /**
     * @inheritdoc
     */
    public function __construct($dsn, $username = null, $passwd = null, $options = [])
    {
        if ($dsn instanceof \mysqli) {
            $this->mysqli = $dsn;
        } else {
            list($host, $port, $dbname, $socket) = $this->parseDsn($dsn);
            $this->mysqli = new \mysqli($host, $username, $passwd, $dbname, $port, $socket);

            if (!empty($this->options[\PDO::MYSQL_ATTR_INIT_COMMAND])) {
                $this->exec($this->options[\PDO::MYSQL_ATTR_INIT_COMMAND]);
            }
        }

        $this->setOptions($options);
    }

    public static function withConnection(\mysqli $connection)
    {
        return new static($connection);
    }

    /**
     * @inheritdoc
     */
    public function prepare($statement, $options = null)
    {
        $pdoStatement = new MysqliPDOStatement($this, __FUNCTION__, $statement, $options);

        return !$this->mysqli->errno
            ? $pdoStatement
            : false;
    }

    /**
     * @inheritdoc
     */
    public function beginTransaction()
    {
        return ($this->mysqli->begin_transaction() && ++$this->mysqliTransaction);
    }

    /**
     * @inheritdoc
     */
    public function commit()
    {
        return ($this->mysqli->commit() && (--$this->mysqliTransaction || true));
    }

    /**
     * @inheritdoc
     */
    public function rollBack()
    {
        return ($this->mysqli->rollback() && (--$this->mysqliTransaction || true));
    }

    /**
     * @inheritdoc
     */
    public function inTransaction()
    {
        return $this->mysqliTransaction > 0;
    }

    /**
     * @inheritdoc
     */
    public function setAttribute($attribute, $value)
    {
        return $this->setOptions([$attribute => $value]);
    }

    /**
     * @inheritdoc
     */
    public function exec($statement)
    {
        $result = $this->options[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY]
            ? $this->mysqli->query($statement)
            : $this->mysqli->real_query($statement);

        if ($result instanceof \mysqli_result) {
            $result->close();
            return true;
        }

        return $result ? $this->mysqli->affected_rows : false;
    }

    /**
     * @inheritdoc
     */
    public function query($statement, $mode = null, $arg3 = null, array $ctorargs = array())
    {
        $pdoStatement = new MysqliPDOStatement($this, __FUNCTION__, $statement, $mode, $arg3, $ctorargs);

        return !$this->mysqli->errno
            ? $pdoStatement
            : false;
    }

    /**
     * @inheritdoc
     */
    public function lastInsertId($name = null)
    {
        return $this->mysqli->insert_id;
    }

    /**
     * @inheritdoc
     */
    public function errorCode()
    {
        return $this->mysqli->errno;
    }

    /**
     * @inheritdoc
     */
    public function errorInfo()
    {
        return $this->mysqli->error;
    }

    /**
     * @inheritdoc
     */
    public function getAttribute($attribute)
    {
        return isset($this->options[$attribute])
            ? $this->options[$attribute]
            : null;
    }

    /**
     * @inheritdoc
     */
    public function quote($string, $parameter_type = \PDO::PARAM_STR)
    {
        switch ($parameter_type) {
            case \PDO::PARAM_NULL:
                $string = 'NULL';
                break;
            case \PDO::PARAM_BOOL:
                $string = intval(!(in_array(strtolower($string), ['false', 'f']) || empty($value)));
                break;
            case \PDO::PARAM_INT:
                $string = intval($string);
                break;
            default:
                $string = "'{$this->mysqli->real_escape_string($string)}'";
        }

        return $string;
    }

    /**
     * @inheritdoc
     */
    public static function getAvailableDrivers()
    {
        return ['mysql'];
    }

    /**
     * @inheritdoc
     */
    protected function setOptions($options)
    {
        foreach ($options as $option => $value) {
            if (!isset($this->options[$option])) {
                continue;
            }

            $this->options[$option] = $value;

            if ($option == \PDO::ATTR_AUTOCOMMIT) {
                $this->mysqli->autocommit((bool)$value);
            }
        }

        return true;
    }

    /**
     * @return \mysqli
     */
    public function getConnection()
    {
        return $this->mysqli;
    }

    /**
     * Parse connection values from PDO_MYSQL DSN string
     *
     * @param $dsn
     * @return array
     */
    protected function parseDsn($dsn)
    {
        if (!preg_match('/^mysql:/i', $dsn)) {
            throw new MysqliPDOException('Check available drivers');
        }

        $dsnArray = ['host' => null, 'port' => null, 'dbname' => null, 'unix_socket' => null];
        $parts = explode(';', substr($dsn, 6));

        foreach ($parts as $part) {
            list($name, $value) = explode('=', trim($part));
            $name = strtolower($name);

            if (array_key_exists($name, $dsnArray)) {
                $dsnArray[$name] = $value;
            }
        }

        return array_values($dsnArray);
    }

}