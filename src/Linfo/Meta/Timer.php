<?php

namespace Linfo\Meta;

class Timer
{
    protected static $timers = [];
    protected $id = null;
    protected $start = null;

    public static function getResults()
    {
        return self::$timers;
    }

    public static function clear()
    {
        self::$timers = [];
    }

    public function __construct($id)
    {
        $this->id = $id;
        $this->start = microtime(true);
    }

    public function __destruct()
    {
        $duration = microtime(true) - $this->start;
        self::$timers[] = array(
          'id' => $this->id,
          'duration' => $duration,
        );
    }
}
