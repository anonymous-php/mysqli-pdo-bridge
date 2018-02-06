<?php

namespace Anonymous\MysqliPdoBridge;


/**
 * Class MysqliPDOErrorHandler
 * @author Anonymous PHP Developer
 * @package Anonymous\MysqliPdoBridge
 */
class MysqliPDOErrorHandler
{

    /** @var MysqliPDO $bridge */
    protected $bridge;

    /** @var \mysqli_stmt */
    protected $statement;


    /**
     * MysqliPDOErrorHandler constructor.
     * @param MysqliPDO $bridge
     * @param \mysqli_stmt|null $statement
     */
    public function __construct(MysqliPDO $bridge, \mysqli_stmt $statement = null)
    {
        $this->bridge = $bridge;
        $this->statement = $statement;
    }

    /**
     * Free resources
     */
    public function __destruct()
    {
        $this->bridge = null;
        $this->statement = null;
    }

    /**
     * @param $pdoMode
     * @return mixed
     */
    protected function mapReportMode($pdoMode)
    {
        $map = array(
            \PDO::ERRMODE_SILENT => MYSQLI_REPORT_OFF,
            \PDO::ERRMODE_WARNING => MYSQLI_REPORT_ERROR,
            \PDO::ERRMODE_EXCEPTION => MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT,
        );

        return isset($map[$pdoMode]) ? $map[$pdoMode] : reset($map);
    }

    /**
     * @param callable $callable
     * @param bool $falseOnError
     * @return bool|mixed
     */
    public function __invoke(callable $callable, $falseOnError = true)
    {
        $error = false;

        $currentMysqliReportMode = (new \mysqli_driver())->report_mode;
        mysqli_report($this->mapReportMode($this->bridge->getAttribute(\PDO::ATTR_ERRMODE)));

        try {
            $result = call_user_func($callable);
        } catch (\mysqli_sql_exception $e) {
            $error = true;
            throw new MysqliPDOException($e->getMessage(), $e->getCode());
        } finally {
            mysqli_report($currentMysqliReportMode);
        }

        return $falseOnError && ($error || $this->bridge->getConnection()->errno || $this->statement->errno)
            ? false
            : $result;
    }

}