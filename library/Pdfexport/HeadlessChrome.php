<?php
// Icinga PDF Export | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\Pdfexport;

use Exception;
use Icinga\Application\Logger;
use Icinga\Application\Platform;
use Icinga\File\Storage\StorageInterface;
use Icinga\File\Storage\TemporaryLocalFileStorage;
use React\ChildProcess\Process;
use React\EventLoop\Factory;
use WebSocket\Client;
use WebSocket\ConnectionException;

class HeadlessChrome
{
    /**
     * Line of stderr output identifying the websocket url
     *
     * First matching group is the used port and the second one the browser id.
     */
    const DEBUG_ADDR_PATTERN = '/^DevTools listening on ws:\/\/(127\.0\.0\.1:\d+)\/devtools\/browser\/([\w-]+)$/';

    /** @var string Path to the Chrome binary */
    protected $binary;

    /**
     * The document to print
     *
     * @var PrintableHtmlDocument
     */
    protected $document;

    /** @var string Target Url */
    protected $url;

    /** @var StorageInterface */
    protected $fileStorage;

    /**
     * Get the path to the Chrome binary
     *
     * @return  string
     */
    public function getBinary()
    {
        return $this->binary;
    }

    /**
     * Set the path to the Chrome binary
     *
     * @param   string  $binary
     *
     * @return  $this
     */
    public function setBinary($binary)
    {
        $this->binary = $binary;

        return $this;
    }

    /**
     * Get the target Url
     *
     * @return  string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set the target Url
     *
     * @param   string  $url
     *
     * @return  $this
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get the file storage
     *
     * @return  StorageInterface
     */
    public function getFileStorage()
    {
        if ($this->fileStorage === null) {
            $this->fileStorage = new TemporaryLocalFileStorage();
        }

        return $this->fileStorage;
    }

    /**
     * Set the file storage
     *
     * @param   StorageInterface  $fileStorage
     *
     * @return  $this
     */
    public function setFileStorage($fileStorage)
    {
        $this->fileStorage = $fileStorage;

        return $this;
    }

    /**
     * Render the given argument name-value pairs as shell-escaped string
     *
     * @param   array   $arguments
     *
     * @return  string
     */
    public static function renderArgumentList(array $arguments)
    {
        $list = [];

        foreach ($arguments as $name => $value) {
            if ($value !== null) {
                $value = escapeshellarg($value);

                if (! is_int($name)) {
                    if (substr($name, -1) === '=') {
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

    /**
     * Use the given HTML as input
     *
     * @param string|PrintableHtmlDocument $html
     * @param bool $asFile
     * @return $this
     */
    public function fromHtml($html, $asFile = true)
    {
        if ($html instanceof PrintableHtmlDocument) {
            $this->document = $html;
            $html = $this->document->render();
        }

        if ($asFile) {
            $path = uniqid('icingaweb2-pdfexport-') . '.html';
            $storage = $this->getFileStorage();

            $storage->create($path, $html);

            $path = $storage->resolvePath($path, true);

            $this->setUrl("file://$path");
        } else {
            $this->setUrl('data:text/html,' . rawurlencode($html));
        }

        return $this;
    }

    /**
     * Export to PDF
     *
     * @return string
     * @throws Exception
     */
    public function toPdf()
    {
        $browserHome = $this->getFileStorage()->resolvePath('HOME');
        $commandLine = join(' ', [
            escapeshellarg($this->getBinary()),
            static::renderArgumentList([
                '--bwsi',
                '--headless',
                '--disable-gpu',
                '--no-sandbox',
                '--no-first-run',
                '--disable-dev-shm-usage',
                '--remote-debugging-port=0',
                '--homedir=' => $browserHome,
                '--user-data-dir=' => $browserHome
            ])
        ]);

        if (Platform::isLinux()) {
            Logger::debug('Starting browser process: HOME=%s exec %s', $browserHome, $commandLine);
            $chrome = new Process('exec ' . $commandLine, null, ['HOME' => $browserHome]);
        } else {
            Logger::debug('Starting browser process: %s', $commandLine);
            $chrome = new Process($commandLine);
        }

        $loop = Factory::create();
        $chrome->start($loop);

        $pdf = null;
        $chrome->stderr->once('data', function ($chunk) use (&$pdf, $chrome) {
            if (preg_match(self::DEBUG_ADDR_PATTERN, trim($chunk), $matches)) {
                $pdf = $this->printToPDF($matches[1], $matches[2], isset($this->document)
                    ? $this->document->getPrintParameters()
                    : []);
            } else {
                throw new Exception(sprintf('Failed to start browser: %s', $chunk));
            }

            $chrome->terminate();
        });

        $chrome->on('exit', function ($exitCode, $termSignal) {
            if ($exitCode) {
                throw new Exception($exitCode);
            }
        });

        $loop->run();

        return $pdf;
    }

    /**
     * Export to PDF and save as file on disk
     *
     * @return string The path to the file on disk
     */
    public function savePdf()
    {
        $path = uniqid('icingaweb2-pdfexport-') . '.pdf';

        $storage = $this->getFileStorage();
        $storage->create($path, '');

        $path = $storage->resolvePath($path, true);
        file_put_contents($path, $this->toPdf());

        return $path;
    }

    private function printToPDF($socket, $browserId, array $parameters)
    {
        $browser = new Client(sprintf('ws://%s/devtools/browser/%s', $socket, $browserId));

        // Open new tab, get its id
        $result = $this->communicate($browser, 'Target.createTarget', [
            'url'   => 'about:blank'
        ]);
        if (isset($result['targetId'])) {
            $targetId = $result['targetId'];
        } else {
            throw new Exception('Expected target id. Got instead: ' . json_encode($result));
        }

        $page = new Client(sprintf('ws://%s/devtools/page/%s', $socket, $targetId), ['timeout' => 60]);

        // enable page events
        $result = $this->communicate($page, 'Page.enable');
        if (! empty($result)) {
            throw new Exception('Expected empty result. Got instead: ' . json_encode($result));
        }

        // Navigate to target
        $result = $this->communicate($page, 'Page.navigate', [
            'url'   => $this->getUrl()
        ]);
        if (isset($result['frameId'])) {
            $frameId = $result['frameId'];
        } else {
            throw new Exception('Expected navigation frame. Got instead: ' . json_encode($result));
        }

        // wait for page to fully load
        $this->waitFor($page, 'Page.frameStoppedLoading', ['frameId' => $frameId]);

        // print pdf
        $result = $this->communicate($page, 'Page.printToPDF', array_merge(
            $parameters,
            ['transferMode' => 'ReturnAsBase64', 'printBackground' => true]
        ));
        if (isset($result['data']) && !empty($result['data'])) {
            $pdf = base64_decode($result['data']);
        } else {
            throw new Exception('Expected base64 data. Got instead: ' . json_encode($result));
        }

        $page->close();  // We're done with the tab, tell this the browser

        // close tab
        $result = $this->communicate($browser, 'Target.closeTarget', [
            'targetId' => $targetId
        ]);
        if (! isset($result['success'])) {
            throw new Exception('Expected close confirmation. Got instead: ' . json_encode($result));
        }

        try {
            $browser->close();
        } catch (ConnectionException $e) {
            // For some reason, the browser doesn't send a response
            Logger::debug(sprintf('Failed to close browser connection: ' . $e->getMessage()));
        }

        return $pdf;
    }

    private function renderApiCall($method, $options = null)
    {
        $data = [
            'id' => time(),
            'method' => $method,
            'params' => $options ?: []
        ];

        return json_encode($data, JSON_FORCE_OBJECT);
    }

    private function parseApiResponse($payload)
    {
        $data = json_decode($payload, true);
        if (isset($data['method']) || isset($data['result'])) {
            return $data;
        } elseif (isset($data['error'])) {
            throw new Exception(sprintf(
                'Error response (%s): %s',
                $data['error']['code'],
                $data['error']['message']
            ));
        } else {
            throw new Exception(sprintf('Unknown response received: %s', $payload));
        }
    }

    private function communicate(Client $ws, $method, $params = null)
    {
        Logger::debug('Transmitting CDP call: %s(%s)', $method, $params ? join(',', array_keys($params)) : '');
        $ws->send($this->renderApiCall($method, $params));

        do {
            $response = $this->parseApiResponse($ws->receive());
            $gotEvent = isset($response['method']);

            if ($gotEvent) {
                Logger::debug(
                    'Received CDP event: %s(%s)',
                    $response['method'],
                    join(',', array_keys($response['params']))
                );
            }
        } while ($gotEvent);

        Logger::debug('Received CDP result: %s', empty($response['result'])
            ? 'none'
            : join(',', array_keys($response['result'])));

        return $response['result'];
    }

    private function waitFor(Client $ws, $eventName, array $expectedParams = null)
    {
        Logger::debug(
            'Awaiting CDP event: %s(%s)',
            $eventName,
            $expectedParams ? join(',', array_keys($expectedParams)) : ''
        );

        $wait = true;

        do {
            $response = $this->parseApiResponse($ws->receive());
            if (isset($response['method'])) {
                $method = $response['method'];
                $params = $response['params'];

                Logger::debug('Received CDP event: %s(%s)', $method, join(',', array_keys($params)));

                if ($method === $eventName) {
                    if ($expectedParams !== null) {
                        $diff = array_intersect_assoc($params, $expectedParams);
                        $wait = empty($diff);
                    } else {
                        $wait = false;
                    }
                }
            }
        } while ($wait);

        return $params;
    }

    /**
     * Get the major version number of Chrome or false on failure
     *
     * @return  int|false
     *
     * @throws  Exception
     */
    public function getVersion()
    {
        $command = new ShellCommand(
            escapeshellarg($this->getBinary()) . ' ' . static::renderArgumentList(['--version']),
            false
        );

        $output = $command->execute();

        if ($command->getExitCode() !== 0) {
            throw new \Exception($output->stderr);
        }

        if (preg_match('/\s(\d+)\.[\d\.]+\s/', $output->stdout, $match)) {
            return (int) $match[1];
        }

        return false;
    }
}
