# Domain Reseller API – Blesta Registrar Module

A Blesta registrar module for the [Domain Reseller API](https://domain-reseller-api.de) by Wolfscastle.
Register, transfer and manage domains (including `.de` and `.eu`) directly from Blesta – with contact
handles, managed DNS, DNSSEC, auth codes, lifecycle actions and TLD price synchronisation for the
Blesta Domain Manager.

## Features

| Area | What the module does |
| --- | --- |
| **Ordering** | Domain registration (1–10 years) and transfers (auth code) from the order form or admin area, premium protection, IDN support |
| **Contacts** | Creates the owner handle from the client profile (person or organisation) and reuses it for identical data; optional account-wide admin/tech/billing handles; contact editing for admins and clients; admin reassignment of handles incl. confirmed (possibly paid) owner changes / `.eu` trades |
| **Nameservers** | Custom nameservers or the managed DNS (`ns1/ns2.domain-reseller-api.de`), switching in both directions |
| **Glue records** | Create, update and delete hosts below the domain (admin and client), `setNameserverIps` for the Domain Manager |
| **Transfer lock** | Lock/unlock where the TLD supports it (admin and client) |
| **DNS** | Full record management (A, AAAA, CNAME, MX, TXT, SRV, CAA, NS, PTR, TLSA, SVCB, HTTPS, Redirect, Flatten), zone export and BIND zone import (admin) |
| **DNSSEC** | Automatic signing for managed DNS, DNSKEY publishing for custom nameservers |
| **Auth codes** | Retrieve (`.de`, `.eu`) and reset (`.de`) – optionally available to clients |
| **Lifecycle** | Paid renewals for the booked term, cancellation at end of term, cancellation revert, restore from redemption, immediate deletion, outgoing transfer approval/rejection, `.de` hold/unhold, renew date sync |
| **Domain Manager** | TLD import and price sync with the exact per-term prices (all company currencies), expiration/registration date sync, availability and transfer checks, nameserver management |
| **ID protection** | WHOIS privacy where supported, otherwise the registry protection flag of the owner contact |
| **Operations** | Multiple accounts (module rows), sandbox mode, wallet balance overview, request/response logging with masked secrets, English and German translations |

## Requirements

- Blesta 6.x (tested end-to-end with Blesta 6.0.3 and its Domain Manager) with PHP 8.1 or newer
  (`curl`, `json`, `mbstring`; `intl` recommended for IDNs). The views use Bootstrap 5 and plain
  JavaScript (no jQuery).
- An account at [domain-reseller-api.de](https://domain-reseller-api.de) with an API key.
  Restricted keys need the scopes `domains`, `contacts`, `dns` and `pricing` (`billing:read` is only
  used to show the wallet balance).
- A Domain Reseller API version that accepts contacts of type `person` without an organisation
  (natural persons are created without one, as with DENIC, OpenProvider and Vautron/BDOM).

## Installation

1. Download the latest release (or clone this repository) and copy the `components/` directory into
   your Blesta installation, so that the module ends up in
   `<blesta>/components/modules/domain_reseller_api/`.
2. In Blesta go to **Settings → Company → Modules → Available**, and install **Domain Reseller API**.
3. Click **Add Account** and enter:

   | Field | Description |
   | --- | --- |
   | Label | Any name to identify the account |
   | API Key | Your `drapi_…` key – the connection is tested when saving |
   | API URL | `https://domain-reseller-api.de` (only change when instructed) |
   | Sandbox Mode | Test without registering domains or being charged |
   | Allow Premium Domains | Premium domains are charged at their premium price; when off they are reported as unavailable |
   | On Cancellation | *Delete at end of term* (revertible until expiry) or *only disable auto-renewal* |
   | Fallback Phone Number | Used when a client has no valid phone number (`+49.301234567`) |
   | Admin/Tech/Billing Handle | Optional handles of your own that are used for every new domain instead of the owner. They are never modified through Blesta |

### With the Domain Manager (recommended)

1. Go to **Domains → TLD Pricing**, choose *Import TLDs* and select **Domain Reseller API**.
2. Use the price synchronisation to keep the TLD prices up to date (markups are configured in the
   Domain Manager). The module returns the **net** prices charged by the API (registration incl. any
   setup fee, renewal, transfer incl. setup fee) converted to all company currencies.
3. Optionally enable the configurable options *DNS Management*, *ID Protection* and *EPP Code* for
   the TLDs.

### Without the Domain Manager

Create a package with the module **Domain Reseller API**, select the TLDs, optional default
nameservers and whether clients may request auth codes / manage DNS.

## How it works

**Registration** – The domain is checked first (premium domains are rejected unless allowed), then the
owner contact is created from the client profile (`organization` for companies, otherwise `person`;
street and house number are split automatically) and the domain is registered with the term of the
selected pricing. Without at least two nameservers the managed DNS is used. Identical contact data
reuses the same handle per client.

**Transfers** – Started with the auth code entered in the order form. The domain stays in
*Transfer in progress* until the registry completes it; management tabs become available afterwards.

**Renewals** – Blesta is the billing master: when a renewal invoice is paid, Blesta's cron calls the
module, which renews the domain for the booked term (`POST /domains/{domain}/renew`, charged to your
wallet). A scheduled deletion is canceled first; expired domains are renewed as well, domains in
redemption are reported (restore them in the *Actions* tab). To avoid double renewals and charges,
the module keeps the API's own auto-renewal disabled (it can still be enabled per domain in the
*Settings* tab).

**Suspension** disables the API's auto-renewal; **unsuspension** changes nothing at the registry (the
domain is renewed again once the client pays). **Cancellation** schedules the deletion at the end of
the term (or lets the domain expire, see *On Cancellation*); reverting the cancellation in Blesta
cancels the deletion again.

**ID protection** – For TLDs that support WHOIS privacy and owners that are natural persons, WHOIS
privacy is requested (please make sure your clients accept the WHOIS privacy terms during ordering).
For all other TLDs the registry protection flag of the owner contact is used. Changing the option on
an existing service toggles WHOIS privacy, falling back to the protection flag.

**Contacts** – Natural persons are created without an organisation, organisations with their company
name. Clients can only edit the contact handles that the module created for them; shared handles and
the account-wide admin/tech/billing handles are read-only. An edited handle is no longer reused for
new orders.

## Service tabs

| Tab | Admin | Client |
| --- | --- | --- |
| Contacts | Edit contacts, assign other handles to owner/admin/tech/billing | Edit contacts |
| Name Servers | Custom nameservers or managed DNS | Custom nameservers or managed DNS |
| DNS Records | Records, zone export/import | Records (if *DNS Management* is enabled) |
| DNSSEC | Enable/disable, DNSKEY records | Same (if *DNS Management* is enabled) |
| Hosts | Glue records | Glue records |
| Settings | Overview, transfer lock, auto-renewal, auth code | Overview, transfer lock, auth code (if allowed) |
| Actions | Restore, cancel/schedule/immediate deletion, transfer-out, `.de` hold, renew date sync | – |

Clients get DNS management when the service has the `dns_management` configurable option or the
package allows it; auth codes when the service has the `epp_code` option or the package allows it.
Contact handles configured as account-wide admin/tech/billing handles are shown read-only.

## Limitations

- The transfer lock is only available for TLDs whose registry supports it (not for `.de`).
- Auth codes can only be retrieved for `.de` and `.eu` domains (and reset for `.de`).
- Transfers are a single operation and are therefore only offered for one year.
- Only the terms offered by the API (`periods`) are bookable; other terms are rejected.
- The registry only accepts email addresses consisting of letters, digits, `.`, `-` and `_`.

## Development

The tests run against stubs of the Blesta framework and an in-memory fake of the API (no network,
no Blesta installation required). The templates are rendered in the tests as well.

```bash
composer install
vendor/bin/phpunit

# or with Docker
docker build -t domain-reseller-api-blesta . && docker run --rm domain-reseller-api-blesta
```

Repository layout:

```
components/modules/domain_reseller_api/
├── domain_reseller_api.php       # Module (RegistrarModule)
├── apis/                         # HTTP client and response wrapper
├── lib/                          # Pure helpers (contacts, phone, DNS, pricing)
├── config/                       # Field and TLD configuration
├── language/{en_us,de_de}/       # Translations
└── views/default/                # Admin and client templates
tests/                            # PHPUnit tests and Blesta stubs
```

Releases are created by pushing a `v*` tag; the workflow injects the version and attaches a zip
containing the `components/` directory.

## License

MIT – see [LICENSE](LICENSE).
