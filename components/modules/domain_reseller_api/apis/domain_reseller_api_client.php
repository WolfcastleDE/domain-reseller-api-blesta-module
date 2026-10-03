<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'domain_reseller_api_response.php';

/**
 * HTTP client for the Domain Reseller API (https://domain-reseller-api.de).
 *
 * Every method maps 1:1 onto a REST endpoint of the API (v1) and returns a
 * DomainResellerApiResponse. Authentication uses a bearer API key
 * (drapi_xxxxxxxxxxxx_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx); sandbox mode is enabled
 * through the `x-sandbox: true` header.
 *
 * @package blesta
 * @subpackage blesta.components.modules.domain_reseller_api
 * @see https://domain-reseller-api.de/swagger
 */
class DomainResellerApiClient
{
    /**
     * @var string The default API base URL
     */
    const DEFAULT_URL = 'https://domain-reseller-api.de';

    /**
     * @var string The API version prefix
     */
    const PREFIX = '/api/v1';

    /**
     * @var string The module version reported in the User-Agent header
     */
    const VERSION = '1.0.0';

    /**
     * @var string The API key
     */
    private $api_key;

    /**
     * @var string The API base URL (without trailing slash)
     */
    private $base_url;

    /**
     * @var bool Whether to send requests in sandbox mode
     */
    private $sandbox;

    /**
     * @var int The request timeout in seconds
     */
    private $timeout;

    /**
     * @var array The last request made (method, url, body) for logging purposes
     */
    private $last_request = [];

    /**
     * @param string $api_key The API key
     * @param string|null $base_url The API base URL (defaults to the production API)
     * @param bool $sandbox True to send all requests in sandbox mode
     * @param int $timeout The request timeout in seconds
     * @throws InvalidArgumentException If the base URL is not HTTPS (plain HTTP is only allowed for localhost)
     */
    public function __construct($api_key, $base_url = null, $sandbox = false, $timeout = 120)
    {
        $this->api_key = trim((string) $api_key);
        $this->base_url = rtrim(trim((string) ($base_url ?: self::DEFAULT_URL)), '/');
        $this->sandbox = (bool) $sandbox;
        $this->timeout = max(5, (int) $timeout);

        if (!self::isAllowedUrl($this->base_url)) {
            throw new InvalidArgumentException('The API URL must use HTTPS.');
        }
    }

    /**
     * Checks whether the given base URL may be used (HTTPS, or HTTP on localhost for development)
     *
     * @param string $url The URL to check
     * @return bool True if the URL is allowed
     */
    public static function isAllowedUrl($url)
    {
        $url = (string) $url;
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return stripos($url, 'https://') === 0
            || (bool) preg_match('#^http://(localhost|127\.0\.0\.1|\[::1\])(:\d+)?(/|$)#i', $url);
    }

    /**
     * @return string The API base URL
     */
    public function getBaseUrl()
    {
        return $this->base_url;
    }

    /**
     * @return bool Whether sandbox mode is enabled
     */
    public function isSandbox()
    {
        return $this->sandbox;
    }

    /**
     * @return array The last request (method, url, body)
     */
    public function lastRequest()
    {
        return $this->last_request;
    }

    // ------------------------------------------------------------------
    // Health & pricing (public endpoints)
    // ------------------------------------------------------------------

    /**
     * @return DomainResellerApiResponse
     */
    public function health()
    {
        return $this->request('GET', '/health/');
    }

    /**
     * Fetches the pricing for all active TLDs, or a single TLD
     *
     * @param string|null $tld The TLD (without leading dot), or null for all TLDs
     * @return DomainResellerApiResponse
     */
    public function getPricing($tld = null)
    {
        $query = [];
        if ($tld !== null && $tld !== '') {
            $query['tld'] = ltrim(strtolower($tld), '.');
        }

        return $this->request('GET', '/pricing/', $query);
    }

    // ------------------------------------------------------------------
    // Domains
    // ------------------------------------------------------------------

    /**
     * @param int $page The page (1-based)
     * @param int $page_size The number of results per page (max 100)
     * @param string|null $search An optional name filter
     * @param string|null $status An optional status filter
     * @return DomainResellerApiResponse
     */
    public function listDomains($page = 1, $page_size = 20, $search = null, $status = null)
    {
        $query = ['page' => max(1, (int) $page), 'pageSize' => min(100, max(1, (int) $page_size))];
        if ($search !== null && $search !== '') {
            $query['search'] = $search;
        }
        if ($status !== null && $status !== '') {
            $query['status'] = $status;
        }

        return $this->request('GET', '/domains/', $query);
    }

    /**
     * @param string $domain The domain name
     * @return DomainResellerApiResponse
     */
    public function checkDomain($domain)
    {
        return $this->request('GET', '/domains/check/' . $this->segment($domain));
    }

    /**
     * Checks up to 50 domains at once
     *
     * @param array $domains A list of domain names
     * @return DomainResellerApiResponse
     */
    public function checkDomains(array $domains)
    {
        return $this->request('POST', '/domains/check', [], ['domains' => array_values($domains)]);
    }

    /**
     * @param string $query The search label
     * @param array $tlds A list of TLDs to prioritise
     * @return DomainResellerApiResponse
     */
    public function suggestDomains($query, array $tlds = [])
    {
        $params = ['q' => $query];
        if (!empty($tlds)) {
            $params['defaults'] = implode(',', array_map(function ($tld) {
                return ltrim($tld, '.');
            }, $tlds));
        }

        return $this->request('GET', '/domains/suggest', $params);
    }

    /**
     * @param string $domain The domain name
     * @return DomainResellerApiResponse
     */
    public function getDomain($domain)
    {
        return $this->request('GET', '/domains/' . $this->segment($domain));
    }

    /**
     * Registers a domain
     *
     * @param array $params The request body: domain, years, nameservers, contacts{owner,admin,tech,billing},
     *  whoisPrivacy, acceptWppTerms
     * @return DomainResellerApiResponse
     */
    public function registerDomain(array $params)
    {
        return $this->request('POST', '/domains/register', [], $params);
    }

    /**
     * Initiates an incoming transfer
     *
     * @param array $params The request body: domain, authCode, contacts{owner,admin,tech,billing},
     *  whoisPrivacy, acceptWppTerms
     * @return DomainResellerApiResponse
     */
    public function transferDomain(array $params)
    {
        return $this->request('POST', '/domains/transfer', [], $params);
    }

    /**
     * Updates nameservers, auto-renew and/or managed DNS of a domain
     *
     * @param string $domain The domain name
     * @param array $params Any of: nameservers (array), autoRenew (bool), useManagedDns (bool)
     * @return DomainResellerApiResponse
     */
    public function updateDomain($domain, array $params)
    {
        return $this->request('PATCH', '/domains/' . $this->segment($domain), [], $params);
    }

    /**
     * Assigns contact handles to a domain
     *
     * @param string $domain The domain name
     * @param array $contacts Any of owner, admin, tech, billing => handle
     * @param bool $confirm_owner_change True to confirm a (possibly chargeable) owner change
     * @return DomainResellerApiResponse
     */
    public function updateDomainContacts($domain, array $contacts, $confirm_owner_change = false)
    {
        $body = ['contacts' => $contacts];
        if ($confirm_owner_change) {
            $body['confirmOwnerChange'] = true;
        }

        return $this->request('PATCH', '/domains/' . $this->segment($domain) . '/contacts', [], $body);
    }

    /**
     * Cancels a domain at the end of its term, or deletes it immediately
     *
     * @param string $domain The domain name
     * @param bool $immediate True to delete immediately (only allowed while pending deletion)
     * @return DomainResellerApiResponse
     */
    public function deleteDomain($domain, $immediate = false)
    {
        $query = $immediate ? ['immediate' => 'true'] : [];

        return $this->request('DELETE', '/domains/' . $this->segment($domain), $query);
    }

    /**
     * @param string $domain The domain name
     * @return DomainResellerApiResponse
     */
    public function getDomainState($domain)
    {
        return $this->request('GET', '/domains/' . $this->segment($domain) . '/state');
    }

    /**
     * @param string $domain The domain name
     * @return DomainResellerApiResponse
     */
    public function getAuthCode($domain)
    {
        return $this->request('GET', '/domains/' . $this->segment($domain) . '/authcode');
    }

    /**
     * @param string $domain The domain name (.de only)
     * @return DomainResellerApiResponse
     */
    public function resetAuthCode($domain)
    {
        return $this->request('POST', '/domains/' . $this->segment($domain) . '/reset-authcode');
    }

    /**
     * Renews a domain (charged to the wallet)
     *
     * @param string $domain The domain name
     * @param int|null $years The renewal term (must be a bookable term; defaults to the TLD minimum)
     * @return DomainResellerApiResponse
     */
    public function renewDomain($domain, $years = null)
    {
        $body = $years !== null ? ['years' => (int) $years] : [];

        return $this->request('POST', '/domains/' . $this->segment($domain) . '/renew', [], $body);
    }

    /**
     * @param string $domain The domain name
     * @return DomainResellerApiResponse
     */
    public function getTransferLock($domain)
    {
        return $this->request('GET', '/domains/' . $this->segment($domain) . '/lock');
    }

    /**
     * @param string $domain The domain name
     * @param bool $locked True to lock the domain against transfers
     * @return DomainResellerApiResponse
     */
    public function setTransferLock($domain, $locked)
    {
        return $this->request('PUT', '/domains/' . $this->segment($domain) . '/lock', [], ['locked' => (bool) $locked]);
    }

    /**
     * Enables or disables WHOIS privacy (OpenProvider routed TLDs, natural persons only)
     *
     * @param string $domain The domain name
     * @param bool $enabled True to enable WHOIS privacy
     * @param bool $accept_terms True to confirm that the WHOIS privacy terms were accepted (required to enable)
     * @return DomainResellerApiResponse
     */
    public function setWhoisPrivacy($domain, $enabled, $accept_terms = false)
    {
        $body = ['enabled' => (bool) $enabled];
        if ($accept_terms) {
            $body['acceptWppTerms'] = true;
        }

        return $this->request('PUT', '/domains/' . $this->segment($domain) . '/whois-privacy', [], $body);
    }

    /**
     * @param string $domain The domain name
     * @return DomainResellerApiResponse
     */
    public function getHosts($domain)
    {
        return $this->request('GET', '/domains/' . $this->segment($domain) . '/hosts');
    }

    /**
     * Creates a glue record (host below the domain)
     *
     * @param string $domain The domain name
     * @param string $hostname The host name (e.g. ns1.example.com)
     * @param string|null $ipv4 The IPv4 address
     * @param string|null $ipv6 The IPv6 address
     * @return DomainResellerApiResponse
     */
    public function createHost($domain, $hostname, $ipv4 = null, $ipv6 = null)
    {
        return $this->request(
            'POST',
            '/domains/' . $this->segment($domain) . '/hosts',
            [],
            $this->filterEmpty(['hostname' => $hostname, 'ipv4' => $ipv4, 'ipv6' => $ipv6])
        );
    }

    /**
     * Updates the addresses of a glue record
     *
     * @param string $domain The domain name
     * @param string $hostname The host name
     * @param string|null $ipv4 The IPv4 address
     * @param string|null $ipv6 The IPv6 address
     * @return DomainResellerApiResponse
     */
    public function updateHost($domain, $hostname, $ipv4 = null, $ipv6 = null)
    {
        return $this->request(
            'PUT',
            '/domains/' . $this->segment($domain) . '/hosts/' . $this->segment($hostname),
            [],
            $this->filterEmpty(['ipv4' => $ipv4, 'ipv6' => $ipv6])
        );
    }

    /**
     * @param string $domain The domain name
     * @param string $hostname The host name
     * @return DomainResellerApiResponse
     */
    public function deleteHost($domain, $hostname)
    {
        return $this->request('DELETE', '/domains/' . $this->segment($domain) . '/hosts/' . $this->segment($hostname));
    }

    /**
     * @param string $domain The domain name
     * @return DomainResellerApiResponse
     */
    public function restoreDomain($domain)
    {
        return $this->request('POST', '/domains/' . $this->segment($domain) . '/restore');
    }

    /**
     * @param string $domain The domain name
     * @return DomainResellerApiResponse
     */
    public function cancelDelete($domain)
    {
        return $this->request('POST', '/domains/' . $this->segment($domain) . '/cancel-delete');
    }

    /**
     * @param string $domain The domain name
     * @return DomainResellerApiResponse
     */
    public function getDnssec($domain)
    {
        return $this->request('GET', '/domains/' . $this->segment($domain) . '/dnssec');
    }

    /**
     * @param string $domain The domain name
     * @param bool $enabled Whether DNSSEC should be enabled
     * @param array $dnskeys DNSKEY records (only used for domains with custom nameservers)
     * @return DomainResellerApiResponse
     */
    public function updateDnssec($domain, $enabled, array $dnskeys = [])
    {
        $body = ['enabled' => (bool) $enabled];
        if (!empty($dnskeys)) {
            $body['dnskeys'] = array_values($dnskeys);
        }

        return $this->request('PUT', '/domains/' . $this->segment($domain) . '/dnssec', [], $body);
    }

    /**
     * Approves or rejects a pending outgoing transfer
     *
     * @param string $domain The domain name
     * @param bool $approve True to approve, false to reject
     * @return DomainResellerApiResponse
     */
    public function transferOut($domain, $approve = true)
    {
        return $this->request(
            'POST',
            '/domains/' . $this->segment($domain) . '/transfer-out',
            [],
            ['approve' => (bool) $approve]
        );
    }

    /**
     * @return DomainResellerApiResponse
     */
    public function getPendingTransferOuts()
    {
        return $this->request('GET', '/domains/transfer-out/pending');
    }

    /**
     * Initiates a .eu trade (owner change)
     *
     * @param string $domain The domain name
     * @param array $params The request body: contacts{owner,admin,tech,zone}, nameservers, password
     * @return DomainResellerApiResponse
     */
    public function tradeDomain($domain, array $params)
    {
        return $this->request('POST', '/domains/' . $this->segment($domain) . '/trade', [], $params);
    }

    /**
     * Puts a .de domain on hold or releases it
     *
     * @param string $domain The domain name
     * @param bool $disconnect True to put on hold, false to release
     * @param string|null $exec_date An optional execution date (YYYY-MM-DD)
     * @param bool $exec_on_expire True to execute when the domain expires
     * @return DomainResellerApiResponse
     */
    public function holdDomain($domain, $disconnect, $exec_date = null, $exec_on_expire = false)
    {
        $body = ['disconnect' => (bool) $disconnect];
        if (!empty($exec_date)) {
            $body['execDate'] = $exec_date;
        }
        if ($exec_on_expire) {
            $body['execOnExpire'] = true;
        }

        return $this->request('POST', '/domains/' . $this->segment($domain) . '/hold', [], $body);
    }

    // ------------------------------------------------------------------
    // Contacts (handles)
    // ------------------------------------------------------------------

    /**
     * @param int $page The page (1-based)
     * @param int $page_size The number of results per page (max 100)
     * @param string|null $search An optional search term
     * @return DomainResellerApiResponse
     */
    public function listContacts($page = 1, $page_size = 100, $search = null)
    {
        $query = ['page' => max(1, (int) $page), 'pageSize' => min(100, max(1, (int) $page_size))];
        if ($search !== null && $search !== '') {
            $query['search'] = $search;
        }

        return $this->request('GET', '/contacts/', $query);
    }

    /**
     * @param string $handle The contact handle
     * @return DomainResellerApiResponse
     */
    public function getContact($handle)
    {
        return $this->request('GET', '/contacts/' . $this->segment($handle));
    }

    /**
     * @param array $params The contact data
     * @return DomainResellerApiResponse
     */
    public function createContact(array $params)
    {
        return $this->request('POST', '/contacts/', [], $params);
    }

    /**
     * @param string $handle The contact handle
     * @param array $params The contact fields to update
     * @return DomainResellerApiResponse
     */
    public function updateContact($handle, array $params)
    {
        return $this->request('PATCH', '/contacts/' . $this->segment($handle), [], $params);
    }

    /**
     * @param string $handle The contact handle
     * @return DomainResellerApiResponse
     */
    public function deleteContact($handle)
    {
        return $this->request('DELETE', '/contacts/' . $this->segment($handle));
    }

    // ------------------------------------------------------------------
    // Managed DNS
    // ------------------------------------------------------------------

    /**
     * @param string $domain The domain name
     * @return DomainResellerApiResponse
     */
    public function getDnsRecords($domain)
    {
        return $this->request('GET', '/dns/' . $this->segment($domain));
    }

    /**
     * @param string $domain The domain name
     * @param array $record The record: type, name, content, ttl, priority
     * @return DomainResellerApiResponse
     */
    public function createDnsRecord($domain, array $record)
    {
        return $this->request('POST', '/dns/' . $this->segment($domain) . '/records', [], $record);
    }

    /**
     * @param string $domain The domain name
     * @param string $record_id The record ID
     * @param array $record Any of: name, content, ttl, priority
     * @return DomainResellerApiResponse
     */
    public function updateDnsRecord($domain, $record_id, array $record)
    {
        return $this->request(
            'PATCH',
            '/dns/' . $this->segment($domain) . '/records/' . $this->segment($record_id),
            [],
            $record
        );
    }

    /**
     * @param string $domain The domain name
     * @param string $record_id The record ID
     * @return DomainResellerApiResponse
     */
    public function deleteDnsRecord($domain, $record_id)
    {
        return $this->request('DELETE', '/dns/' . $this->segment($domain) . '/records/' . $this->segment($record_id));
    }

    /**
     * @param string $domain The domain name
     * @return DomainResellerApiResponse
     */
    public function getZoneInfo($domain)
    {
        return $this->request('GET', '/dns/' . $this->segment($domain) . '/zone-info');
    }

    /**
     * @param string $domain The domain name
     * @return DomainResellerApiResponse
     */
    public function exportZone($domain)
    {
        return $this->request('GET', '/dns/' . $this->segment($domain) . '/export');
    }

    /**
     * @param string $domain The domain name
     * @param string $zone_file A BIND formatted zone file
     * @return DomainResellerApiResponse
     */
    public function importZone($domain, $zone_file)
    {
        return $this->request('POST', '/dns/' . $this->segment($domain) . '/import', [], ['zoneFile' => (string) $zone_file]);
    }

    // ------------------------------------------------------------------
    // Account
    // ------------------------------------------------------------------

    /**
     * @return DomainResellerApiResponse
     */
    public function getWallet()
    {
        return $this->request('GET', '/billing/wallet');
    }

    // ------------------------------------------------------------------
    // Transport
    // ------------------------------------------------------------------

    /**
     * Performs a request against the API
     *
     * @param string $method The HTTP method
     * @param string $path The endpoint path (relative to /api/v1)
     * @param array $query Query string parameters
     * @param array|null $body The JSON body (sent for POST/PUT/PATCH)
     * @return DomainResellerApiResponse
     */
    public function request($method, $path, array $query = [], ?array $body = null)
    {
        $method = strtoupper($method);
        $url = $this->base_url . self::PREFIX . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $payload = null;
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            // Always send a JSON object, the API validates bodies as objects
            $payload = json_encode(empty($body) ? new stdClass() : $body);
        }

        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Accept: application/json',
            'User-Agent: Blesta-DomainResellerApi/' . self::VERSION,
        ];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        if ($this->sandbox) {
            $headers[] = 'x-sandbox: true';
        }

        $this->last_request = ['method' => $method, 'url' => $url, 'body' => $body];

        return $this->send($method, $url, $headers, $payload);
    }

    /**
     * Sends the HTTP request using cURL
     *
     * @param string $method The HTTP method
     * @param string $url The full URL
     * @param array $headers A list of HTTP headers
     * @param string|null $payload The request body
     * @return DomainResellerApiResponse
     */
    protected function send($method, $url, array $headers, $payload)
    {
        $ch = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS') && defined('CURLPROTO_HTTP')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS | CURLPROTO_HTTP;
        }
        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = $payload;
        }
        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = ($raw === false ? curl_error($ch) : null);
        curl_close($ch);

        return new DomainResellerApiResponse($status, $raw === false ? '' : $raw, $error ?: null);
    }

    /**
     * Removes null and empty string values
     *
     * @param array $values The values
     * @return array The filtered values
     */
    private function filterEmpty(array $values)
    {
        return array_filter($values, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    /**
     * Encodes a single path segment
     *
     * @param string $value The value
     * @return string The encoded segment
     */
    private function segment($value)
    {
        return rawurlencode(trim((string) $value));
    }
}
