<?php

namespace Tests;

use DomainResellerApiClient;
use DomainResellerApiResponse;
use FakeApiClient;
use FakeApiServer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

class ApiClientTest extends \PHPUnit\Framework\TestCase
{
    /** @var FakeApiServer */
    private $server;

    protected function setUp(): void
    {
        $this->server = new FakeApiServer();
    }

    private function client($sandbox = false, $url = 'https://domain-reseller-api.de/')
    {
        return new FakeApiClient($this->server, TestCase::API_KEY, $url, $sandbox);
    }

    private function lastRequest()
    {
        return end($this->server->requests);
    }

    public function testRejectsPlainHttpUrls()
    {
        $this->expectException(InvalidArgumentException::class);
        new DomainResellerApiClient(TestCase::API_KEY, 'http://domain-reseller-api.de');
    }

    public function testAllowsLocalhostOverHttp()
    {
        $this->assertTrue(DomainResellerApiClient::isAllowedUrl('http://localhost:3000'));
        $this->assertTrue(DomainResellerApiClient::isAllowedUrl('http://127.0.0.1/'));
        $this->assertTrue(DomainResellerApiClient::isAllowedUrl('https://api.example.com'));
        $this->assertFalse(DomainResellerApiClient::isAllowedUrl('http://example.com'));
        $this->assertFalse(DomainResellerApiClient::isAllowedUrl('ftp://example.com'));
        $this->assertFalse(DomainResellerApiClient::isAllowedUrl('not a url'));
    }

    public function testDefaultsToProductionUrl()
    {
        $client = new DomainResellerApiClient(TestCase::API_KEY);
        $this->assertSame('https://domain-reseller-api.de', $client->getBaseUrl());
    }

    public function testSendsAuthenticationAndJsonHeaders()
    {
        $this->client()->getDomain('example.de');
        $request = $this->lastRequest();

        $this->assertSame('GET', $request['method']);
        $this->assertSame('https://domain-reseller-api.de/api/v1/domains/example.de', $request['url']);
        $this->assertContains('Authorization: Bearer ' . TestCase::API_KEY, $request['headers']);
        $this->assertContains('Accept: application/json', $request['headers']);
        $this->assertNotContains('x-sandbox: true', $request['headers']);
        $this->assertNull($request['raw_body'], 'GET requests must not send a body');
    }

    public function testSandboxHeader()
    {
        $this->client(true)->listDomains();
        $this->assertContains('x-sandbox: true', $this->lastRequest()['headers']);
    }

    public function testEncodesPathSegments()
    {
        $this->client()->getContact('ABC/../x y');
        $this->assertSame('/contacts/ABC%2F..%2Fx%20y', $this->lastRequest()['path']);
    }

    public function testPostWithoutBodySendsEmptyObject()
    {
        $this->client()->restoreDomain('example.de');
        $request = $this->lastRequest();

        $this->assertSame('POST', $request['method']);
        $this->assertSame('/domains/example.de/restore', $request['path']);
        $this->assertSame('{}', $request['raw_body']);
        $this->assertContains('Content-Type: application/json', $request['headers']);
    }

    #[DataProvider('endpointProvider')]
    public function testEndpoints($call, $method, $path, $query = [], $body = null)
    {
        $call($this->client());
        $request = $this->lastRequest();

        $this->assertSame($method, $request['method']);
        $this->assertSame($path, $request['path']);
        $this->assertEquals($query, $request['query']);
        if ($body !== null) {
            $this->assertEquals($body, $request['body']);
        }
    }

    public static function endpointProvider()
    {
        return [
            'health' => [function ($c) { $c->health(); }, 'GET', '/health/'],
            'pricing all' => [function ($c) { $c->getPricing(); }, 'GET', '/pricing/'],
            'pricing tld' => [function ($c) { $c->getPricing('.DE'); }, 'GET', '/pricing/', ['tld' => 'de']],
            'list domains' => [
                function ($c) { $c->listDomains(0, 500, 'exa', 'active'); },
                'GET', '/domains/', ['page' => '1', 'pageSize' => '100', 'search' => 'exa', 'status' => 'active']
            ],
            'check' => [function ($c) { $c->checkDomain('example.com'); }, 'GET', '/domains/check/example.com'],
            'bulk check' => [
                function ($c) { $c->checkDomains(['a.de', 'b.de']); },
                'POST', '/domains/check', [], ['domains' => ['a.de', 'b.de']]
            ],
            'suggest' => [
                function ($c) { $c->suggestDomains('shop', ['.de', 'com']); },
                'GET', '/domains/suggest', ['q' => 'shop', 'defaults' => 'de,com']
            ],
            'register' => [
                function ($c) { $c->registerDomain(['domain' => 'a.de', 'contacts' => ['owner' => 'H1']]); },
                'POST', '/domains/register', [], ['domain' => 'a.de', 'contacts' => ['owner' => 'H1']]
            ],
            'transfer' => [
                function ($c) { $c->transferDomain(['domain' => 'a.de', 'authCode' => 'X']); },
                'POST', '/domains/transfer', [], ['domain' => 'a.de', 'authCode' => 'X']
            ],
            'update' => [
                function ($c) { $c->updateDomain('a.de', ['autoRenew' => false]); },
                'PATCH', '/domains/a.de', [], ['autoRenew' => false]
            ],
            'update contacts' => [
                function ($c) { $c->updateDomainContacts('a.de', ['owner' => 'H2'], true); },
                'PATCH', '/domains/a.de/contacts', [], ['contacts' => ['owner' => 'H2'], 'confirmOwnerChange' => true]
            ],
            'update contacts without confirm' => [
                function ($c) { $c->updateDomainContacts('a.de', ['tech' => 'H3']); },
                'PATCH', '/domains/a.de/contacts', [], ['contacts' => ['tech' => 'H3']]
            ],
            'delete scheduled' => [function ($c) { $c->deleteDomain('a.de'); }, 'DELETE', '/domains/a.de'],
            'delete immediate' => [
                function ($c) { $c->deleteDomain('a.de', true); },
                'DELETE', '/domains/a.de', ['immediate' => 'true']
            ],
            'state' => [function ($c) { $c->getDomainState('a.de'); }, 'GET', '/domains/a.de/state'],
            'authcode' => [function ($c) { $c->getAuthCode('a.de'); }, 'GET', '/domains/a.de/authcode'],
            'reset authcode' => [function ($c) { $c->resetAuthCode('a.de'); }, 'POST', '/domains/a.de/reset-authcode'],
            'cancel delete' => [function ($c) { $c->cancelDelete('a.de'); }, 'POST', '/domains/a.de/cancel-delete'],
            'dnssec get' => [function ($c) { $c->getDnssec('a.de'); }, 'GET', '/domains/a.de/dnssec'],
            'dnssec put' => [
                function ($c) { $c->updateDnssec('a.de', true, ['k1']); },
                'PUT', '/domains/a.de/dnssec', [], ['enabled' => true, 'dnskeys' => ['k1']]
            ],
            'dnssec disable' => [
                function ($c) { $c->updateDnssec('a.de', false); },
                'PUT', '/domains/a.de/dnssec', [], ['enabled' => false]
            ],
            'transfer out' => [
                function ($c) { $c->transferOut('a.de', false); },
                'POST', '/domains/a.de/transfer-out', [], ['approve' => false]
            ],
            'pending transfer outs' => [function ($c) { $c->getPendingTransferOuts(); }, 'GET', '/domains/transfer-out/pending'],
            'trade' => [
                function ($c) { $c->tradeDomain('a.eu', ['contacts' => ['owner' => 'H']]); },
                'POST', '/domains/a.eu/trade', [], ['contacts' => ['owner' => 'H']]
            ],
            'hold' => [
                function ($c) { $c->holdDomain('a.de', true, '2026-01-01', true); },
                'POST', '/domains/a.de/hold', [], ['disconnect' => true, 'execDate' => '2026-01-01', 'execOnExpire' => true]
            ],
            'list contacts' => [
                function ($c) { $c->listContacts(2, 50, 'max'); },
                'GET', '/contacts/', ['page' => '2', 'pageSize' => '50', 'search' => 'max']
            ],
            'create contact' => [
                function ($c) { $c->createContact(['firstName' => 'A']); },
                'POST', '/contacts/', [], ['firstName' => 'A']
            ],
            'update contact' => [
                function ($c) { $c->updateContact('H1', ['city' => 'Köln']); },
                'PATCH', '/contacts/H1', [], ['city' => 'Köln']
            ],
            'delete contact' => [function ($c) { $c->deleteContact('H1'); }, 'DELETE', '/contacts/H1'],
            'dns records' => [function ($c) { $c->getDnsRecords('a.de'); }, 'GET', '/dns/a.de'],
            'dns create' => [
                function ($c) { $c->createDnsRecord('a.de', ['type' => 'A', 'name' => '@', 'content' => '1.2.3.4']); },
                'POST', '/dns/a.de/records', [], ['type' => 'A', 'name' => '@', 'content' => '1.2.3.4']
            ],
            'dns update' => [
                function ($c) { $c->updateDnsRecord('a.de', '42', ['ttl' => 300]); },
                'PATCH', '/dns/a.de/records/42', [], ['ttl' => 300]
            ],
            'dns delete' => [function ($c) { $c->deleteDnsRecord('a.de', '42'); }, 'DELETE', '/dns/a.de/records/42'],
            'zone info' => [function ($c) { $c->getZoneInfo('a.de'); }, 'GET', '/dns/a.de/zone-info'],
            'zone export' => [function ($c) { $c->exportZone('a.de'); }, 'GET', '/dns/a.de/export'],
            'zone import' => [
                function ($c) { $c->importZone('a.de', 'www 300 IN A 1.2.3.4'); },
                'POST', '/dns/a.de/import', [], ['zoneFile' => 'www 300 IN A 1.2.3.4']
            ],
            'wallet' => [function ($c) { $c->getWallet(); }, 'GET', '/billing/wallet'],
        ];
    }

    public function testLastRequestIsRecorded()
    {
        $client = $this->client();
        $client->updateDomain('a.de', ['autoRenew' => true]);

        $this->assertSame(
            ['method' => 'PATCH', 'url' => 'https://domain-reseller-api.de/api/v1/domains/a.de', 'body' => ['autoRenew' => true]],
            $client->lastRequest()
        );
    }

    public function testTransportErrorsAreReported()
    {
        // Nothing listens on port 9 (discard) of the loopback interface
        $client = new DomainResellerApiClient(TestCase::API_KEY, 'http://127.0.0.1:9', false, 5);
        $response = $client->health();

        $this->assertFalse($response->success());
        $this->assertSame(0, $response->status());
        $this->assertStringStartsWith('Connection error: ', $response->error());
    }

    public function testResponseSuccess()
    {
        $response = new DomainResellerApiResponse(200, '{"success":true,"data":{"a":1},"message":"ok"}');

        $this->assertTrue($response->success());
        $this->assertSame(['a' => 1], $response->data());
        $this->assertSame('ok', $response->get('message'));
        $this->assertNull($response->error());
        $this->assertSame([], $response->errors());
    }

    public function testResponseWithoutEnvelopeIsSuccessfulOn2xx()
    {
        $response = new DomainResellerApiResponse(200, '{"status":"ok"}');

        $this->assertTrue($response->success());
        $this->assertSame('default', $response->data('default'));
    }

    public function testResponseErrorWithDetails()
    {
        $response = new DomainResellerApiResponse(400, '{"success":false,"error":"Validation error","details":"domain is required"}');

        $this->assertFalse($response->success());
        $this->assertSame('Validation error: domain is required', $response->error());
        $this->assertSame(['Validation error: domain is required'], $response->errors());
    }

    public function testResponseErrorFallsBackToMessage()
    {
        $response = new DomainResellerApiResponse(403, '{"success":false,"message":"Missing scope"}');

        $this->assertSame('Missing scope', $response->error());
    }

    public function testSuccessFalseOn200IsAnError()
    {
        $response = new DomainResellerApiResponse(200, '{"success":false,"error":"Contact not found"}');

        $this->assertFalse($response->success());
        $this->assertSame('Contact not found', $response->error());
    }

    public function testInvalidJson()
    {
        $response = new DomainResellerApiResponse(502, '<html>Bad gateway</html>');

        $this->assertFalse($response->success());
        $this->assertNull($response->body());
        $this->assertSame('<html>Bad gateway</html>', $response->raw());
        $this->assertSame('Invalid response from the Domain Reseller API (HTTP 502)', $response->error());
    }

    public function testErrorWithoutMessage()
    {
        $response = new DomainResellerApiResponse(500, '{"success":false}');

        $this->assertSame('The Domain Reseller API returned an error (HTTP 500)', $response->error());
    }
}
