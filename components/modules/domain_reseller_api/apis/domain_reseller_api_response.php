<?php
/**
 * Wraps a single response from the Domain Reseller API.
 *
 * The API answers with JSON envelopes of the form
 * `{"success": true, "data": {...}}` or `{"success": false, "error": "...", "details": "..."}`.
 * Some endpoints (e.g. PATCH /domains/{domain}) return their payload at the top level
 * instead of inside `data`, so both are accessible.
 *
 * @package blesta
 * @subpackage blesta.components.modules.domain_reseller_api
 */
class DomainResellerApiResponse
{
    /**
     * @var int The HTTP status code (0 on transport errors)
     */
    private $status;

    /**
     * @var array|null The decoded JSON body
     */
    private $body;

    /**
     * @var string The raw response body
     */
    private $raw;

    /**
     * @var string|null A transport-level error (cURL), if any
     */
    private $transport_error;

    /**
     * @param int $status The HTTP status code
     * @param string $raw The raw response body
     * @param string|null $transport_error A transport-level error message, if the request failed
     */
    public function __construct($status, $raw, $transport_error = null)
    {
        $this->status = (int) $status;
        $this->raw = (string) $raw;
        $this->transport_error = $transport_error;

        $decoded = ($this->raw !== '' ? json_decode($this->raw, true) : null);
        $this->body = is_array($decoded) ? $decoded : null;
    }

    /**
     * @return bool True if the request succeeded (2xx status and no `success: false` in the body)
     */
    public function success()
    {
        if ($this->transport_error !== null || $this->status < 200 || $this->status >= 300) {
            return false;
        }

        if ($this->body === null) {
            return false;
        }

        // Endpoints without an envelope (e.g. health) are successful on 2xx
        return !array_key_exists('success', $this->body) || $this->body['success'] === true;
    }

    /**
     * @return int The HTTP status code (0 if the request never reached the API)
     */
    public function status()
    {
        return $this->status;
    }

    /**
     * @return array|null The full decoded JSON body
     */
    public function body()
    {
        return $this->body;
    }

    /**
     * Returns the `data` member of the response, or the given default if not present
     *
     * @param mixed $default The value to return when the response has no data
     * @return mixed The response data
     */
    public function data($default = null)
    {
        if (is_array($this->body) && array_key_exists('data', $this->body)) {
            return $this->body['data'];
        }

        return $default;
    }

    /**
     * Fetches a top-level member of the response body
     *
     * @param string $key The key to fetch
     * @param mixed $default The value to return when the key is not set
     * @return mixed The value
     */
    public function get($key, $default = null)
    {
        if (is_array($this->body) && array_key_exists($key, $this->body)) {
            return $this->body[$key];
        }

        return $default;
    }

    /**
     * @return string The raw response body
     */
    public function raw()
    {
        return $this->raw;
    }

    /**
     * Builds a human readable error message for a failed response
     *
     * @return string|null The error message, or null if the request succeeded
     */
    public function error()
    {
        if ($this->success()) {
            return null;
        }

        if ($this->transport_error !== null) {
            return 'Connection error: ' . $this->transport_error;
        }

        if ($this->body === null) {
            return 'Invalid response from the Domain Reseller API (HTTP ' . $this->status . ')';
        }

        $message = '';
        if (isset($this->body['error']) && is_string($this->body['error'])) {
            $message = $this->body['error'];
        } elseif (isset($this->body['message']) && is_string($this->body['message'])) {
            $message = $this->body['message'];
        }

        if (isset($this->body['details']) && is_string($this->body['details']) && $this->body['details'] !== '') {
            $message .= ($message !== '' ? ': ' : '') . $this->body['details'];
        }

        if ($message === '') {
            $message = 'The Domain Reseller API returned an error (HTTP ' . $this->status . ')';
        }

        return $message;
    }

    /**
     * @return array A list of error messages (empty if the request succeeded)
     */
    public function errors()
    {
        $error = $this->error();

        return $error === null ? [] : [$error];
    }
}
