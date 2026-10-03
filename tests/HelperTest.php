<?php

namespace Tests;

use DomainResellerApiHelper as Helper;
use PHPUnit\Framework\Attributes\DataProvider;

class HelperTest extends \PHPUnit\Framework\TestCase
{
    // ------------------------------------------------------------------
    // Domains
    // ------------------------------------------------------------------

    public function testNormalizeDomain()
    {
        $this->assertSame('example.de', Helper::normalizeDomain('  Example.DE. '));
        $this->assertSame('example.com', Helper::normalizeDomain('https://example.com/path'));
        $this->assertSame('', Helper::normalizeDomain(null));
    }

    public function testNormalizeIdnDomain()
    {
        if (!function_exists('idn_to_ascii')) {
            $this->markTestSkipped('ext-intl is not available');
        }

        $this->assertSame('xn--mller-kva.de', Helper::normalizeDomain('Müller.de'));
    }

    public function testIsValidDomain()
    {
        $this->assertTrue(Helper::isValidDomain('example.de'));
        $this->assertTrue(Helper::isValidDomain('sub-domain.example.co.uk'));
        $this->assertFalse(Helper::isValidDomain('example'));
        $this->assertFalse(Helper::isValidDomain('-example.de'));
        $this->assertFalse(Helper::isValidDomain(''));
    }

    public function testGetTld()
    {
        $this->assertSame('.de', Helper::getTld('example.de'));
        $this->assertSame('.co.uk', Helper::getTld('example.co.uk', ['.uk', '.co.uk']));
        $this->assertSame('.uk', Helper::getTld('example.uk', ['.uk', '.co.uk']));
        $this->assertSame('.co.uk', Helper::getTld('example.co.uk'));
        $this->assertSame('', Helper::getTld('localhost'));
    }

    // ------------------------------------------------------------------
    // Nameservers
    // ------------------------------------------------------------------

    public function testNormalizeNameservers()
    {
        $this->assertSame(
            ['ns1.example.com', 'ns2.example.com'],
            Helper::normalizeNameservers([' NS1.example.com. ', '', 'ns2.example.com', 'ns1.example.com', null, ['x']])
        );
    }

    public function testNameserversFromVars()
    {
        $this->assertSame(
            ['ns1.a.de', 'ns2.a.de', 'ns3.a.de'],
            Helper::nameserversFromVars(['ns1' => 'ns1.a.de', 'ns2' => '', 'ns3' => 'ns2.a.de', 'ns' => ['ns3.a.de']], 5)
        );
        $this->assertSame(['a.de', 'b.de'], Helper::nameserversFromVars(['ns1' => 'a.de', 'ns2' => 'b.de', 'ns3' => 'c.de'], 2));
    }

    public function testIsManagedNameserverSet()
    {
        $this->assertTrue(Helper::isManagedNameserverSet(['ns1.domain-reseller-api.de', 'NS2.domain-reseller-api.de.']));
        $this->assertTrue(Helper::isManagedNameserverSet(['ns3.domain-reseller-api.de', 'ns4.domain-reseller-api.de']));
        $this->assertFalse(Helper::isManagedNameserverSet(['ns1.domain-reseller-api.de', 'ns.example.com']));
        $this->assertFalse(Helper::isManagedNameserverSet([]));
    }

    public function testIsValidHostname()
    {
        $this->assertTrue(Helper::isValidHostname('ns1.example.com'));
        $this->assertFalse(Helper::isValidHostname('ns1'));
        $this->assertFalse(Helper::isValidHostname('ns1..example.com'));
        $this->assertFalse(Helper::isValidHostname('1.2.3.4'));
    }

    // ------------------------------------------------------------------
    // Phone numbers
    // ------------------------------------------------------------------

    #[DataProvider('phoneProvider')]
    public function testFormatPhone($input, $country, $expected)
    {
        $this->assertSame($expected, Helper::formatPhone($input, $country));
    }

    public static function phoneProvider()
    {
        return [
            'already formatted' => ['+49.301234567', 'DE', '+49.301234567'],
            'international with spaces' => ['+49 30 1234567', 'DE', '+49.301234567'],
            'international with trunk prefix' => ['+49 (0)30 1234567', 'DE', '+49.301234567'],
            'double zero prefix' => ['0049 30 1234567', 'DE', '+49.301234567'],
            'national german' => ['030 / 123 45 67', 'DE', '+49.301234567'],
            'national austrian' => ['0664 1234567', 'AT', '+43.6641234567'],
            'international other country' => ['+41 44 123 45 67', 'DE', '+41.441234567'],
            'three digit code' => ['+420 123 456 789', '', '+420.123456789'],
            'nanp international' => ['+1 (555) 123-4567', 'US', '+1.5551234567'],
            'nanp national' => ['555-123-4567', 'US', '+1.5551234567'],
            'nanp national with 1' => ['1 555 123 4567', 'CA', '+1.5551234567'],
            'italy keeps trunk zero' => ['06 1234 5678', 'IT', '+39.0612345678'],
            'italy international' => ['+39 06 1234 5678', 'IT', '+39.0612345678'],
            'extension removed' => ['+49 30 1234567 ext. 12', 'DE', '+49.301234567'],
            'empty' => ['', 'DE', null],
            'letters only' => ['n/a', 'DE', null],
            'national without country' => ['030 1234567', '', null],
            'unknown international code' => ['+999 1234', '', null],
            'too long' => ['+49 1234567890123456', 'DE', null],
        ];
    }

    // ------------------------------------------------------------------
    // Addresses
    // ------------------------------------------------------------------

    #[DataProvider('streetProvider')]
    public function testSplitStreet($input, $expected)
    {
        $this->assertSame($expected, Helper::splitStreet($input));
    }

    public static function streetProvider()
    {
        return [
            'german' => ['Musterstraße 12a', ['Musterstraße', '12a']],
            'german range' => ['Hauptstr. 5-7', ['Hauptstr.', '5-7']],
            'german letter with space' => ['Am Bach 3 b', ['Am Bach', '3b']],
            'number inside street name' => ['Straße des 17. Juni 135', ['Straße des 17. Juni', '135']],
            'comma separated' => ['Rue de la Paix, 10', ['Rue de la Paix', '10']],
            'north american' => ['123 Main St', ['Main St', '123']],
            'no number' => ['Schlossallee', ['Schlossallee', '']],
            'empty' => ['  ', ['', '']],
            'whitespace normalised' => ["  Gartenweg   4 ", ['Gartenweg', '4']],
        ];
    }

    // ------------------------------------------------------------------
    // Contacts
    // ------------------------------------------------------------------

    public function testBuildContactPayloadForPerson()
    {
        $payload = Helper::buildContactPayload([
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'company' => '',
            'email' => 'max@example.com',
            'phone' => '030 1234567',
            'address1' => 'Musterstraße 12a',
            'address2' => 'Hinterhaus',
            'city' => 'Berlin',
            'state' => 'BE',
            'zip' => '10115',
            'country' => 'de'
        ]);

        $this->assertSame([
            'type' => 'person',
            'sex' => 'NA',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'street' => 'Musterstraße',
            'postalCode' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
            'email' => 'max@example.com',
            'phone' => '+49.301234567',
            'houseNumber' => '12a',
            'extension' => 'Hinterhaus',
            'state' => 'BE'
        ], $payload);
        $this->assertSame([], Helper::validateContactPayload($payload));
    }

    public function testBuildContactPayloadForOrganizationWithPlaceholders()
    {
        $payload = Helper::buildContactPayload([
            'company' => 'ACME GmbH',
            'email' => 'info@acme.de',
            'phone' => '',
            'fax' => '+49 30 999',
            'address1' => 'Industriegebiet',
            'city' => 'Hamburg',
            'zip' => '',
            'country' => 'DE',
            'protection' => true
        ], '+49.401111');

        $this->assertSame('organization', $payload['type']);
        $this->assertSame('ACME GmbH', $payload['firstName']);
        $this->assertSame('ACME GmbH', $payload['lastName']);
        $this->assertSame('ACME GmbH', $payload['organization']);
        $this->assertSame('Industriegebiet', $payload['street']);
        $this->assertArrayNotHasKey('houseNumber', $payload, 'No house number is sent without one');
        $this->assertSame('-', $payload['postalCode']);
        $this->assertSame('+49.401111', $payload['phone'], 'The fallback phone is used');
        $this->assertSame('+49.30999', $payload['fax']);
        $this->assertTrue($payload['protection']);
        $this->assertArrayNotHasKey('extension', $payload);
        $this->assertArrayNotHasKey('state', $payload);
    }

    public function testBuildContactPayloadTruncatesLongValues()
    {
        $payload = Helper::buildContactPayload([
            'first_name' => str_repeat('a', 150),
            'last_name' => 'B',
            'address1' => 'Weg 123456789012345678901234',
            'country' => 'DE'
        ]);

        $this->assertSame(100, mb_strlen($payload['firstName']));
        $this->assertLessThanOrEqual(20, mb_strlen($payload['houseNumber']));
    }

    public function testValidateContactPayload()
    {
        $this->assertSame(
            ['country', 'email', 'phone'],
            Helper::validateContactPayload(['country' => 'Germany', 'email' => 'max+tag@example.com', 'phone' => ''])
        );
    }

    public function testContactFingerprintIgnoresCaseAndOrder()
    {
        $a = Helper::contactFingerprint(['city' => 'Berlin', 'email' => 'Max@Example.com']);
        $b = Helper::contactFingerprint(['email' => 'max@example.com ', 'city' => 'berlin']);
        $c = Helper::contactFingerprint(['email' => 'max@example.com', 'city' => 'Hamburg']);

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
    }

    public function testApiContactToBlesta()
    {
        $contact = Helper::apiContactToBlesta([
            'handle' => 'H-1',
            'type' => 'person',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'organization' => 'Max Mustermann',
            'street' => 'Musterstraße',
            'houseNumber' => '12a',
            'extension' => null,
            'city' => 'Berlin',
            'postalCode' => '10115',
            'state' => null,
            'country' => 'de',
            'email' => 'max@example.com',
            'phone' => '+49.301234567'
        ]);

        $this->assertSame([
            'external_id' => 'H-1',
            'email' => 'max@example.com',
            'phone' => '+49.301234567',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'company' => '',
            'address1' => 'Musterstraße 12a',
            'address2' => '',
            'city' => 'Berlin',
            'state' => '',
            'zip' => '10115',
            'country' => 'DE'
        ], $contact);
    }

    public function testApiContactToBlestaForOrganizationWithPlaceholders()
    {
        $contact = Helper::apiContactToBlesta([
            'handle' => 'H-2',
            'type' => 'organization',
            'firstName' => 'Erika',
            'lastName' => 'Musterfrau',
            'organization' => 'ACME GmbH',
            'street' => 'Industriegebiet',
            'houseNumber' => '-',
            'postalCode' => '-',
            'country' => 'DE'
        ]);

        $this->assertSame('ACME GmbH', $contact['company']);
        $this->assertSame('Industriegebiet', $contact['address1']);
        $this->assertSame('', $contact['zip']);
    }

    public function testBlestaContactToUpdate()
    {
        $payload = Helper::blestaContactToUpdate([
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'company' => 'ACME',
            'email' => 'max@example.com',
            'phone' => '+49.301234567',
            'address1' => 'Neue Straße 1',
            'city' => 'Köln',
            'zip' => '50667',
            'country' => 'DE'
        ]);

        $this->assertArrayNotHasKey('type', $payload);
        $this->assertArrayNotHasKey('sex', $payload);
        $this->assertSame('Neue Straße', $payload['street']);
        $this->assertSame('1', $payload['houseNumber']);
        $this->assertSame('ACME', $payload['organization']);
    }

    public function testBlestaContactToUpdateClearsOrganizationOfPersons()
    {
        $payload = Helper::blestaContactToUpdate([
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'company' => '',
            'email' => 'max@example.com',
            'phone' => '+49.301234567',
            'address1' => 'Musterstraße 12a',
            'city' => 'Berlin',
            'zip' => '10115',
            'country' => 'DE'
        ]);

        $this->assertSame('', $payload['organization']);
        $this->assertSame('12a', $payload['houseNumber']);

        $payload = Helper::blestaContactToUpdate(['first_name' => 'Max', 'address1' => 'Schlossallee', 'country' => 'DE']);
        $this->assertSame('Schlossallee', $payload['street']);
        $this->assertSame('', $payload['houseNumber'], 'A missing house number is removed');
    }

    // ------------------------------------------------------------------
    // DNS
    // ------------------------------------------------------------------

    public function testBuildDnsRecord()
    {
        list($record, $errors) = Helper::buildDnsRecord(['type' => 'A', 'name' => 'www.', 'content' => ' 1.2.3.4 ', 'ttl' => '300']);

        $this->assertSame([], $errors);
        $this->assertSame(['type' => 'A', 'name' => 'www', 'content' => '1.2.3.4', 'ttl' => 300], $record);
    }

    public function testBuildDnsRecordDefaults()
    {
        list($record, $errors) = Helper::buildDnsRecord(['type' => 'MX', 'name' => '', 'content' => 'mx.example.com', 'priority' => '10']);

        $this->assertSame([], $errors);
        $this->assertSame('@', $record['name']);
        $this->assertSame(3600, $record['ttl']);
        $this->assertSame(10, $record['priority']);
    }

    public function testBuildDnsRecordErrors()
    {
        list(, $errors) = Helper::buildDnsRecord(['type' => 'SOA', 'name' => 'in valid', 'content' => '', 'ttl' => '10', 'priority' => 'x']);
        $this->assertSame(
            ['type' => 'invalid', 'name' => 'invalid', 'content' => 'empty', 'ttl' => 'invalid', 'priority' => 'invalid'],
            $errors
        );

        list(, $errors) = Helper::buildDnsRecord(['type' => 'MX', 'content' => 'mx.example.com']);
        $this->assertSame(['priority' => 'empty'], $errors);

        list(, $errors) = Helper::buildDnsRecord(['type' => 'NS', 'name' => '@', 'content' => 'ns.example.com']);
        $this->assertSame(['name' => 'apex_ns'], $errors);
    }

    public function testBuildDnsRecordWildcardsAndUnderscores()
    {
        foreach (['*', '*.dev', '_dmarc', '_sip._tcp', 'a.b.c'] as $name) {
            list(, $errors) = Helper::buildDnsRecord(['type' => 'TXT', 'name' => $name, 'content' => 'x']);
            $this->assertSame([], $errors, $name);
        }
    }

    public function testBuildDnsRecordForUpdateOmitsType()
    {
        list($record, $errors) = Helper::buildDnsRecord(['type' => 'ignored', 'name' => 'www', 'content' => '5.6.7.8'], true);

        $this->assertSame([], $errors);
        $this->assertSame(['name' => 'www', 'content' => '5.6.7.8', 'ttl' => 3600], $record);
    }

    public function testParseDnskeys()
    {
        $keys = Helper::parseDnskeys("example.de. IN DNSKEY 257 3 13 abc==\r\n\n; comment\n  example.de.   IN DNSKEY 256 3 13 def==  \nexample.de. IN DNSKEY 257 3 13 abc==");

        $this->assertSame(['example.de. IN DNSKEY 257 3 13 abc==', 'example.de. IN DNSKEY 256 3 13 def=='], $keys);
    }

    // ------------------------------------------------------------------
    // Pricing
    // ------------------------------------------------------------------

    public function testBuildTldPricing()
    {
        $pricing = [
            ['tld' => 'de', 'registerPrice' => '3.50', 'renewPrice' => '4.00', 'transferPrice' => '0.00', 'setupPrice' => '1.00', 'minYears' => 1, 'maxYears' => 2],
            ['tld' => 'com', 'registerPrice' => '9.90', 'renewPrice' => '11.90', 'transferPrice' => '9.90', 'setupPrice' => null, 'minYears' => 2, 'maxYears' => 3],
            ['invalid' => true],
        ];
        $convert = function ($amount, $currency) {
            return $amount * 2;
        };

        $result = Helper::buildTldPricing($pricing, $convert, ['eur', 'USD', 'USD']);

        $this->assertSame(['.de', '.com'], array_keys($result));
        $this->assertSame(['EUR', 'USD'], array_keys($result['.de']));
        $this->assertSame([1, 2], array_keys($result['.de']['EUR']));
        $this->assertSame(['register' => 4.5, 'transfer' => 1.0, 'renew' => 4.0], $result['.de']['EUR'][1]);
        $this->assertSame(['register' => 8.0, 'transfer' => 1.0, 'renew' => 8.0], $result['.de']['EUR'][2]);
        $this->assertSame(['register' => 16.0, 'transfer' => 2.0, 'renew' => 16.0], $result['.de']['USD'][2]);
        $this->assertSame([2, 3], array_keys($result['.com']['EUR']));
        $this->assertSame(['register' => 29.7, 'transfer' => 9.9, 'renew' => 35.7], $result['.com']['EUR'][3]);
    }

    public function testBuildTldPricingFilters()
    {
        $pricing = [
            ['tld' => 'de', 'registerPrice' => '3.50', 'renewPrice' => '3.50', 'transferPrice' => '0', 'minYears' => 1, 'maxYears' => 10],
            ['tld' => 'com', 'registerPrice' => '9.90', 'renewPrice' => '9.90', 'transferPrice' => '9.90'],
        ];
        $convert = function ($amount) {
            return $amount;
        };

        $result = Helper::buildTldPricing($pricing, $convert, ['EUR', 'USD'], [
            'tlds' => ['de'],
            'currencies' => ['eur'],
            'terms' => [1, 3]
        ]);

        $this->assertSame(['.de'], array_keys($result));
        $this->assertSame(['EUR'], array_keys($result['.de']));
        $this->assertSame([1, 3], array_keys($result['.de']['EUR']));
    }

    public function testBuildTldPricingDefaultsToEur()
    {
        $result = Helper::buildTldPricing([['tld' => 'de', 'registerPrice' => '1']], function () {
            return 0;
        }, []);

        $this->assertSame(['EUR'], array_keys($result['.de']));
        $this->assertCount(10, $result['.de']['EUR']);
    }

    public function testTermPricesFromPeriods()
    {
        $terms = Helper::termPrices([
            'registerPrice' => '50.00', 'renewPrice' => '55.00', 'minYears' => 2, 'maxYears' => 10,
            'periods' => [
                ['years' => 5, 'registerPrice' => '200.00', 'renewPrice' => '210.00'],
                ['years' => 2, 'registerPrice' => '100.00', 'renewPrice' => null],
                ['years' => 0, 'registerPrice' => '1.00'],
                ['years' => 3]
            ]
        ]);

        $this->assertSame([2, 5], array_keys($terms));
        $this->assertSame(['register' => 100.0, 'renew' => 110.0], $terms[2], 'A missing renewal price falls back to the per-year price');
        $this->assertSame(['register' => 200.0, 'renew' => 210.0], $terms[5]);
    }

    public function testTermPricesWithoutPeriods()
    {
        $terms = Helper::termPrices(['registerPrice' => '3.50', 'renewPrice' => '4.00', 'minYears' => 1, 'maxYears' => 3]);

        $this->assertSame([1, 2, 3], array_keys($terms));
        $this->assertSame(['register' => 7.0, 'renew' => 8.0], $terms[2]);
    }

    public function testIdnTldsAreValid()
    {
        $this->assertTrue(Helper::isValidDomain('example.xn--p1ai'));
        $this->assertTrue(Helper::isValidHostname('ns1.example.xn--p1ai'));
        $this->assertFalse(Helper::isValidDomain('example.xn--'));
    }

    public function testTermRange()
    {
        $this->assertSame([1, 10], Helper::termRange([]));
        $this->assertSame([2, 5], Helper::termRange(['minYears' => 2, 'maxYears' => 5]));
        $this->assertSame([3, 3], Helper::termRange(['minYears' => 3, 'maxYears' => 1]));
        $this->assertSame([1, 10], Helper::termRange(['minYears' => 0, 'maxYears' => 99]));
    }

    // ------------------------------------------------------------------
    // Misc
    // ------------------------------------------------------------------

    public function testFormatDate()
    {
        $this->assertSame('2026-03-01 10:00:00', Helper::formatDate('2026-03-01T10:00:00.000Z'));
        $this->assertSame('2026-03-01 09:00:00', Helper::formatDate('2026-03-01T10:00:00+01:00'));
        $this->assertSame('01.03.2026', Helper::formatDate('2026-03-01T10:00:00Z', 'd.m.Y'));
        $this->assertFalse(Helper::formatDate(null));
        $this->assertFalse(Helper::formatDate('not a date'));
    }

    public function testMaskSensitive()
    {
        $this->assertSame(
            ['domain' => 'a.de', 'authCode' => '***', 'nested' => ['password' => '***', 'auth' => ''], 'list' => [1]],
            Helper::maskSensitive(['domain' => 'a.de', 'authCode' => 'secret', 'nested' => ['password' => 'x', 'auth' => ''], 'list' => [1]])
        );
        $this->assertSame('raw', Helper::maskSensitive('raw'));
    }
}
