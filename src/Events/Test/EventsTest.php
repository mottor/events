<?php

namespace Mottor\Events\Test;

use Mottor\Events\Events;
use PHPUnit\Framework\TestCase;

/**
 * Class EventsTest
 * @package engine\tests
 */
class EventsTest extends TestCase
{
    /** @var Events */
    protected $events;

    /** @var array */
    protected $testListeners;

    public function __construct($name = null, array $data = [], $dataName = '') {
        parent::__construct($name, $data, $dataName);
        $this->events = new Events();
        $this->testListeners = [
            'class' => TestEventListener::class,
            'classHandlerAsyncInArray' => [
                'handler' => TestEventListener::class,
                'isAsync' => true,
                'log' => true,
            ],
            'classHandlerInArray' => [
                'handler' => TestEventListener::class,
                'isAsync' => false,
                'log' => true,
            ],
            'function' => function ($data) {
                $data = json_encode($data);
                return "test function data:{$data}";
            },
            'functionException' => function ($data) {
                $data = json_encode($data);
                throw new \Exception("Test Exception data:{$data}", 5737);
            },
            'functionAsyncException' => [
                'handler' => function ($data) {
                    $data = json_encode($data);
                    throw new \Exception("Test Exception data:{$data}", 5738);
                },
                'isAsync' => true,
                'log' => true,
            ],
        ];
    }

    public function testSetLogFileName() {
        $logFileName = "test.log";
        $this->events->setLogFileName($logFileName);
        $this->assertEquals($logFileName, $this->events->getLogFileName());

        $logFileName = "folder" . DS . "tmp" . DS . "test.log";
        $this->events->setLogFileName($logFileName);
        $this->assertEquals($logFileName, $this->events->getLogFileName());
    }

    public function testGetLogFileName() {
        $logFileName = "test.log";
        $this->events->setLogFileName($logFileName);
        $this->assertEquals($logFileName, $this->events->getLogFileName());

        $logFileName = "folder" . DS . "tmp" . DS . "test.log";
        $this->events->setLogFileName($logFileName);
        $this->assertEquals($logFileName, $this->events->getLogFileName());
    }

    public function testGetListenersForEvent() {
        $testEventName1 = "testEvent_1";
        $testEventName2 = "testEvent_2";

        $result = $this->events->getListenersForEvent($testEventName1);
        $this->assertEquals([], $result);

        $this->events->addListener($testEventName1, $this->testListeners['class']);
        $result = $this->events->getListenersForEvent($testEventName1);
        $this->assertEquals(1, count($result));

        $this->events->addListener($testEventName1, $this->testListeners['function']);
        $result = $this->events->getListenersForEvent($testEventName1);
        $this->assertEquals(2, count($result));

        $this->events->addListener($testEventName2, $this->testListeners['classHandlerInArray']);
        $result = $this->events->getListenersForEvent($testEventName2);
        $this->assertEquals(1, count($result));
    }

    public function testAddListener() {
        $testEventName = "testEvent";

        $result = $this->events->getListenersForEvent($testEventName);
        $this->assertEquals([], $result);

        $this->events->addListener($testEventName, $this->testListeners['function']);

        $result = $this->events->getListenersForEvent($testEventName);
        $this->assertEquals(1, count($result));

        $this->events->addListener($testEventName, $this->testListeners['class']);
        $result = $this->events->getListenersForEvent($testEventName);
        $this->assertEquals(2, count($result));
    }

    public function testHasListenersForEvent() {
        $testEventName1 = "testEvent1";
        $testEventName2 = "testEvent2";
        $result = $this->events->hasListenersForEvent($testEventName1);
        $this->assertFalse($result);

        $this->events->addListener($testEventName1, $this->testListeners['function']);
        $result = $this->events->hasListenersForEvent($testEventName1);
        $this->assertTrue($result);

        $this->events->clearListenersForEvent($testEventName2);

        $result = $this->events->hasListenersForEvent($testEventName2);
        $this->assertFalse($result);
    }

    public function testPrepareListener() {
        $fCheckFormat = function ($listener) {
            if (!is_array($listener)) {
                return false;
            }

            if (!array_key_exists('handler', $listener)) {
                return false;
            }

            if (!array_key_exists('isAsync', $listener)) {
                return false;
            }

            if (!array_key_exists('log', $listener)) {
                return false;
            }

            return true;
        };

        $result = $this->events->prepareListener($this->testListeners['class']);
        $this->assertTrue($fCheckFormat($result));
        $handlerObject = $result['handler'][0];
        $this->assertTrue(is_object($handlerObject));
        $this->assertTrue($handlerObject instanceof TestEventListener);

        $result = $this->events->prepareListener($this->testListeners['classHandlerAsyncInArray']);
        $this->assertTrue($fCheckFormat($result));
        $this->assertTrue(is_array(['handler']));
        $handlerObject = $result['handler'][0];
        $this->assertTrue(is_string($handlerObject));
        $this->assertTrue(class_exists($handlerObject));
        $this->assertTrue($result['isAsync']);
        $this->assertTrue($result['log']);

        $result = $this->events->prepareListener($this->testListeners['function']);
        $this->assertTrue($fCheckFormat($result));

        $result = $this->events->prepareListener($this->testListeners['classHandlerInArray']);
        $this->assertTrue($fCheckFormat($result));
    }

    public function testTrigger() {
        $testEventName1 = "event_1";
        $data1 = [
            'testEventName1',
            123,
            false,
        ];
        $testEventName2 = "event_2";
        $data2 = [
            'testEventName2',
            321,
            true,
        ];

        $this->events->addListener($testEventName1, $this->testListeners['function']);
        try {
            $this->events->trigger($testEventName1, $data1);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->assertTrue(false);
        }
        $this->events->addListener($testEventName1, $this->testListeners['functionAsyncException']);

        try {
            $this->events->trigger($testEventName1, $data1);
            $this->assertTrue(true);
            $this->assertTrue(file_exists($this->events->getLogFileName()));
            $fileContents = $this->events->getLogContents();
            $this->assertTrue(false !== strpos($fileContents, 'testEventName1'));
        } catch (\Exception $e) {
            $this->assertTrue(false);
        }

        $this->events->clearLog();

        $this->events->addListener($testEventName2, $this->testListeners['function']);
        $this->events->addListener($testEventName2, $this->testListeners['functionException']);

        try {
            $this->events->trigger($testEventName1, $data1);
            $this->assertTrue(file_exists($this->events->getLogFileName()));
            $fileContents = $this->events->getLogContents();
            $this->assertFalse(false !== strpos($fileContents, 'Test Exception data'));
        } catch (\Exception $e) {
            $this->assertTrue(false);
        }

        $this->events->clearLog();

        try {
            $this->events->trigger($testEventName2, $data2);
            $this->assertTrue(file_exists($this->events->getLogFileName()));
            $fileContents = $this->events->getLogContents();
            $this->assertTrue(false !== strpos($fileContents, 'Test Exception data'));
        } catch (\Exception $e) {
            $this->assertTrue(false);
        }
    }

    public function testTriggerAsync() {
        // todo
    }

    public function testExecuteHandler() {
        try {
            $handler = "testest testest testest";
            $this->events->executeHandlerNow($handler);
            $this->assertTrue(false);
        } catch (\Exception $e) {
            $this->assertEquals(5730, $e->getCode());
        }

        $testEventListener = new TestEventListener();
        $data = [
            'text',
            123321,
        ];
        $listener = $this->events->prepareListener($this->testListeners['class']);
        $result = $this->events->executeHandlerNow($listener['handler'], $data);
        $this->assertEquals($testEventListener->handle($data), $result);

        $listener = $this->events->prepareListener($this->testListeners['function']);
        $result = $this->events->executeHandlerNow($listener['handler'], $data);
        $this->assertEquals($listener['handler']($data), $result);

        try {
            $listener = $this->events->prepareListener($this->testListeners['classHandlerAsyncInArray']);
            $this->events->executeHandlerNow($listener['handler'], $data);
            $this->assertTrue(false);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function testWriteLog() {
        $emptyArray = [];
        try {
            $this->events->writeLog($emptyArray);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->assertTrue(false);
        }

        $data = [
            'test_boolean' => true,
            'test_string' => "hi lol test Милка попик",
            'test_integer' => "123321",
        ];
        $this->events->writeLog($data);
        $this->assertTrue(file_exists($this->events->getLogFileName()));
        $fileContents = $this->events->getLogContents();
        $this->assertTrue(false !== strpos($fileContents, json_encode($data)));
    }

    public function testClearLog() {
        $this->assertFalse(file_exists($this->events->getLogFileName()));
        $data = [
            'test_boolean' => true,
            'test_string' => "hi lol test Милка попик",
            'test_integer' => "123321",
        ];
        $this->events->writeLog($data);
        $this->assertTrue(file_exists($this->events->getLogFileName()));

        $this->events->clearLog();
        $this->assertFalse(file_exists($this->events->getLogFileName()));
    }

    public function testClearListenersForEvent() {
        $testEventName = "test";
        $this->events->addListener($testEventName, $this->testListeners['classHandlerAsyncInArray']);
        $this->events->addListener($testEventName, $this->testListeners['class']);
        $this->events->addListener($testEventName, $this->testListeners['function']);

        $this->assertTrue($this->events->hasListenersForEvent($testEventName));
        $this->events->clearListenersForEvent($testEventName);
        $this->assertFalse($this->events->hasListenersForEvent($testEventName));
    }

    public function testGetLogContents() {
        $this->assertEquals("", $this->events->getLogContents());
        $this->events->writeLog(['test']);
        $this->assertNotEquals("", $this->events->getLogContents());
        $contents = $this->events->getLogContents();
        $this->assertTrue(false !== strpos($contents, 'test'));
    }

    public function testRunAllHandlers() {
        $testEventName = "testEvent";

        foreach ($this->testListeners as $listener) {
            $this->events->addListener($testEventName, $listener);
        }

        $this->events->trigger($testEventName);
        var_dump(json_decode($this->events->getLogContents()));
    }

    public function tearDown() {
        $this->events->clearLog();
    }
}