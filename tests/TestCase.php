<?php

namespace Tests;

use DomainResellerApiHelper;
use FakeApiServer;
use FakeClients;
use FakeContacts;
use FakeCurrencies;
use FakeModuleClientMeta;
use FakeServices;
use Language;
use TestableDomainResellerApi;
use TestRegistry;
use View;

/**
 * Base test case providing a module wired to a fake API and Blesta fixtures
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    const API_KEY = 'drapi_abcdefghijkl_abcdefghijklmnopqrstuvwxyz012345';

    /** @var FakeApiServer */
    protected $server;

    /** @var TestableDomainResellerApi */
    protected $module;

    protected function setUp(): void
    {
        parent::setUp();

        TestRegistry::reset();
        Language::$lang = [];
        Language::$language = 'en_us';
        View::$rendered = [];

        TestRegistry::$models = [
            'Clients' => new FakeClients(),
            'Contacts' => new FakeContacts(),
            'ModuleClientMeta' => new FakeModuleClientMeta(),
            'Currencies' => new FakeCurrencies(),
            'Services' => new FakeServices()
        ];

        $this->server = new FakeApiServer();
        $this->module = $this->makeModule();
    }

    /**
     * Creates a module with a single module row
     *
     * @param array $meta Module row meta overrides
     * @return TestableDomainResellerApi
     */
    protected function makeModule(array $meta = [])
    {
        TestRegistry::$rows = [
            (object) [
                'id' => 1,
                'module_id' => 7,
                'meta' => (object) array_merge([
                    'label' => 'Main account',
                    'api_key' => self::API_KEY,
                    'api_url' => 'https://domain-reseller-api.de',
                    'sandbox' => 'false',
                    'allow_premium' => 'false',
                    'cancel_action' => 'schedule_delete',
                    'fallback_phone' => '',
                    'admin_handle' => '',
                    'tech_handle' => '',
                    'billing_handle' => ''
                ], $meta)
            ]
        ];

        $module = new TestableDomainResellerApi($this->server);
        $module->setModule((object) ['id' => 7, 'name' => 'Domain Reseller API', 'rows' => TestRegistry::$rows]);
        $module->setModuleRow(TestRegistry::$rows[0]);

        return $module;
    }

    /**
     * Adds a client to the fake Clients model
     *
     * @param array $overrides Client field overrides
     * @param array $numbers Phone numbers ([type => number])
     * @return \stdClass The client
     */
    protected function addClient(array $overrides = [], array $numbers = ['phone' => '030 1234567'])
    {
        $client = (object) array_merge([
            'id' => 5,
            'contact_id' => 50,
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'company' => '',
            'email' => 'max@example.com',
            'address1' => 'Musterstraße 12a',
            'address2' => '',
            'city' => 'Berlin',
            'state' => 'BE',
            'zip' => '10115',
            'country' => 'DE'
        ], $overrides);

        TestRegistry::$models['Clients']->clients[$client->id] = $client;
        TestRegistry::$models['Contacts']->numbers[$client->contact_id] = [];
        foreach ($numbers as $type => $number) {
            TestRegistry::$models['Contacts']->numbers[$client->contact_id][] = (object) [
                'type' => $type,
                'number' => $number
            ];
        }

        return $client;
    }

    /**
     * Builds a domain package
     *
     * @param array $meta Package meta overrides
     * @return \stdClass The package
     */
    protected function makePackage(array $meta = [])
    {
        return (object) [
            'id' => 3,
            'module_row' => 1,
            'meta' => (object) $meta,
            'pricing' => [
                (object) ['id' => 11, 'term' => 1, 'period' => 'year'],
                (object) ['id' => 12, 'term' => 2, 'period' => 'year']
            ]
        ];
    }

    /**
     * Builds a domain service
     *
     * @param string $domain The domain
     * @param array $options Configurable options (name => value)
     * @param array $package_meta Package meta
     * @return \stdClass The service
     */
    protected function makeService($domain = 'example.de', array $options = [], array $package_meta = [])
    {
        $service_options = [];
        foreach ($options as $name => $value) {
            $service_options[] = (object) ['option_name' => $name, 'option_value' => $value];
        }

        return (object) [
            'id' => 99,
            'client_id' => 5,
            'module_row_id' => 1,
            'pricing_id' => 11,
            'fields' => [
                (object) ['key' => 'domain', 'value' => $domain],
                (object) ['key' => 'ns1', 'value' => 'ns1.domain-reseller-api.de'],
                (object) ['key' => 'ns2', 'value' => 'ns2.domain-reseller-api.de']
            ],
            'options' => $service_options,
            'package' => (object) ['meta' => (object) $package_meta]
        ];
    }

    /**
     * Returns API domain info as returned by GET /domains/{domain}
     *
     * @param array $overrides Field overrides
     * @param array $state State flag overrides
     * @return array The domain data
     */
    protected function domainData(array $overrides = [], array $state = [])
    {
        $domain = $overrides['name'] ?? 'example.de';

        return array_merge([
            'id' => 'dom_1',
            'name' => $domain,
            'tld' => substr($domain, strpos($domain, '.') + 1),
            'status' => 'active',
            'registeredAt' => '2025-03-01T10:00:00.000Z',
            'expiresAt' => '2026-03-01T10:00:00.000Z',
            'autoRenew' => true,
            'nameservers' => ['ns1.domain-reseller-api.de', 'ns2.domain-reseller-api.de'],
            'useManagedDns' => true,
            'dnssecEnabled' => false,
            'dnskeys' => [],
            'contacts' => ['owner' => 'OWN-1', 'admin' => 'OWN-1', 'tech' => 'TECH-1', 'billing' => 'OWN-1'],
            'authCode' => null,
            'stateInfo' => array_merge([
                'description' => 'Active and operational',
                'canModify' => true,
                'canRenew' => true,
                'canRestore' => false,
                'canTransferOut' => true,
                'canTrade' => false,
                'canHold' => true,
                'canUnhold' => false,
                'canScheduleDelete' => true,
                'canCancelDelete' => false,
                'canDeleteImmediate' => false,
                'allowedActions' => ['RENEW', 'SCHEDULE_DELETE']
            ], $state)
        ], $overrides);
    }

    /**
     * Registers GET /domains/{domain}
     *
     * @param array $overrides Field overrides
     * @param array $state State flag overrides
     */
    protected function givenDomain(array $overrides = [], array $state = [])
    {
        $data = $this->domainData($overrides, $state);
        $this->server->on('GET', '/domains/' . $data['name'], 200, ['success' => true, 'data' => $data]);

        return $data;
    }

    /**
     * Returns an API contact
     *
     * @param string $handle The handle
     * @param array $overrides Field overrides
     * @return array The contact
     */
    protected function contactData($handle, array $overrides = [])
    {
        return array_merge([
            'id' => 'c_' . $handle,
            'handle' => $handle,
            'type' => 'person',
            'sex' => 'NA',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'organization' => 'Max Mustermann',
            'street' => 'Musterstraße',
            'houseNumber' => '12a',
            'city' => 'Berlin',
            'postalCode' => '10115',
            'state' => null,
            'country' => 'DE',
            'extension' => null,
            'email' => 'max@example.com',
            'phone' => '+49.301234567',
            'fax' => null
        ], $overrides);
    }

    /**
     * Registers GET /contacts/{handle}
     *
     * @param string $handle The handle
     * @param array $overrides Field overrides
     */
    protected function givenContact($handle, array $overrides = [])
    {
        $this->server->on('GET', '/contacts/' . $handle, 200, ['success' => true, 'data' => $this->contactData($handle, $overrides)]);
    }

    /**
     * Registers GET /pricing
     */
    protected function givenPricing()
    {
        $this->server->on('GET', '/pricing/', 200, [
            'success' => true,
            'currency' => 'EUR',
            'data' => [
                [
                    'tld' => 'de', 'registerPrice' => '3.50', 'renewPrice' => '3.50', 'transferPrice' => '0.00',
                    'restorePrice' => '25.00', 'setupPrice' => '1.00', 'minYears' => 1, 'maxYears' => 3
                ],
                [
                    'tld' => 'com', 'registerPrice' => '9.90', 'renewPrice' => '11.90', 'transferPrice' => '9.90',
                    'restorePrice' => null, 'setupPrice' => null, 'minYears' => 1, 'maxYears' => 10
                ]
            ]
        ]);
    }

    /**
     * @return array The current module errors (empty array if none)
     */
    protected function errors()
    {
        return $this->module->errors() ?: [];
    }

    /**
     * Asserts that the module has no errors
     */
    protected function assertNoErrors()
    {
        $this->assertSame([], $this->errors(), 'Unexpected module errors: ' . json_encode($this->errors()));
    }
}
