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
use Icinga\Exception\IcingaException;
use Icinga\File\Storage\StorageInterface;
use Icinga\File\Storage\TemporaryLocalFileStorage;
use Icinga\Module\Notifications\Api\OpenApiDescriptionElement\OadV1Delete;
use Icinga\Module\Pdfexport\PrintableHtmlDocument;
use Icinga\Module\Pdfexport\ShellCommand;
use LogicException;
use RuntimeException;
use Throwable;
use WebSocket\Client;

class HeadlessChromeBackend implements PfdPrintBackend
{
    /** @var int */
    public const MIN_SUPPORTED_CHROME_VERSION = 59;

    /** @var float Maximum time to wait for the port to appear in the output */
    public const DEVTOOLS_PORT_TIMEOUT_SECONDS = 30.0;

    /** @var string */
    public const WAIT_FOR_NETWORK = 'wait-for-network';

    /** @var int Maximum length of one parameter to log */
    public const MAX_PARAM_LENGTH = 256;

    protected ?StorageInterface $fileStorage = null;

    protected bool $useFilesystemTransfer = false;

    protected ?Client $browser = null;

    protected ?Client $page = null;

    protected ?string $frameId;

    protected ?string $sessionId = null;

    private array $interceptedRequests = [];

    private array $interceptedEvents = [];

    protected ?ShellCommand $process = null;

    protected ?string $socket = null;

    protected ?string $browserId = null;

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Create an instance of the backend by connecting to an already running chrome/chromium instance
     *
     * @param string $host the hostname to connect to
     * @param int $port the chrome devtools protocoll port
     *
     * @return self
     *
     * @throws RuntimeException
     */
    public static function createRemote(string $host, int $port): self
    {
        $instance = new self();
        $instance->socket = "$host:$port";
        try {
            $result = $instance->getJsonVersion();
            if (! is_array($result)) {
                throw new RuntimeException('Failed to determine remote chrome version via the /json/version endpoint.');
            }

            $parts = explode('/', $result['webSocketDebuggerUrl']);
            $instance->browserId = end($parts);
        } catch (RuntimeException $e) {
            Logger::warning(
                "Failed to connect to remote chrome: %s\n%s",
                $instance->socket,
                IcingaException::getConfidentialTraceAsString($e),
            );

            throw $e;
        }

        return $instance;
    }

    /**
     * Spawn a new instance of the Chrome/Chromium browser and hands back a backend instance
     *
     * @param string $path the path to the executeable
     * @param bool $useFile should the transfer of the html document use the filesystem
     *
     * @throws RuntimeException
     */
    public static function createLocal(string $path, bool $useFile = false): self
    {
        $instance = new self();
        $instance->useFilesystemTransfer = $useFile;
        if (! file_exists($path)) {
            throw new RuntimeException(sprintf('Local chrome binary not found: %s', $path));
        }

        $browserHome = $instance->getFileStorage()->resolvePath('HOME');
        $args = [
            '--bwsi',
            '--headless',
            '--disable-gpu',
            '--no-sandbox',
            '--no-first-run',
            '--disable-dev-shm-usage',
            '--remote-debugging-port=' => 0,
            '--homedir='               => $browserHome,
            '--user-data-dir='         => $browserHome,
        ];

        if (Platform::isLinux()) {
            $args['--ozone-platform='] = 'headless';
        }

        $commandLine = join(' ', [
            escapeshellarg($path),
            static::renderArgumentList($args),
        ]);

        $env = null;
        if (Platform::isLinux()) {
            Logger::debug('Starting browser process: HOME=%s exec %s', $browserHome, $commandLine);
            $env = array_merge($_ENV, ['HOME' => $browserHome]);
            // Redirect stderr to /dev/null before exec-ing any wrapper script. Distro wrappers
            // (e.g. Fedora's chromium-browser, Google Chrome) route Chrome's stderr through a cat
            // subprocess writing to PHP's pipe. Keeping that pipe open causes either buffer-fill
            // deadlock (IO thread) or EPIPE when PHP closes the read end (fatal CHECK in renderer).
            // With stderr going to /dev/null the cat subprocess never blocks and never dies.
            $commandLine = 'exec ' . $commandLine . ' 2>/dev/null';
        } else {
            Logger::debug('Starting browser process: %s', $commandLine);
        }

        $instance->process = new ShellCommand($commandLine, false, $env);
        $instance->process->start();
        Logger::debug('Started browser process');

        // Chrome writes the DevTools port to DevToolsActivePort in user-data-dir once the
        // DevTools server is listening. Poll for it instead of reading stderr.
        $portFile = $browserHome . '/DevToolsActivePort';
        $deadline = microtime(true) + static::DEVTOOLS_PORT_TIMEOUT_SECONDS;
        while (microtime(true) < $deadline) {
            usleep(100000);
            if (is_readable($portFile)) {
                $content = file_get_contents($portFile);
                if ($content !== false) {
                    $lines = explode("\n", trim($content));
                    $port = (int) ($lines[0] ?? 0);
                    $browserId = basename(trim($lines[1] ?? ''));
                    if ($port > 0 && $browserId !== '') {
                        $instance->socket = '127.0.0.1:' . $port;
                        $instance->browserId = $browserId;
                        Logger::debug(
                            'Caught browser info from DevToolsActivePort: %s, id: %s',
                            $instance->socket,
                            $instance->browserId
                        );
                        break;
                    }
                }
            }
        }

        if ($instance->socket === null) {
            Logger::error(
                'Chrome did not create DevToolsActivePort within %d seconds',
                static::DEVTOOLS_PORT_TIMEOUT_SECONDS,
            );
            throw new RuntimeException('Could not start browser process.');
        }

        return $instance;
    }

    protected function closeLocal(): void
    {
        Logger::debug('Closing local chrome instance');
        if ($this->process !== null) {
            $code = $this->process->stop();
            Logger::debug("Closed local chrome with exit code %d", $code);
            $this->process = null;
        }

        try {
            $this->fileStorage = null;
        } catch (Exception $e) {
            Logger::error(
                "Failed to close local temporary file storage: %s\n%s",
                $e->getMessage(),
                IcingaException::getConfidentialTraceAsString($e),
            );
        }
    }

    /**
     * Get the file storage
     */
    public function getFileStorage(): StorageInterface
    {
        return $this->fileStorage ??= new TemporaryLocalFileStorage();
    }

    /**
     * Render the given argument name-value pairs as shell-escaped string
     */
    protected static function renderArgumentList(array $arguments): string
    {
        $list = [];
        foreach ($arguments as $name => $value) {
            if ($value === null) {
                continue;
            }

            if (is_int($name)) {
                $list[] = escapeshellarg($value);
                continue;
            }

            $list[] = str_ends_with($name, '=')
                ? escapeshellarg($name . $value)
                : escapeshellarg($name) . ' ' . escapeshellarg($value);
        }

        return implode(' ', $list);
    }

    protected function getPrintParameters(PrintableHtmlDocument $document): array
    {
        return array_merge([
            'printBackground' => true,
            'transferMode'    => 'ReturnAsBase64',
        ], $document->getPrintParameters());
    }

    public function toPdf(PrintableHtmlDocument $document): string
    {
        $this->setContent($document);
        return $this->printToPdf($this->getPrintParameters($document));
    }

    protected function getBrowser(): Client
    {
        return $this->browser ??= new Client(sprintf('ws://%s/devtools/browser/%s', $this->socket, $this->browserId));
    }

    protected function closeBrowser(): void
    {
        if ($this->browser === null) {
            return;
        }

        try {
            $this->closePage();
        } catch (Exception $e) {
            Logger::warning(
                "Failed to close browser: %s\n%s",
                $e->getMessage(),
                IcingaException::getConfidentialTraceAsString($e),
            );
        }

        try {
            $this->browser->close();
            $this->browser = null;
        } catch (Exception $e) {
            // For some reason, the browser doesn't send a response
            Logger::warning(
                "Failed to close browser connection: %s\n%s",
                $e->getMessage(),
                IcingaException::getConfidentialTraceAsString($e),
            );
        }
    }

    public function getPage(): Client
    {
        if ($this->page === null) {
            $browser = $this->getBrowser();
            // Open new tab, get its id
            $result = $this->communicate($browser, 'Target.createTarget', ['url' => 'about:blank']);

            if (isset($result['targetId'])) {
                $this->frameId = $result['targetId'];
            } else {
                throw new RuntimeException('Expected target id. Got instead: ' . json_encode($result));
            }

            // Try direct page WebSocket first (works for all standard Chrome/Chromium builds).
            // Fall back to flatten session for headless-only builds that don't expose /devtools/page/{id}.
            $direct = new Client(sprintf('ws://%s/devtools/page/%s', $this->socket, $this->frameId));
            try {
                $this->communicate($direct, 'Log.enable');
                $this->page = $direct;
            } catch (Exception $directException) {
                try {
                    $direct->close();
                } catch (Exception $e) {
                    Logger::warning(
                        "Failed to close websocket client: %s\n%s",
                        $directException->getMessage(),
                        IcingaException::getConfidentialTraceAsString($e),
                    );
                }

                $attached = $this->communicate($browser, 'Target.attachToTarget', [
                    'targetId' => $this->frameId,
                    'flatten'  => true,
                ]);

                if (isset($attached['sessionId'])) {
                    $this->sessionId = $attached['sessionId'];
                    $this->page = $browser;
                    $this->communicate($this->page, 'Log.enable');
                } else {
                    throw new RuntimeException(sprintf(
                        'Failed to connect to page. Direct: %s. Flatten returned no session.',
                        $directException->getMessage(),
                    ));
                }
            }

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
            throw new RuntimeException('Expected close confirmation. Got instead: ' . json_encode($result));
        }

        // Only close page if it's a separate connection; if it's the same as browser, leave browser open
        if ($this->page !== $this->browser) {
            try {
                $this->page->close();
            } catch (Throwable $e) {
                Logger::warning(
                    "Failed to close page connection: %s\n%s",
                    $e->getMessage(),
                    IcingaException::getConfidentialTraceAsString($e),
                );
            }
        }

        $this->page = null;
        $this->frameId = null;
        $this->sessionId = null;
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
            $result = $this->communicate($page, 'Page.navigate', ['url' => $url]);

            if (isset($result['frameId'])) {
                $this->frameId = $result['frameId'];
            } else {
                throw new RuntimeException('Expected navigation frame. Got instead: ' . json_encode($result));
            }

            // wait for the page to fully load
            $this->waitFor($page, 'Page.frameStoppedLoading', ['frameId' => $this->frameId]);

            try {
                $storage->delete($path);
            } catch (Exception $e) {
                Logger::warning(
                    "Failed to delete file: %s\n%s",
                    $e->getMessage(),
                    IcingaException::getConfidentialTraceAsString($e),
                );
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
            $waitForLayout = file_get_contents($module->getJsDir() . '/wait-for-layout.js');
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
        if (empty($result['data'])) {
            throw new RuntimeException('Expected base64 data. Got instead empty data instead.');
        }

        $decoded = base64_decode($result['data']);
        if ($decoded === false) {
            throw new RuntimeException('Expected base64 data. Got instead: ' . json_encode($result));
        }

        return $decoded;
    }

    private function renderApiCall($method, $options = null, ?string $sessionId = null): string
    {
        $payload = [
            'id'     => time(),
            'method' => $method,
            'params' => $options ?: [],
        ];
        if ($sessionId !== null) {
            $payload['sessionId'] = $sessionId;
        }
        return json_encode($payload, JSON_FORCE_OBJECT);
    }

    private function parseApiResponse(string $payload)
    {
        $data = json_decode($payload, true);
        if (isset($data['method']) || isset($data['result'])) {
            return $data;
        } elseif (isset($data['error'])) {
            throw new Exception(sprintf('Error response (%s): %s', $data['error']['code'], $data['error']['message']));
        } else {
            throw new Exception(sprintf('Unknown response received: %s', $payload));
        }
    }

    protected static function shortenParams(array $params): array
    {
        foreach ($params as &$value) {
            if (is_array($value)) {
                $value = static::shortenParams($value);
            } elseif (is_string($value) && strlen($value) > static::MAX_PARAM_LENGTH) {
                $value = substr($value, 0, static::MAX_PARAM_LENGTH) . '...';
            }
        }

        return $params;
    }

    private function registerEvent($method, $params): void
    {
        if (Logger::getInstance()->getLevel() === Logger::DEBUG) {
            $shortenedParams = static::shortenParams($params);
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
        // Include sessionId for page-level commands when using flatten sessions
        $sessionId = null;
        if ($this->sessionId !== null && $ws === $this->page && ! str_starts_with($method, 'Target.')) {
            $sessionId = $this->sessionId;
        }
        Logger::debug('Transmitting CDP call: %s(%s)', $method, $params ? join(',', array_keys($params)) : '');
        $ws->text($this->renderApiCall($method, $params, $sessionId));
        do {
            $response = $this->parseApiResponse($ws->receive()->getContent());
            $gotEvent = isset($response['method']);

            if ($gotEvent) {
                $this->registerEvent($response['method'], $response['params'] ?? []);
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
     * Return true if the DevTools HTTP server responds within $timeout seconds.
     */
    protected function probeDevtools(float $timeout): bool
    {
        try {
            $client = new HttpClient(['timeout' => $timeout, 'connect_timeout' => $timeout]);
            $response = $client->request('GET', sprintf('http://%s/json/version', $this->socket));
            return $response->getStatusCode() === 200;
        } catch (Exception $e) {
            Logger::warning(
                "Devtools request failed: %s (%s)",
                $e->getMessage(),
                IcingaException::getConfidentialTraceAsString($e),
            );
            return false;
        }
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
        if (! is_array($version) || empty($version['Browser'])) {
            throw new Exception("Invalid Version Json");
        }

        preg_match('/(?:Chrome|Chromium)\/([0-9]+)/', $version['Browser'], $matches);
        if (! isset($matches[1])) {
            throw new Exception("Malformed Chrome Version String: " . $version['Browser']);
        }

        return (int) $matches[1];
    }

    public function isSupported(): bool
    {
        try {
            return $this->getVersion() >= self::MIN_SUPPORTED_CHROME_VERSION;
        } catch (Exception $e) {
            Logger::warning('Chrome version check failed: %s. Checking DevTools availability.', $e->getMessage());
            return $this->probeDevtools(2.0);
        }
    }

    public function close(): void
    {
        $this->closeBrowser();
        $this->closeLocal();
    }

    public function supportsCoverPage(): bool
    {
        return true;
    }
}
