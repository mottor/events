<?php

namespace Mottor\Events;

/**
 * Interface EventListenerInterface
 * @package Mottor\Events
 */
interface EventListenerInterface
{
    /**
     * конструктор в таком виде, требует от класса отсутствие входных параметров
     * так как объект будет создаваться в модуле Events
     * и Events не будет знать о входных параметрах
     *
     * EventListenerInterface constructor.
     */
    public function __construct();

    /**
     * @param array $data
     */
    public function handle(array $data);
}