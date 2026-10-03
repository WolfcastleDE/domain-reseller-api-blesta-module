<?php
/**
 * Domain Reseller API module configuration
 */

// Service fields shown when adding a domain
Configure::set('DomainResellerApi.domain_fields', [
    'domain' => [
        'label' => Language::_('DomainResellerApi.service_fields.domain', true),
        'type' => 'text'
    ]
]);

// Transfer fields
Configure::set('DomainResellerApi.transfer_fields', [
    'auth' => [
        'label' => Language::_('DomainResellerApi.service_fields.auth', true),
        'type' => 'text'
    ]
]);

// Nameserver fields
Configure::set('DomainResellerApi.nameserver_fields', [
    'ns1' => [
        'label' => Language::_('DomainResellerApi.service_fields.ns1', true),
        'type' => 'text'
    ],
    'ns2' => [
        'label' => Language::_('DomainResellerApi.service_fields.ns2', true),
        'type' => 'text'
    ],
    'ns3' => [
        'label' => Language::_('DomainResellerApi.service_fields.ns3', true),
        'type' => 'text'
    ],
    'ns4' => [
        'label' => Language::_('DomainResellerApi.service_fields.ns4', true),
        'type' => 'text'
    ],
    'ns5' => [
        'label' => Language::_('DomainResellerApi.service_fields.ns5', true),
        'type' => 'text'
    ]
]);

// DNS record types offered in the DNS management tabs
Configure::set('DomainResellerApi.dns_record_types', [
    'A' => 'A',
    'AAAA' => 'AAAA',
    'CNAME' => 'CNAME',
    'MX' => 'MX',
    'TXT' => 'TXT',
    'SRV' => 'SRV',
    'CAA' => 'CAA',
    'NS' => 'NS',
    'PTR' => 'PTR',
    'TLSA' => 'TLSA',
    'SVCB' => 'SVCB',
    'HTTPS' => 'HTTPS',
    'Redirect' => 'Redirect (HTTP)',
    'Flatten' => 'Flatten (CNAME at apex)'
]);

// TLDs offered when the pricing list can not be fetched
Configure::set('DomainResellerApi.tlds', [
    '.de',
    '.eu',
    '.com',
    '.net',
    '.org',
    '.info',
    '.biz',
    '.io',
    '.dev',
    '.app',
    '.at',
    '.ch'
]);
