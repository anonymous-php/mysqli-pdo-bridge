<?php

namespace Anonymous\MysqliPdoBridge;


/**
 * Class MysqliPDOStatement
 * @author Anonymous PHP Developer
 * @package Anonymous\MysqliPdoBridge
 */
class MysqliPDOStatement extends \PDOStatement
{

    /** @var MysqliPDO */
    protected $bridge;

    /** @var \mysqli */
    protected $mysqli;

    /** @var string */
    protected $queryMysqli;
    protected $queryBindings = array();

    /** @var \mysqli_stmt */
    protected $statement;
    protected $statementBindings = array();

    /** @var \mysqli_result */
    protected $result;
    protected $resultBindings = array();

    protected $defaultFetchMode;
    protected $defaultFetchArgument;
    protected $defaultFetchConstructorParams = array();

    /** @var MysqliPDOErrorHandler */
    protected $errorHandler;


    /**
     * MysqliPDOStatement constructor.
     * @param MysqliPDO $bridge
     * @param string $method
     * @param string $statement
     */
    public function __construct($bridge, $method, $statement)
    {
        $this->bridge = $bridge;
        $this->mysqli = $this->bridge->getConnection();

        $this->defaultFetchMode = $this->bridge->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE);

        $args = func_get_args();

        call_user_func_array([$this, $method], array_slice($args, 2));
    }

    /**
     * @inheritdoc
     */
    public function execute($input_parameters = null)
    {
        return $this->errorHandler->handle(function () use ($input_parameters) {
            $mysqliParams = array();
            $paramsCount = count($this->queryBindings);

            if ($paramsCount) {
                $types = '';

                foreach ($this->statementBindings as $name => $params) {
                    if (is_numeric($name)) {
                        $n = $name - 1;
                    } else {
                        $name = substr($name, 0, 1) != ':' ? ":{$name}" : $name;
                        $n = array_search($name, $this->queryBindings);

                        if ($n === false) {
                            return false;
                        }
                    }

                    list($var, $type, $length) = $params;
                    $value = $var;
                    if ($length !== null) {
                        $value = mb_substr($value, 0, $length);
                    }
                    $mysqliParams[$n] = $value;
                    $types .= $this->mapPDOToMysqliType($type);
                }

                if (is_array($input_parameters) && count($input_parameters)) {
                    foreach ($input_parameters as $name => $value) {
                        $n = false;

                        if (is_numeric($name)) {
                            foreach ($this->queryBindings as $key => $var) {
                                if ($var == '?' && !array_key_exists($key, $mysqliParams)) {
                                    $n = $key;
                                    break;
                                }
                            }
                        } else {
                            $name = substr($name, 0, 1) != ':' ? ":{$name}" : $name;
                            $n = array_search($name, $this->queryBindings);
                        }

                        if ($n === false) {
                            return false;
                        }

                        $mysqliParams[$n] = $value;
                        $types .= 's';
                    }
                }

                if (!empty($mysqliParams)) {
                    ksort($mysqliParams);

                    $params = array();
                    foreach ($mysqliParams as $key => $value) {
                        $params[$key] = &$mysqliParams[$key];
                    }

                    array_unshift($mysqliParams, $types);
                    if (!call_user_func_array([$this->statement, 'bind_param'], $mysqliParams)) {
                        return false;
                    }
                }
            }

            if ($result = $this->statement->execute()) {
                $this->result = $this->statement->get_result();
            }

            return $result;
        });
    }

    /**
     * @inheritdoc
     */
    public function fetch($fetch_style = null, $cursor_orientation = \PDO::FETCH_ORI_NEXT, $cursor_offset = null)
    {
        if ($this->bridge->getAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY) && $cursor_offset !== null) {
            $this->result->data_seek($cursor_offset);
        }

        return $this->fetchRow($fetch_style);
    }

    /**
     * @inheritdoc
     */
    public function bindParam($parameter, &$variable, $data_type = \PDO::PARAM_STR, $length = null, $driver_options = null)
    {
        $parameter = !is_numeric($parameter) && substr($parameter, 0, 1) != ':'
            ? ":{$parameter}"
            : $parameter;

        if (is_numeric($parameter) && isset($this->queryBindings[$parameter - 1]) && $this->queryBindings[$parameter - 1] == '?'
            || !is_numeric($parameter) && in_array($parameter, $this->queryBindings))
        {
            $this->statementBindings[$parameter] = array(&$variable, $data_type, $length);

            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function bindColumn($column, &$param, $type = \PDO::PARAM_STR, $maxlen = null, $driverdata = null)
    {
        if ($column > 0 && $column <= count($this->queryBindings)) {
            $this->resultBindings[$column] = array(&$param, $type, $maxlen);

            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function bindValue($parameter, $value, $data_type = \PDO::PARAM_STR)
    {
        $parameter = !is_numeric($parameter) && substr($parameter, 0, 1) != ':'
            ? ":{$parameter}"
            : $parameter;

        if (is_numeric($parameter) && isset($this->queryBindings[$parameter - 1]) && $this->queryBindings[$parameter - 1] == '?'
            || !is_numeric($parameter) && in_array($parameter, $this->queryBindings))
        {
            $this->statementBindings[$parameter] = array($value, $data_type, null);

            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function rowCount()
    {
        return $this->statement->affected_rows;
    }

    /**
     * @inheritdoc
     */
    public function fetchColumn($column_number = 0)
    {
        return $this->result->fetch_field_direct($column_number);
    }

    /**
     * @inheritdoc
     */
    public function fetchAll($how = null, $class_name = null, $ctor_args = null)
    {
        if (($mysqliFetchStyle = $this->mapFetchStyle($how)) !== null
            && $this->bridge->getAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY))
        {
            return $this->result->fetch_all($mysqliFetchStyle);
        }

        $result = array();

        while ($row = $this->fetchRow($how, $class_name, (array)$ctor_args)) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function fetchObject($class_name = null, $ctor_args = null)
    {
        $class_name = $class_name !== null ? $class_name : 'stdClass';
        return $this->fetchRow(\PDO::FETCH_CLASS, $class_name, (array)$ctor_args);
    }

    /**
     * @inheritdoc
     */
    public function errorCode()
    {
        return $this->statement instanceof \mysqli_stmt
            ? $this->statement->errno
            : $this->mysqli->errno;
    }

    /**
     * @inheritdoc
     */
    public function errorInfo()
    {
        return $this->statement instanceof \mysqli_stmt
            ? $this->statement->error
            : $this->mysqli->error;
    }

    /**
     * @inheritdoc
     */
    public function setAttribute($attribute, $value)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getAttribute($attribute)
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function columnCount()
    {
        return $this->result->field_count;
    }

    /**
     * @inheritdoc
     */
    public function getColumnMeta($column)
    {
        return $this->mapColumnMeta($this->result->fetch_field_direct($column));
    }

    /**
     * @inheritdoc
     */
    public function setFetchMode($mode, $params = null)
    {
        $this->defaultFetchMode = $mode;
        $this->defaultFetchArgument = $params;
    }

    /**
     * @inheritdoc
     */
    public function nextRowset()
    {
        if (!$this->statement->more_results()) {
            return false;
        }

        $result = $this->statement->next_result();
        $this->result = $this->statement->get_result();

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function closeCursor()
    {
        if ($this->result instanceof \mysqli_result) {
            $this->result->close();
        }
    }

    /**
     * @inheritdoc
     */
    public function debugDumpParams()
    {
        parent::debugDumpParams(); // TODO: Change the autogenerated stub
    }

    /**
     * Free resources
     */
    public function __destruct()
    {
        $this->errorHandler = null;

        if ($this->result instanceof \mysqli_result) {
            $this->result->close();
        }

        if ($this->statement instanceof \mysqli_stmt) {
            $this->statement->close();
        }
    }

    /**
     * Parse query and prepare prepared statement
     *
     * @param $statement
     * @param array $driver_options
     */
    protected function prepare($statement, $driver_options = array())
    {
        $this->parseQuery($statement);

        if ($this->statement = $this->mysqli->prepare($this->queryMysqli)) {
            $this->errorHandler = new MysqliPDOErrorHandler($this->bridge, $this->statement);
        }
    }

    /**
     * Parse and execute query
     *
     * @param $statement
     * @param null $mode
     * @param null $arg3
     * @param array $ctorargs
     */
    protected function query($statement, $mode = null, $arg3 = null, array $ctorargs = array())
    {
        $this->parseQuery($statement);

        if ($mode !== null) {
            $this->defaultFetchMode = $mode;
        }

        if ($arg3 !== null) {
            $this->defaultFetchArgument = $arg3;
        }

        if (!empty($ctorargs)) {
            $this->defaultFetchConstructorParams = $ctorargs;
        }

        $this->result = $this->bridge->getAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY)
            ? $this->mysqli->query($statement)
            : $this->mysqli->real_query($statement);
    }

    /**
     * Parse query
     *
     * @param $queryString
     */
    protected function parseQuery($queryString)
    {
        $strings = [];
        $bindings = [];
        $mysqliQuery = '';

        if (preg_match_all('/"([^#"\\\\]*(?:\\\\.[^#"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\'/ms', $queryString, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $strings[] = [$match[1], $match[1] + mb_strlen($match[0])];
            }
        }

        $cursor = 0;

        if (preg_match_all('/(\:\b[a-z0-9_-]+\b|\?)/ims', $queryString, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                foreach ($strings as $string) {
                    if ($match[1] >= $string[0] && $match[1] <= $string[1]) {
                        continue(2);
                    }
                }

                $bindings[] = $match[0];
                $mysqliQuery .= mb_substr($queryString, $cursor, $match[1] - $cursor) . '?';
                $cursor = $match[1] + mb_strlen($match[0]);
            }

            if ($cursor < mb_strlen($queryString)) {
                $mysqliQuery .= mb_substr($queryString, $cursor);
            }
        } else {
            $mysqliQuery = $queryString;
        }

        $this->queryMysqli = $mysqliQuery;
        $this->queryBindings = $bindings;
    }

    /**
     * Map PDO type to PHP type and cast the value
     *
     * @param $value
     * @param $type
     * @return bool|int|null|string
     */
    protected function castPDOToPHPType($value, $type)
    {
        switch ($type) {
            case \PDO::PARAM_NULL:
                return null;
            case \PDO::PARAM_INT:
                return intval($value);
            case \PDO::PARAM_BOOL:
                return !(in_array(strtolower($value), array('false', 'f')) || empty($value));
            default:
                return (string)$value;
        }
    }

    /**
     * Fetch row with any type of mode
     *
     * @param null $fetch_style
     * @param null $fetch_argument
     * @param array $ctor_args
     * @return bool|mixed|null|object|\stdClass
     */
    protected function fetchRow($fetch_style = null, $fetch_argument = null, array $ctor_args = array())
    {
        if ($fetch_style === null) {
            if (($fetch_style = $this->defaultFetchMode) !== null) {
                $fetch_argument = $fetch_argument !== null ? $fetch_argument : $this->defaultFetchArgument;
                $ctor_args = !empty($ctor_args) ? $ctor_args : $this->defaultFetchConstructorParams;
            } else {
                $fetch_style = \PDO::FETCH_BOTH;
            }
        }

        if ($fetch_style == \PDO::FETCH_COLUMN) {
            $result = $this->result->fetch_array(MYSQLI_NUM);

            if (!$result) {
                return $result;
            }

            $column = intval($fetch_argument !== null ? $fetch_argument : $this->defaultFetchArgument);

            return isset($result[$column]) ? $result[$column] : null;
        } elseif ($fetch_style == \PDO::FETCH_FUNC) {
            $result = $this->result->fetch_array(\PDO::FETCH_BOTH);

            if (!$result) {
                return $result;
            }

            return call_user_func($fetch_argument, $result);
        } elseif (in_array($fetch_style, array(\PDO::FETCH_CLASS, \PDO::FETCH_OBJ))) {
            $fetch_argument = $fetch_argument && $fetch_style == \PDO::FETCH_CLASS ? $fetch_argument : 'stdClass';

            return $ctor_args && $fetch_style == \PDO::FETCH_CLASS
                ? $this->result->fetch_object($fetch_argument, $ctor_args)
                : $this->result->fetch_object($fetch_argument);
        } elseif ($fetch_style == \PDO::FETCH_INTO) {
            $result = $this->result->fetch_array(MYSQLI_ASSOC);

            if (!$result) {
                return $result;
            }

            $this->hydrateObject(
                $fetch_argument,
                $result
            );

            return true;
        } elseif ($fetch_style == \PDO::FETCH_BOUND) {
            $result = $this->result->fetch_array(MYSQLI_BOTH);

            if (!$result) {
                return $result;
            }

            foreach ($this->resultBindings as $binding => $params) {
                $value = isset($result[$binding - 1]) ? $result[$binding - 1] : null;
                if ($params[2]) {
                    $value = mb_substr($value, 0, $params[2]);
                }
                if ($params[1]) {
                    $value = $this->castPDOToPHPType($value, $params[1]);
                }
                $params[0] = $value;
            }

            return true;
        }

        $styles = array(\PDO::FETCH_BOTH, \PDO::FETCH_ASSOC, \PDO::FETCH_NUM);
        $fetch_style = in_array($fetch_style, $styles) ? $fetch_style : reset($styles);

        return $this->result->fetch_array($this->mapFetchStyle($fetch_style));
    }

    /**
     * Map PDO fetch style to mysqli style
     *
     * @param $mode
     * @return mixed
     */
    protected function mapFetchStyle($mode)
    {
        $map = array(
            \PDO::FETCH_BOTH => MYSQLI_BOTH,
            \PDO::FETCH_ASSOC => MYSQLI_ASSOC,
            \PDO::FETCH_NUM => MYSQLI_NUM,
        );

        return isset($map[$mode]) ? $map[$mode] : null;
    }

    /**
     * Map PDO type to mysqli type
     *
     * @param $type
     * @return string
     */
    protected function mapPDOToMysqliType($type)
    {
        switch ($type) {
            case \PDO::PARAM_INT:
                return 'i';
            default:
                return 's';
        }
    }

    /**
     * Hydrate object with received data
     *
     * @param $object
     * @param $data
     * @return mixed
     */
    protected function hydrateObject($object, $data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (property_exists($object, $key)) {
                    $object->{$key} = $value;
                }
            }
        }

        return $object;
    }

    /**
     * Map mysqli column meta to PDO column meta format
     *
     * @param $meta
     * @return array
     */
    protected function mapColumnMeta($meta)
    {
        $map = array(
            'orgname' => 'name',
            'type' => 'driver:decl_type',
            'table' => 'table',
            'length' => 'len',
            'decimals' => 'precision',
            'flags' => 'flags',
        );

        $result = array();

        foreach ($map as $mysqliName => $pdoName) {
            $result[$pdoName] = isset($meta[$mysqliName]) ? $meta[$mysqliName] : null;
        }

        return $result;
    }

}