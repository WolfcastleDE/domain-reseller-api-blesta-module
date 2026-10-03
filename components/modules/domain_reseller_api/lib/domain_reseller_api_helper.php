<?php
/**
 * Framework independent helpers for the Domain Reseller API module.
 *
 * Everything in here is pure (no Blesta, no HTTP) so it can be unit tested in isolation:
 * contact mapping, phone/address normalisation, nameserver handling, DNS record input
 * validation, TLD pricing conversion and status mapping.
 *
 * @package blesta
 * @subpackage blesta.components.modules.domain_reseller_api
 */
class DomainResellerApiHelper
{
    /**
     * @var string Hostname pattern accepted by the API for nameservers
     */
    const HOSTNAME_PATTERN = '/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+([a-z]{2,63}|xn--[a-z0-9-]{1,59})$/i';

    /**
     * @var string Phone format required by the API (+CC.NUMBER)
     */
    const PHONE_PATTERN = '/^\+\d{1,3}\.\d{1,14}$/';

    /**
     * @var string Email format accepted by the upstream registry
     */
    const EMAIL_PATTERN = '/^[0-9a-zA-Z._-]{1,64}@[0-9a-zA-Z._-]{3,64}$/';

    /**
     * @var string Placeholder used when the registry requires a value that is not available
     */
    const PLACEHOLDER = '-';

    /**
     * @var array Default managed nameservers of the Domain Reseller API
     */
    const MANAGED_NAMESERVERS = ['ns1.domain-reseller-api.de', 'ns2.domain-reseller-api.de'];

    /**
     * @var array DNS record types that can be managed through the API
     */
    const DNS_RECORD_TYPES = [
        'A', 'AAAA', 'CNAME', 'MX', 'TXT', 'SRV', 'CAA', 'NS', 'PTR', 'TLSA', 'SVCB', 'HTTPS', 'Redirect', 'Flatten'
    ];

    /**
     * @var array DNS record types that require a priority
     */
    const DNS_PRIORITY_TYPES = ['MX', 'SRV'];

    /**
     * @var array ITU calling codes by ISO 3166-1 alpha-2 country code
     */
    const CALLING_CODES = [
        'AD' => '376', 'AE' => '971', 'AF' => '93', 'AG' => '1', 'AI' => '1', 'AL' => '355', 'AM' => '374',
        'AO' => '244', 'AR' => '54', 'AS' => '1', 'AT' => '43', 'AU' => '61', 'AW' => '297', 'AX' => '358',
        'AZ' => '994', 'BA' => '387', 'BB' => '1', 'BD' => '880', 'BE' => '32', 'BF' => '226', 'BG' => '359',
        'BH' => '973', 'BI' => '257', 'BJ' => '229', 'BM' => '1', 'BN' => '673', 'BO' => '591', 'BR' => '55',
        'BS' => '1', 'BT' => '975', 'BW' => '267', 'BY' => '375', 'BZ' => '501', 'CA' => '1', 'CD' => '243',
        'CF' => '236', 'CG' => '242', 'CH' => '41', 'CI' => '225', 'CK' => '682', 'CL' => '56', 'CM' => '237',
        'CN' => '86', 'CO' => '57', 'CR' => '506', 'CU' => '53', 'CV' => '238', 'CW' => '599', 'CY' => '357',
        'CZ' => '420', 'DE' => '49', 'DJ' => '253', 'DK' => '45', 'DM' => '1', 'DO' => '1', 'DZ' => '213',
        'EC' => '593', 'EE' => '372', 'EG' => '20', 'ER' => '291', 'ES' => '34', 'ET' => '251', 'FI' => '358',
        'FJ' => '679', 'FK' => '500', 'FM' => '691', 'FO' => '298', 'FR' => '33', 'GA' => '241', 'GB' => '44',
        'GD' => '1', 'GE' => '995', 'GF' => '594', 'GG' => '44', 'GH' => '233', 'GI' => '350', 'GL' => '299',
        'GM' => '220', 'GN' => '224', 'GP' => '590', 'GQ' => '240', 'GR' => '30', 'GT' => '502', 'GU' => '1',
        'GW' => '245', 'GY' => '592', 'HK' => '852', 'HN' => '504', 'HR' => '385', 'HT' => '509', 'HU' => '36',
        'ID' => '62', 'IE' => '353', 'IL' => '972', 'IM' => '44', 'IN' => '91', 'IQ' => '964', 'IR' => '98',
        'IS' => '354', 'IT' => '39', 'JE' => '44', 'JM' => '1', 'JO' => '962', 'JP' => '81', 'KE' => '254',
        'KG' => '996', 'KH' => '855', 'KI' => '686', 'KM' => '269', 'KN' => '1', 'KP' => '850', 'KR' => '82',
        'KW' => '965', 'KY' => '1', 'KZ' => '7', 'LA' => '856', 'LB' => '961', 'LC' => '1', 'LI' => '423',
        'LK' => '94', 'LR' => '231', 'LS' => '266', 'LT' => '370', 'LU' => '352', 'LV' => '371', 'LY' => '218',
        'MA' => '212', 'MC' => '377', 'MD' => '373', 'ME' => '382', 'MF' => '590', 'MG' => '261', 'MH' => '692',
        'MK' => '389', 'ML' => '223', 'MM' => '95', 'MN' => '976', 'MO' => '853', 'MP' => '1', 'MQ' => '596',
        'MR' => '222', 'MS' => '1', 'MT' => '356', 'MU' => '230', 'MV' => '960', 'MW' => '265', 'MX' => '52',
        'MY' => '60', 'MZ' => '258', 'NA' => '264', 'NC' => '687', 'NE' => '227', 'NG' => '234', 'NI' => '505',
        'NL' => '31', 'NO' => '47', 'NP' => '977', 'NR' => '674', 'NU' => '683', 'NZ' => '64', 'OM' => '968',
        'PA' => '507', 'PE' => '51', 'PF' => '689', 'PG' => '675', 'PH' => '63', 'PK' => '92', 'PL' => '48',
        'PM' => '508', 'PR' => '1', 'PS' => '970', 'PT' => '351', 'PW' => '680', 'PY' => '595', 'QA' => '974',
        'RE' => '262', 'RO' => '40', 'RS' => '381', 'RU' => '7', 'RW' => '250', 'SA' => '966', 'SB' => '677',
        'SC' => '248', 'SD' => '249', 'SE' => '46', 'SG' => '65', 'SH' => '290', 'SI' => '386', 'SK' => '421',
        'SL' => '232', 'SM' => '378', 'SN' => '221', 'SO' => '252', 'SR' => '597', 'SS' => '211', 'ST' => '239',
        'SV' => '503', 'SX' => '1', 'SY' => '963', 'SZ' => '268', 'TC' => '1', 'TD' => '235', 'TG' => '228',
        'TH' => '66', 'TJ' => '992', 'TL' => '670', 'TM' => '993', 'TN' => '216', 'TO' => '676', 'TR' => '90',
        'TT' => '1', 'TV' => '688', 'TW' => '886', 'TZ' => '255', 'UA' => '380', 'UG' => '256', 'US' => '1',
        'UY' => '598', 'UZ' => '998', 'VA' => '39', 'VC' => '1', 'VE' => '58', 'VG' => '1', 'VI' => '1',
        'VN' => '84', 'VU' => '678', 'WF' => '681', 'WS' => '685', 'XK' => '383', 'YE' => '967', 'YT' => '262',
        'ZA' => '27', 'ZM' => '260', 'ZW' => '263',
    ];

    /**
     * @var array Countries that keep the national trunk prefix (0) in international format
     */
    const KEEP_TRUNK_PREFIX = ['IT', 'SM', 'VA'];

    // ------------------------------------------------------------------
    // Domains
    // ------------------------------------------------------------------

    /**
     * Normalises a domain name (trimmed, lower case, no trailing dot, IDN converted to punycode)
     *
     * @param string $domain The domain name
     * @return string The normalised domain name
     */
    public static function normalizeDomain($domain)
    {
        $domain = strtolower(trim((string) $domain));
        $domain = preg_replace('#^[a-z]+://#', '', $domain);
        $domain = rtrim(explode('/', $domain)[0], '.');

        if ($domain !== '' && preg_match('/[^\x20-\x7e]/', $domain) && function_exists('idn_to_ascii')) {
            $ascii = defined('INTL_IDNA_VARIANT_UTS46')
                ? idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46)
                : idn_to_ascii($domain);
            if ($ascii !== false) {
                $domain = strtolower($ascii);
            }
        }

        return $domain;
    }

    /**
     * Checks whether the given value is a syntactically valid domain name
     *
     * @param string $domain The domain name
     * @return bool True if valid
     */
    public static function isValidDomain($domain)
    {
        return (bool) preg_match(self::HOSTNAME_PATTERN, self::normalizeDomain($domain));
    }

    /**
     * Determines the TLD (with leading dot) of a domain, preferring the longest known TLD
     *
     * @param string $domain The domain name
     * @param array $known_tlds A list of known TLDs (with leading dot)
     * @return string The TLD including the leading dot (e.g. ".de"), or an empty string
     */
    public static function getTld($domain, array $known_tlds = [])
    {
        $domain = self::normalizeDomain($domain);

        usort($known_tlds, function ($a, $b) {
            return strlen($b) - strlen($a);
        });
        foreach ($known_tlds as $tld) {
            $tld = '.' . ltrim(strtolower($tld), '.');
            if (strlen($domain) > strlen($tld) && substr($domain, -strlen($tld)) === $tld) {
                return $tld;
            }
        }

        $position = strpos($domain, '.');

        return $position === false ? '' : substr($domain, $position);
    }

    // ------------------------------------------------------------------
    // Nameservers
    // ------------------------------------------------------------------

    /**
     * Normalises a list of nameservers: trims, lower-cases, removes trailing dots, empty and duplicate entries
     *
     * @param array $nameservers A list of nameservers
     * @return array The normalised, numerically indexed list
     */
    public static function normalizeNameservers(array $nameservers)
    {
        $result = [];
        foreach ($nameservers as $nameserver) {
            if (!is_scalar($nameserver)) {
                continue;
            }

            $nameserver = rtrim(strtolower(trim((string) $nameserver)), '.');
            if ($nameserver !== '' && !in_array($nameserver, $result, true)) {
                $result[] = $nameserver;
            }
        }

        return $result;
    }

    /**
     * Collects nameservers from a list of input fields (ns1..ns{max} or an `ns` array)
     *
     * @param array $vars The input vars
     * @param int $max The maximum number of nameservers
     * @return array The normalised list of nameservers
     */
    public static function nameserversFromVars(array $vars, $max = 6)
    {
        $nameservers = [];
        for ($i = 1; $i <= $max; $i++) {
            if (isset($vars['ns' . $i])) {
                $nameservers[] = $vars['ns' . $i];
            }
        }
        if (isset($vars['ns']) && is_array($vars['ns'])) {
            $nameservers = array_merge($nameservers, array_values($vars['ns']));
        }

        return array_slice(self::normalizeNameservers($nameservers), 0, $max);
    }

    /**
     * @param string $nameserver The nameserver hostname
     * @return bool True if the hostname is valid
     */
    public static function isValidHostname($nameserver)
    {
        return (bool) preg_match(self::HOSTNAME_PATTERN, (string) $nameserver);
    }

    /**
     * Checks whether a set of nameservers points to the managed DNS of the Domain Reseller API
     *
     * @param array $nameservers A list of nameservers
     * @return bool True if all nameservers belong to the managed DNS service
     */
    public static function isManagedNameserverSet(array $nameservers)
    {
        $nameservers = self::normalizeNameservers($nameservers);
        if (empty($nameservers)) {
            return false;
        }

        foreach ($nameservers as $nameserver) {
            if (!in_array($nameserver, self::MANAGED_NAMESERVERS, true)
                && substr($nameserver, -strlen('.domain-reseller-api.de')) !== '.domain-reseller-api.de'
            ) {
                return false;
            }
        }

        return true;
    }

    // ------------------------------------------------------------------
    // Contacts
    // ------------------------------------------------------------------

    /**
     * Formats a phone number into the +CC.NUMBER format required by the registry
     *
     * @param string $phone The phone number in any common format
     * @param string $country The ISO 3166-1 alpha-2 country code of the contact
     * @return string|null The formatted number, or null if it could not be formatted
     */
    public static function formatPhone($phone, $country = '')
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }

        if (preg_match(self::PHONE_PATTERN, $phone)) {
            return $phone;
        }

        // Drop extensions such as "x123" or "ext. 123"
        $phone = preg_replace('/\s*(?:x|ext\.?|extension)\s*\d+$/i', '', $phone);

        $international = false;
        if (strpos($phone, '+') === 0) {
            $international = true;
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if (!$international && strpos($digits, '00') === 0) {
            $international = true;
            $digits = substr($digits, 2);
        }

        if ($digits === '') {
            return null;
        }

        $country = strtoupper(trim((string) $country));
        $country_code = self::CALLING_CODES[$country] ?? null;

        if ($international) {
            // "+49 (0) 30 123" style numbers carry a redundant trunk prefix
            $code = null;
            if ($country_code !== null && strpos($digits, $country_code) === 0) {
                $code = $country_code;
            } else {
                foreach ([3, 2, 1] as $length) {
                    $candidate = substr($digits, 0, $length);
                    if (in_array($candidate, self::CALLING_CODES, true)) {
                        $code = $candidate;
                        break;
                    }
                }
            }

            if ($code === null) {
                return null;
            }

            $number = substr($digits, strlen($code));
            $code_country = $country_code === $code ? $country : array_search($code, self::CALLING_CODES, true);
            if (preg_match('/\(\s*0\s*\)/', $phone) || !in_array($code_country, self::KEEP_TRUNK_PREFIX, true)) {
                $number = ltrim($number, '0');
            }
        } else {
            if ($country_code === null) {
                return null;
            }

            $code = $country_code;
            $number = in_array($country, self::KEEP_TRUNK_PREFIX, true) ? $digits : ltrim($digits, '0');
            // NANP numbers are often written with the leading 1
            if ($code === '1' && strlen($number) === 11 && $number[0] === '1') {
                $number = substr($number, 1);
            }
        }

        if ($number === '' || strlen($number) > 14) {
            return null;
        }

        return '+' . $code . '.' . $number;
    }

    /**
     * Splits an address line into street and house number
     *
     * Supports the European ("Musterstraße 12a") and the North American ("123 Main St") format.
     *
     * @param string $address The address line
     * @return array A list containing the street and house number
     */
    public static function splitStreet($address)
    {
        $address = trim(preg_replace('/\s+/u', ' ', (string) $address));
        if ($address === '') {
            return ['', ''];
        }

        $number = '\d+\s?[a-zA-Z]?(?:\s?[-\/]\s?\d+\s?[a-zA-Z]?)?';

        // European: street first, number last
        if (preg_match('/^(.*?\D)[\s,]+(' . $number . ')$/u', $address, $matches)) {
            return [rtrim($matches[1], ' ,'), str_replace(' ', '', $matches[2])];
        }

        // North American: number first
        if (preg_match('/^(' . $number . ')[\s,]+(.*\D.*)$/u', $address, $matches)) {
            return [trim($matches[2]), str_replace(' ', '', $matches[1])];
        }

        return [$address, ''];
    }

    /**
     * Builds the API contact payload from a normalised contact
     *
     * @param array $contact The contact containing any of:
     *
     *  - first_name, last_name, company, email, phone, fax, address1, address2, city, state, zip, country,
     *    protection (bool)
     * @param string|null $fallback_phone A phone number to use when the contact has none
     * @return array The payload for POST /contacts
     */
    public static function buildContactPayload(array $contact, $fallback_phone = null)
    {
        $value = function ($key) use ($contact) {
            return trim((string) ($contact[$key] ?? ''));
        };

        $first_name = $value('first_name');
        $last_name = $value('last_name');
        $company = $value('company');
        $country = strtoupper($value('country'));

        list($street, $house_number) = self::splitStreet($value('address1'));

        $phone = self::formatPhone($value('phone'), $country);
        if ($phone === null && !empty($fallback_phone)) {
            $phone = self::formatPhone($fallback_phone, $country);
        }

        $payload = [
            'type' => $company !== '' ? 'organization' : 'person',
            'sex' => 'NA',
            'firstName' => self::limit($first_name !== '' ? $first_name : ($company !== '' ? $company : self::PLACEHOLDER), 100),
            'lastName' => self::limit($last_name !== '' ? $last_name : ($company !== '' ? $company : self::PLACEHOLDER), 100),
            'street' => self::limit($street !== '' ? $street : self::PLACEHOLDER, 255),
            'postalCode' => self::limit($value('zip') !== '' ? $value('zip') : self::PLACEHOLDER, 20),
            'city' => self::limit($value('city') !== '' ? $value('city') : self::PLACEHOLDER, 100),
            'country' => $country,
            'email' => $value('email'),
            'phone' => (string) $phone,
        ];

        // Addresses without a separate house number send none
        if ($house_number !== '') {
            $payload['houseNumber'] = self::limit($house_number, 20);
        }

        // Natural persons have no organisation (registries treat it as the marker of a legal person)
        if ($company !== '') {
            $payload['organization'] = self::limit($company, 255);
        }

        $fax = self::formatPhone($value('fax'), $country);
        if ($fax !== null) {
            $payload['fax'] = $fax;
        }

        if ($value('address2') !== '') {
            $payload['extension'] = self::limit($value('address2'), 255);
        }

        if ($value('state') !== '') {
            $payload['state'] = self::limit($value('state'), 100);
        }

        if (array_key_exists('protection', $contact) && $contact['protection'] !== null) {
            $payload['protection'] = (bool) $contact['protection'];
        }

        return $payload;
    }

    /**
     * Validates a contact payload against the API constraints
     *
     * @param array $payload The payload built by buildContactPayload()
     * @return array A list of invalid field names (empty if valid)
     */
    public static function validateContactPayload(array $payload)
    {
        $invalid = [];

        if (!preg_match('/^[A-Z]{2}$/', $payload['country'] ?? '')) {
            $invalid[] = 'country';
        }
        if (!preg_match(self::EMAIL_PATTERN, $payload['email'] ?? '')) {
            $invalid[] = 'email';
        }
        if (!preg_match(self::PHONE_PATTERN, $payload['phone'] ?? '')) {
            $invalid[] = 'phone';
        }

        return $invalid;
    }

    /**
     * Creates a stable fingerprint of a contact payload, used to reuse identical contact handles
     *
     * @param array $payload The contact payload
     * @return string The fingerprint
     */
    public static function contactFingerprint(array $payload)
    {
        ksort($payload);
        array_walk($payload, function (&$value) {
            $value = is_string($value) ? mb_strtolower(trim($value)) : $value;
        });

        return sha1(json_encode($payload));
    }

    /**
     * Converts an API contact into the contact format used by Blesta registrar modules
     *
     * @param array $contact The contact as returned by GET /contacts/{handle}
     * @return array The contact (external_id, email, phone, first_name, last_name, company, address1,
     *  address2, city, state, zip, country)
     */
    public static function apiContactToBlesta(array $contact)
    {
        $street = trim((string) ($contact['street'] ?? ''));
        $house_number = trim((string) ($contact['houseNumber'] ?? ''));
        if ($street === self::PLACEHOLDER) {
            $street = '';
        }
        if ($house_number !== '' && $house_number !== self::PLACEHOLDER) {
            $street = trim($street . ' ' . $house_number);
        }

        $clean = function ($key) use ($contact) {
            $value = trim((string) ($contact[$key] ?? ''));

            return $value === self::PLACEHOLDER ? '' : $value;
        };

        $first_name = $clean('firstName');
        $last_name = $clean('lastName');
        $organization = $clean('organization');
        // Persons carry their own name as organisation; do not show it as a company
        $is_person = ($contact['type'] ?? '') === 'person'
            || mb_strtolower($organization) === mb_strtolower(trim($first_name . ' ' . $last_name));

        return [
            'external_id' => (string) ($contact['handle'] ?? ''),
            'email' => $clean('email'),
            'phone' => $clean('phone'),
            'first_name' => $first_name,
            'last_name' => $last_name,
            'company' => $is_person ? '' : $organization,
            'address1' => $street,
            'address2' => $clean('extension'),
            'city' => $clean('city'),
            'state' => $clean('state'),
            'zip' => $clean('postalCode'),
            'country' => strtoupper($clean('country')),
        ];
    }

    /**
     * Builds a PATCH /contacts/{handle} payload from contact form input in Blesta format
     *
     * @param array $contact The submitted contact (first_name, last_name, company, email, phone, address1,
     *  address2, city, state, zip, country)
     * @param string|null $fallback_phone A phone number to use if the submitted number is invalid
     * @return array The update payload
     */
    public static function blestaContactToUpdate(array $contact, $fallback_phone = null)
    {
        $payload = self::buildContactPayload($contact, $fallback_phone);

        // The contact type can not be changed after creation
        unset($payload['type'], $payload['sex']);

        // Empty values remove the organisation (persons) and the house number
        foreach (['organization', 'houseNumber'] as $field) {
            if (!isset($payload[$field])) {
                $payload[$field] = '';
            }
        }

        return $payload;
    }

    // ------------------------------------------------------------------
    // DNS
    // ------------------------------------------------------------------

    /**
     * Validates and normalises a DNS record submitted through a form
     *
     * @param array $input The input containing type, name, content, ttl, priority
     * @param bool $update True when updating an existing record (the type can not be changed)
     * @return array A list containing the API payload and a list of error messages keyed by field
     */
    public static function buildDnsRecord(array $input, $update = false)
    {
        $errors = [];
        $type = trim((string) ($input['type'] ?? ''));
        $name = trim((string) ($input['name'] ?? ''));
        $content = trim((string) ($input['content'] ?? ''));
        $ttl = trim((string) ($input['ttl'] ?? ''));
        $priority = trim((string) ($input['priority'] ?? ''));

        if (!$update && !in_array($type, self::DNS_RECORD_TYPES, true)) {
            $errors['type'] = 'invalid';
        }

        $name = rtrim($name, '.');
        if ($name === '') {
            $name = '@';
        }
        if ($name !== '@' && !preg_match('/^(\*\.)?([a-zA-Z0-9_]([a-zA-Z0-9_-]{0,61}[a-zA-Z0-9_])?)(\.[a-zA-Z0-9_]([a-zA-Z0-9_-]{0,61}[a-zA-Z0-9_])?)*$|^\*$/', $name)) {
            $errors['name'] = 'invalid';
        }

        if ($content === '') {
            $errors['content'] = 'empty';
        }

        $payload = ['name' => $name, 'content' => $content];
        if (!$update) {
            $payload = ['type' => $type] + $payload;
        }

        if ($ttl === '') {
            $payload['ttl'] = 3600;
        } elseif (!ctype_digit($ttl) || (int) $ttl < 60 || (int) $ttl > 86400) {
            $errors['ttl'] = 'invalid';
        } else {
            $payload['ttl'] = (int) $ttl;
        }

        if ($priority !== '') {
            if (!ctype_digit($priority) || (int) $priority > 65535) {
                $errors['priority'] = 'invalid';
            } else {
                $payload['priority'] = (int) $priority;
            }
        } elseif (!$update && in_array($type, self::DNS_PRIORITY_TYPES, true)) {
            $errors['priority'] = 'empty';
        }

        if (!$update && $type === 'NS' && $name === '@') {
            $errors['name'] = 'apex_ns';
        }

        return [$payload, $errors];
    }

    /**
     * Parses a block of DNSKEY records (one per line)
     *
     * @param string $text The DNSKEY records
     * @return array A list of DNSKEY records
     */
    public static function parseDnskeys($text)
    {
        $keys = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line));
            if ($line !== '' && strpos($line, ';') !== 0) {
                $keys[] = $line;
            }
        }

        return array_slice(array_values(array_unique($keys)), 0, 10);
    }

    // ------------------------------------------------------------------
    // Pricing
    // ------------------------------------------------------------------

    /**
     * Converts the API pricing list into the format expected by Blesta
     *
     * Prices are net EUR prices as charged by the API: the exact total of every bookable term
     * (`periods`) plus the setup fee for registrations. Transfers are a single operation
     * (`transferPrice + setupPrice`), listed for every term.
     *
     * @param array $pricing The `data` array returned by GET /pricing
     * @param callable $convert A callback ($amount, $currency) returning the amount converted from EUR
     * @param array $currencies A list of currency codes to return
     * @param array $filters Filters: tlds (with leading dot), terms
     * @return array [tld => [currency => [years => ['register' => x, 'transfer' => y, 'renew' => z]]]]
     */
    public static function buildTldPricing(array $pricing, callable $convert, array $currencies, array $filters = [])
    {
        $currencies = array_values(array_unique(array_filter(array_map('strtoupper', $currencies))));
        if (empty($currencies)) {
            $currencies = ['EUR'];
        }

        $tld_filter = isset($filters['tlds']) ? array_map(function ($tld) {
            return '.' . ltrim(strtolower($tld), '.');
        }, (array) $filters['tlds']) : null;
        $currency_filter = isset($filters['currencies'])
            ? array_map('strtoupper', (array) $filters['currencies'])
            : null;
        $term_filter = isset($filters['terms']) ? array_map('intval', (array) $filters['terms']) : null;

        $result = [];
        foreach ($pricing as $row) {
            if (!is_array($row) || empty($row['tld'])) {
                continue;
            }

            $tld = '.' . ltrim(strtolower($row['tld']), '.');
            if ($tld_filter !== null && !in_array($tld, $tld_filter, true)) {
                continue;
            }

            $transfer = (float) ($row['transferPrice'] ?? 0);
            $setup = (float) ($row['setupPrice'] ?? 0);
            $terms = self::termPrices($row);

            foreach ($currencies as $currency) {
                if ($currency_filter !== null && !in_array($currency, $currency_filter, true)) {
                    continue;
                }

                foreach ($terms as $years => $term) {
                    if ($term_filter !== null && !in_array($years, $term_filter, true)) {
                        continue;
                    }

                    $prices = [
                        'register' => $term['register'] + $setup,
                        'transfer' => $transfer + $setup,
                        'renew' => $term['renew'],
                    ];

                    foreach ($prices as $operation => $amount) {
                        $converted = $currency === 'EUR' ? $amount : $convert($amount, $currency);
                        $prices[$operation] = round((float) $converted, 2);
                    }

                    $result[$tld][$currency][$years] = $prices;
                }
            }
        }

        return $result;
    }

    /**
     * Returns the bookable terms of a TLD pricing row with their total net prices
     *
     * Uses the exact per-term totals of `periods` (a TLD may be cheaper per year on longer
     * terms). Without `periods`, every term between minYears and maxYears is priced per year.
     * A term without a renewal price is renewed at the per-year renewal price.
     *
     * @param array $row The pricing row
     * @return array [years => ['register' => total, 'renew' => total]], ordered by years
     */
    public static function termPrices(array $row)
    {
        $per_year_register = (float) ($row['registerPrice'] ?? 0);
        $per_year_renew = (float) ($row['renewPrice'] ?? $per_year_register);

        $terms = [];
        foreach ((array) ($row['periods'] ?? []) as $period) {
            $years = (int) ($period['years'] ?? 0);
            if ($years < 1 || $years > 10 || !isset($period['registerPrice'])) {
                continue;
            }

            $terms[$years] = [
                'register' => (float) $period['registerPrice'],
                'renew' => isset($period['renewPrice']) && $period['renewPrice'] !== null
                    ? (float) $period['renewPrice']
                    : $per_year_renew * $years
            ];
        }

        if (empty($terms)) {
            list($min, $max) = self::termRange($row);
            for ($years = $min; $years <= $max; $years++) {
                $terms[$years] = ['register' => $per_year_register * $years, 'renew' => $per_year_renew * $years];
            }
        }

        ksort($terms);

        return $terms;
    }

    /**
     * Returns the allowed registration term range of a TLD pricing row
     *
     * @param array $row The pricing row
     * @return array A list containing the minimum and maximum number of years
     */
    public static function termRange(array $row)
    {
        $min = max(1, (int) ($row['minYears'] ?? 1));
        $max = min(10, max($min, (int) ($row['maxYears'] ?? 10)));

        return [$min, $max];
    }

    // ------------------------------------------------------------------
    // Misc
    // ------------------------------------------------------------------

    /**
     * Formats an ISO 8601 date (as returned by the API) in UTC
     *
     * @param string|null $date The date
     * @param string $format The output format
     * @return string|false The formatted date or false if the date is empty/invalid
     */
    public static function formatDate($date, $format = 'Y-m-d H:i:s')
    {
        if (empty($date)) {
            return false;
        }

        try {
            $datetime = new DateTime((string) $date, new DateTimeZone('UTC'));
        } catch (Exception $e) {
            return false;
        }
        $datetime->setTimezone(new DateTimeZone('UTC'));

        return $datetime->format($format);
    }

    /**
     * Masks sensitive values in data that is written to the module log
     *
     * @param mixed $data The data
     * @return mixed The masked data
     */
    public static function maskSensitive($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $sensitive = ['authcode', 'auth', 'epp_code', 'password', 'api_key', 'apikey', 'authorization'];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::maskSensitive($value);
            } elseif (is_string($key) && in_array(strtolower($key), $sensitive, true) && $value !== '' && $value !== null) {
                $data[$key] = '***';
            }
        }

        return $data;
    }

    /**
     * Truncates a string to the given number of characters
     *
     * @param string $value The value
     * @param int $length The maximum length
     * @return string The truncated value
     */
    private static function limit($value, $length)
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
    }
}
