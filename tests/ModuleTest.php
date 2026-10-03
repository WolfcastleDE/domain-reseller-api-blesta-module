<?php

namespace Tests;

use Configure;
use Language;
use ModuleFields;
use TestRegistry;

class ModuleTest extends TestCase
{
    // ==================================================================
    // Metadata
    // ==================================================================

    public function testMetadata()
    {
        $this->assertSame('Domain Reseller API', $this->module->getName());
        $this->assertSame('registrar', $this->module->getType());
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $this->module->getVersion());
        $this->assertTrue($this->module->supportsFeature('dns_management'));
        $this->assertTrue($this->module->supportsFeature('epp_code'));
        $this->assertTrue($this->module->supportsFeature('id_protection'));
        $this->assertFalse($this->module->supportsFeature('email_forwarding'));
    }

    public function testConfigurationIsLoaded()
    {
        $this->assertArrayHasKey('domain', Configure::get('DomainResellerApi.domain_fields'));
        $this->assertArrayHasKey('auth', Configure::get('DomainResellerApi.transfer_fields'));
        $this->assertCount(5, Configure::get('DomainResellerApi.nameserver_fields'));
    }

    public function testGermanTranslationIsComplete()
    {
        $dir = dirname(__DIR__) . '/components/modules/domain_reseller_api/language/';
        $lang = [];
        include $dir . 'en_us/domain_reseller_api.php';
        $en = $lang;
        $lang = [];
        include $dir . 'de_de/domain_reseller_api.php';

        $this->assertSame([], array_diff(array_keys($en), array_keys($lang)));
        $this->assertSame([], array_diff(array_keys($lang), array_keys($en)));
    }

    // ==================================================================
    // Module rows
    // ==================================================================

    private function rowVars(array $overrides = [])
    {
        return array_merge([
            'label' => 'Main account',
            'api_key' => self::API_KEY,
            'api_url' => 'https://domain-reseller-api.de/',
            'sandbox' => 'true',
            'fallback_phone' => '',
            'admin_handle' => '',
            'tech_handle' => '',
            'billing_handle' => ''
        ], $overrides);
    }

    public function testAddModuleRow()
    {
        $this->server->on('GET', '/domains/', 200, ['success' => true, 'data' => ['domains' => [], 'pagination' => []]]);

        $vars = $this->rowVars();
        $meta = $this->module->addModuleRow($vars);

        $this->assertNoErrors();
        $meta = array_column($meta, null, 'key');
        $this->assertSame(self::API_KEY, $meta['api_key']['value']);
        $this->assertSame(1, $meta['api_key']['encrypted']);
        $this->assertSame(0, $meta['label']['encrypted']);
        $this->assertSame('https://domain-reseller-api.de', $meta['api_url']['value']);
        $this->assertSame('true', $meta['sandbox']['value']);
        $this->assertSame('false', $meta['allow_premium']['value']);
        $this->assertSame('schedule_delete', $meta['cancel_action']['value']);

        // The connection test uses the sandbox header and a minimal page
        $request = $this->server->requestsTo('GET', '/domains/')[0];
        $this->assertContains('x-sandbox: true', $request['headers']);
        $this->assertSame(['page' => '1', 'pageSize' => '1'], $request['query']);
    }

    public function testAddModuleRowRejectsInvalidKey()
    {
        $vars = $this->rowVars(['api_key' => 'not-a-key', 'label' => '']);
        $this->assertNull($this->module->addModuleRow($vars));

        $errors = $this->errors();
        $this->assertArrayHasKey('label', $errors);
        $this->assertArrayHasKey('format', $errors['api_key']);
        $this->assertArrayNotHasKey('valid_connection', $errors['api_key']);
        $this->assertSame([], $this->server->requests, 'No connection test for malformed keys');
    }

    public function testAddModuleRowRejectsFailedConnection()
    {
        $this->server->on('GET', '/domains/', 401, ['success' => false, 'error' => 'Unauthorized']);

        $vars = $this->rowVars();
        $this->assertNull($this->module->addModuleRow($vars));
        $this->assertArrayHasKey('valid_connection', $this->errors()['api_key']);
    }

    public function testAddModuleRowRejectsInsecureUrlAndBadOptions()
    {
        $vars = $this->rowVars([
            'api_url' => 'http://example.com',
            'fallback_phone' => 'call me',
            'cancel_action' => 'nonsense'
        ]);
        $this->assertNull($this->module->addModuleRow($vars));

        $errors = $this->errors();
        $this->assertArrayHasKey('api_url', $errors);
        $this->assertArrayHasKey('valid_connection', $errors['api_key']);
        $this->assertSame('schedule_delete', $vars['cancel_action']);
    }

    public function testAddModuleRowValidatesHandles()
    {
        $this->server->on('GET', '/domains/', 200, ['success' => true, 'data' => ['domains' => []]]);
        $this->givenContact('RESELLER-1');

        $vars = $this->rowVars(['tech_handle' => 'RESELLER-1', 'billing_handle' => 'UNKNOWN-1']);
        $this->assertNull($this->module->addModuleRow($vars));

        $errors = $this->errors();
        $this->assertArrayNotHasKey('tech_handle', $errors);
        $this->assertArrayHasKey('billing_handle', $errors);
    }

    public function testEditModuleRowKeepsExistingKey()
    {
        $this->server->on('GET', '/domains/', 200, ['success' => true, 'data' => ['domains' => []]]);

        $vars = $this->rowVars(['api_key' => '']);
        $meta = $this->module->editModuleRow(TestRegistry::$rows[0], $vars);

        $this->assertNoErrors();
        $this->assertSame(self::API_KEY, array_column($meta, 'value', 'key')['api_key']);
    }

    public function testManageViewsRender()
    {
        $this->server->on('GET', '/billing/wallet', 200, ['success' => true, 'data' => ['balance' => 12.5, 'currency' => 'EUR', 'postpaid' => false]]);

        $module = (object) ['id' => 7, 'name' => 'Domain Reseller API', 'rows' => TestRegistry::$rows];
        $vars = [];
        $html = $this->module->manageModule($module, $vars);
        $this->assertStringContainsString('Main account', $html);
        $this->assertStringContainsString('12.50 EUR', $html);

        $vars = [];
        $html = $this->module->manageAddRow($vars);
        $this->assertStringContainsString('name="api_key"', $html);
        $this->assertStringContainsString('https://domain-reseller-api.de', $html);

        $vars = [];
        $html = $this->module->manageEditRow(TestRegistry::$rows[0], $vars);
        $this->assertStringContainsString('Main account', $html);
        $this->assertStringNotContainsString(self::API_KEY, $html, 'The stored API key is never rendered');
    }

    public function testManageModuleWithUnavailableWallet()
    {
        $module = (object) ['id' => 7, 'name' => 'Domain Reseller API', 'rows' => TestRegistry::$rows];
        $vars = [];
        $html = $this->module->manageModule($module, $vars);

        $this->assertStringContainsString('Unavailable', $html);
    }

    // ==================================================================
    // Packages & service fields
    // ==================================================================

    public function testPackageFields()
    {
        $this->givenPricing();

        $fields = $this->module->getPackageFields((object) ['module_row' => 1, 'meta' => ['tlds' => ['.de']]]);
        $names = $fields->getFieldNames();

        $this->assertContains('meta[tlds][]', $names);
        $this->assertContains('meta[ns][]', $names);
        $this->assertContains('meta[epp_code]', $names);
        $this->assertContains('meta[dns_management]', $names);
    }

    public function testAddPackageNormalizesMeta()
    {
        $meta = $this->module->addPackage(['meta' => [
            'tlds' => ['.de', '.com'],
            'ns' => ['NS1.Example.com.', '', 'ns2.example.com'],
            'epp_code' => '1',
            'dns_management' => 'yes'
        ]]);

        $this->assertNoErrors();
        $meta = array_column($meta, 'value', 'key');
        $this->assertSame(['ns1.example.com', 'ns2.example.com'], $meta['ns']);
        $this->assertSame('1', $meta['epp_code']);
        $this->assertSame('0', $meta['dns_management']);
        $this->assertSame(['.de', '.com'], $meta['tlds']);
    }

    public function testEditPackageRejectsInvalidNameservers()
    {
        $this->assertNull($this->module->editPackage($this->makePackage(), ['meta' => ['ns' => ['not a host']]]));
        $this->assertArrayHasKey('meta[ns]', $this->errors());
    }

    public function testAdminAddFields()
    {
        $fields = $this->module->getAdminAddFields($this->makePackage(['ns' => ['ns1.a.de', 'ns2.a.de']]));

        $this->assertInstanceOf(ModuleFields::class, $fields);
        $names = $fields->getFieldNames();
        foreach (['domain', 'transfer', 'auth', 'ns1', 'ns5'] as $name) {
            $this->assertContains($name, $names);
        }
        $this->assertStringContainsString('domainResellerApiToggle', $fields->getHtml());
    }

    public function testClientAddFieldsForRegistration()
    {
        $vars = (object) ['domain' => 'example.de'];
        $names = $this->module->getClientAddFields($this->makePackage(['ns' => ['ns1.a.de', 'ns2.a.de']]), $vars)->getFieldNames();

        $this->assertContains('domain', $names);
        $this->assertContains('ns1', $names);
        $this->assertNotContains('auth', $names);
        $this->assertSame('ns1.a.de', $vars->ns1, 'Package nameservers are the defaults');
    }

    public function testClientAddFieldsForTransfer()
    {
        $names = $this->module->getClientAddFields($this->makePackage(), (object) ['domain' => 'example.de', 'transfer' => '1'])->getFieldNames();

        $this->assertContains('auth', $names);
        $this->assertContains('transfer', $names);
        $this->assertNotContains('ns1', $names);
    }

    public function testAdminEditFields()
    {
        $this->assertSame(['domain'], $this->module->getAdminEditFields($this->makePackage())->getFieldNames());
    }

    public function testValidateService()
    {
        $this->assertTrue($this->module->validateService($this->makePackage(), ['domain' => 'example.de', 'ns1' => 'ns1.a.de', 'ns2' => 'ns2.a.de']));

        $this->assertFalse($this->module->validateService($this->makePackage(), ['domain' => 'invalid']));
        $this->assertArrayHasKey('domain', $this->errors());

        $this->assertFalse($this->module->validateService($this->makePackage(), ['domain' => 'example.de', 'ns1' => 'ns1.a.de']));
        $this->assertArrayHasKey('count', $this->errors()['ns2']);

        $this->assertFalse($this->module->validateService($this->makePackage(), ['domain' => 'example.de', 'ns1' => 'bad host', 'ns2' => 'ns2.a.de']));
        $this->assertArrayHasKey('ns1', $this->errors());

        $this->assertFalse($this->module->validateService($this->makePackage(), ['domain' => 'example.de', 'transfer' => '1', 'auth' => ' ']));
        $this->assertArrayHasKey('auth', $this->errors());
    }

    public function testValidateServiceEdit()
    {
        $this->assertTrue($this->module->validateServiceEdit($this->makeService(), []));
        $this->assertFalse($this->module->validateServiceEdit($this->makeService(), ['domain' => 'bad']));
    }

    // ==================================================================
    // Registration
    // ==================================================================

    private function givenRegistrationWorks($domain = 'example.de', array $check = [])
    {
        $this->server->on('GET', '/domains/check/' . $domain, 200, ['success' => true, 'data' => array_merge([
            'domain' => $domain, 'available' => true, 'premium' => false, 'price' => 3.5, 'currency' => 'EUR'
        ], $check)]);
        $this->server->on('POST', '/contacts/', 200, ['success' => true, 'data' => ['id' => 'c1', 'handle' => 'NEW-HANDLE-1', 'message' => 'ok']]);
        $this->server->on('POST', '/domains/register', 200, ['success' => true, 'data' => [
            'domainId' => 'd1', 'domain' => $domain, 'status' => 'active', 'expiresAt' => '2027-01-01T00:00:00Z'
        ]]);
    }

    private function orderVars(array $overrides = [])
    {
        return array_merge([
            'domain' => 'Example.DE',
            'client_id' => 5,
            'pricing_id' => 12,
            'use_module' => 'true',
            'ns1' => 'ns1.example.net',
            'ns2' => 'ns2.example.net'
        ], $overrides);
    }

    public function testAddServiceRegistersDomain()
    {
        $this->addClient();
        $this->givenRegistrationWorks();

        $meta = $this->module->addService($this->makePackage(), $this->orderVars());

        $this->assertNoErrors();
        $this->assertSame(
            ['GET /domains/check/example.de', 'POST /contacts/', 'POST /domains/register', 'PATCH /domains/example.de'],
            $this->server->summary()
        );
        $this->assertSame(
            ['autoRenew' => false],
            $this->server->requestsTo('PATCH', '/domains/example.de')[0]['body'],
            'Renewals are triggered by Blesta, not by the API'
        );

        $contact = $this->server->requestsTo('POST', '/contacts/')[0]['body'];
        $this->assertSame('person', $contact['type']);
        $this->assertArrayNotHasKey('organization', $contact, 'Natural persons have no organisation');
        $this->assertSame('Musterstraße', $contact['street']);
        $this->assertSame('12a', $contact['houseNumber']);
        $this->assertSame('+49.301234567', $contact['phone']);
        $this->assertArrayNotHasKey('protection', $contact);

        $register = $this->server->requestsTo('POST', '/domains/register')[0]['body'];
        $this->assertSame([
            'domain' => 'example.de',
            'years' => 2,
            'contacts' => ['owner' => 'NEW-HANDLE-1'],
            'nameservers' => ['ns1.example.net', 'ns2.example.net']
        ], $register);

        $meta = array_column($meta, 'value', 'key');
        $this->assertSame('example.de', $meta['domain']);
        $this->assertSame('ns1.example.net', $meta['ns1']);
        $this->assertSame('', $meta['ns3']);
    }

    public function testAddServiceUsesManagedDnsWithoutNameservers()
    {
        $this->addClient();
        $this->givenRegistrationWorks();

        $this->module->addService($this->makePackage(), $this->orderVars(['ns1' => '', 'ns2' => '']));

        $this->assertNoErrors();
        $register = $this->server->requestsTo('POST', '/domains/register')[0]['body'];
        $this->assertArrayNotHasKey('nameservers', $register);
    }

    public function testAddServiceUsesAccountHandles()
    {
        $this->module = $this->makeModule(['admin_handle' => 'RES-ADMIN', 'tech_handle' => 'RES-TECH']);
        $this->addClient(['company' => 'ACME GmbH']);
        $this->givenRegistrationWorks();

        $this->module->addService($this->makePackage(), $this->orderVars());

        $this->assertNoErrors();
        $this->assertSame('organization', $this->server->requestsTo('POST', '/contacts/')[0]['body']['type']);
        $this->assertSame('ACME GmbH', $this->server->requestsTo('POST', '/contacts/')[0]['body']['organization']);
        $this->assertSame(
            ['owner' => 'NEW-HANDLE-1', 'admin' => 'RES-ADMIN', 'tech' => 'RES-TECH'],
            $this->server->requestsTo('POST', '/domains/register')[0]['body']['contacts']
        );
    }

    public function testContactHandlesAreReused()
    {
        $this->addClient();
        $this->givenRegistrationWorks();
        $this->module->addService($this->makePackage(), $this->orderVars());
        $this->assertNoErrors();

        $this->givenRegistrationWorks('second.de');
        $this->givenContact('NEW-HANDLE-1');
        $this->module->addService($this->makePackage(), $this->orderVars(['domain' => 'second.de']));

        $this->assertNoErrors();
        $this->assertCount(1, $this->server->requestsTo('POST', '/contacts/'), 'The contact is only created once');
        $this->assertCount(1, $this->server->requestsTo('GET', '/contacts/NEW-HANDLE-1'));
        $this->assertSame('NEW-HANDLE-1', $this->server->requestsTo('POST', '/domains/register')[1]['body']['contacts']['owner']);
    }

    public function testDeletedReusedHandleIsRecreated()
    {
        $this->addClient();
        $this->givenRegistrationWorks();
        $this->module->addService($this->makePackage(), $this->orderVars());

        // GET /contacts/NEW-HANDLE-1 is not registered and answers 404
        $this->givenRegistrationWorks('second.de');
        $this->module->addService($this->makePackage(), $this->orderVars(['domain' => 'second.de']));

        $this->assertNoErrors();
        $this->assertCount(2, $this->server->requestsTo('POST', '/contacts/'));
    }

    public function testAddServiceWithoutModuleDoesNotCallTheApi()
    {
        $meta = $this->module->addService($this->makePackage(), $this->orderVars(['use_module' => 'false']));

        $this->assertNoErrors();
        $this->assertSame([], $this->server->requests);
        $this->assertSame('example.de', array_column($meta, 'value', 'key')['domain']);
    }

    public function testAddServiceRejectsPremiumDomains()
    {
        $this->addClient();
        $this->givenRegistrationWorks('example.de', ['premium' => true, 'price' => 499]);

        $this->assertNull($this->module->addService($this->makePackage(), $this->orderVars()));
        $this->assertArrayHasKey('premium', $this->errors()['domain']);
        $this->assertSame([], $this->server->requestsTo('POST', '/domains/register'));
        $this->assertSame([], $this->server->requestsTo('POST', '/contacts/'), 'No contact is created for rejected domains');
    }

    public function testAddServiceAllowsPremiumDomainsWhenEnabled()
    {
        $this->module = $this->makeModule(['allow_premium' => 'true']);
        $this->addClient();
        $this->givenRegistrationWorks('example.de', ['premium' => true]);

        $this->module->addService($this->makePackage(), $this->orderVars());

        $this->assertNoErrors();
        $this->assertSame([], $this->server->requestsTo('GET', '/domains/check/example.de'), 'No premium check is needed');
        $this->assertCount(1, $this->server->requestsTo('POST', '/domains/register'));
    }

    public function testAddServiceRejectsUnavailableDomains()
    {
        $this->addClient();
        $this->givenRegistrationWorks('example.de', ['available' => false]);

        $this->assertNull($this->module->addService($this->makePackage(), $this->orderVars()));
        $this->assertArrayHasKey('unavailable', $this->errors()['domain']);
    }

    public function testAddServiceReportsApiErrors()
    {
        $this->addClient();
        $this->givenRegistrationWorks();
        $this->server->on('POST', '/domains/register', 402, ['success' => false, 'error' => 'Insufficient wallet balance']);

        $this->assertNull($this->module->addService($this->makePackage(), $this->orderVars()));
        $this->assertSame(
            'Domain Reseller API: Insufficient wallet balance',
            $this->errors()['api']['response']
        );
    }

    public function testAddServiceRetriesWhileZoneIsNotAuthoritative()
    {
        $this->addClient();
        $this->givenRegistrationWorks();
        $this->server->on('POST', '/domains/register', 503, ['success' => false, 'error' => 'DNS zone not ready'], 1);

        $this->module->addService($this->makePackage(), $this->orderVars(['ns1' => '', 'ns2' => '']));

        $this->assertNoErrors();
        $this->assertCount(2, $this->server->requestsTo('POST', '/domains/register'));
        $this->assertSame([15], $this->module->pauses);
    }

    public function testAddServiceGivesUpAfterRetries()
    {
        $this->addClient();
        $this->givenRegistrationWorks();
        $this->server->on('POST', '/domains/register', 503, ['success' => false, 'error' => 'DNS zone not ready']);

        $this->assertNull($this->module->addService($this->makePackage(), $this->orderVars()));
        $this->assertCount(3, $this->server->requestsTo('POST', '/domains/register'));
        $this->assertArrayHasKey('api', $this->errors());
    }

    public function testIdProtectionRequestsWhoisPrivacy()
    {
        $this->addClient();
        $this->givenRegistrationWorks('example.com');

        $this->module->addService(
            $this->makePackage(),
            $this->orderVars(['domain' => 'example.com', 'configoptions' => ['id_protection' => 1]])
        );

        $this->assertNoErrors();
        $this->assertTrue($this->server->requestsTo('POST', '/contacts/')[0]['body']['protection']);
        $register = $this->server->requestsTo('POST', '/domains/register')[0]['body'];
        $this->assertTrue($register['whoisPrivacy']);
        $this->assertTrue($register['acceptWppTerms']);
    }

    public function testIdProtectionFallsBackToContactProtection()
    {
        $this->addClient();
        $this->givenRegistrationWorks();
        $this->server->on('POST', '/domains/register', 400, [
            'success' => false,
            'error' => 'WHOIS privacy is not available for this TLD. For Vautron TLDs use the contact-level WHOIS protection flag instead.'
        ], 1);

        $this->module->addService($this->makePackage(), $this->orderVars(['configoptions' => ['id_protection' => 1]]));

        $this->assertNoErrors();
        $requests = $this->server->requestsTo('POST', '/domains/register');
        $this->assertCount(2, $requests);
        $this->assertArrayNotHasKey('whoisPrivacy', $requests[1]['body']);
        $this->assertTrue($this->server->requestsTo('POST', '/contacts/')[0]['body']['protection']);
    }

    public function testIdProtectionForOrganizationsOnlyUsesContactProtection()
    {
        $this->addClient(['company' => 'ACME GmbH']);
        $this->givenRegistrationWorks();

        $this->module->addService($this->makePackage(), $this->orderVars(['configoptions' => ['id_protection' => 1]]));

        $this->assertNoErrors();
        $this->assertArrayNotHasKey('whoisPrivacy', $this->server->requestsTo('POST', '/domains/register')[0]['body']);
    }

    public function testClientWithoutPhoneNeedsFallback()
    {
        $this->addClient([], []);

        $this->assertNull($this->module->addService($this->makePackage(), $this->orderVars()));
        $this->assertArrayHasKey('phone', $this->errors()['contact']);
        $this->assertSame([], $this->server->requestsTo('POST', '/contacts/'));
        $this->assertSame([], $this->server->requestsTo('POST', '/domains/register'));
    }

    public function testFallbackPhoneIsUsed()
    {
        $this->module = $this->makeModule(['fallback_phone' => '+49.40123456']);
        $this->addClient([], []);
        $this->givenRegistrationWorks();

        $this->module->addService($this->makePackage(), $this->orderVars());

        $this->assertNoErrors();
        $this->assertSame('+49.40123456', $this->server->requestsTo('POST', '/contacts/')[0]['body']['phone']);
    }

    public function testMissingClient()
    {
        $this->assertNull($this->module->addService($this->makePackage(), $this->orderVars(['client_id' => 404])));
        $this->assertArrayHasKey('client_id', $this->errors());
    }

    public function testContactCreationErrorIsReported()
    {
        $this->addClient();
        $this->givenRegistrationWorks();
        $this->server->on('POST', '/contacts/', 200, ['success' => false, 'error' => 'Invalid postal code']);

        $this->assertNull($this->module->addService($this->makePackage(), $this->orderVars()));
        $this->assertSame('Domain Reseller API: Invalid postal code', $this->errors()['contact']['response']);
        $this->assertSame([], $this->server->requestsTo('POST', '/domains/register'));
    }

    public function testMissingModuleRow()
    {
        TestRegistry::$rows = [];
        $module = new \TestableDomainResellerApi($this->server);
        $module->setModule((object) ['id' => 7]);

        $this->assertNull($module->addService($this->makePackage(), $this->orderVars()));
        $this->assertArrayHasKey('module_row', $module->errors());
    }

    // ==================================================================
    // Transfers
    // ==================================================================

    public function testAddServiceTransfersDomain()
    {
        $this->addClient();
        $this->server->on('POST', '/contacts/', 200, ['success' => true, 'data' => ['handle' => 'NEW-HANDLE-1']]);
        $this->server->on('POST', '/domains/transfer', 200, ['success' => true, 'data' => [
            'domainId' => 'd1', 'domain' => 'example.de', 'status' => 'pending_transfer', 'message' => 'ok'
        ]]);

        $meta = $this->module->addService(
            $this->makePackage(),
            $this->orderVars(['transfer' => '1', 'auth' => ' SECRET-123 ', 'ns1' => '', 'ns2' => ''])
        );

        $this->assertNoErrors();
        $this->assertSame(['POST /contacts/', 'POST /domains/transfer'], $this->server->summary());
        $this->assertSame(
            ['domain' => 'example.de', 'authCode' => 'SECRET-123', 'contacts' => ['owner' => 'NEW-HANDLE-1']],
            $this->server->requestsTo('POST', '/domains/transfer')[0]['body']
        );
        $this->assertNotContains('SECRET-123', array_column($meta, 'value'), 'The auth code is not stored');

        // The auth code is masked in the module log
        foreach ($this->module->logs as $log) {
            $this->assertStringNotContainsString('SECRET-123', (string) $log['data']);
        }
    }

    public function testTransferWithoutAuthCodeFails()
    {
        $this->assertNull($this->module->addService($this->makePackage(), $this->orderVars(['transfer' => '1'])));
        $this->assertArrayHasKey('auth', $this->errors());
        $this->assertFalse($this->module->transferDomain('example.de', 1, ['contacts' => ['owner' => 'H']]));
        $this->assertFalse($this->module->transferDomain('example.de', 1, ['auth' => 'X']));
        $this->assertArrayHasKey('contacts', $this->errors());
        $this->assertSame([], $this->server->requests);
    }

    public function testCheckTransferAvailability()
    {
        $this->server->on('GET', '/domains/check/taken.de', 200, ['success' => true, 'data' => ['available' => false, 'premium' => false]]);
        $this->server->on('GET', '/domains/check/free.de', 200, ['success' => true, 'data' => ['available' => true, 'premium' => false]]);

        $this->assertTrue($this->module->checkTransferAvailability('taken.de', 1));
        $this->assertFalse($this->module->checkTransferAvailability('free.de', 1));
        $this->assertTrue($this->module->checkTransferAvailability('error.de', 1), 'API errors do not block transfers');
    }

    // ==================================================================
    // Availability & pricing
    // ==================================================================

    public function testCheckAvailability()
    {
        $this->server->on('GET', '/domains/check/free.de', 200, ['success' => true, 'data' => ['available' => true, 'premium' => false]]);
        $this->server->on('GET', '/domains/check/taken.de', 200, ['success' => true, 'data' => ['available' => false, 'premium' => false]]);
        $this->server->on('GET', '/domains/check/premium.com', 200, ['success' => true, 'data' => ['available' => true, 'premium' => true]]);

        $this->assertTrue($this->module->checkAvailability('Free.de', 1));
        $this->assertFalse($this->module->checkAvailability('taken.de', 1));
        $this->assertFalse($this->module->checkAvailability('error.de', 1));
        $this->assertFalse($this->module->checkAvailability('premium.com', 1));
        $this->assertArrayHasKey('premium', $this->errors()['domain']);

        $module = $this->makeModule(['allow_premium' => 'true']);
        $this->assertTrue($module->checkAvailability('premium.com', 1));
    }

    public function testGetTlds()
    {
        $this->givenPricing();

        $this->assertSame(['.de', '.com'], $this->module->getTlds(1));
        $this->assertCount(1, $this->server->requests, 'Pricing is cached per request');
    }

    public function testGetTldsFallsBackToDefaults()
    {
        $this->assertContains('.de', $this->module->getTlds(1));
    }

    public function testPricingIsCachedAcrossRequests()
    {
        Configure::set('Caching.on', true);
        $this->givenPricing();
        $this->module->getTlds(1);

        $module = $this->makeModule();
        $this->assertSame(['.de', '.com'], $module->getTlds(1));
        $this->assertCount(1, $this->server->requests);
    }

    public function testGetTldPricing()
    {
        $this->givenPricing();

        $pricing = $this->module->getTldPricing(1);

        $this->assertSame(['EUR', 'USD'], array_keys($pricing['.de']));
        $this->assertSame([1, 2, 3], array_keys($pricing['.de']['EUR']));
        $this->assertSame(['register' => 4.5, 'transfer' => 1.0, 'renew' => 3.5], $pricing['.de']['EUR'][1]);
        $this->assertSame(['register' => 8.0, 'transfer' => 1.0, 'renew' => 7.0], $pricing['.de']['EUR'][2]);
        $this->assertSame(['register' => 4.95, 'transfer' => 1.1, 'renew' => 3.85], $pricing['.de']['USD'][1]);
        $this->assertCount(10, $pricing['.com']['EUR']);
    }

    public function testGetFilteredTldPricing()
    {
        $this->givenPricing();

        $pricing = $this->module->getFilteredTldPricing(null, ['tlds' => ['.com'], 'currencies' => ['USD'], 'terms' => [1]]);

        $this->assertSame(['.com'], array_keys($pricing));
        $this->assertSame(['USD'], array_keys($pricing['.com']));
        $this->assertSame([1], array_keys($pricing['.com']['USD']));
    }

    public function testPricingUnavailable()
    {
        $this->assertSame([], $this->module->getTldPricing(1));
    }

    public function testIsValidTermUsesBookableTerms()
    {
        $this->server->on('GET', '/pricing/', 200, ['success' => true, 'data' => [
            ['tld' => 'ai', 'registerPrice' => '60.00', 'renewPrice' => '60.00', 'transferPrice' => '120.00', 'minYears' => 2, 'maxYears' => 10,
                'periods' => [['years' => 2, 'registerPrice' => '120.00', 'renewPrice' => '120.00'], ['years' => 4, 'registerPrice' => '200.00', 'renewPrice' => null]]]
        ]]);

        $this->assertTrue($this->module->isValidTerm('.ai', 2));
        $this->assertFalse($this->module->isValidTerm('.ai', 3));
        $this->assertTrue($this->module->isValidTerm('.ai', 4));

        $pricing = $this->module->getFilteredTldPricing(null, ['currencies' => ['EUR']]);
        $this->assertSame([2, 4], array_keys($pricing['.ai']['EUR']));
        $this->assertSame(['register' => 200.0, 'transfer' => 120.0, 'renew' => 240.0], $pricing['.ai']['EUR'][4]);
    }

    public function testIsValidTerm()
    {
        $this->givenPricing();

        $this->assertTrue($this->module->isValidTerm('.de', 3));
        $this->assertFalse($this->module->isValidTerm('.de', 4));
        $this->assertTrue($this->module->isValidTerm('.com', 10));
        $this->assertFalse($this->module->isValidTerm('.com', 11));
        $this->assertTrue($this->module->isValidTerm('.xyz', 5), 'Unknown TLDs allow 1-10 years');
        $this->assertTrue($this->module->isValidTerm('.de', 1, true));
        $this->assertFalse($this->module->isValidTerm('.de', 2, true));
    }

    // ==================================================================
    // Domain information
    // ==================================================================

    public function testDates()
    {
        $this->givenDomain();
        $service = $this->makeService();

        $this->assertSame('2026-03-01 10:00:00', $this->module->getExpirationDate($service));
        $this->assertSame('2025-03-01', $this->module->getRegistrationDate($service, 'Y-m-d'));
        $this->assertCount(1, $this->server->requests, 'Domain info is cached per request');
    }

    public function testDatesOfUnknownDomainDoNotRaiseErrors()
    {
        $this->assertFalse($this->module->getExpirationDate($this->makeService('unknown.de')));
        $this->assertNoErrors();
    }

    public function testGetDomainInfo()
    {
        $data = $this->givenDomain();

        $this->assertSame($data, $this->module->getDomainInfo('EXAMPLE.de', 1));
        $this->assertSame([], $this->module->getDomainInfo('unknown.de', 1));
        $this->assertSame('Domain Reseller API: Not found', $this->errors()['api']['response']);
    }

    public function testGetDomainNameServers()
    {
        $this->givenDomain(['nameservers' => ['ns1.example.net', 'ns2.example.net']]);

        $this->assertSame(
            [['url' => 'ns1.example.net', 'ips' => []], ['url' => 'ns2.example.net', 'ips' => []]],
            $this->module->getDomainNameServers('example.de', 1)
        );
    }

    public function testSetCustomNameserversDisablesManagedDns()
    {
        $this->givenDomain();
        $this->server->on('PATCH', '/domains/example.de', 200, ['success' => true]);

        $this->assertTrue($this->module->setDomainNameservers('example.de', 1, ['NS1.example.net', 'ns2.example.net', '']));

        $this->assertSame(
            ['nameservers' => ['ns1.example.net', 'ns2.example.net'], 'useManagedDns' => false],
            $this->server->requestsTo('PATCH', '/domains/example.de')[0]['body']
        );
    }

    public function testSetCustomNameserversOnCustomDomain()
    {
        $this->givenDomain(['useManagedDns' => false, 'nameservers' => ['a.example.net', 'b.example.net']]);
        $this->server->on('PATCH', '/domains/example.de', 200, ['success' => true]);

        $this->assertTrue($this->module->setDomainNameservers('example.de', 1, ['c.example.net', 'd.example.net']));
        $this->assertSame(
            ['nameservers' => ['c.example.net', 'd.example.net']],
            $this->server->requestsTo('PATCH', '/domains/example.de')[0]['body']
        );
    }

    public function testSetManagedNameserversEnablesManagedDns()
    {
        $this->givenDomain(['useManagedDns' => false, 'nameservers' => ['a.example.net', 'b.example.net']]);
        $this->server->on('PATCH', '/domains/example.de', 200, ['success' => true]);

        $this->assertTrue($this->module->setDomainNameservers('example.de', 1, ['ns1.domain-reseller-api.de', 'ns2.domain-reseller-api.de']));
        $this->assertSame(['useManagedDns' => true], $this->server->requestsTo('PATCH', '/domains/example.de')[0]['body']);
    }

    public function testSetManagedNameserversOnManagedDomainIsNoop()
    {
        $this->givenDomain();

        $this->assertTrue($this->module->setDomainNameservers('example.de', 1, ['ns1.domain-reseller-api.de', 'ns2.domain-reseller-api.de']));
        $this->assertSame([], $this->server->requestsTo('PATCH', '/domains/example.de'));
    }

    public function testSetNameserversValidation()
    {
        $this->assertFalse($this->module->setDomainNameservers('example.de', 1, ['ns1.example.net']));
        $this->assertArrayHasKey('count', $this->errors()['nameservers']);

        $this->assertFalse($this->module->setDomainNameservers('example.de', 1, ['ns1.example.net', 'invalid']));
        $this->assertArrayHasKey('invalid', $this->errors()['nameservers']);
        $this->assertSame([], $this->server->requests);
    }

    public function testSetNameserversReportsApiError()
    {
        $this->givenDomain(['useManagedDns' => false]);
        $this->server->on('PATCH', '/domains/example.de', 422, ['success' => false, 'error' => 'Nameserver-Validierung fehlgeschlagen']);

        $this->assertFalse($this->module->setDomainNameservers('example.de', 1, ['a.example.net', 'b.example.net']));
        $this->assertStringContainsString('Nameserver-Validierung', $this->errors()['api']['response']);
    }

    public function testGetDomainContacts()
    {
        $this->givenDomain();
        $this->givenContact('OWN-1');
        $this->givenContact('TECH-1', ['type' => 'organization', 'organization' => 'Hosting AG', 'firstName' => 'Tech', 'lastName' => 'Support']);

        $contacts = $this->module->getDomainContacts('example.de', 1);

        $this->assertCount(2, $contacts);
        $this->assertSame('OWN-1', $contacts[0]->external_id);
        $this->assertSame('Musterstraße 12a', $contacts[0]->address1);
        $this->assertSame('', $contacts[0]->company);
        $this->assertSame('Hosting AG', $contacts[1]->company);
        $this->assertCount(1, $this->server->requestsTo('GET', '/contacts/OWN-1'), 'Shared handles are fetched once');
    }

    public function testSetDomainContacts()
    {
        $this->module = $this->makeModule(['tech_handle' => 'TECH-1']);
        $this->givenDomain();
        $this->server->on('PATCH', '/contacts/OWN-1', 200, ['success' => true, 'message' => 'ok']);

        $result = $this->module->setDomainContacts('example.de', [[
            'external_id' => 'OWN-1',
            'first_name' => 'Erika',
            'last_name' => 'Musterfrau',
            'company' => '',
            'email' => 'erika@example.com',
            'phone' => '+49 30 7654321',
            'address1' => 'Neuer Weg 7',
            'address2' => '',
            'city' => 'Potsdam',
            'state' => '',
            'zip' => '14467',
            'country' => 'de'
        ]], 1);

        $this->assertTrue($result);
        $this->assertNoErrors();
        $body = $this->server->requestsTo('PATCH', '/contacts/OWN-1')[0]['body'];
        $this->assertSame('', $body['organization'], 'A person contact has its organisation removed');
        $this->assertSame('Neuer Weg', $body['street']);
        $this->assertSame('7', $body['houseNumber']);
        $this->assertSame('+49.307654321', $body['phone']);
        $this->assertSame('DE', $body['country']);
    }

    public function testSetDomainContactsProtectsForeignAndAccountHandles()
    {
        $this->module = $this->makeModule(['tech_handle' => 'TECH-1']);
        $this->givenDomain();

        $valid = ['email' => 'a@b.de', 'phone' => '+49.301', 'country' => 'DE', 'first_name' => 'A', 'last_name' => 'B'];
        $result = $this->module->setDomainContacts('example.de', [
            ['external_id' => 'TECH-1'] + $valid,
            ['external_id' => 'SOMEONE-ELSE'] + $valid,
            ['external_id' => 'OWN-1', 'email' => 'invalid', 'phone' => '', 'country' => 'Germany']
        ], 1);

        $this->assertFalse($result);
        $errors = $this->errors()['contacts'];
        $this->assertArrayHasKey('TECH-1_protected', $errors);
        $this->assertArrayHasKey('SOMEONE-ELSE_unknown', $errors);
        $this->assertArrayHasKey('OWN-1_email', $errors);
        $this->assertArrayHasKey('OWN-1_phone', $errors);
        $this->assertArrayHasKey('OWN-1_country', $errors);
        $this->assertCount(0, array_filter($this->server->requests, function ($r) {
            return $r['method'] === 'PATCH';
        }));
    }

    public function testTransferLock()
    {
        $this->server->on('GET', '/domains/example.com/lock', 200, ['success' => true, 'data' => ['domain' => 'example.com', 'supported' => true, 'locked' => true]]);
        $this->server->on('GET', '/domains/example.de/lock', 200, ['success' => true, 'data' => ['domain' => 'example.de', 'supported' => false, 'locked' => null]]);
        $this->server->on('PUT', '/domains/example.com/lock', 200, ['success' => true, 'data' => ['domain' => 'example.com', 'locked' => false]]);

        $this->assertTrue($this->module->getDomainIsLocked('example.com', 1));
        $this->assertFalse($this->module->getDomainIsLocked('example.de', 1));
        $this->assertTrue($this->module->unlockDomain('example.com', 1));
        $this->assertTrue($this->module->lockDomain('example.com', 1));
        $this->assertNoErrors();

        $requests = $this->server->requestsTo('PUT', '/domains/example.com/lock');
        $this->assertSame(['locked' => false], $requests[0]['body']);
        $this->assertSame(['locked' => true], $requests[1]['body']);

        $this->server->on('PUT', '/domains/example.de/lock', 422, ['success' => false, 'error' => 'Transfer lock is not supported for this TLD']);
        $this->assertFalse($this->module->lockDomain('example.de', 1));
        $this->assertStringContainsString('not supported', $this->errors()['api']['response']);
    }

    public function testSetNameserverIps()
    {
        $this->givenDomain();
        $this->server->on('GET', '/domains/example.de/hosts', 200, ['success' => true, 'data' => ['domain' => 'example.de', 'hosts' => [
            ['hostname' => 'ns1.example.de', 'ipv4' => '192.0.2.1']
        ]]]);
        $this->server->on('PUT', '/domains/example.de/hosts/ns1.example.de', 200, ['success' => true, 'data' => ['hostname' => 'ns1.example.de']]);
        $this->server->on('POST', '/domains/example.de/hosts', 200, ['success' => true, 'data' => ['hostname' => 'ns2.example.de']]);

        $this->assertTrue($this->module->setNameserverIps([
            'NS1.example.de' => ['192.0.2.10', '2001:db8::10'],
            'ns2.example.de' => ['192.0.2.11']
        ], 1));

        $this->assertNoErrors();
        $this->assertSame(['ipv4' => '192.0.2.10', 'ipv6' => '2001:db8::10'], $this->server->requestsTo('PUT', '/domains/example.de/hosts/ns1.example.de')[0]['body']);
        $this->assertSame(['hostname' => 'ns2.example.de', 'ipv4' => '192.0.2.11'], $this->server->requestsTo('POST', '/domains/example.de/hosts')[0]['body']);
    }

    public function testSetNameserverIpsForUnknownDomain()
    {
        $this->assertFalse($this->module->setNameserverIps(['ns1.unknown.de' => ['192.0.2.1']], 1));
        $this->assertArrayHasKey('hosts', $this->errors());
    }

    // ==================================================================
    // Lifecycle
    // ==================================================================

    public function testRenewServiceRenewsTheBookedTerm()
    {
        $this->givenDomain();
        $this->server->on('POST', '/domains/example.de/renew', 200, ['success' => true, 'data' => [
            'domain' => 'example.de', 'years' => 2, 'expiresAt' => '2028-03-01T10:00:00.000Z', 'message' => 'ok'
        ]]);
        $this->server->on('PATCH', '/domains/example.de', 200, ['success' => true]);
        $service = $this->makeService();
        $service->pricing_id = 12;

        $this->assertNull($this->module->renewService($this->makePackage(), $service));

        $this->assertNoErrors();
        $this->assertSame(['years' => 2], $this->server->requestsTo('POST', '/domains/example.de/renew')[0]['body']);
        $this->assertSame(
            ['autoRenew' => false],
            $this->server->requestsTo('PATCH', '/domains/example.de')[0]['body'],
            'The API must not renew the domain a second time'
        );
    }

    public function testRenewExpiredDomain()
    {
        $this->givenDomain(['status' => 'expired']);
        $this->server->on('POST', '/domains/example.de/renew', 200, ['success' => true, 'data' => ['domain' => 'example.de', 'years' => 1]]);

        $this->assertTrue($this->module->renewDomain('example.de', 1, ['years' => 1]));
        $this->assertCount(1, $this->server->requestsTo('POST', '/domains/example.de/renew'));
    }

    public function testRenewReportsApiErrors()
    {
        $this->givenDomain();
        $this->server->on('POST', '/domains/example.de/renew', 402, ['success' => false, 'error' => 'Insufficient wallet balance']);

        $this->assertFalse($this->module->renewDomain('example.de', 1, ['years' => 1]));
        $this->assertSame('Domain Reseller API: Insufficient wallet balance', $this->errors()['api']['response']);
        $this->assertSame([], $this->server->requestsTo('PATCH', '/domains/example.de'));
    }

    public function testRenewCancelsScheduledDeletion()
    {
        $this->givenDomain(['status' => 'pending_delete']);
        $this->server->on('POST', '/domains/example.de/cancel-delete', 200, ['success' => true]);
        $this->server->on('POST', '/domains/example.de/renew', 200, ['success' => true, 'data' => ['domain' => 'example.de', 'years' => 1]]);

        $this->assertTrue($this->module->renewDomain('example.de', 1));
        $this->assertSame(
            ['GET /domains/example.de', 'POST /domains/example.de/cancel-delete', 'POST /domains/example.de/renew', 'PATCH /domains/example.de'],
            $this->server->summary()
        );
        $this->assertSame('{}', $this->server->requestsTo('POST', '/domains/example.de/renew')[0]['raw_body'], 'Without a term the TLD minimum is used');
    }

    public function testRenewPendingDomainIsNoop()
    {
        $this->givenDomain(['status' => 'pending_transfer']);

        $this->assertTrue($this->module->renewDomain('example.de', 1));
        $this->assertCount(1, $this->server->requests);
    }



    public function testRenewRedemptionDomainFails()
    {
        $this->givenDomain(['status' => 'redemption']);

        $this->assertFalse($this->module->renewDomain('example.de', 1));
        $this->assertArrayHasKey('redemption', $this->errors()['domain']);
    }

    public function testRenewTransferredDomainFails()
    {
        $this->givenDomain(['status' => 'transferred_away']);

        $this->assertFalse($this->module->renewDomain('example.de', 1));
        $this->assertStringContainsString('transferred_away', $this->errors()['domain']['state']);
    }

    public function testRestoreDomain()
    {
        $this->server->on('POST', '/domains/example.de/restore', 200, ['success' => true, 'message' => 'Domain restored successfully']);

        $this->assertTrue($this->module->restoreDomain('example.de', 1));

        $this->server->on('POST', '/domains/example.de/restore', 402, ['success' => false, 'error' => 'Insufficient wallet balance']);
        $this->assertFalse($this->module->restoreDomain('example.de', 1));
        $this->assertArrayHasKey('api', $this->errors());
    }

    public function testCancelServiceSchedulesDeletion()
    {
        $this->server->on('DELETE', '/domains/example.de', 200, ['success' => true]);

        $this->assertNull($this->module->cancelService($this->makePackage(), $this->makeService()));
        $this->assertNoErrors();
        $this->assertSame(['DELETE /domains/example.de'], $this->server->summary());
        $this->assertSame([], $this->server->requests[0]['query'], 'The deletion is not immediate');
    }

    public function testCancelServiceFallsBackToDisablingAutoRenew()
    {
        $this->server->on('DELETE', '/domains/example.de', 422, ['success' => false, 'error' => 'State machine constraint']);
        $this->server->on('PATCH', '/domains/example.de', 200, ['success' => true]);

        $this->assertNull($this->module->cancelService($this->makePackage(), $this->makeService()));
        $this->assertNoErrors();
        $this->assertSame(['autoRenew' => false], $this->server->requestsTo('PATCH', '/domains/example.de')[0]['body']);
    }

    public function testCancelServiceOfUnknownDomainSucceeds()
    {
        $this->assertNull($this->module->cancelService($this->makePackage(), $this->makeService()));
        $this->assertNoErrors();
    }

    public function testCancelServiceReportsUpstreamErrors()
    {
        $this->server->on('DELETE', '/domains/example.de', 502, ['success' => false, 'error' => 'Upstream provider error']);

        $this->module->cancelService($this->makePackage(), $this->makeService());
        $this->assertArrayHasKey('api', $this->errors());
        $this->assertSame([], $this->server->requestsTo('PATCH', '/domains/example.de'));
    }

    public function testCancelServiceCanOnlyDisableAutoRenew()
    {
        $this->module = $this->makeModule(['cancel_action' => 'disable_autorenew']);
        $this->server->on('PATCH', '/domains/example.de', 200, ['success' => true]);

        $this->module->cancelService($this->makePackage(), $this->makeService());

        $this->assertNoErrors();
        $this->assertSame(['PATCH /domains/example.de'], $this->server->summary());
    }

    public function testUncancelService()
    {
        $this->givenDomain(['status' => 'pending_delete']);
        $this->server->on('POST', '/domains/example.de/cancel-delete', 200, ['success' => true]);

        $this->assertNull($this->module->uncancelService($this->makePackage(), $this->makeService()));
        $this->assertNoErrors();
        $this->assertCount(1, $this->server->requestsTo('POST', '/domains/example.de/cancel-delete'));
    }

    public function testUncancelActiveDomainIsNoop()
    {
        $this->givenDomain();

        $this->assertNull($this->module->uncancelService($this->makePackage(), $this->makeService()));
        $this->assertNoErrors();
        $this->assertSame(['GET /domains/example.de'], $this->server->summary());
    }

    public function testUncancelUnknownDomain()
    {
        $this->assertNull($this->module->uncancelService($this->makePackage(), $this->makeService()));
        $this->assertNoErrors();
    }

    public function testSuspendAndUnsuspend()
    {
        $this->server->on('PATCH', '/domains/example.de', 200, ['success' => true]);

        $this->assertNull($this->module->suspendService($this->makePackage(), $this->makeService()));
        $this->assertNull($this->module->unsuspendService($this->makePackage(), $this->makeService()));

        $requests = $this->server->requestsTo('PATCH', '/domains/example.de');
        $this->assertCount(1, $requests, 'Unsuspending changes nothing at the registry');
        $this->assertSame(['autoRenew' => false], $requests[0]['body']);
    }

    public function testSuspendToleratesUnmodifiableDomains()
    {
        $this->server->on('PATCH', '/domains/example.de', 422, ['success' => false, 'error' => 'Domain cannot be modified']);

        $this->assertNull($this->module->suspendService($this->makePackage(), $this->makeService()));
        $this->assertNoErrors();

        $this->server->on('PATCH', '/domains/example.de', 500, ['success' => false, 'error' => 'Internal server error']);
        $this->module->suspendService($this->makePackage(), $this->makeService());
        $this->assertArrayHasKey('api', $this->errors());
    }

    public function testEditServiceKeepsFields()
    {
        $meta = $this->module->editService($this->makePackage(), $this->makeService(), ['use_module' => 'true']);

        $meta = array_column($meta, 'value', 'key');
        $this->assertSame('example.de', $meta['domain']);
        $this->assertSame('ns1.domain-reseller-api.de', $meta['ns1']);
        $this->assertSame([], $this->server->requests);
    }

    public function testEditServiceUpdatesDomainField()
    {
        $meta = $this->module->editService($this->makePackage(), $this->makeService(), ['domain' => 'Other.de', 'ns1' => 'a.example.net', 'ns2' => 'b.example.net']);

        $meta = array_column($meta, 'value', 'key');
        $this->assertSame('other.de', $meta['domain']);
        $this->assertSame('a.example.net', $meta['ns1']);
    }

    public function testEditServiceTogglesWhoisPrivacy()
    {
        $this->givenDomain(['name' => 'example.com']);
        $this->server->on('PUT', '/domains/example.com/whois-privacy', 200, ['success' => true, 'data' => ['domain' => 'example.com', 'whoisPrivacy' => true]]);

        $this->module->editService($this->makePackage(), $this->makeService('example.com'), [
            'use_module' => 'true',
            'configoptions' => ['id_protection' => '1']
        ]);

        $this->assertNoErrors();
        $this->assertSame(
            ['enabled' => true, 'acceptWppTerms' => true],
            $this->server->requestsTo('PUT', '/domains/example.com/whois-privacy')[0]['body']
        );
        $this->assertSame([], $this->server->requestsTo('PATCH', '/contacts/OWN-1'));
    }

    public function testEditServiceFallsBackToContactProtection()
    {
        $this->givenDomain();
        $this->server->on('PUT', '/domains/example.de/whois-privacy', 400, ['success' => false, 'error' => 'WHOIS privacy is not available for this TLD.']);
        $this->server->on('PATCH', '/contacts/OWN-1', 200, ['success' => true]);

        $this->module->editService($this->makePackage(), $this->makeService(), [
            'use_module' => 'true',
            'configoptions' => ['id_protection' => '1']
        ]);

        $this->assertNoErrors();
        $this->assertSame(['protection' => true], $this->server->requestsTo('PATCH', '/contacts/OWN-1')[0]['body']);

        // Unchanged option: no API call
        $this->module->editService($this->makePackage(), $this->makeService('example.de', ['id_protection' => '1']), [
            'use_module' => 'true',
            'configoptions' => ['id_protection' => '1']
        ]);
        $this->assertCount(1, $this->server->requestsTo('PATCH', '/contacts/OWN-1'));
    }

    // ==================================================================
    // Tabs
    // ==================================================================

    public function testAdminTabs()
    {
        $this->assertSame(
            ['tabContacts', 'tabNameservers', 'tabDns', 'tabDnssec', 'tabHosts', 'tabSettings', 'tabActions'],
            array_keys($this->module->getAdminServiceTabs($this->makeService()))
        );
    }

    public function testClientTabsDependOnDnsManagement()
    {
        $this->assertSame(
            ['tabClientContacts', 'tabClientNameservers', 'tabClientHosts', 'tabClientSettings'],
            array_keys($this->module->getClientServiceTabs($this->makeService()))
        );
        $this->assertArrayHasKey('tabClientDns', $this->module->getClientServiceTabs($this->makeService('example.de', ['dns_management' => '1'])));
        $this->assertArrayHasKey('tabClientDnssec', $this->module->getClientServiceTabs($this->makeService('example.de', [], ['dns_management' => '1'])));
        $this->assertSame('', $this->module->tabClientDns($this->makePackage(), $this->makeService()));
        $this->assertSame('', $this->module->tabClientDnssec($this->makePackage(), $this->makeService()));
    }

    public function testClientTabsLoadPackageMetaWhenMissing()
    {
        TestRegistry::$models['Packages'] = new \FakePackages();
        TestRegistry::$models['Packages']->packages[3] = (object) ['id' => 3, 'meta' => (object) ['dns_management' => '1']];

        $service = $this->makeService();
        unset($service->package);
        $service->package_id = 3;

        $this->assertArrayHasKey('tabClientDns', $this->module->getClientServiceTabs($service));

        $service->package_id = 4;
        $this->assertArrayNotHasKey('tabClientDns', $this->module->getClientServiceTabs($service));
    }

    public function testDisabledOptionDoesNotEnableFeature()
    {
        $this->assertArrayNotHasKey('tabClientDns', $this->module->getClientServiceTabs($this->makeService('example.de', ['dns_management' => 0])));
    }

    /**
     * Registers everything the tabs read for an active domain
     */
    private function givenManageableDomain(array $overrides = [], array $state = [])
    {
        $data = $this->givenDomain($overrides, $state);
        $this->givenContact('OWN-1');
        $this->givenContact('TECH-1');
        $this->server->on('GET', '/contacts/', 200, ['success' => true, 'data' => ['contacts' => [
            ['handle' => 'OWN-1', 'name' => 'Max Mustermann', 'email' => 'max@example.com'],
            ['handle' => 'TECH-1', 'name' => 'Tech Support', 'email' => 'tech@example.com']
        ]]]);
        $this->server->on('GET', '/dns/example.de', 200, ['success' => true, 'data' => ['domain' => 'example.de', 'useManagedDns' => true, 'records' => [
            ['id' => '1', 'type' => 'NS', 'name' => '@', 'content' => 'ns1.domain-reseller-api.de', 'ttl' => 3600, 'priority' => null, 'managed' => true],
            ['id' => '2', 'type' => 'A', 'name' => 'www', 'content' => '192.0.2.10', 'ttl' => 3600, 'priority' => null],
            ['id' => '3', 'type' => 'MX', 'name' => '@', 'content' => 'mx.example.de', 'ttl' => 3600, 'priority' => 10]
        ]]]);
        $this->server->on('GET', '/domains/example.de/dnssec', 200, ['success' => true, 'data' => [
            'domain' => 'example.de', 'dnssecEnabled' => true, 'useManagedDns' => true, 'dnskeys' => [],
            'keys' => [['id' => 1, 'keytype' => 'csk', 'active' => true, 'published' => true, 'algorithm' => 'ecdsap256sha256', 'bits' => 256, 'dnskey' => 'k', 'ds' => ['example.de. IN DS 12345 13 2 aabb']]]
        ]]);

        return $data;
    }

    public function testAllTabsRender()
    {
        $this->givenManageableDomain();
        $package = $this->makePackage(['epp_code' => '1']);
        $service = $this->makeService('example.de', ['dns_management' => '1']);

        $tabs = [
            'tabContacts' => 'tab_contacts',
            'tabClientContacts' => 'tab_client_contacts',
            'tabNameservers' => 'tab_nameservers',
            'tabClientNameservers' => 'tab_client_nameservers',
            'tabDns' => 'tab_dns',
            'tabClientDns' => 'tab_client_dns',
            'tabDnssec' => 'tab_dnssec',
            'tabClientDnssec' => 'tab_client_dnssec',
            'tabSettings' => 'tab_settings',
            'tabClientSettings' => 'tab_client_settings',
            'tabActions' => 'tab_actions'
        ];

        foreach ($tabs as $method => $view) {
            $html = $this->module->{$method}($package, $service, [], []);
            $this->assertIsString($html, $method);
            $this->assertArrayHasKey($view, \View::$rendered, $method);
            $this->assertStringContainsString('<form', $html, $method);
        }

        $this->assertNoErrors();
        $this->assertStringContainsString('192.0.2.10', \View::$rendered['tab_dns']['html']);
        $this->assertStringContainsString('IN DS 12345', \View::$rendered['tab_dnssec']['html']);
        $this->assertStringContainsString('Musterstraße 12a', \View::$rendered['tab_client_contacts']['html']);
        $this->assertStringContainsString('2026-03-01', \View::$rendered['tab_client_settings']['html']);
        $this->assertStringContainsString('name="handles[owner]"', \View::$rendered['tab_contacts']['html']);
        $this->assertStringNotContainsString('handles[owner]', \View::$rendered['tab_client_contacts']['html']);
    }

    public function testTabsInGerman()
    {
        Language::$lang = [];
        Language::$language = 'de_de';
        $module = $this->makeModule();
        $this->givenManageableDomain();

        $html = $module->tabClientSettings($this->makePackage(), $this->makeService(), [], []);

        $this->assertStringContainsString('Domainübersicht', $html);
        $this->assertStringContainsString('Aktiv', $html);
    }

    public function testTabOfUnknownDomainShowsError()
    {
        $html = $this->module->tabSettings($this->makePackage(), $this->makeService('unknown.de'), [], []);

        $this->assertSame('', trim($html));
        $this->assertArrayHasKey('api', $this->errors());
    }

    public function testPendingDomainTabsAreReadOnly()
    {
        $this->givenManageableDomain(['status' => 'pending_transfer']);

        $html = $this->module->tabClientNameservers($this->makePackage(), $this->makeService(), [], []);
        $this->assertStringContainsString('still being registered or transferred', $html);

        $this->module->tabNameservers($this->makePackage(), $this->makeService(), [], ['ns1' => 'a.example.net', 'ns2' => 'b.example.net']);
        $this->assertArrayHasKey('pending', $this->errors()['domain']);
        $this->assertSame([], $this->server->requestsTo('PATCH', '/domains/example.de'));
    }

    /**
     * Marks handles as created by the module for the test client
     */
    private function givenClientHandles(array $handles, array $fingerprints = [])
    {
        $meta = TestRegistry::$models['ModuleClientMeta'];
        $meta->set(5, 7, 1, [
            ['key' => 'owned_handles', 'value' => json_encode($handles)],
            ['key' => 'contact_handles', 'value' => json_encode($fingerprints)]
        ]);
    }

    public function testContactsTabUpdatesContacts()
    {
        $this->givenManageableDomain();
        $this->givenClientHandles(['OWN-1'], ['fp-1' => 'OWN-1', 'fp-2' => 'OTHER-1']);
        $this->server->on('PATCH', '/contacts/OWN-1', 200, ['success' => true]);

        $this->module->tabClientContacts($this->makePackage(), $this->makeService(), [], [
            'action' => 'update_contacts',
            'contacts' => ['OWN-1' => [
                'first_name' => 'Max', 'last_name' => 'Mustermann', 'company' => '', 'email' => 'max@example.com',
                'phone' => '+49.301234567', 'address1' => 'Musterstraße 13', 'address2' => '', 'city' => 'Berlin',
                'state' => '', 'zip' => '10115', 'country' => 'DE'
            ]]
        ]);

        $this->assertNoErrors();
        $this->assertSame('13', $this->server->requestsTo('PATCH', '/contacts/OWN-1')[0]['body']['houseNumber']);
        $this->assertSame(['The contacts have been updated.'], $this->module->getMessages()['success']['message']);

        // The edited handle is no longer reused for new orders, but stays editable
        $meta = TestRegistry::$models['ModuleClientMeta'];
        $this->assertSame(['fp-2' => 'OTHER-1'], json_decode($meta->get(5, 'contact_handles', 7, 1)->value, true));
        $this->assertContains('OWN-1', json_decode($meta->get(5, 'owned_handles', 7, 1)->value, true));
    }

    public function testClientCanNotEditForeignHandles()
    {
        $this->givenManageableDomain();
        $this->givenClientHandles(['OWN-1']);
        $this->server->on('PATCH', '/contacts/TECH-1', 200, ['success' => true]);

        $html = $this->module->tabClientContacts($this->makePackage(), $this->makeService(), [], []);
        $this->assertStringContainsString('name="contacts[OWN-1][city]"', $html);
        $this->assertStringNotContainsString('name="contacts[TECH-1][city]"', $html, 'Foreign handles are read-only');

        $this->module->tabClientContacts($this->makePackage(), $this->makeService(), [], [
            'action' => 'update_contacts',
            'contacts' => ['TECH-1' => ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'x@example.com', 'phone' => '+49.301', 'country' => 'DE']]
        ]);

        $this->assertArrayHasKey('TECH-1_protected', $this->errors()['contacts']);
        $this->assertSame([], $this->server->requestsTo('PATCH', '/contacts/TECH-1'));
    }

    public function testStaffCanEditAnyNonAccountHandle()
    {
        $this->givenManageableDomain();
        $this->server->on('PATCH', '/contacts/TECH-1', 200, ['success' => true]);

        $this->module->tabContacts($this->makePackage(), $this->makeService(), [], [
            'action' => 'update_contacts',
            'contacts' => ['TECH-1' => ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'x@example.com', 'phone' => '+49.301', 'address1' => 'Weg 1', 'city' => 'Bonn', 'zip' => '53111', 'country' => 'DE']]
        ]);

        $this->assertNoErrors();
        $this->assertCount(1, $this->server->requestsTo('PATCH', '/contacts/TECH-1'));
    }

    public function testOrderRecordsOwnedHandles()
    {
        $this->addClient();
        $this->givenRegistrationWorks();

        $this->module->addService($this->makePackage(), $this->orderVars());

        $owned = json_decode(TestRegistry::$models['ModuleClientMeta']->get(5, 'owned_handles', 7, 1)->value, true);
        $this->assertSame(['NEW-HANDLE-1'], $owned);
    }

    public function testHostsTab()
    {
        $this->givenManageableDomain();
        $this->server->on('GET', '/domains/example.de/hosts', 200, ['success' => true, 'data' => ['domain' => 'example.de', 'hosts' => [
            ['hostname' => 'ns1.example.de', 'ipv4' => '192.0.2.1']
        ]]]);
        $this->server->on('POST', '/domains/example.de/hosts', 200, ['success' => true, 'data' => ['hostname' => 'ns2.example.de']]);
        $this->server->on('PUT', '/domains/example.de/hosts/ns1.example.de', 200, ['success' => true, 'data' => ['hostname' => 'ns1.example.de']]);
        $this->server->on('DELETE', '/domains/example.de/hosts/ns1.example.de', 200, ['success' => true, 'message' => 'ok']);

        $html = $this->module->tabHosts($this->makePackage(), $this->makeService(), [], []);
        $this->assertStringContainsString('ns1.example.de', $html);
        $this->assertStringContainsString('192.0.2.1', $html);

        $this->module->tabClientHosts($this->makePackage(), $this->makeService(), [], ['action' => 'save', 'hostname' => 'ns2.example.de', 'ipv4' => '192.0.2.2', 'ipv6' => '']);
        $this->module->tabHosts($this->makePackage(), $this->makeService(), [], ['action' => 'save', 'hostname' => 'NS1.example.de', 'ipv4' => '192.0.2.9', 'ipv6' => '2001:db8::9']);
        $this->module->tabHosts($this->makePackage(), $this->makeService(), [], ['action' => 'delete', 'hostname' => 'ns1.example.de']);

        $this->assertNoErrors();
        $this->assertSame(['hostname' => 'ns2.example.de', 'ipv4' => '192.0.2.2'], $this->server->requestsTo('POST', '/domains/example.de/hosts')[0]['body']);
        $this->assertSame(['ipv4' => '192.0.2.9', 'ipv6' => '2001:db8::9'], $this->server->requestsTo('PUT', '/domains/example.de/hosts/ns1.example.de')[0]['body']);
        $this->assertCount(1, $this->server->requestsTo('DELETE', '/domains/example.de/hosts/ns1.example.de'));
    }

    public function testHostsTabValidation()
    {
        $this->givenManageableDomain();
        $this->server->on('GET', '/domains/example.de/hosts', 200, ['success' => true, 'data' => ['domain' => 'example.de', 'hosts' => []]]);

        $this->module->tabHosts($this->makePackage(), $this->makeService(), [], ['action' => 'save', 'hostname' => 'ns1.other.de', 'ipv4' => '999.1.1.1', 'ipv6' => 'nope']);

        $errors = $this->errors();
        $this->assertArrayHasKey('hostname', $errors);
        $this->assertArrayHasKey('ipv4', $errors);
        $this->assertArrayHasKey('ipv6', $errors);
        $this->assertSame([], $this->server->requestsTo('POST', '/domains/example.de/hosts'));
    }

    public function testSettingsTabTransferLock()
    {
        $this->givenManageableDomain(['name' => 'example.com']);
        $this->server->on('GET', '/domains/example.com/lock', 200, ['success' => true, 'data' => ['domain' => 'example.com', 'supported' => true, 'locked' => true]]);
        $this->server->on('PUT', '/domains/example.com/lock', 200, ['success' => true, 'data' => ['domain' => 'example.com', 'locked' => false]]);

        $html = $this->module->tabClientSettings($this->makePackage(), $this->makeService('example.com'), [], []);
        $this->assertStringContainsString('name="action" value="transfer_lock"', $html);

        $this->module->tabClientSettings($this->makePackage(), $this->makeService('example.com'), [], ['action' => 'transfer_lock', 'locked' => '0']);
        $this->assertSame(['locked' => false], $this->server->requestsTo('PUT', '/domains/example.com/lock')[0]['body']);
        $this->assertNoErrors();
    }

    public function testSettingsTabHidesUnsupportedLock()
    {
        $this->givenManageableDomain();
        $this->server->on('GET', '/domains/example.de/lock', 200, ['success' => true, 'data' => ['domain' => 'example.de', 'supported' => false, 'locked' => null]]);

        $html = $this->module->tabSettings($this->makePackage(), $this->makeService(), [], []);
        $this->assertStringNotContainsString('transfer_lock', $html);
    }

    public function testEnablingManagedDnsRetriesWhileZoneIsNotReady()
    {
        $this->givenManageableDomain(['useManagedDns' => false, 'nameservers' => ['a.example.net', 'b.example.net']]);
        $this->server->on('PATCH', '/domains/example.de', 200, ['success' => true]);
        $this->server->on('PATCH', '/domains/example.de', 503, ['success' => false, 'error' => 'Zone not ready'], 1);

        $this->module->tabNameservers($this->makePackage(), $this->makeService(), [], ['use_managed_dns' => '1']);

        $this->assertNoErrors();
        $this->assertCount(2, $this->server->requestsTo('PATCH', '/domains/example.de'));
        $this->assertSame([15], $this->module->pauses);
    }

    public function testClientCanNotAssignHandles()
    {
        $this->givenManageableDomain();

        $this->module->tabClientContacts($this->makePackage(), $this->makeService(), [], [
            'action' => 'assign_handles',
            'handles' => ['owner' => 'TECH-1']
        ]);

        $this->assertSame([], $this->server->requestsTo('PATCH', '/domains/example.de/contacts'));
    }

    public function testAdminAssignsHandlesWithOwnerChangeConfirmation()
    {
        $this->givenManageableDomain();
        $this->server->on('PATCH', '/domains/example.de/contacts', 200, ['success' => true, 'data' => ['charged' => 0, 'domain' => 'example.de']]);
        $this->server->on('PATCH', '/domains/example.de/contacts', 409, [
            'success' => false,
            'error' => 'Owner change requires confirmation',
            'ownerChange' => ['required' => true, 'method' => 'update', 'netPrice' => 0, 'price' => 0, 'currency' => 'EUR', 'vatPercent' => 19, 'confirmField' => 'confirmOwnerChange']
        ], 1);

        $post = ['action' => 'assign_handles', 'handles' => ['owner' => 'TECH-1', 'admin' => '', 'tech' => 'TECH-1', 'billing' => '']];
        $this->module->tabContacts($this->makePackage(), $this->makeService(), [], $post);

        $this->assertArrayHasKey('required', $this->errors()['confirm_owner_change']);
        $this->assertSame(
            ['contacts' => ['owner' => 'TECH-1']],
            $this->server->requestsTo('PATCH', '/domains/example.de/contacts')[0]['body'],
            'Unchanged roles are not sent'
        );

        // Second request, confirming the owner change
        $this->module = $this->makeModule();
        $this->module->tabContacts($this->makePackage(), $this->makeService(), [], $post + ['confirm_owner_change' => '1']);
        $this->assertSame(
            ['contacts' => ['owner' => 'TECH-1'], 'confirmOwnerChange' => true],
            $this->server->requestsTo('PATCH', '/domains/example.de/contacts')[1]['body']
        );
        $this->assertNoErrors();
    }

    public function testAssignHandlesRequiresAChange()
    {
        $this->givenManageableDomain();

        $this->module->tabContacts($this->makePackage(), $this->makeService(), [], [
            'action' => 'assign_handles',
            'handles' => ['owner' => 'OWN-1']
        ]);

        $this->assertArrayHasKey('unchanged', $this->errors()['handles']);
    }

    public function testNameserverTabEnablesManagedDns()
    {
        $this->givenManageableDomain(['useManagedDns' => false, 'nameservers' => ['a.example.net', 'b.example.net']]);
        $this->server->on('PATCH', '/domains/example.de', 200, ['success' => true]);

        $this->module->tabNameservers($this->makePackage(), $this->makeService(), [], ['use_managed_dns' => '1']);

        $this->assertNoErrors();
        $this->assertSame(['useManagedDns' => true], $this->server->requestsTo('PATCH', '/domains/example.de')[0]['body']);
    }

    public function testNameserverTabSetsCustomNameservers()
    {
        $this->givenManageableDomain();
        $this->server->on('PATCH', '/domains/example.de', 200, ['success' => true]);

        $this->module->tabClientNameservers($this->makePackage(), $this->makeService(), [], [
            'ns1' => 'a.example.net', 'ns2' => 'b.example.net', 'ns3' => ''
        ]);

        $this->assertNoErrors();
        $this->assertSame(
            ['nameservers' => ['a.example.net', 'b.example.net'], 'useManagedDns' => false],
            $this->server->requestsTo('PATCH', '/domains/example.de')[0]['body']
        );
    }

    public function testNameserverTabKeepsInputOnError()
    {
        $this->givenManageableDomain();

        $this->module->tabNameservers($this->makePackage(), $this->makeService(), [], ['ns1' => 'only.example.net']);

        $this->assertArrayHasKey('nameservers', $this->errors());
        $this->assertSame('only.example.net', \View::$rendered['tab_nameservers']['vars']['vars']->ns1);
    }

    public function testDnsTabAddsRecord()
    {
        $this->givenManageableDomain();
        $this->server->on('POST', '/dns/example.de/records', 200, ['success' => true, 'data' => ['id' => '9']]);

        $this->module->tabClientDns($this->makePackage(), $this->makeService('example.de', ['dns_management' => '1']), [], [
            'action' => 'add', 'type' => 'TXT', 'name' => '_dmarc', 'content' => 'v=DMARC1; p=none', 'ttl' => '300', 'priority' => ''
        ]);

        $this->assertNoErrors();
        $this->assertSame(
            ['type' => 'TXT', 'name' => '_dmarc', 'content' => 'v=DMARC1; p=none', 'ttl' => 300],
            $this->server->requestsTo('POST', '/dns/example.de/records')[0]['body']
        );
    }

    public function testDnsTabValidatesRecords()
    {
        $this->givenManageableDomain();

        $this->module->tabDns($this->makePackage(), $this->makeService(), [], ['action' => 'add', 'type' => 'MX', 'name' => '@', 'content' => '']);

        $errors = $this->errors();
        $this->assertArrayHasKey('content', $errors);
        $this->assertArrayHasKey('priority', $errors);
        $this->assertSame([], $this->server->requestsTo('POST', '/dns/example.de/records'));
    }

    public function testDnsTabUpdatesAndDeletesRecords()
    {
        $this->givenManageableDomain();
        $this->server->on('PATCH', '/dns/example.de/records/2', 200, ['success' => true]);
        $this->server->on('DELETE', '/dns/example.de/records/3', 200, ['success' => true]);

        $this->module->tabDns($this->makePackage(), $this->makeService(), [], ['action' => 'update', 'record_id' => '2', 'name' => 'www', 'content' => '192.0.2.20', 'ttl' => '600']);
        $this->module->tabDns($this->makePackage(), $this->makeService(), [], ['action' => 'delete', 'record_id' => '3']);

        $this->assertNoErrors();
        $this->assertSame(
            ['name' => 'www', 'content' => '192.0.2.20', 'ttl' => 600],
            $this->server->requestsTo('PATCH', '/dns/example.de/records/2')[0]['body']
        );
        $this->assertCount(1, $this->server->requestsTo('DELETE', '/dns/example.de/records/3'));
    }

    public function testDnsTabEditPrefillsForm()
    {
        $this->givenManageableDomain();

        $html = $this->module->tabDns($this->makePackage(), $this->makeService(), ['edit' => '3'], []);

        $this->assertStringContainsString('name="record_id" value="3"', $html);
        $this->assertStringContainsString('>mx.example.de</textarea>', $html);
        $this->assertStringContainsString('name="action" value="update"', $html);
    }

    public function testDnsTabEnablesManagedDnsAndShowsExport()
    {
        $this->givenManageableDomain(['useManagedDns' => false]);
        $html = $this->module->tabDns($this->makePackage(), $this->makeService(), [], []);
        $this->assertStringContainsString('enable_managed_dns', $html);
        $this->assertSame([], $this->server->requestsTo('GET', '/dns/example.de'));

        // A new request with managed DNS enabled
        $this->module = $this->makeModule();
        $this->givenManageableDomain();
        $this->server->on('GET', '/dns/example.de/export', 200, ['success' => true, 'data' => "example.de. 3600 IN A 192.0.2.1\n"]);
        $html = $this->module->tabDns($this->makePackage(), $this->makeService(), ['export' => '1'], []);
        $this->assertStringContainsString('example.de. 3600 IN A 192.0.2.1', $html);
    }

    public function testDnsTabImportIsAdminOnly()
    {
        $this->givenManageableDomain();
        $this->server->on('POST', '/dns/example.de/import', 200, ['success' => true, 'result' => 'ok']);
        $post = ['action' => 'import', 'zone_file' => 'www 300 IN A 192.0.2.1'];

        $this->module->tabClientDns($this->makePackage(), $this->makeService('example.de', ['dns_management' => '1']), [], $post);
        $this->assertSame([], $this->server->requestsTo('POST', '/dns/example.de/import'));

        $this->module->tabDns($this->makePackage(), $this->makeService(), [], $post);
        $this->assertSame(['zoneFile' => 'www 300 IN A 192.0.2.1'], $this->server->requestsTo('POST', '/dns/example.de/import')[0]['body']);
    }

    public function testDnssecTabForCustomNameservers()
    {
        $this->givenManageableDomain(['useManagedDns' => false]);
        $this->server->on('PUT', '/domains/example.de/dnssec', 200, ['success' => true, 'data' => []]);

        $this->module->tabDnssec($this->makePackage(), $this->makeService(), [], ['dnssec_enabled' => '1', 'dnskeys' => '']);
        $this->assertArrayHasKey('dnskeys', $this->errors());

        $this->module->tabDnssec($this->makePackage(), $this->makeService(), [], [
            'dnssec_enabled' => '1',
            'dnskeys' => "example.de. IN DNSKEY 257 3 13 AAAA\nexample.de. IN DNSKEY 256 3 13 BBBB"
        ]);
        $this->assertSame(
            ['enabled' => true, 'dnskeys' => ['example.de. IN DNSKEY 257 3 13 AAAA', 'example.de. IN DNSKEY 256 3 13 BBBB']],
            $this->server->requestsTo('PUT', '/domains/example.de/dnssec')[0]['body']
        );
    }

    public function testDnssecTabForManagedDns()
    {
        $this->givenManageableDomain();
        $this->server->on('PUT', '/domains/example.de/dnssec', 200, ['success' => true, 'data' => []]);

        $this->module->tabClientDnssec($this->makePackage(), $this->makeService('example.de', ['dns_management' => '1']), [], ['dnskeys' => 'ignored']);

        $this->assertNoErrors();
        $this->assertSame(['enabled' => false], $this->server->requestsTo('PUT', '/domains/example.de/dnssec')[0]['body']);
    }

    public function testSettingsTabAuthCode()
    {
        $this->givenManageableDomain();
        $this->server->on('GET', '/domains/example.de/authcode', 200, ['success' => true, 'data' => ['domain' => 'example.de', 'authCode' => 'AUTH-XYZ']]);
        $this->server->on('POST', '/domains/example.de/reset-authcode', 200, ['success' => true, 'data' => ['message' => 'ok']]);
        $package = $this->makePackage(['epp_code' => '1']);

        $html = $this->module->tabClientSettings($package, $this->makeService(), [], ['action' => 'get_auth_code']);
        $this->assertStringContainsString('AUTH-XYZ', $html);

        $this->module->tabClientSettings($package, $this->makeService(), [], ['action' => 'reset_auth_code']);
        $this->assertCount(1, $this->server->requestsTo('POST', '/domains/example.de/reset-authcode'));
        $this->assertNoErrors();
    }

    public function testClientSettingsWithoutEppPermission()
    {
        $this->givenManageableDomain();

        $html = $this->module->tabClientSettings($this->makePackage(), $this->makeService(), [], ['action' => 'get_auth_code']);

        $this->assertSame([], $this->server->requestsTo('GET', '/domains/example.de/authcode'));
        $this->assertStringNotContainsString('get_auth_code', $html);
    }

    public function testAuthCodeOnlyForSupportedTlds()
    {
        $this->givenManageableDomain(['name' => 'example.com']);

        $html = $this->module->tabSettings($this->makePackage(), $this->makeService('example.com'), [], ['action' => 'get_auth_code']);

        $this->assertSame([], $this->server->requestsTo('GET', '/domains/example.com/authcode'));
        $this->assertStringContainsString('only be retrieved through the API for .de and .eu', $html);
    }

    public function testSettingsTabUpdatesAutoRenew()
    {
        $this->givenManageableDomain();
        $this->server->on('PATCH', '/domains/example.de', 200, ['success' => true]);

        $this->module->tabSettings($this->makePackage(), $this->makeService(), [], ['action' => 'auto_renew', 'auto_renew' => '0']);
        $this->module->tabClientSettings($this->makePackage(), $this->makeService(), [], ['action' => 'auto_renew', 'auto_renew' => '0']);

        $this->assertCount(1, $this->server->requestsTo('PATCH', '/domains/example.de'), 'Clients can not change auto-renewal');
        $this->assertSame(['autoRenew' => false], $this->server->requestsTo('PATCH', '/domains/example.de')[0]['body']);
    }

    public function testActionsTabRestore()
    {
        $this->givenManageableDomain(['status' => 'redemption'], ['canRestore' => true, 'canScheduleDelete' => false, 'canTransferOut' => false, 'canHold' => false]);
        $this->server->on('POST', '/domains/example.de/restore', 200, ['success' => true]);

        $html = $this->module->tabActions($this->makePackage(), $this->makeService(), [], []);
        $this->assertStringContainsString('name="action" value="restore"', $html);
        $this->assertStringNotContainsString('value="schedule_delete"', $html);

        $this->module->tabActions($this->makePackage(), $this->makeService(), [], ['action' => 'restore']);
        $this->assertCount(1, $this->server->requestsTo('POST', '/domains/example.de/restore'));
        $this->assertSame(['The domain example.de has been restored.'], $this->module->getMessages()['success']['message']);
    }

    public function testActionsTabRejectsUnavailableActions()
    {
        $this->givenManageableDomain();

        $this->module->tabActions($this->makePackage(), $this->makeService(), [], ['action' => 'restore']);

        $this->assertArrayHasKey('unavailable', $this->errors()['action']);
        $this->assertSame([], $this->server->requestsTo('POST', '/domains/example.de/restore'));
    }

    public function testActionsTabImmediateDeletionRequiresConfirmation()
    {
        $this->givenManageableDomain(['status' => 'pending_delete'], ['canDeleteImmediate' => true, 'canCancelDelete' => true]);
        $this->server->on('DELETE', '/domains/example.de', 200, ['success' => true]);

        $this->module->tabActions($this->makePackage(), $this->makeService(), [], ['action' => 'delete_immediate', 'confirm_domain' => 'other.de']);
        $this->assertArrayHasKey('confirm_domain', $this->errors());
        $this->assertSame([], $this->server->requestsTo('DELETE', '/domains/example.de'));

        $this->module->tabActions($this->makePackage(), $this->makeService(), [], ['action' => 'delete_immediate', 'confirm_domain' => 'EXAMPLE.de']);
        $this->assertSame(['immediate' => 'true'], $this->server->requestsTo('DELETE', '/domains/example.de')[0]['query']);
    }

    public function testActionsTabTransferOutAndHold()
    {
        $this->givenManageableDomain();
        $this->server->on('GET', '/domains/transfer-out/pending', 200, ['success' => true, 'data' => ['transfers' => [
            ['cr_date' => '2026-09-30T08:00:00Z', 'domain' => 'example.de', 'object_id' => '1', 'provider' => 'x', 'user_id' => 'u'],
            ['cr_date' => '2026-09-30T08:00:00Z', 'domain' => 'other.de', 'object_id' => '2', 'provider' => 'x', 'user_id' => 'u']
        ]]]);
        $this->server->on('POST', '/domains/example.de/transfer-out', 200, ['success' => true, 'data' => ['operation' => 'REJECT']]);
        $this->server->on('POST', '/domains/example.de/hold', 200, ['success' => true, 'data' => []]);

        $html = $this->module->tabActions($this->makePackage(), $this->makeService(), [], []);
        $this->assertCount(1, \View::$rendered['tab_actions']['vars']['transfers']);
        $this->assertStringContainsString('2026-09-30T08:00:00Z', $html);

        $this->module->tabActions($this->makePackage(), $this->makeService(), [], ['action' => 'transfer_out_reject']);
        $this->assertSame(['approve' => false], $this->server->requestsTo('POST', '/domains/example.de/transfer-out')[0]['body']);

        $this->module->tabActions($this->makePackage(), $this->makeService(), [], ['action' => 'hold']);
        $this->assertSame(['disconnect' => true], $this->server->requestsTo('POST', '/domains/example.de/hold')[0]['body']);
        $this->assertNoErrors();
    }

    public function testActionsTabSyncsRenewDate()
    {
        $this->givenManageableDomain();

        $this->module->tabActions($this->makePackage(), $this->makeService(), [], ['action' => 'sync_renew_date']);

        $this->assertNoErrors();
        $this->assertSame(
            [['service_id' => 99, 'vars' => ['date_renews' => '2026-03-01 10:00:00'], 'bypass_module' => true]],
            TestRegistry::$models['Services']->edits
        );
    }

    // ==================================================================
    // Logging
    // ==================================================================

    public function testRequestsAreLogged()
    {
        $this->server->on('GET', '/domains/check/example.de', 200, ['success' => true, 'data' => ['available' => true, 'premium' => false]]);
        $this->module->checkAvailability('example.de', 1);

        $this->assertCount(2, $this->module->logs);
        $this->assertSame('input', $this->module->logs[0]['direction']);
        $this->assertSame('GET https://domain-reseller-api.de/api/v1/domains/check/example.de', $this->module->logs[0]['url']);
        $this->assertSame('output', $this->module->logs[1]['direction']);
        $this->assertTrue($this->module->logs[1]['success']);
    }

    public function testSandboxRequestsAreMarkedInTheLog()
    {
        $module = $this->makeModule(['sandbox' => 'true']);
        $module->checkAvailability('example.de', 1);

        $this->assertStringEndsWith('[sandbox]', $module->logs[0]['url']);
        $this->assertFalse($module->logs[1]['success']);
    }
}
