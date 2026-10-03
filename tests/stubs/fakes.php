<?php
/**
 * Test doubles: an in-memory Domain Reseller API and fake Blesta models
 */

/**
 * In-memory API server answering requests of the FakeApiClient
 */
class FakeApiServer
{
    /** @var array Registered handlers */
    private $handlers = [];

    /** @var array All requests received: method, path, query, body, headers */
    public $requests = [];

    /**
     * Registers a response for a request
     *
     * @param string $method The HTTP method
     * @param string $path The path relative to /api/v1 (exact match, or a regex starting with #)
     * @param int $status The HTTP status
     * @param array|callable|null $body The JSON body, or a callable receiving the request and returning [status, body]
     * @param int|null $times How often this handler may answer (null = unlimited)
     * @return $this
     */
    public function on($method, $path, $status = 200, $body = null, $times = null)
    {
        array_unshift($this->handlers, compact('method', 'path', 'status', 'body', 'times'));

        return $this;
    }

    /**
     * Handles a request
     *
     * @param string $method The HTTP method
     * @param string $url The full URL
     * @param array $headers The request headers
     * @param string|null $payload The request body
     * @return DomainResellerApiResponse
     */
    public function handle($method, $url, array $headers, $payload)
    {
        $parts = parse_url($url);
        $path = substr($parts['path'], strlen('/api/v1'));
        parse_str($parts['query'] ?? '', $query);
        $request = [
            'method' => $method,
            'path' => $path,
            'query' => $query,
            'body' => $payload !== null ? json_decode($payload, true) : null,
            'raw_body' => $payload,
            'headers' => $headers,
            'url' => $url
        ];
        $this->requests[] = $request;

        foreach ($this->handlers as $i => $handler) {
            if ($handler['method'] !== $method) {
                continue;
            }
            $matches = strpos($handler['path'], '#') === 0
                ? (bool) preg_match($handler['path'], $path)
                : $handler['path'] === $path;
            if (!$matches || $handler['times'] === 0) {
                continue;
            }
            if ($handler['times'] !== null) {
                $this->handlers[$i]['times']--;
            }

            $status = $handler['status'];
            $body = $handler['body'];
            if (is_callable($body)) {
                list($status, $body) = $body($request);
            }

            return new DomainResellerApiResponse($status, $body === null ? '' : json_encode($body));
        }

        return new DomainResellerApiResponse(404, json_encode(['success' => false, 'error' => 'Not found']));
    }

    /**
     * Returns all requests matching the method and path
     *
     * @param string $method The HTTP method
     * @param string $path The path (exact match or regex starting with #)
     * @return array The matching requests
     */
    public function requestsTo($method, $path)
    {
        return array_values(array_filter($this->requests, function ($request) use ($method, $path) {
            $matches = strpos($path, '#') === 0
                ? (bool) preg_match($path, $request['path'])
                : $request['path'] === $path;

            return $request['method'] === $method && $matches;
        }));
    }

    /**
     * @return array A list of "METHOD path" strings of all requests
     */
    public function summary()
    {
        return array_map(function ($request) {
            return $request['method'] . ' ' . $request['path'];
        }, $this->requests);
    }
}

/**
 * API client sending requests to a FakeApiServer instead of the network
 */
class FakeApiClient extends DomainResellerApiClient
{
    /** @var FakeApiServer */
    private $server;

    public function __construct(FakeApiServer $server, $api_key, $base_url = null, $sandbox = false, $timeout = 120)
    {
        $this->server = $server;
        parent::__construct($api_key, $base_url, $sandbox, $timeout);
    }

    protected function send($method, $url, array $headers, $payload)
    {
        return $this->server->handle($method, $url, $headers, $payload);
    }
}

/**
 * The module wired to the fake API server
 */
class TestableDomainResellerApi extends DomainResellerApi
{
    /** @var FakeApiServer */
    public $server;

    /** @var array Seconds waited between retries */
    public $pauses = [];

    public function __construct(FakeApiServer $server)
    {
        $this->server = $server;
        parent::__construct();
    }

    protected function getApi($row, $timeout = null)
    {
        $meta = $row->meta ?? new stdClass();
        $url = trim((string) ($meta->api_url ?? ''));
        if ($url === '' || !DomainResellerApiClient::isAllowedUrl($url)) {
            $url = DomainResellerApiClient::DEFAULT_URL;
        }

        return new FakeApiClient(
            $this->server,
            (string) ($meta->api_key ?? ''),
            $url,
            ($meta->sandbox ?? 'false') == 'true',
            $timeout ?? 120
        );
    }

    protected function pause($seconds)
    {
        $this->pauses[] = $seconds;
    }
}

class FakeClients
{
    public $clients = [];

    public function get($client_id)
    {
        return $this->clients[$client_id] ?? false;
    }
}

class FakeContacts
{
    public $numbers = [];

    /**
     * Mimics Blesta's Contacts::intlNumber(), which keeps the national trunk prefix
     */
    public function intlNumber($number, $country, $separator = '.')
    {
        return '+49' . $separator . preg_replace('/\D+/', '', $number);
    }

    public function getNumbers($contact_id)
    {
        return $this->numbers[$contact_id] ?? [];
    }
}

class FakeModuleClientMeta
{
    public $meta = [];

    public function get($client_id, $key, $module_id, $module_row_id = 0)
    {
        $id = implode('|', [$client_id, $key, $module_id, $module_row_id]);

        return isset($this->meta[$id]) ? (object) ['key' => $key, 'value' => $this->meta[$id]] : false;
    }

    public function set($client_id, $module_id, $module_row_id, array $meta)
    {
        foreach ($meta as $field) {
            $this->meta[implode('|', [$client_id, $field['key'], $module_id, $module_row_id])] = $field['value'];
        }
    }
}

class FakeCurrencies
{
    public $codes = ['EUR', 'USD'];

    public function getAll($company_id)
    {
        return array_map(function ($code) {
            return (object) ['code' => $code];
        }, $this->codes);
    }

    public function convert($amount, $from, $to, $company_id)
    {
        return $to === 'USD' ? $amount * 1.1 : $amount;
    }
}

class FakeServices
{
    public $edits = [];

    public function edit($service_id, array $vars, $bypass_module = false)
    {
        $this->edits[] = compact('service_id', 'vars', 'bypass_module');
    }

    public function errors()
    {
        return false;
    }
}

class FakePackages
{
    public $packages = [];

    public function get($package_id)
    {
        return $this->packages[$package_id] ?? false;
    }
}
