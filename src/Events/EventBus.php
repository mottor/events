<?php

namespace Mottor\Events;

/**
 * Class EventBus
 * @package Mottor\Events
 */
class EventBus
{
    /**
     * @var string путь к файл с указанием самого файла
     */
    protected $logFileName = 'event.log';

    /** @var array */
    protected $eventListeners = [];

    /**
     * @param string $fileName
     */
    public function setLogFileName($fileName) {
        $this->logFileName = $fileName;
    }

    /**
     * @return string
     */
    public function getLogFileName() {
        return $this->logFileName;
    }

    /**
     * @param string $eventName
     * @return array
     */
    public function getListenersForEvent($eventName) {
        return $this->hasListenersForEvent($eventName) ? $this->eventListeners[$eventName] : [];
    }

    /**
     * @param string $eventName
     * @param mixed $listener
     */
    public function addListener($eventName, $listener) {
        if (!$this->hasListenersForEvent($eventName)) {
            $this->eventListeners[$eventName] = [];
        }

        $this->eventListeners[$eventName][] = $listener;
    }

    /**
     * @param string $eventName
     * @return bool
     */
    public function hasListenersForEvent($eventName) {
        return array_key_exists($eventName, $this->eventListeners) && count($this->eventListeners[$eventName]) > 0;
    }

    /**
     * @param mixed $listener
     * @return array
     */
    public function prepareListener($listener) {
        $preparedListener = [
            'handler' => [],
            'isAsync' => false,
            'log' => false,
        ];

        if (is_array($listener) && array_key_exists('handler', $listener)) {
            $preparedListener['handler'] = $listener['handler'];
            $preparedListener['isAsync'] = array_key_exists('isAsync', $listener) ? $listener['isAsync'] : $preparedListener['isAsync'];
            $preparedListener['log'] = array_key_exists('log', $listener) ? $listener['log'] : $preparedListener['log'];
        } else {
            $preparedListener['handler'] = $listener;
        }

        if (is_string($preparedListener['handler'])
            && class_exists($preparedListener['handler'])
            && is_subclass_of($preparedListener['handler'], EventListenerInterface::class)
        ) {
            $handler = $preparedListener['handler'];
            if ($preparedListener['isAsync']) {
                // Для асинхронных обработчиков будем опрерировать названием классов.
                // Обработчик асинхронных событий будет создавать экземпляр объекта самостоятельно.
                // (здесь ничего не делаем)
            } else {
                // В случае синхронного вызова, необходимо создать объект для работы функции call_user_func.
                // Если в метод call_user_func передать название класса с методом, он будет вызывать метод как статичный.
                // В нашем случае, обработчик должен быть объектом для вызова метода handle.
                $handler = new $handler();
            }

            $preparedListener['handler'] = [$handler, 'handle'];
        }

        return $preparedListener;
    }

    /**
     * @param string $eventName
     * @param array $data
     */
    public function trigger($eventName, array $data = []) {
        $logList = [];
        $listenerList = $this->getListenersForEvent($eventName);

        foreach ($listenerList as $index => $listener) {
            $log = [
                'event_name' => $eventName,
                'data' => $data,
                'listenerIndex' => $index,
            ];

            try {
                $listener = $this->prepareListener($listener);
                $log['isAsync'] = $listener['isAsync'];

                if ($listener['isAsync']) {
                    $log['result'] = $this->executeHandlerAsync($listener['handler'], $data);
                } else {
                    $log['result'] = $this->executeHandlerNow($listener['handler'], $data);
                }

                if ($listener['log']) {
                    $logList[] = $log;
                }
            } catch (\Exception $e) {
                $log['error'] = [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                ];

                $logList[] = $log;
            }
        }

        $this->writeLog($logList);
    }

    /**
     * @param mixed $listener
     * @param array $data
     * @return string
     */
    public function executeHandlerAsync($listener, array $data) {
        // todo
        return "";
    }

    /**
     * @param mixed $handler
     * @param array $data
     * @return bool|string
     * @throws \Exception
     */
    public function executeHandlerNow($handler, array $data = []) {
        if (!is_callable($handler)) {
            throw new \Exception("Handler is not callable", 5730);
        }

        $result = false;

        try {
            ob_start();
            $result = call_user_func($handler, $data);
        } finally {
            ob_end_clean();
        }

        return $result;
    }

    /**
     * @param array $log
     */
    public function writeLog(array $log) {
        try {
            if (0 == count($log)) {
                return;
            }

            $log = json_encode($log);

            file_put_contents($this->logFileName, "{$log}\n\n", FILE_APPEND);
        } catch (\Exception $e) {
            // пустой, так как нет необходимости
        }
    }

    public function clearLog() {
        if (file_exists($this->logFileName)) {
            unlink($this->logFileName);
        }
    }

    /**
     * @param string $eventName
     */
    public function clearListenersForEvent($eventName) {
        $this->eventListeners[$eventName] = [];
    }

    /**
     * @return string
     */
    public function getLogContents() {
        if (!file_exists($this->logFileName)) {
            return "";
        }

        return (string) file_get_contents($this->logFileName);
    }
}
