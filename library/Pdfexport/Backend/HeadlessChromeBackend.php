<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Backend;

use Exception;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ServerException;
use Icinga\Application\Icinga;
use Icinga\Application\Logger;
use Icinga\Application\Platform;
use Icinga\File\Storage\StorageInterface;
use Icinga\File\Storage\TemporaryLocalFileStorage;
use Icinga\Module\Pdfexport\PrintableHtmlDocument;
use Icinga\Module\Pdfexport\ShellCommand;
use LogicException;
use Throwable;
use WebSocket\Client;

class HeadlessChromeBackend implements PfdPrintBackend
{
    /** @var int */
    public const MIN_SUPPORTED_CHROME_VERSION = 59;

    /**
     * Line of stderr output identifying the websocket url
     *
     * The first matching group is the used port, and the second one the browser id.
     */
    public const DEBUG_ADDR_PATTERN = '/DevTools listening on ws:\/\/((?>\d+\.?){4}:\d+)\/devtools\/browser\/([\w-]+)/';

    /** @var string */
    public const WAIT_FOR_NETWORK = 'wait-for-network';

    protected ?StorageInterface $fileStorage = null;

    protected bool $useFilesystemTransfer = false;

    protected ?Client $browser = null;

    protected ?Client $page = null;

    protected ?string $frameId;

    private array $interceptedRequests = [];

    private array $interceptedEvents = [];

    protected ?ShellCommand $process = null;

    protected ?string $socket = null;

    protected ?string $browserId = null;

    public function __destruct()
    {
        $this->close();
    }

    public static function createRemote(string $host, int $port): static
    {
        $instance = new self();
        $instance->socket = "$host:$port";
        try {
            $result = $instance->getJsonVersion();

            if (! is_array($result)) {
                throw new Exception('Failed to determine remote chrome version via the /json/version endpoint.');
            }

            $parts = explode('/', $result['webSocketDebuggerUrl']);
            $instance->browserId = end($parts);
        } catch (Exception $e) {
            Logger::warning(
                'Failed to connect to remote chrome: %s (%s)',
                $instance->socket,
                $e,
            );

            throw $e;
        }

        return $instance;
    }

    public static function createLocal(string $path, bool $useFile = false): static
    {
        $instance = new self();
        $instance->useFilesystemTransfer = $useFile;

        if (! file_exists($path)) {
            throw new Exception('Local chrome binary not found: ' . $path);
        }

        $browserHome = $instance->getFileStorage()->resolvePath('HOME');

        $commandLine = join(' ', [
            escapeshellarg($path),
            static::renderArgumentList([
                '--bwsi',
                '--headless',
                '--disable-gpu',
                '--no-sandbox',
                '--no-first-run',
                '--disable-dev-shm-usage',
                '--remote-debugging-port=0',
                '--homedir='       => $browserHome,
                '--user-data-dir=' => $browserHome,
            ]),
        ]);

        $env = null;
        if (Platform::isLinux()) {
            Logger::debug('Starting browser process: HOME=%s exec %s', $browserHome, $commandLine);
            $env = array_merge($_ENV, ['HOME' => $browserHome]);
            $commandLine = 'exec ' . $commandLine;
        } else {
            Logger::debug('Starting browser process: %s', $commandLine);
        }

        $instance->process = new ShellCommand($commandLine, false, $env);
        $instance->process->start();
        Logger::debug('Started browser process');
        $instance->process->wait(function ($stdout, $stderr) use ($instance) {
            if ($stdout !== '') {
                Logger::debug('Caught browser stdout: %d', mb_strlen($stdout));
            }
            if ($stderr !== '') {
                Logger::error('Browser process stderr: %d', mb_strlen($stderr));
                if (preg_match(self::DEBUG_ADDR_PATTERN, trim($stderr), $matches)) {
                    $instance->socket = $matches[1];
                    $instance->browserId = $matches[2];

                    Logger::debug('Caught browser info socket: %s, id: %s', $instance->socket, $instance->browserId);
                    return false;
                }
            }
            return true;
        });

        if ($instance->socket === null || $instance->browserId === null) {
            throw new Exception('Could not start browser process.');
        }

        return $instance;
    }

    protected function closeLocal(): void
    {
        Logger::debug('Closing local chrome instance');

        if ($this->process !== null) {
            $code = $this->process->stop();
            Logger::error("Closed local chrome with exit code %d", $code);
            $this->process = null;
        }

        try {
            if ($this->fileStorage !== null) {
                unset($this->fileStorage);
                $this->fileStorage = null;
            }
        } catch (Exception $exception) {
            Logger::error("Failed to close local temporary file storage: " . $exception->getMessage());
        }
    }

    /**
     * Get the file storage
     */
    public function getFileStorage(): StorageInterface
    {
        if ($this->fileStorage === null) {
            $this->fileStorage = new TemporaryLocalFileStorage();
        }

        return $this->fileStorage;
    }

    /**
     * Render the given argument name-value pairs as shell-escaped string
     */
    public static function renderArgumentList(array $arguments): string
    {
        $list = [];

        foreach ($arguments as $name => $value) {
            if ($value !== null) {
                $value = escapeshellarg($value);

                if (! is_int($name)) {
                    if (str_ends_with($name, '=')) {
                        $glue = '';
                    } else {
                        $glue = ' ';
                    }

                    $list[] = escapeshellarg($name) . $glue . $value;
                } else {
                    $list[] = $value;
                }
            } else {
                $list[] = escapeshellarg($name);
            }
        }

        return implode(' ', $list);
    }

    protected function getPrintParameters(PrintableHtmlDocument $document): array
    {
        $parameters = [
            'printBackground' => true,
            'transferMode'    => 'ReturnAsBase64',
        ];

        return array_merge(
            $parameters,
            $document->getPrintParameters(),
        );
    }

    public function toPdf(PrintableHtmlDocument $document): string
    {
        $this->setContent($document);
        $printParameters = $this->getPrintParameters($document);
        return $this->printToPdf($printParameters);
    }

    protected function getBrowser(): Client
    {
        if ($this->browser === null) {
            $this->browser = new Client(sprintf('ws://%s/devtools/browser/%s', $this->socket, $this->browserId));
        }
        return $this->browser;
    }

    protected function closeBrowser(): void
    {
        if ($this->browser === null) {
            return;
        }

        $this->closePage();

        try {
            $this->browser->close();
            $this->browser = null;
        } catch (Throwable $e) {
            // For some reason, the browser doesn't send a response
            Logger::debug('Failed to close browser connection: ' . $e->getMessage());
        }
    }

    public function getPage(): Client
    {
        if ($this->page === null) {
            $browser = $this->getBrowser();

            // Open new tab, get its id
            $result = $this->communicate($browser, 'Target.createTarget', [
                'url' => 'about:blank',
            ]);
            if (isset($result['targetId'])) {
                $this->frameId = $result['targetId'];
            } else {
                throw new Exception('Expected target id. Got instead: ' . json_encode($result));
            }

            $this->page = new Client(sprintf('ws://%s/devtools/page/%s', $this->socket, $this->frameId));

            // enable various events
            $this->communicate($this->page, 'Log.enable');
            $this->communicate($this->page, 'Network.enable');
            $this->communicate($this->page, 'Page.enable');

            try {
                $this->communicate($this->page, 'Console.enable');
            } catch (Exception) {
                // Deprecated, might fail
            }
        }
        return $this->page;
    }

    public function closePage(): void
    {
        if ($this->browser === null || $this->page === null) {
            return;
        }

        // close tab
        $result = $this->communicate($this->browser, 'Target.closeTarget', [
            'targetId' => $this->frameId,
        ]);

        if (! isset($result['success'])) {
            throw new Exception('Expected close confirmation. Got instead: ' . json_encode($result));
        }

        $this->page = null;
        $this->frameId = null;
    }

    protected function setContent(PrintableHtmlDocument $document): void
    {
        $page = $this->getPage();

        if ($document->isEmpty()) {
            throw new LogicException('Nothing to print');
        }

        if ($this->useFilesystemTransfer) {
            $path = uniqid('icingaweb2-pdfexport-') . '.html';
            $storage = $this->getFileStorage();

            $storage->create($path, $document->render());

            $absPath = $storage->resolvePath($path, true);

            Logger::debug('Using filesystem transfer to local chrome instance. Path: ' . $absPath);

            $url = "file://$absPath";

            // Navigate to target
            $result = $this->communicate($page, 'Page.navigate', [
                'url' => $url,
            ]);

            if (isset($result['frameId'])) {
                $this->frameId = $result['frameId'];
            } else {
                throw new Exception('Expected navigation frame. Got instead: ' . json_encode($result));
            }

            // wait for the page to fully load
            $this->waitFor(
                $page,
                'Page.frameStoppedLoading',
                [
                    'frameId' => $this->frameId,
                ],
            );

            try {
                $storage->delete($path);
            } catch (Exception $e) {
                Logger::warning('Failed to delete file: ' . $e->getMessage());
            }
        } else {
            $this->communicate($page, 'Page.setDocumentContent', [
                'frameId' => $this->frameId,
                'html'    => $document->render(),
            ]);
        }

        // wait for the page to fully load
        $this->waitFor($page, 'Page.loadEventFired');

        // Wait for network activity to finish
        $this->waitFor($page, self::WAIT_FOR_NETWORK);

        // Wait for the layout to initialize
        if (! $document->isEmpty()) {
            // Ensure layout scripts work in the same environment as the pdf printing itself
            $this->communicate($page, 'Emulation.setEmulatedMedia', ['media' => 'print']);

            $this->communicate($page, 'Runtime.evaluate', [
                'timeout'    => 1000,
                'expression' => 'setTimeout(() => new Layout().apply(), 0)',
            ]);

            $module = Icinga::app()->getModuleManager()->getModule('pdfexport');
            if (! method_exists($module, 'getJsDir')) {
                $jsPath = join(DIRECTORY_SEPARATOR, [$module->getBaseDir(), 'public', 'js']);
            } else {
                $jsPath = $module->getJsDir();
            }

            $waitForLayout = file_get_contents($jsPath . '/wait-for-layout.js');

            $promisedResult = $this->communicate($page, 'Runtime.evaluate', [
                'awaitPromise'  => true,
                'returnByValue' => true,
                'timeout'       => 1000, // Failsafe: doesn't apply to `await` it seems
                'expression'    => $waitForLayout,
            ]);
            if (isset($promisedResult['exceptionDetails'])) {
                if (isset($promisedResult['exceptionDetails']['exception']['description'])) {
                    Logger::error(
                        'PDF layout failed to initialize: %s',
                        $promisedResult['exceptionDetails']['exception']['description'],
                    );
                } else {
                    Logger::warning('PDF layout failed to initialize. Pages might look skewed.');
                }
            }

            // Reset media emulation, this may prevent the real media from coming into effect?
            $this->communicate($page, 'Emulation.setEmulatedMedia', ['media' => '']);
        }
    }

    protected function printToPdf(array $printParameters): string
    {
        $page = $this->getPage();

        // print pdf
        $result = $this->communicate($page, 'Page.printToPDF', array_merge(
            $printParameters,
            ['transferMode' => 'ReturnAsBase64', 'printBackground' => true],
        ));
        if (! empty($result['data'])) {
            $pdf = base64_decode($result['data']);
        } else {
            throw new Exception('Expected base64 data. Got instead: ' . json_encode($result));
        }

        return $pdf;
    }

    private function renderApiCall($method, $options = null): string
    {
        $data = [
            'id'     => time(),
            'method' => $method,
            'params' => $options ?: [],
        ];

        return json_encode($data, JSON_FORCE_OBJECT);
    }

    private function parseApiResponse(string $payload)
    {
        $data = json_decode($payload, true);
        if (isset($data['method']) || isset($data['result'])) {
            return $data;
        } elseif (isset($data['error'])) {
            throw new Exception(sprintf(
                'Error response (%s): %s',
                $data['error']['code'],
                $data['error']['message'],
            ));
        } else {
            throw new Exception(sprintf('Unknown response received: %s', $payload));
        }
    }

    private function registerEvent($method, $params): void
    {
        if (Logger::getInstance()->getLevel() === Logger::DEBUG) {
            $shortenValues = function ($params) use (&$shortenValues) {
                foreach ($params as &$value) {
                    if (is_array($value)) {
                        $value = $shortenValues($value);
                    } elseif (is_string($value)) {
                        $shortened = substr($value, 0, 256);
                        if ($shortened !== $value) {
                            $value = $shortened . '...';
                        }
                    }
                }

                return $params;
            };
            $shortenedParams = $shortenValues($params);

            Logger::debug(
                'Received CDP event: %s(%s)',
                $method,
                join(',', array_map(function ($param) use ($shortenedParams) {
                    return $param . '=' . json_encode($shortenedParams[$param]);
                }, array_keys($shortenedParams))),
            );
        }

        if ($method === 'Network.requestWillBeSent') {
            $this->interceptedRequests[$params['requestId']] = $params;
        } elseif ($method === 'Network.loadingFinished') {
            unset($this->interceptedRequests[$params['requestId']]);
        } elseif ($method === 'Network.loadingFailed') {
            $requestData = $this->interceptedRequests[$params['requestId']];
            unset($this->interceptedRequests[$params['requestId']]);

            Logger::error(
                'Headless Chrome was unable to complete a request to "%s". Error: %s',
                $requestData['request']['url'],
                $params['errorText'],
            );
        } else {
            $this->interceptedEvents[] = ['method' => $method, 'params' => $params];
        }
    }

    private function communicate(Client $ws, $method, $params = null)
    {
        Logger::debug('Transmitting CDP call: %s(%s)', $method, $params ? join(',', array_keys($params)) : '');
        $ws->text($this->renderApiCall($method, $params));

        do {
            $response = $this->parseApiResponse($ws->receive()->getContent());
            $gotEvent = isset($response['method']);

            if ($gotEvent) {
                $this->registerEvent($response['method'], $response['params']);
            }
        } while ($gotEvent);

        Logger::debug('Received CDP result: %s', empty($response['result'])
            ? 'none'
            : join(',', array_keys($response['result'])));

        return $response['result'];
    }

    private function waitFor(Client $ws, $eventName, ?array $expectedParams = null)
    {
        if ($eventName !== self::WAIT_FOR_NETWORK) {
            Logger::debug(
                'Awaiting CDP event: %s(%s)',
                $eventName,
                $expectedParams ? join(',', array_keys($expectedParams)) : '',
            );
        } elseif (empty($this->interceptedRequests)) {
            return null;
        }

        $wait = true;
        $interceptedPos = -1;

        $params = null;
        do {
            if (isset($this->interceptedEvents[++$interceptedPos])) {
                $response = $this->interceptedEvents[$interceptedPos];
                $intercepted = true;
            } else {
                $response = $this->parseApiResponse($ws->receive()->getContent());
                $intercepted = false;
            }

            if (isset($response['method'])) {
                $method = $response['method'];
                $params = $response['params'];

                if (! $intercepted) {
                    $this->registerEvent($method, $params);
                }

                if ($eventName === self::WAIT_FOR_NETWORK) {
                    $wait = ! empty($this->interceptedRequests);
                } elseif ($method === $eventName) {
                    if ($expectedParams !== null) {
                        $diff = array_intersect_assoc($params, $expectedParams);
                        $wait = empty($diff);
                    } else {
                        $wait = false;
                    }
                }

                if (! $wait && $intercepted) {
                    unset($this->interceptedEvents[$interceptedPos]);
                }
            }
        } while ($wait);

        return $params;
    }

    /**
     * Fetch result from the /json/version API endpoint
     */
    protected function getJsonVersion(): bool|array
    {
        $client = new HttpClient();

        try {
            $response = $client->request('GET', sprintf('http://%s/json/version', $this->socket));
        } catch (ServerException $e) {
            // Check if we've run into the host header security change, and re-run the request with no host header.
            // ref: https://issues.chromium.org/issues/40090537
            if (str_contains($e->getMessage(), 'Host header is specified and is not an IP address or localhost.')) {
                $response = $client->request(
                    'GET',
                    sprintf('http://%s/json/version', $this->socket),
                    ['headers' => ['Host' => null]],
                );
            } else {
                throw $e;
            }
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        return json_decode($response->getBody(), true);
    }

    public function getVersion(): int
    {
        $version = $this->getJsonVersion();

        if (! isset($version['Browser'])) {
            throw new Exception("Invalid Version Json");
        }

        preg_match('/Chrome\/([0-9]+)/', $version['Browser'], $matches);

        if (! isset($matches[1])) {
            throw new Exception("Malformed Chrome Version String: " . $version['Browser']);
        }

        return (int) $matches[1];
    }

    public function isSupported(): bool
    {
        return $this->getVersion() >= self::MIN_SUPPORTED_CHROME_VERSION;
    }

    public function close(): void
    {
        $this->closeBrowser();
        $this->closeBrowser();
        $this->closeLocal();
    }
}
