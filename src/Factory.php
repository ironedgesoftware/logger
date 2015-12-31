<?php
declare(strict_types=1);

/*
 * This file is part of the logger package.
 *
 * (c) Gustavo Falco <comfortablynumb84@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IronEdge\Component\Logger;

/*
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 */
use IronEdge\Component\Logger\Exception\InvalidConfigException;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class Factory
{
    const HANDLER_TYPE_STREAM               = 'stream';
    const HANDLER_TYPE_ERROR_LOG            = 'error_log';
    const HANDLER_TYPE_NULL                 = 'null';

    const FORMATTER_TYPE_LINE               = 'line';


    /**
     * Field availableFormatterTypes.
     *
     * @var array
     */
    public static $availableFormatterTypes  = [
        self::FORMATTER_TYPE_LINE
    ];

    /**
     * Field _loggers.
     *
     * @var array
     */
    private $_loggers = [];

    /**
     * Field _handlers.
     *
     * @var array
     */
    private $_handlers = [];

    /**
     * Field _processors.
     *
     * @var array
     */
    private $_processors = [];

    /**
     * Field _formatters.
     *
     * @var array
     */
    private $_formatters = [];


    /**
     * @param string $id     - Logger ID.
     * @param array  $config - Logger config.
     *
     * @return LoggerInterface
     */
    public function createLogger(string $id, array $config): LoggerInterface
    {
        if (!isset($this->_loggers[$id])) {
            $handlers = [];
            $processors = [];

            if (isset($config['handlers'])) {
                foreach ($config['handlers'] as $handlerId) {
                    $handlers[$handlerId] = $this->getHandler($handlerId);
                }
            }

            if (isset($config['processors'])) {
                foreach ($config['processors'] as $processorId) {
                    $processors[$processorId] = $this->getProcessor($processorId);
                }
            }

            $this->setLogger(
                $id,
                new Logger(
                    $id,
                    $handlers,
                    $processors,
                    isset($config['timezone']) ?
                        $config['timezone'] :
                        null
                )
            );
        }

        return $this->_loggers[$id];
    }

    /**
     * Sets a logger instance.
     *
     * @param string          $id     - Logger ID.
     * @param LoggerInterface $logger - Logger.
     *
     * @return Factory
     */
    public function setLogger(string $id, LoggerInterface $logger): self
    {
        $this->_loggers[$id] = $logger;

        return $this;
    }

    /**
     * Returns a logger instance.
     *
     * @param string $id - Logger ID.
     *
     * @return LoggerInterface
     */
    public function getLogger(string $id)
    {
        if (!isset($this->_loggers[$id])) {
            throw new \RuntimeException('Logger with ID "'.$id.'" is not registered on the logger factory.');
        }

        return $this->_loggers[$id];
    }

    /**
     * Returns an array of the registered loggers.
     *
     * @return array
     */
    public function getLoggers()
    {
        return $this->_loggers;
    }

    /**
     * Creates a handler.
     *
     * @param string $id     - Handler ID.
     * @param string $type   - Handler type.
     * @param int    $level  - Level.
     * @param array  $config - Handler config.
     *
     * @throws InvalidConfigException
     *
     * @return HandlerInterface
     */
    public function createHandler(string $id, string $type, int $level, array $config): HandlerInterface
    {
        if (!isset($this->_handlers[$id])) {
            $config = array_replace(
                [
                    'processorIds'          => []
                ],
                $config
            );

            if (!is_array($config['processorIds'])) {
                throw InvalidConfigException::create('processorIds', 'array');
            }

            /** @var HandlerInterface $handler */
            $handler = null;

            switch ($type) {
                case self::HANDLER_TYPE_STREAM:
                    $config = array_replace(
                        [
                            'bubble'            => true,
                            'filePermission'    => null,
                            'useLocking'        => false
                        ],
                        $config
                    );

                    if (!isset($config['stream']) || !is_string($config['stream'])) {
                        throw InvalidConfigException::create('stream', 'string');
                    }

                    $handler = new StreamHandler(
                        $config['stream'],
                        $level,
                        $config['bubble'],
                        $config['filePermission'],
                        $config['useLocking']
                    );

                    $this->setHandler($id, $handler);

                    break;
                case self::HANDLER_TYPE_ERROR_LOG:
                    $config = array_replace(
                        [
                            'messageType'       => ErrorLogHandler::OPERATING_SYSTEM,
                            'bubble'            => true,
                            'expandNewLines'    => false
                        ],
                        $config
                    );
                    $handler = new ErrorLogHandler(
                        $config['messageType'],
                        $level,
                        $config['bubble'],
                        $config['expandNewLines']
                    );

                    $this->setHandler($id, $handler);

                    break;
                case self::HANDLER_TYPE_NULL:
                    $handler = new NullHandler($level);

                    $this->setHandler($id, $handler);

                    break;
                default:
                    throw new \InvalidArgumentException(
                        'Type "'.$type.'" is not handled yet by this component. You can set manually the handler '.
                        'instance using the "setHandler" method.'
                    );
            }

            if ($config['processorIds']) {
                foreach ($config['processorIds'] as $processorId) {
                    if (!isset($this->_processors[$processorId])) {
                        throw InvalidConfigException::create(
                            'processorIds',
                            'array',
                            'Additionally, processor ID "'.$processorId.'" does not exist.'
                        );
                    }

                    $handler->pushProcessor($this->_processors[$processorId]);
                }
            }

            $this->_handlers[$id] = $handler;
        }

        return $this->_handlers[$id];
    }

    /**
     * Sets a handler instance.
     *
     * @param string           $handlerId - Handler ID.
     * @param HandlerInterface $handler   - Handler.
     *
     * @return Factory
     */
    public function setHandler(string $handlerId, HandlerInterface $handler): self
    {
        $this->_handlers[$handlerId] = $handler;

        return $this;
    }

    /**
     * Returns a handler instance.
     *
     * @param string $id - Handler ID.
     *
     * @return HandlerInterface
     */
    public function getHandler(string $id)
    {
        if (!isset($this->_handlers[$id])) {
            throw new \RuntimeException('Handler with ID "'.$id.'" is not registered on the logger factory.');
        }

        return $this->_handlers[$id];
    }

    /**
     * Returns an array of registered handlers.
     *
     * @return array
     */
    public function getHandlers()
    {
        return $this->_handlers;
    }

    /**
     * Creates a processor.
     *
     * @param string $id     - Processor ID
     * @param string $type   - Processor type.
     * @param array  $config - Processor config.
     *
     * @return callable
     */
    public function createProcessor(string $id, string $type, array $config): callable
    {
        // @TODO
    }

    /**
     * Sets a processor instance.
     *
     * @param string           $processorId - Processor ID.
     * @param callable         $processor   - Processor.
     *
     * @return Factory
     */
    public function setProcessor(string $processorId, callable $processor): self
    {
        $this->_processors[$processorId] = $processor;

        return $this;
    }

    /**
     * Returns a processor instance.
     *
     * @param string $id - Processor ID.
     *
     * @return callable
     */
    public function getProcessor(string $id)
    {
        if (!isset($this->_processors[$id])) {
            throw new \RuntimeException('Processor with ID "'.$id.'" is not registered on the logger factory.');
        }

        return $this->_processors[$id];
    }

    /**
     * Returns an array of registered processors.
     *
     * @return array
     */
    public function getProcessors()
    {
        return $this->_processors;
    }

    /**
     * Creates a Formatter.
     *
     * @param string $id     - Formatter ID
     * @param string $type   - Formatter type.
     * @param array  $config - Formatter config.
     *
     * @throws InvalidConfigException
     *
     * @return FormatterInterface
     */
    public function createFormatter(string $id, string $type, array $config): FormatterInterface
    {
        if (!isset($this->_formatters[$id])) {
            switch ($type) {
                case self::FORMATTER_TYPE_LINE:
                    $config = array_replace(
                        [
                            'format'                        => null,
                            'dateFormat'                    => null,
                            'allowInlineLineBreaks'         => false,
                            'ignoreEmptyContextAndExtra'    => false
                        ],
                        $config
                    );

                    $this->_formatters[$id] = new LineFormatter(
                        $config['format'],
                        $config['dateFormat'],
                        $config['allowInlineLineBreaks'],
                        $config['ignoreEmptyContextAndExtra']
                    );

                    break;
                default:
                    throw InvalidConfigException::create(
                        'type',
                        'string',
                        'Allowed types: '.implode(', ', self::$availableFormatterTypes)
                    );
            }
        }

        return $this->_formatters[$id];
    }

    /**
     * Sets a Formatter instance.
     *
     * @param string   $formatterId - Formatter ID.
     * @param callable $formatter   - Formatter.
     *
     * @return Factory
     */
    public function setFormatter(string $formatterId, FormatterInterface $formatter): self
    {
        $this->_formatters[$formatterId] = $formatter;

        return $this;
    }

    /**
     * Returns a formatter instance.
     *
     * @param string $id - Formatter ID.
     *
     * @return FormatterInterface
     */
    public function getFormatter(string $id)
    {
        if (!isset($this->_formatters[$id])) {
            throw new \RuntimeException('Formatter with ID "'.$id.'" is not registered on the logger factory.');
        }

        return $this->_formatters[$id];
    }

    /**
     * Returns an array of registered formatters.
     *
     * @return array
     */
    public function getFormatters()
    {
        return $this->_formatters;
    }
}