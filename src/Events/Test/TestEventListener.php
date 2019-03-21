<?php
namespace Mottor\Events\Test;

use Mottor\Events\EventListenerInterface;

/**
 * Class TestEventListener
 * @package Mottor\Events\Test
 */
class TestEventListener implements EventListenerInterface
{
    /**
     * TestEventListener constructor.
     */
    public function __construct() {
    }

    /**
     * @param array $data
     * @return string
     */
    public function handle(array $data = []) {
        $data = json_encode($data);
        return "TestEventListener->handle data:{$data}";
    }
}