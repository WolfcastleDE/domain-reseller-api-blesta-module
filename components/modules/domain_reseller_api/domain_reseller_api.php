<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'apis' . DIRECTORY_SEPARATOR . 'domain_reseller_api_client.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'domain_reseller_api_helper.php';

/**
 * Domain Reseller API registrar module
 *
 * Registers, transfers and manages domains through the Domain Reseller API by Wolfscastle
 * (https://domain-reseller-api.de), including contact handles, nameservers, managed DNS,
 * DNSSEC, auth codes, lifecycle actions (restore, deletion, transfer-out, .de hold) and
 * TLD pricing synchronisation for the Blesta Domain Manager.
 *
 * @package blesta
 * @subpackage blesta.components.modules.domain_reseller_api
 * @link https://domain-reseller-api.de/swagger API documentation
 */
class DomainResellerApi extends RegistrarModule
{
    /**
     * @var string The module view path
     */
    const VIEW_PATH = 'components' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR
        . 'domain_reseller_api' . DIRECTORY_SEPARATOR;

    /**
     * @var array Module row meta fields
     */
    const ROW_META_FIELDS = [
        'label', 'api_key', 'api_url', 'sandbox', 'allow_premium', 'cancel_action',
        'fallback_phone', 'admin_handle', 'tech_handle', 'billing_handle'
    ];

    /**
     * @var array Contact roles of a domain
     */
    const CONTACT_ROLES = ['owner', 'admin', 'tech', 'billing'];

    /**
     * @var int Maximum number of nameservers handled by Blesta service fields
     */
    const MAX_NAMESERVERS = 5;

    /**
     * @var array Domain info cache for the current request, keyed by row ID and domain
     */
    private $domain_cache = [];

    /**
     * @var array TLD pricing cache for the current request, keyed by API URL
     */
    private $pricing_cache = [];

    /**
     * @var array|null Handles a client may edit (null = no restriction, e.g. for staff)
     */
    private $editable_handles = null;

    /**
     * Initializes the module
     */
    public function __construct()
    {
        Language::loadLang('domain_reseller_api', null, __DIR__ . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR);

        Loader::loadComponents($this, ['Input']);

        $this->loadConfig(__DIR__ . DIRECTORY_SEPARATOR . 'config.json');

        Configure::load('domain_reseller_api', __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR);
    }

    // ==================================================================
    // Module rows (accounts)
    // ==================================================================

    /**
     * Returns the rendered view of the manage module page
     *
     * @param mixed $module A stdClass object representing the module and its rows
     * @param array $vars An array of post data submitted to or on the manage module page
     * @return string HTML content containing information to display when viewing the manager module page
     */
    public function manageModule($module, array &$vars)
    {
        $this->view = $this->makeView('manage');
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        // Show the wallet balance of every account
        $balances = [];
        foreach ((array) ($module->rows ?? []) as $row) {
            $balances[$row->id] = $this->fetchWallet($row);
        }

        $this->view->set('module', $module);
        $this->view->set('balances', $balances);

        return $this->view->fetch();
    }

    /**
     * Returns the rendered view of the add module row page
     *
     * @param array $vars An array of post data submitted to or on the add module row page
     * @return string HTML content containing information to display when viewing the add module row page
     */
    public function manageAddRow(array &$vars)
    {
        $this->view = $this->makeView('add_row');
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        if (empty($vars)) {
            $vars = [
                'api_url' => DomainResellerApiClient::DEFAULT_URL,
                'sandbox' => 'false',
                'allow_premium' => 'false',
                'cancel_action' => 'schedule_delete'
            ];
        } else {
            $this->setUncheckedBoxes($vars);
        }

        $this->view->set('vars', (object) $vars);
        $this->view->set('cancel_actions', $this->getCancelActions());

        return $this->view->fetch();
    }

    /**
     * Returns the rendered view of the edit module row page
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     * @param array $vars An array of post data submitted to or on the edit module row page
     * @return string HTML content containing information to display when viewing the edit module row page
     */
    public function manageEditRow($module_row, array &$vars)
    {
        $this->view = $this->makeView('edit_row');
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        if (empty($vars)) {
            $vars = (array) $module_row->meta;
        } else {
            $this->setUncheckedBoxes($vars);
        }

        $this->view->set('vars', (object) $vars);
        $this->view->set('cancel_actions', $this->getCancelActions());

        return $this->view->fetch();
    }

    /**
     * Adds the module row. Sets Input errors on failure, preventing the row from being added.
     *
     * @param array $vars An array of module info to add
     * @return array A numerically indexed array of meta fields for the module row
     */
    public function addModuleRow(array &$vars)
    {
        return $this->buildRowMeta($vars);
    }

    /**
     * Edits the module row. Sets Input errors on failure, preventing the row from being updated.
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     * @param array $vars An array of module info to update
     * @return array A numerically indexed array of meta fields for the module row
     */
    public function editModuleRow($module_row, array &$vars)
    {
        // Keep the stored API key when the field is left empty
        if (isset($vars['api_key']) && trim($vars['api_key']) === '' && !empty($module_row->meta->api_key)) {
            $vars['api_key'] = $module_row->meta->api_key;
        }

        return $this->buildRowMeta($vars);
    }

    /**
     * Validates the given connection details by fetching the domain list of the account
     *
     * @param string $api_key The API key
     * @param string $api_url The API base URL
     * @param string $sandbox "true" to use sandbox mode
     * @return bool True if the connection is valid
     */
    public function validateConnection($api_key, $api_url = null, $sandbox = 'false')
    {
        if (!DomainResellerApiClient::isAllowedUrl($api_url ?: DomainResellerApiClient::DEFAULT_URL)) {
            return false;
        }

        $row = (object) ['meta' => (object) ['api_key' => $api_key, 'api_url' => $api_url, 'sandbox' => $sandbox]];
        $api = $this->getApi($row, 20);

        return $this->call($api, 'listDomains', [1, 1])->success();
    }

    /**
     * Validates an optional contact handle that is used for every domain
     *
     * @param string $handle The contact handle
     * @param string $api_key The API key
     * @param string $api_url The API base URL
     * @param string $sandbox "true" to use sandbox mode
     * @return bool True if the handle is empty or exists in the account
     */
    public function validateHandle($handle, $api_key = null, $api_url = null, $sandbox = 'false')
    {
        if (trim((string) $handle) === '') {
            return true;
        }

        if (!DomainResellerApiClient::isAllowedUrl($api_url ?: DomainResellerApiClient::DEFAULT_URL)) {
            return false;
        }

        $row = (object) ['meta' => (object) ['api_key' => $api_key, 'api_url' => $api_url, 'sandbox' => $sandbox]];

        return $this->call($this->getApi($row, 20), 'getContact', [trim($handle)])->success();
    }

    /**
     * Validates the optional fallback phone number
     *
     * @param string $phone The phone number
     * @return bool True if empty or a valid international phone number
     */
    public function validatePhone($phone)
    {
        return trim((string) $phone) === ''
            || DomainResellerApiHelper::formatPhone($phone, '') !== null;
    }

    // ==================================================================
    // Packages
    // ==================================================================

    /**
     * Returns all fields used when adding/editing a package
     *
     * @param stdClass $vars A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object containing the fields to render
     */
    public function getPackageFields($vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        $fields = new ModuleFields();
        $meta = (array) ($vars->meta ?? []);

        // Module row used to fetch the available TLDs
        $module_row_id = null;
        if (!empty($vars->module_row)) {
            $module_row_id = $vars->module_row;
        } elseif (!empty($vars->module_group)) {
            $rows = $this->getModuleRows($vars->module_group);
            $module_row_id = !empty($rows) ? reset($rows)->id : null;
        }

        // TLDs
        $tld_options = $fields->label(Language::_('DomainResellerApi.package_fields.tld_options', true));
        $tlds = $this->getTlds($module_row_id);
        sort($tlds);
        foreach ($tlds as $tld) {
            $tld_label = $fields->label($tld, 'tld_' . $tld);
            $tld_options->attach(
                $fields->fieldCheckbox(
                    'meta[tlds][]',
                    $tld,
                    in_array($tld, (array) ($meta['tlds'] ?? [])),
                    ['id' => 'tld_' . $tld],
                    $tld_label
                )
            );
        }
        $fields->setField($tld_options);

        // Default nameservers
        for ($i = 1; $i <= self::MAX_NAMESERVERS; $i++) {
            $ns = $fields->label(
                Language::_('DomainResellerApi.package_fields.ns' . $i, true),
                'domain_reseller_api_ns' . $i
            );
            $ns->attach(
                $fields->fieldText(
                    'meta[ns][]',
                    ((array) ($meta['ns'] ?? []))[$i - 1] ?? null,
                    ['id' => 'domain_reseller_api_ns' . $i]
                )
            );
            if ($i === 1) {
                $ns->attach($fields->tooltip(Language::_('DomainResellerApi.package_fields.tooltip.ns', true)));
            }
            $fields->setField($ns);
        }

        // EPP code access for clients
        $epp_code = $fields->label(
            Language::_('DomainResellerApi.package_fields.epp_code', true),
            'domain_reseller_api_epp_code'
        );
        $epp_code->attach(
            $fields->fieldCheckbox(
                'meta[epp_code]',
                '1',
                ($meta['epp_code'] ?? '0') == '1',
                ['id' => 'domain_reseller_api_epp_code']
            )
        );
        $epp_code->attach($fields->tooltip(Language::_('DomainResellerApi.package_fields.tooltip.epp_code', true)));
        $fields->setField($epp_code);

        // DNS management for clients
        $dns = $fields->label(
            Language::_('DomainResellerApi.package_fields.dns_management', true),
            'domain_reseller_api_dns_management'
        );
        $dns->attach(
            $fields->fieldCheckbox(
                'meta[dns_management]',
                '1',
                ($meta['dns_management'] ?? '0') == '1',
                ['id' => 'domain_reseller_api_dns_management']
            )
        );
        $dns->attach($fields->tooltip(Language::_('DomainResellerApi.package_fields.tooltip.dns_management', true)));
        $fields->setField($dns);

        return $fields;
    }

    /**
     * Validates input data when attempting to add a package
     *
     * @param array $vars An array of key/value pairs used to add the package
     * @return array A numerically indexed array of meta fields to be stored for this package
     */
    public function addPackage(?array $vars = null)
    {
        return $this->buildPackageMeta($vars ?? []);
    }

    /**
     * Validates input data when attempting to edit a package
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of key/value pairs used to edit the package
     * @return array A numerically indexed array of meta fields to be stored for this package
     */
    public function editPackage($package, ?array $vars = null)
    {
        return $this->buildPackageMeta($vars ?? []);
    }

    // ==================================================================
    // Service fields
    // ==================================================================

    /**
     * Returns all fields to display to an admin attempting to add a service with the module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param stdClass $vars A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object containing the fields to render
     */
    public function getAdminAddFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);
        $vars = $this->withDefaultNameservers($package, $vars);
        if (!isset($vars->transfer)) {
            $vars->transfer = !empty($vars->auth) ? '1' : '0';
        }

        $fields = Configure::get('DomainResellerApi.domain_fields');
        $fields['transfer'] = [
            'label' => Language::_('DomainResellerApi.service_fields.transfer', true),
            'type' => 'radio',
            'options' => [
                '0' => Language::_('DomainResellerApi.service_fields.transfer_register', true),
                '1' => Language::_('DomainResellerApi.service_fields.transfer_transfer', true)
            ]
        ];

        $module_fields = $this->arrayToModuleFields(
            array_merge(
                $fields,
                Configure::get('DomainResellerApi.transfer_fields'),
                Configure::get('DomainResellerApi.nameserver_fields')
            ),
            null,
            $vars
        );

        $module_fields->setHtml(
            "<script type=\"text/javascript\">
                (function () {
                    function domainResellerApiToggle() {
                        var checked = document.querySelector('input[name=\"transfer\"]:checked');
                        var transfer = checked && checked.value == '1';
                        var toggle = function (id, show) {
                            var field = document.getElementById(id);
                            var row = field ? (field.closest('li') || field.closest('.mb-3') || field.parentNode) : null;
                            if (row) {
                                row.style.display = show ? '' : 'none';
                            }
                        };
                        toggle('auth_id', transfer);
                        for (var i = 1; i <= " . self::MAX_NAMESERVERS . "; i++) {
                            toggle('ns' + i + '_id', !transfer);
                        }
                    }
                    document.querySelectorAll('input[name=\"transfer\"]').forEach(function (radio) {
                        radio.addEventListener('change', domainResellerApiToggle);
                    });
                    domainResellerApiToggle();
                })();
            </script>"
        );

        return $module_fields;
    }

    /**
     * Returns all fields to display to a client attempting to add a service with the module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param stdClass $vars A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object containing the fields to render
     */
    public function getClientAddFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);
        $vars = $this->withDefaultNameservers($package, $vars);

        $fields = Configure::get('DomainResellerApi.domain_fields');
        // The domain is chosen in the order form already
        $fields['domain']['type'] = 'hidden';
        $fields['domain']['label'] = null;

        if ($this->isTransfer((array) $vars)) {
            $fields['transfer'] = ['type' => 'hidden', 'options' => '1'];
            $fields = array_merge($fields, Configure::get('DomainResellerApi.transfer_fields'));
        } else {
            $fields = array_merge($fields, Configure::get('DomainResellerApi.nameserver_fields'));
        }

        return $this->arrayToModuleFields($fields, null, $vars);
    }

    /**
     * Returns all fields to display to an admin attempting to edit a service with the module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param stdClass $vars A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object containing the fields to render
     */
    public function getAdminEditFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        return $this->arrayToModuleFields(Configure::get('DomainResellerApi.domain_fields'), null, $vars);
    }

    // ==================================================================
    // Service validation
    // ==================================================================

    /**
     * Attempts to validate service info. Sets Input errors on failure.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of user supplied info to satisfy the request
     * @return bool True if the service validates, false otherwise
     */
    public function validateService($package, ?array $vars = null)
    {
        $vars = $vars ?? [];
        $this->Input->setRules($this->getServiceRules($vars));

        return $this->Input->validates($vars);
    }

    /**
     * Attempts to validate an existing service against a set of service info updates
     *
     * @param stdClass $service A stdClass object representing the service to validate for editing
     * @param array $vars An array of user-supplied info to satisfy the request
     * @return bool True if the service update validates or false otherwise
     */
    public function validateServiceEdit($service, ?array $vars = null)
    {
        $vars = $vars ?? [];
        $this->Input->setRules($this->getServiceRules($vars, true));

        return $this->Input->validates($vars);
    }

    // ==================================================================
    // Service lifecycle
    // ==================================================================

    /**
     * Registers or transfers the domain. Sets Input errors on failure, preventing the service from being added.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of user supplied info to satisfy the request
     * @param stdClass $parent_package The parent package (if the current service is an addon service)
     * @param stdClass $parent_service The parent service (if the current service is an addon service)
     * @param string $status The status of the service being added
     * @return array A numerically indexed array of meta fields to be stored for this service
     */
    public function addService(
        $package,
        ?array $vars = null,
        $parent_package = null,
        $parent_service = null,
        $status = 'pending'
    ) {
        $vars = $vars ?? [];
        $row = $this->resolveRow($package->module_row ?? null);
        if (!$row) {
            $this->setError('module_row', 'missing', Language::_('DomainResellerApi.!error.module_row.missing', true));

            return;
        }

        if (isset($vars['domain'])) {
            $vars['domain'] = DomainResellerApiHelper::normalizeDomain($vars['domain']);
        }

        if (!$this->validateService($package, $vars)) {
            return;
        }

        $domain = $vars['domain'];
        $nameservers = DomainResellerApiHelper::nameserversFromVars($vars, self::MAX_NAMESERVERS);

        if (($vars['use_module'] ?? 'true') == 'true') {
            $id_protection = !empty($vars['configoptions']['id_protection']);
            $transfer = $this->isTransfer($vars);

            // Check the domain before any contact is created
            if (!$transfer && !$this->checkRegistrable($row, $domain)) {
                return;
            }

            $owner = $this->getClientContactHandle($row, $vars['client_id'] ?? null, $id_protection);
            if ($owner === null) {
                return;
            }

            $params = [
                'contacts' => $this->buildContactRoles($row, $owner['handle']),
                'whois_privacy' => $id_protection && $owner['type'] === 'person'
            ];

            if ($transfer) {
                $params['auth'] = trim((string) ($vars['auth'] ?? ''));
                $success = $this->transferDomain($domain, $row->id, $params);
            } else {
                $params['years'] = $this->getTermYears($package, $vars['pricing_id'] ?? null);
                $params['nameservers'] = $nameservers;
                $params['skip_availability_check'] = true;
                $success = $this->registerDomain($domain, $row->id, $params);
            }

            if (!$success) {
                return;
            }
        }

        return $this->buildServiceMeta($domain, $nameservers);
    }

    /**
     * Edits the service. Changes to the domain itself are made through the service tabs.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $vars An array of user supplied info to satisfy the request
     * @param stdClass $parent_package The parent package (if the current service is an addon service)
     * @param stdClass $parent_service The parent service (if the current service is an addon service)
     * @return array A numerically indexed array of meta fields to be stored for this service
     */
    public function editService($package, $service, ?array $vars = null, $parent_package = null, $parent_service = null)
    {
        $vars = $vars ?? [];
        $fields = (array) $this->serviceFieldsToObject((array) ($service->fields ?? []));

        if (isset($vars['domain'])) {
            $vars['domain'] = DomainResellerApiHelper::normalizeDomain($vars['domain']);
        }

        if (!$this->validateServiceEdit($service, $vars)) {
            return;
        }

        // Toggle WHOIS protection of the owner contact when the ID protection option changes
        if (($vars['use_module'] ?? 'true') == 'true' && isset($vars['configoptions'])) {
            $wanted = !empty($vars['configoptions']['id_protection']);
            if ($wanted !== $this->featureServiceEnabled('id_protection', $service)) {
                $row = $this->resolveRow($service->module_row_id ?? ($package->module_row ?? null));
                if ($row) {
                    $this->setOwnerProtection($row, $this->getServiceDomain($service), $wanted);
                }
            }
        }

        $domain = $vars['domain'] ?? ($fields['domain'] ?? '');
        $nameservers = [];
        for ($i = 1; $i <= self::MAX_NAMESERVERS; $i++) {
            $nameservers[] = $vars['ns' . $i] ?? ($fields['ns' . $i] ?? '');
        }

        return $this->buildServiceMeta($domain, DomainResellerApiHelper::normalizeNameservers($nameservers));
    }

    /**
     * Cancels the domain: schedules its deletion at the end of the term, or disables auto-renewal
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package The parent package (if the current service is an addon service)
     * @param stdClass $parent_service The parent service (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields
     */
    public function cancelService($package, $service, $parent_package = null, $parent_service = null)
    {
        if (!($row = $this->getServiceRow($package, $service))) {
            return;
        }
        $api = $this->getApi($row);
        $domain = $this->getServiceDomain($service);

        if (($row->meta->cancel_action ?? 'schedule_delete') == 'schedule_delete') {
            $response = $this->call($api, 'deleteDomain', [$domain, false]);
            if ($response->success() || $response->status() == 404) {
                $this->forgetDomain($row, $domain);

                return null;
            }

            // Domains that can not be scheduled for deletion (e.g. pending) at least stop renewing
            if ($response->status() != 422) {
                $this->setApiError($response);

                return;
            }
        }

        $response = $this->call($api, 'updateDomain', [$domain, ['autoRenew' => false]]);
        if (!$response->success() && $response->status() != 404) {
            $this->setApiError($response);

            return;
        }

        $this->forgetDomain($row, $domain);

        return null;
    }

    /**
     * Reverts a cancellation by canceling a scheduled deletion
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $vars An array of user supplied info
     * @param stdClass $parent_package The parent package (if the current service is an addon service)
     * @param stdClass $parent_service The parent service (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields
     */
    public function uncancelService($package, $service, array $vars = [], $parent_package = null, $parent_service = null)
    {
        if (!($row = $this->getServiceRow($package, $service))) {
            return;
        }
        $domain = $this->getServiceDomain($service);
        $info = $this->getDomainInfo($domain, $row->id);
        if (empty($info)) {
            // The domain is not (or no longer) managed in the account, nothing to revert
            $this->Input->setErrors([]);

            return null;
        }

        if (($info['status'] ?? '') != 'pending_delete') {
            return null;
        }

        $response = $this->call($this->getApi($row), 'cancelDelete', [$domain]);
        $this->forgetDomain($row, $domain);
        if (!$response->success()) {
            $this->setApiError($response);

            return;
        }

        // Canceling the deletion re-enables the API's auto-renewal; renewals are driven by Blesta
        $this->disableApiAutoRenew($row, $domain);

        return null;
    }

    /**
     * Suspends the domain by disabling its auto-renewal
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package The parent package (if the current service is an addon service)
     * @param stdClass $parent_service The parent service (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields
     */
    public function suspendService($package, $service, $parent_package = null, $parent_service = null)
    {
        return $this->setServiceAutoRenew($package, $service, false);
    }

    /**
     * Unsuspends the domain
     *
     * Nothing changes at the registry: renewals are triggered by Blesta (renewService), so the
     * domain is renewed again once the client pays.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package The parent package (if the current service is an addon service)
     * @param stdClass $parent_service The parent service (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields
     */
    public function unsuspendService($package, $service, $parent_package = null, $parent_service = null)
    {
        return null;
    }

    /**
     * Renews the domain when the service renews
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package The parent package (if the current service is an addon service)
     * @param stdClass $parent_service The parent service (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields
     */
    public function renewService($package, $service, $parent_package = null, $parent_service = null)
    {
        if (!($row = $this->getServiceRow($package, $service))) {
            return;
        }

        $years = $this->getTermYears($package, $service->pricing_id ?? null);
        if (!$this->renewDomain($this->getServiceDomain($service), $row->id, ['years' => $years])) {
            return;
        }

        return null;
    }

    // ==================================================================
    // Registrar methods
    // ==================================================================

    /**
     * Verifies that the provided domain name is available for registration
     *
     * @param string $domain The domain to lookup
     * @param int $module_row_id The ID of the module row to fetch for the current module
     * @return bool True if the domain is available, false otherwise
     */
    public function checkAvailability($domain, $module_row_id = null)
    {
        if (!($row = $this->resolveRow($module_row_id))) {
            return false;
        }

        $response = $this->call($this->getApi($row, 30), 'checkDomain', [DomainResellerApiHelper::normalizeDomain($domain)]);
        if (!$response->success()) {
            return false;
        }

        $data = (array) $response->data([]);
        if (empty($data['available'])) {
            return false;
        }

        if (!empty($data['premium']) && ($row->meta->allow_premium ?? 'false') != 'true') {
            $this->setError(
                'domain',
                'premium',
                Language::_('DomainResellerApi.!error.domain.premium', true, $domain)
            );

            return false;
        }

        return true;
    }

    /**
     * Verifies that the provided domain name is available for transfer (i.e. it is registered)
     *
     * @param string $domain The domain to lookup
     * @param int $module_row_id The ID of the module row to fetch for the current module
     * @return bool True if the domain is available for transfer, false otherwise
     */
    public function checkTransferAvailability($domain, $module_row_id = null)
    {
        if (!($row = $this->resolveRow($module_row_id))) {
            return false;
        }

        $response = $this->call($this->getApi($row, 30), 'checkDomain', [DomainResellerApiHelper::normalizeDomain($domain)]);
        if (!$response->success()) {
            // Let the transfer request itself decide
            return true;
        }

        return empty($response->data([])['available']);
    }

    /**
     * Verifies if a domain of the provided TLD can be registered or transferred for the provided term
     *
     * @param string $tld The TLD to verify
     * @param int $term The term in years
     * @param bool $transfer True if the domain is going to be transferred
     * @return bool True if the term is valid for the TLD
     */
    public function isValidTerm($tld, $term, $transfer = false)
    {
        $term = (int) $term;
        if ($transfer) {
            // Transfers are always processed as a single operation
            return $term == 1;
        }

        foreach ($this->fetchPricing() as $pricing) {
            if ('.' . ltrim(strtolower($pricing['tld'] ?? ''), '.') == '.' . ltrim(strtolower($tld), '.')) {
                return array_key_exists($term, DomainResellerApiHelper::termPrices($pricing));
            }
        }

        return $term >= 1 && $term <= 10;
    }

    /**
     * Registers a new domain
     *
     * @param string $domain The domain to register
     * @param int $module_row_id The ID of the module row to fetch for the current module
     * @param array $vars A list of vars to submit with the registration request:
     *
     *  - contacts An array of owner (required), admin, tech, billing contact handles
     *  - years The registration period (1-10)
     *  - nameservers A list of nameservers (at least two, otherwise the managed DNS is used)
     *  - whois_privacy True to request WHOIS privacy (OpenProvider routed TLDs, natural persons only)
     *  - skip_availability_check True if the premium/availability check was already done
     * @return bool True if the domain was successfully registered, false otherwise
     */
    public function registerDomain($domain, $module_row_id = null, array $vars = [])
    {
        if (!($row = $this->resolveRow($module_row_id))) {
            $this->setError('module_row', 'missing', Language::_('DomainResellerApi.!error.module_row.missing', true));

            return false;
        }
        $api = $this->getApi($row);
        $domain = DomainResellerApiHelper::normalizeDomain($domain);

        if (empty($vars['contacts']['owner'])) {
            $this->setError('contacts', 'missing', Language::_('DomainResellerApi.!error.contacts.missing', true));

            return false;
        }

        if (empty($vars['skip_availability_check']) && !$this->checkRegistrable($row, $domain)) {
            return false;
        }

        $params = [
            'domain' => $domain,
            'years' => max(1, min(10, (int) ($vars['years'] ?? 1))),
            'contacts' => $this->filterContactRoles((array) $vars['contacts'])
        ];

        $nameservers = DomainResellerApiHelper::normalizeNameservers((array) ($vars['nameservers'] ?? []));
        if (count($nameservers) >= 2) {
            $params['nameservers'] = array_slice($nameservers, 0, 6);
        }

        $response = $this->submitWithPrivacy($api, 'registerDomain', $params, !empty($vars['whois_privacy']));

        // The managed DNS zone may need a few seconds to become authoritative; the API refunds
        // and asks to retry in that case
        for ($attempt = 1; $attempt <= 2 && $response->status() == 503; $attempt++) {
            $this->pause(15);
            $response = $this->submitWithPrivacy($api, 'registerDomain', $params, !empty($vars['whois_privacy']));
        }

        $this->forgetDomain($row, $domain);
        if (!$response->success()) {
            $this->setApiError($response);

            return false;
        }

        // Renewals are triggered by Blesta, so the API must not renew on its own
        $this->disableApiAutoRenew($row, $domain);

        return true;
    }

    /**
     * Initiates an incoming domain transfer
     *
     * @param string $domain The domain to transfer
     * @param int $module_row_id The ID of the module row to fetch for the current module
     * @param array $vars A list of vars to submit with the transfer request:
     *
     *  - auth The auth (EPP) code
     *  - contacts An array of owner (required), admin, tech, billing contact handles
     *  - whois_privacy True to request WHOIS privacy (OpenProvider routed TLDs, natural persons only)
     * @return bool True if the transfer was successfully initiated, false otherwise
     */
    public function transferDomain($domain, $module_row_id = null, array $vars = [])
    {
        if (!($row = $this->resolveRow($module_row_id))) {
            $this->setError('module_row', 'missing', Language::_('DomainResellerApi.!error.module_row.missing', true));

            return false;
        }

        $auth = trim((string) ($vars['auth'] ?? ($vars['authcode'] ?? ($vars['epp_code'] ?? ''))));
        if ($auth === '') {
            $this->setError('auth', 'empty', Language::_('DomainResellerApi.!error.auth.empty', true));

            return false;
        }

        if (empty($vars['contacts']['owner'])) {
            $this->setError('contacts', 'missing', Language::_('DomainResellerApi.!error.contacts.missing', true));

            return false;
        }

        $domain = DomainResellerApiHelper::normalizeDomain($domain);
        $params = [
            'domain' => $domain,
            'authCode' => $auth,
            'contacts' => $this->filterContactRoles((array) $vars['contacts'])
        ];

        $response = $this->submitWithPrivacy($this->getApi($row), 'transferDomain', $params, !empty($vars['whois_privacy']));
        $this->forgetDomain($row, $domain);

        if (!$response->success()) {
            $this->setApiError($response);

            return false;
        }

        return true;
    }

    /**
     * Renews a domain for the given term (charged to the wallet)
     *
     * A domain scheduled for deletion has the deletion canceled first. Domains that are still
     * being registered or transferred need no renewal (the first term is included).
     *
     * @param string $domain The domain to renew
     * @param int $module_row_id The ID of the module row to fetch for the current module
     * @param array $vars A list of vars: years (the renewal term)
     * @return bool True if the domain was successfully renewed, false otherwise
     */
    public function renewDomain($domain, $module_row_id = null, array $vars = [])
    {
        if (!($row = $this->resolveRow($module_row_id))) {
            $this->setError('module_row', 'missing', Language::_('DomainResellerApi.!error.module_row.missing', true));

            return false;
        }

        $domain = DomainResellerApiHelper::normalizeDomain($domain);
        $info = $this->getDomainInfo($domain, $row->id);
        if (empty($info)) {
            return false;
        }

        $api = $this->getApi($row);
        $status = $info['status'] ?? '';
        switch ($status) {
            case 'pending':
            case 'pending_transfer':
                // The registration/transfer includes the first term
                return true;
            case 'pending_delete':
                $response = $this->call($api, 'cancelDelete', [$domain]);
                if (!$response->success()) {
                    $this->setApiError($response);

                    return false;
                }
                break;
            case 'active':
            case 'expired':
                break;
            case 'redemption':
                $this->setError('domain', 'redemption', Language::_('DomainResellerApi.!error.renew.redemption', true, $domain));

                return false;
            default:
                $this->setError('domain', 'state', Language::_('DomainResellerApi.!error.renew.state', true, $domain, $status));

                return false;
        }

        $years = isset($vars['years']) ? max(1, min(10, (int) $vars['years'])) : null;
        $response = $this->call($api, 'renewDomain', [$domain, $years]);
        $this->forgetDomain($row, $domain);

        if (!$response->success()) {
            $this->setApiError($response);

            return false;
        }

        // Renewals are triggered by Blesta, so the API must not renew on its own
        $this->disableApiAutoRenew($row, $domain);

        return true;
    }

    /**
     * Restores a domain from the redemption period (a restore fee is charged by the API)
     *
     * @param string $domain The domain to restore
     * @param int $module_row_id The ID of the module row to fetch for the current module
     * @param array $vars Unused
     * @return bool True if the domain was successfully restored, false otherwise
     */
    public function restoreDomain($domain, $module_row_id = null, array $vars = [])
    {
        return $this->simpleDomainAction($domain, $module_row_id, 'restoreDomain');
    }

    /**
     * Gets a list of basic information for a domain
     *
     * @param string $domain The domain to lookup
     * @param int $module_row_id The ID of the module row to fetch for the current module
     * @return array The domain information as returned by the API (empty on failure)
     */
    public function getDomainInfo($domain, $module_row_id = null)
    {
        if (!($row = $this->resolveRow($module_row_id))) {
            $this->setError('module_row', 'missing', Language::_('DomainResellerApi.!error.module_row.missing', true));

            return [];
        }

        $domain = DomainResellerApiHelper::normalizeDomain($domain);
        $key = $row->id . '|' . $domain;
        if (!array_key_exists($key, $this->domain_cache)) {
            $response = $this->call($this->getApi($row), 'getDomain', [$domain]);
            if (!$response->success()) {
                $this->setApiError($response);

                return [];
            }

            $this->domain_cache[$key] = (array) $response->data([]);
        }

        return $this->domain_cache[$key];
    }

    /**
     * Gets a list of contacts associated with a domain
     *
     * @param string $domain The domain to lookup
     * @param int $module_row_id The ID of the module row to fetch for the current module
     * @return array A list of contact objects (external_id is the contact handle)
     */
    public function getDomainContacts($domain, $module_row_id = null)
    {
        $contacts = [];
        foreach ($this->fetchDomainContacts($domain, $module_row_id)['contacts'] as $contact) {
            $contacts[] = (object) $contact;
        }

        return $contacts;
    }

    /**
     * Updates the contacts associated with a domain
     *
     * Contacts are identified by their handle (external_id). Only handles that are assigned to the
     * domain can be updated; handles configured as account-wide admin/tech/billing contacts are
     * never modified.
     *
     * @param string $domain The domain for which to update contact info
     * @param array $vars A list of contact arrays (external_id, email, phone, first_name, last_name, company,
     *  address1, address2, city, state, zip, country)
     * @param int $module_row_id The ID of the module row to fetch for the current module
     * @return bool True if the contacts were updated, false otherwise
     */
    public function setDomainContacts($domain, array $vars = [], $module_row_id = null)
    {
        if (!($row = $this->resolveRow($module_row_id))) {
            $this->setError('module_row', 'missing', Language::_('DomainResellerApi.!error.module_row.missing', true));

            return false;
        }

        $info = $this->getDomainInfo($domain, $row->id);
        if (empty($info)) {
            return false;
        }

        $assigned = array_filter(array_values((array) ($info['contacts'] ?? [])));
        $protected = $this->getProtectedHandles($row);
        $api = $this->getApi($row);

        $errors = [];
        foreach ($vars as $contact) {
            $contact = (array) $contact;
            $handle = trim((string) ($contact['external_id'] ?? ''));

            if ($handle === '' || !in_array($handle, $assigned, true)) {
                $errors['contacts'][$handle . '_unknown'] = Language::_(
                    'DomainResellerApi.!error.contacts.unknown_handle',
                    true,
                    $handle
                );
                continue;
            }
            if (in_array($handle, $protected, true)
                || ($this->editable_handles !== null && !in_array($handle, $this->editable_handles, true))
            ) {
                $errors['contacts'][$handle . '_protected'] = Language::_(
                    'DomainResellerApi.!error.contacts.protected_handle',
                    true,
                    $handle
                );
                continue;
            }

            $payload = DomainResellerApiHelper::blestaContactToUpdate($contact, $row->meta->fallback_phone ?? null);
            $invalid = DomainResellerApiHelper::validateContactPayload($payload);
            if (!empty($invalid)) {
                foreach ($invalid as $field) {
                    $errors['contacts'][$handle . '_' . $field] = Language::_(
                        'DomainResellerApi.!error.contacts.invalid_' . $field,
                        true,
                        $handle
                    );
                }
                continue;
            }

            $response = $this->call($api, 'updateContact', [$handle, $payload]);
            if (!$response->success()) {
                $errors['contacts'][$handle . '_api'] = $handle . ': ' . $response->error();
            }
        }

        $this->forgetDomain($row, $domain);
        if (!empty($errors)) {
            $this->Input->setErrors($errors);

            return false;
        }

        return true;
    }

    /**
     * Gets the nameservers of a domain
     *
     * @param string $domain The domain to lookup
     * @param int $module_row_id The ID of the module row to fetch for the current module
     * @return array A list of name servers, each with the fields url and ips
     */
    public function getDomainNameServers($domain, $module_row_id = null)
    {
        $info = $this->getDomainInfo($domain, $module_row_id);

        $nameservers = [];
        foreach ((array) ($info['nameservers'] ?? []) as $nameserver) {
            $nameservers[] = ['url' => trim($nameserver), 'ips' => []];
        }

        return $nameservers;
    }

    /**
     * Assigns new nameservers to a domain
     *
     * Assigning the nameservers of the managed DNS (ns1/ns2.domain-reseller-api.de) enables the
     * managed DNS zone; any other nameservers disable it.
     *
     * @param string $domain The domain for which to assign new name servers
     * @param int|null $module_row_id The ID of the module row to fetch for the current module
     * @param array $vars A list of name servers to assign (e.g. [ns1, ns2])
     * @return bool True if the name servers were successfully updated, false otherwise
     */
    public function setDomainNameservers($domain, $module_row_id = null, array $vars = [])
    {
        if (!($row = $this->resolveRow($module_row_id))) {
            $this->setError('module_row', 'missing', Language::_('DomainResellerApi.!error.module_row.missing', true));

            return false;
        }

        $nameservers = DomainResellerApiHelper::normalizeNameservers($vars);
        if (count($nameservers) < 2) {
            $this->setError('nameservers', 'count', Language::_('DomainResellerApi.!error.nameservers.count', true));

            return false;
        }
        foreach ($nameservers as $nameserver) {
            if (!DomainResellerApiHelper::isValidHostname($nameserver)) {
                $this->setError('nameservers', 'invalid', Language::_('DomainResellerApi.!error.nameservers.invalid', true, $nameserver));

                return false;
            }
        }

        $info = $this->getDomainInfo($domain, $row->id);
        if (empty($info)) {
            return false;
        }

        $managed = !empty($info['useManagedDns']);
        if (DomainResellerApiHelper::isManagedNameserverSet($nameservers)) {
            if ($managed) {
                return true;
            }
            $params = ['useManagedDns' => true];
        } else {
            $params = ['nameservers' => array_slice($nameservers, 0, 6)];
            if ($managed) {
                $params['useManagedDns'] = false;
            }
        }

        $api = $this->getApi($row);
        $response = isset($params['useManagedDns']) && $params['useManagedDns'] === true
            ? $this->enableManagedDns($api, $domain)
            : $this->call($api, 'updateDomain', [$domain, $params]);
        $this->forgetDomain($row, $domain);
        if (!$response->success()) {
            $this->setApiError($response);

            return false;
        }

        return true;
    }

    /**
     * Returns whether the domain is locked against transfers
     *
     * @param string $domain The domain to lookup
     * @param int $module_row_id The ID of the module row to fetch for the current module
     * @return bool True if the domain has a registrar lock, false otherwise
     */
    public function getDomainIsLocked($domain, $module_row_id = null)
    {
        if (!($row = $this->resolveRow($module_row_id))) {
            return false;
        }

        $lock = $this->fetchTransferLock($row, DomainResellerApiHelper::normalizeDomain($domain));

        return !empty($lock['supported']) && !empty($lock['locked']);
    }

    /**
     * Locks the domain against transfers
     *
     * @param string $domain The domain to lock
     * @param int $module_row_id The ID of the module row to fetch for the current module
     * @return bool True if the domain was successfully locked, false otherwise
     */
    public function lockDomain($domain, $module_row_id = null)
    {
        return $this->setDomainLock($domain, $module_row_id, true);
    }

    /**
     * Unlocks the domain for transfers
     *
     * @param string $domain The domain to unlock
     * @param int $module_row_id The ID of the module row to fetch for the current module
     * @return bool True if the domain was successfully unlocked, false otherwise
     */
    public function unlockDomain($domain, $module_row_id = null)
    {
        return $this->setDomainLock($domain, $module_row_id, false);
    }

    /**
     * Creates or updates glue records
     *
     * The domain of every host is determined by looking up its parent domains in the account.
     *
     * @param array $vars A list of hosts and their addresses (hostname => [ip, ...])
     * @param int|null $module_row_id The ID of the module row to fetch for the current module
     * @return bool True if all glue records were updated, false otherwise
     */
    public function setNameserverIps(array $vars = [], $module_row_id = null)
    {
        if (!($row = $this->resolveRow($module_row_id))) {
            $this->setError('module_row', 'missing', Language::_('DomainResellerApi.!error.module_row.missing', true));

            return false;
        }
        $api = $this->getApi($row);

        foreach ($vars as $hostname => $ips) {
            $hostname = DomainResellerApiHelper::normalizeDomain($hostname);
            $ipv4 = null;
            $ipv6 = null;
            foreach ((array) $ips as $ip) {
                $ip = trim((string) $ip);
                if ($ipv4 === null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ipv4 = $ip;
                } elseif ($ipv6 === null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $ipv6 = $ip;
                }
            }

            $domain = $this->findParentDomain($row, $hostname);
            if ($domain === null || ($ipv4 === null && $ipv6 === null)) {
                $this->setError('hosts', 'invalid', Language::_('DomainResellerApi.!error.host.parent', true, $hostname));

                return false;
            }

            $existing = $this->call($api, 'getHosts', [$domain]);
            $known = array_map(function ($host) {
                return strtolower($host['hostname'] ?? '');
            }, (array) ($existing->data([])['hosts'] ?? []));

            $method = in_array($hostname, $known, true) ? 'updateHost' : 'createHost';
            $response = $this->call($api, $method, [$domain, $hostname, $ipv4, $ipv6]);
            if (!$response->success()) {
                $this->setApiError($response);

                return false;
            }
        }

        return true;
    }

    /**
     * Gets the domain registration date
     *
     * @param stdClass $service The service belonging to the domain to lookup
     * @param string $format The format to return the registration date in
     * @return string|false The domain registration date in UTC time in the given format
     */
    public function getRegistrationDate($service, $format = 'Y-m-d H:i:s')
    {
        $info = $this->getServiceDomainInfo($service);

        return DomainResellerApiHelper::formatDate($info['registeredAt'] ?? null, $format);
    }

    /**
     * Gets the domain expiration date
     *
     * @param stdClass $service The service belonging to the domain to lookup
     * @param string $format The format to return the expiration date in
     * @return string|false The domain expiration date in UTC time in the given format
     */
    public function getExpirationDate($service, $format = 'Y-m-d H:i:s')
    {
        $info = $this->getServiceDomainInfo($service);

        return DomainResellerApiHelper::formatDate($info['expiresAt'] ?? null, $format);
    }

    /**
     * Gets the list of TLDs offered by the Domain Reseller API
     *
     * @param int $module_row_id The ID of the module row to fetch for the current module
     * @return array A list of TLDs (with leading dot)
     */
    public function getTlds($module_row_id = null)
    {
        $tlds = [];
        foreach ($this->fetchPricing($module_row_id) as $pricing) {
            if (!empty($pricing['tld'])) {
                $tlds[] = '.' . ltrim(strtolower($pricing['tld']), '.');
            }
        }

        if (empty($tlds)) {
            $tlds = (array) Configure::get('DomainResellerApi.tlds');
        }

        return array_values(array_unique($tlds));
    }

    /**
     * Gets the TLD prices (net prices of the Domain Reseller API, converted to all company currencies)
     *
     * @param int $module_row_id The ID of the module row to fetch for the current module
     * @return array [tld => [currency => [years => ['register' => x, 'transfer' => y, 'renew' => z]]]]
     */
    public function getTldPricing($module_row_id = null)
    {
        return $this->getFilteredTldPricing($module_row_id);
    }

    /**
     * Gets a filtered list of TLD prices
     *
     * @param int $module_row_id The ID of the module row to fetch for the current module
     * @param array $filters Filters: tlds, currencies, terms
     * @return array [tld => [currency => [years => ['register' => x, 'transfer' => y, 'renew' => z]]]]
     */
    public function getFilteredTldPricing($module_row_id = null, $filters = [])
    {
        $pricing = $this->fetchPricing($module_row_id);
        if (empty($pricing)) {
            return [];
        }

        if (!isset($this->Currencies)) {
            Loader::loadModels($this, ['Currencies']);
        }

        $company_id = Configure::get('Blesta.company_id');
        $currencies = ['EUR'];
        foreach ((array) $this->Currencies->getAll($company_id) as $currency) {
            $currencies[] = $currency->code;
        }

        $converter = $this->Currencies;
        $convert = function ($amount, $currency) use ($converter, $company_id) {
            return $converter->convert($amount, 'EUR', $currency, $company_id);
        };

        return DomainResellerApiHelper::buildTldPricing($pricing, $convert, $currencies, (array) $filters);
    }

    // ==================================================================
    // Service tabs
    // ==================================================================

    /**
     * Returns all tabs to display to an admin when managing a service
     *
     * @param stdClass $service A stdClass object representing the service
     * @return array An array of tabs in the format of method => title
     */
    public function getAdminServiceTabs($service)
    {
        return [
            'tabContacts' => Language::_('DomainResellerApi.tab_contacts.title', true),
            'tabNameservers' => Language::_('DomainResellerApi.tab_nameservers.title', true),
            'tabDns' => Language::_('DomainResellerApi.tab_dns.title', true),
            'tabDnssec' => Language::_('DomainResellerApi.tab_dnssec.title', true),
            'tabHosts' => Language::_('DomainResellerApi.tab_hosts.title', true),
            'tabSettings' => Language::_('DomainResellerApi.tab_settings.title', true),
            'tabActions' => Language::_('DomainResellerApi.tab_actions.title', true)
        ];
    }

    /**
     * Returns all tabs to display to a client when managing a service
     *
     * @param stdClass $service A stdClass object representing the service
     * @return array An array of tabs in the format of method => ['name' => title, 'icon' => icon]
     */
    public function getClientServiceTabs($service)
    {
        $tabs = [
            'tabClientContacts' => [
                'name' => Language::_('DomainResellerApi.tab_contacts.title', true),
                'icon' => 'fas fa-users'
            ],
            'tabClientNameservers' => [
                'name' => Language::_('DomainResellerApi.tab_nameservers.title', true),
                'icon' => 'fas fa-server'
            ],
            'tabClientDns' => [
                'name' => Language::_('DomainResellerApi.tab_dns.title', true),
                'icon' => 'fas fa-sitemap'
            ],
            'tabClientDnssec' => [
                'name' => Language::_('DomainResellerApi.tab_dnssec.title', true),
                'icon' => 'fas fa-shield-alt'
            ],
            'tabClientHosts' => [
                'name' => Language::_('DomainResellerApi.tab_hosts.title', true),
                'icon' => 'fas fa-hdd'
            ],
            'tabClientSettings' => [
                'name' => Language::_('DomainResellerApi.tab_settings.title', true),
                'icon' => 'fas fa-cog'
            ]
        ];

        if (!$this->clientCanManageDns($service)) {
            unset($tabs['tabClientDns'], $tabs['tabClientDnssec']);
        }

        return $tabs;
    }

    /**
     * Admin contacts tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabContacts($package, $service, ?array $get = null, ?array $post = null, ?array $files = null)
    {
        return $this->manageContacts('tab_contacts', $package, $service, $post, false);
    }

    /**
     * Client contacts tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientContacts($package, $service, ?array $get = null, ?array $post = null, ?array $files = null)
    {
        return $this->manageContacts('tab_client_contacts', $package, $service, $post, true);
    }

    /**
     * Admin nameservers tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabNameservers($package, $service, ?array $get = null, ?array $post = null, ?array $files = null)
    {
        return $this->manageNameservers('tab_nameservers', $package, $service, $post);
    }

    /**
     * Client nameservers tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientNameservers($package, $service, ?array $get = null, ?array $post = null, ?array $files = null)
    {
        return $this->manageNameservers('tab_client_nameservers', $package, $service, $post);
    }

    /**
     * Admin DNS records tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabDns($package, $service, ?array $get = null, ?array $post = null, ?array $files = null)
    {
        return $this->manageDns('tab_dns', $package, $service, $get, $post, false);
    }

    /**
     * Client DNS records tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientDns($package, $service, ?array $get = null, ?array $post = null, ?array $files = null)
    {
        if (!$this->clientCanManageDns($service)) {
            return '';
        }

        return $this->manageDns('tab_client_dns', $package, $service, $get, $post, true);
    }

    /**
     * Admin DNSSEC tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabDnssec($package, $service, ?array $get = null, ?array $post = null, ?array $files = null)
    {
        return $this->manageDnssec('tab_dnssec', $package, $service, $post);
    }

    /**
     * Client DNSSEC tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientDnssec($package, $service, ?array $get = null, ?array $post = null, ?array $files = null)
    {
        if (!$this->clientCanManageDns($service)) {
            return '';
        }

        return $this->manageDnssec('tab_client_dnssec', $package, $service, $post);
    }

    /**
     * Admin glue records (hosts) tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabHosts($package, $service, ?array $get = null, ?array $post = null, ?array $files = null)
    {
        return $this->manageHosts('tab_hosts', $package, $service, $post);
    }

    /**
     * Client glue records (hosts) tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientHosts($package, $service, ?array $get = null, ?array $post = null, ?array $files = null)
    {
        return $this->manageHosts('tab_client_hosts', $package, $service, $post);
    }

    /**
     * Admin settings tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabSettings($package, $service, ?array $get = null, ?array $post = null, ?array $files = null)
    {
        return $this->manageSettings('tab_settings', $package, $service, $post, false);
    }

    /**
     * Client settings tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientSettings($package, $service, ?array $get = null, ?array $post = null, ?array $files = null)
    {
        return $this->manageSettings('tab_client_settings', $package, $service, $post, true);
    }

    /**
     * Admin actions tab (restore, deletion, transfer-out, hold, date sync)
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabActions($package, $service, ?array $get = null, ?array $post = null, ?array $files = null)
    {
        $context = $this->getTabContext($package, $service);
        $this->view = $this->makeView('tab_actions');
        Loader::loadHelpers($this, ['Form', 'Html']);
        $this->view->set('service', $service);

        if (!$context) {
            // The error (e.g. domain not found) is shown by Blesta
            return '';
        }
        list($row, $domain, $info) = $context;
        $api = $this->getApi($row);
        $state = (array) ($info['stateInfo'] ?? []);

        if (!empty($post['action'])) {
            $response = null;
            switch ($post['action']) {
                case 'restore':
                    if (!empty($state['canRestore'])) {
                        $response = $this->call($api, 'restoreDomain', [$domain]);
                    }
                    break;
                case 'cancel_delete':
                    if (!empty($state['canCancelDelete'])) {
                        $response = $this->call($api, 'cancelDelete', [$domain]);
                    }
                    break;
                case 'schedule_delete':
                    if (!empty($state['canScheduleDelete'])) {
                        $response = $this->call($api, 'deleteDomain', [$domain, false]);
                    }
                    break;
                case 'delete_immediate':
                    if (!empty($state['canDeleteImmediate'])) {
                        if (DomainResellerApiHelper::normalizeDomain($post['confirm_domain'] ?? '') !== $domain) {
                            $this->setError('confirm_domain', 'mismatch', Language::_('DomainResellerApi.!error.confirm_domain.mismatch', true));
                        } else {
                            $response = $this->call($api, 'deleteDomain', [$domain, true]);
                        }
                    }
                    break;
                case 'transfer_out_approve':
                case 'transfer_out_reject':
                    if (!empty($state['canTransferOut'])) {
                        $response = $this->call(
                            $api,
                            'transferOut',
                            [$domain, $post['action'] == 'transfer_out_approve']
                        );
                    }
                    break;
                case 'hold':
                case 'unhold':
                    $allowed = $post['action'] == 'hold' ? !empty($state['canHold']) : !empty($state['canUnhold']);
                    if ($allowed) {
                        $response = $this->call($api, 'holdDomain', [$domain, $post['action'] == 'hold']);
                    }
                    break;
                case 'sync_renew_date':
                    $this->syncRenewDate($service, $info);
                    break;
            }

            if ($response !== null) {
                if ($response->success()) {
                    $this->setMessage(
                        'success',
                        Language::_('DomainResellerApi.!success.action_' . $post['action'], true, $domain)
                    );
                } else {
                    $this->setApiError($response);
                }
            } elseif ($post['action'] != 'sync_renew_date' && !$this->Input->errors()) {
                $this->setError('action', 'unavailable', Language::_('DomainResellerApi.!error.action.unavailable', true));
            }

            $this->forgetDomain($row, $domain);
            $info = $this->getDomainInfo($domain, $row->id) ?: $info;
            $state = (array) ($info['stateInfo'] ?? []);
        }

        // Pending outgoing transfers for this domain
        $transfers = [];
        if (!empty($state['canTransferOut'])) {
            $response = $this->call($api, 'getPendingTransferOuts');
            foreach ((array) ($response->data([])['transfers'] ?? []) as $transfer) {
                if (strtolower($transfer['domain'] ?? '') === $domain) {
                    $transfers[] = $transfer;
                }
            }
        }

        $this->view->set('domain', $domain);
        $this->view->set('info', $info);
        $this->view->set('state', $state);
        $this->view->set('transfers', $transfers);

        return $this->view->fetch();
    }

    // ==================================================================
    // Tab implementations
    // ==================================================================

    /**
     * Handles the contacts tabs
     *
     * @param string $view The view to render
     * @param stdClass $package The package
     * @param stdClass $service The service
     * @param array|null $post Any POST parameters
     * @param bool $client True for the client interface
     * @return string The rendered tab
     */
    private function manageContacts($view, $package, $service, ?array $post, $client)
    {
        $context = $this->getTabContext($package, $service);
        $this->view = $this->makeView($view);
        Loader::loadHelpers($this, ['Form', 'Html']);
        $this->view->set('service', $service);

        if (!$context) {
            // The error (e.g. domain not found) is shown by Blesta
            return '';
        }
        list($row, $domain, $info) = $context;

        $vars = new stdClass();
        if (!empty($post) && $this->isPendingState($info)) {
            $this->setError('domain', 'pending', Language::_('DomainResellerApi.!error.domain.pending', true));
        } elseif (!empty($post)) {
            $action = $post['action'] ?? 'update_contacts';

            if ($action == 'update_contacts') {
                $contacts = [];
                foreach ((array) ($post['contacts'] ?? []) as $handle => $contact) {
                    $contacts[] = array_merge((array) $contact, ['external_id' => (string) $handle]);
                }

                // Clients may only edit handles that were created for them
                $this->editable_handles = $client ? $this->getClientHandles($row, $service->client_id ?? null) : null;
                $updated = $this->setDomainContacts($domain, $contacts, $row->id);
                $this->editable_handles = null;

                if ($updated) {
                    // The edited handles no longer match the client profile they were created from
                    $this->forgetHandleFingerprints($row, $service->client_id ?? null, array_column($contacts, 'external_id'));
                    $this->setMessage('success', Language::_('DomainResellerApi.!success.contacts_updated', true));
                } else {
                    $vars = (object) $post;
                }
            } elseif ($action == 'assign_handles' && !$client) {
                $this->assignHandles($row, $domain, $info, $post);
                $vars = (object) $post;
            }

            $info = $this->getDomainInfo($domain, $row->id) ?: $info;
        }

        $contacts = $this->fetchDomainContacts($domain, $row->id);

        // All handles of the account to (re-)assign roles
        $handles = [];
        if (!$client) {
            $response = $this->call($this->getApi($row), 'listContacts', [1, 100]);
            foreach ((array) ($response->data([])['contacts'] ?? []) as $contact) {
                $handles[$contact['handle']] = $contact['handle'] . ' - ' . ($contact['name'] ?? '')
                    . (!empty($contact['email']) ? ' (' . $contact['email'] . ')' : '');
            }
        }

        $this->view->set('domain', $domain);
        $this->view->set('info', $info);
        $this->view->set('roles', $contacts['roles']);
        $this->view->set('contacts', $contacts['contacts']);
        $protected = $this->getProtectedHandles($row);
        if ($client) {
            $own = $this->getClientHandles($row, $service->client_id ?? null);
            foreach (array_keys($contacts['contacts']) as $handle) {
                if (!in_array($handle, $own, true)) {
                    $protected[] = $handle;
                }
            }
        }

        $this->view->set('protected_handles', array_values(array_unique($protected)));
        $this->view->set('handles', $handles);
        $this->view->set('pending', $this->isPendingState($info));
        $this->view->set('vars', $vars);

        return $this->view->fetch();
    }

    /**
     * Handles the nameserver tabs
     *
     * @param string $view The view to render
     * @param stdClass $package The package
     * @param stdClass $service The service
     * @param array|null $post Any POST parameters
     * @return string The rendered tab
     */
    private function manageNameservers($view, $package, $service, ?array $post)
    {
        $context = $this->getTabContext($package, $service);
        $this->view = $this->makeView($view);
        Loader::loadHelpers($this, ['Form', 'Html']);
        $this->view->set('service', $service);

        if (!$context) {
            // The error (e.g. domain not found) is shown by Blesta
            return '';
        }
        list($row, $domain, $info) = $context;

        $vars = new stdClass();
        if (!empty($post)) {
            if ($this->isPendingState($info)) {
                $this->setError('domain', 'pending', Language::_('DomainResellerApi.!error.domain.pending', true));
            } elseif (($post['use_managed_dns'] ?? '0') == '1') {
                $success = true;
                if (empty($info['useManagedDns'])) {
                    $response = $this->enableManagedDns($this->getApi($row), $domain);
                    $success = $response->success();
                    if (!$success) {
                        $this->setApiError($response);
                    }
                }
                if ($success) {
                    $this->setMessage('success', Language::_('DomainResellerApi.!success.managed_dns_enabled', true));
                }
            } else {
                $nameservers = DomainResellerApiHelper::nameserversFromVars($post, self::MAX_NAMESERVERS);
                if ($this->setDomainNameservers($domain, $row->id, $nameservers)) {
                    $this->setMessage('success', Language::_('DomainResellerApi.!success.nameservers_updated', true));
                } else {
                    $vars = (object) $post;
                }
            }

            $this->forgetDomain($row, $domain);
            $info = $this->getDomainInfo($domain, $row->id) ?: $info;
        }

        $nameservers = array_values((array) ($info['nameservers'] ?? []));
        for ($i = 1; $i <= self::MAX_NAMESERVERS; $i++) {
            if (!isset($vars->{'ns' . $i})) {
                $vars->{'ns' . $i} = $nameservers[$i - 1] ?? '';
            }
        }
        if (!isset($vars->use_managed_dns)) {
            $vars->use_managed_dns = !empty($info['useManagedDns']) ? '1' : '0';
        }

        $this->view->set('domain', $domain);
        $this->view->set('info', $info);
        $this->view->set('vars', $vars);
        $this->view->set('nameserver_fields', Configure::get('DomainResellerApi.nameserver_fields'));
        $this->view->set('managed_nameservers', DomainResellerApiHelper::MANAGED_NAMESERVERS);
        $this->view->set('pending', $this->isPendingState($info));

        return $this->view->fetch();
    }

    /**
     * Handles the DNS record tabs
     *
     * @param string $view The view to render
     * @param stdClass $package The package
     * @param stdClass $service The service
     * @param array|null $get Any GET parameters
     * @param array|null $post Any POST parameters
     * @param bool $client True for the client interface
     * @return string The rendered tab
     */
    private function manageDns($view, $package, $service, ?array $get, ?array $post, $client)
    {
        $context = $this->getTabContext($package, $service);
        $this->view = $this->makeView($view);
        Loader::loadHelpers($this, ['Form', 'Html']);
        $this->view->set('service', $service);

        if (!$context) {
            // The error (e.g. domain not found) is shown by Blesta
            return '';
        }
        list($row, $domain, $info) = $context;
        $api = $this->getApi($row);

        $vars = new stdClass();
        if (!empty($post['action'])) {
            $response = null;
            $success_key = null;

            switch ($post['action']) {
                case 'enable_managed_dns':
                    if ($this->isPendingState($info)) {
                        $this->setError('domain', 'pending', Language::_('DomainResellerApi.!error.domain.pending', true));
                    } else {
                        $response = $this->enableManagedDns($api, $domain);
                        $success_key = 'managed_dns_enabled';
                    }
                    break;
                case 'add':
                    list($record, $errors) = DomainResellerApiHelper::buildDnsRecord($post);
                    if ($this->setRecordErrors($errors)) {
                        $response = $this->call($api, 'createDnsRecord', [$domain, $record]);
                        $success_key = 'dns_record_added';
                    }
                    break;
                case 'update':
                    list($record, $errors) = DomainResellerApiHelper::buildDnsRecord($post, true);
                    if ($this->setRecordErrors($errors)) {
                        $response = $this->call($api, 'updateDnsRecord', [$domain, (string) ($post['record_id'] ?? ''), $record]);
                        $success_key = 'dns_record_updated';
                    }
                    break;
                case 'delete':
                    $response = $this->call($api, 'deleteDnsRecord', [$domain, (string) ($post['record_id'] ?? '')]);
                    $success_key = 'dns_record_deleted';
                    break;
                case 'import':
                    if (!$client) {
                        if (trim((string) ($post['zone_file'] ?? '')) === '') {
                            $this->setError('zone_file', 'empty', Language::_('DomainResellerApi.!error.zone_file.empty', true));
                        } else {
                            $response = $this->call($api, 'importZone', [$domain, $post['zone_file']]);
                            $success_key = 'zone_imported';
                        }
                    }
                    break;
            }

            if ($response !== null) {
                if ($response->success()) {
                    $this->setMessage('success', Language::_('DomainResellerApi.!success.' . $success_key, true));
                } else {
                    $this->setApiError($response);
                    $vars = (object) $post;
                }
            } else {
                $vars = (object) $post;
            }

            $this->forgetDomain($row, $domain);
            $info = $this->getDomainInfo($domain, $row->id) ?: $info;
        }

        $records = [];
        $enabled = !empty($info['useManagedDns']);
        if ($enabled) {
            $response = $this->call($api, 'getDnsRecords', [$domain]);
            if ($response->success()) {
                $records = (array) ($response->data([])['records'] ?? []);
            } else {
                $this->setApiError($response);
            }
        }

        // Prefill the form when editing a record
        $edit_record = null;
        if (!empty($get['edit']) && empty($post)) {
            foreach ($records as $record) {
                if ((string) ($record['id'] ?? '') === (string) $get['edit']) {
                    $edit_record = $record;
                    $vars = (object) [
                        'record_id' => $record['id'],
                        'type' => $record['type'] ?? '',
                        'name' => $record['name'] ?? '',
                        'content' => $record['content'] ?? '',
                        'ttl' => $record['ttl'] ?? 3600,
                        'priority' => $record['priority'] ?? ''
                    ];
                    break;
                }
            }
        }

        $zone_file = null;
        if (!$client && $enabled && !empty($get['export'])) {
            $response = $this->call($api, 'exportZone', [$domain]);
            if ($response->success()) {
                $zone_file = (string) $response->data('');
            } else {
                $this->setApiError($response);
            }
        }

        $this->view->set('domain', $domain);
        $this->view->set('info', $info);
        $this->view->set('enabled', $enabled);
        $this->view->set('records', $records);
        $this->view->set('edit_record', $edit_record);
        $this->view->set('zone_file', $zone_file);
        $this->view->set('record_types', Configure::get('DomainResellerApi.dns_record_types'));
        $this->view->set('pending', $this->isPendingState($info));
        $this->view->set('vars', $vars);

        return $this->view->fetch();
    }

    /**
     * Handles the DNSSEC tabs
     *
     * @param string $view The view to render
     * @param stdClass $package The package
     * @param stdClass $service The service
     * @param array|null $post Any POST parameters
     * @return string The rendered tab
     */
    private function manageDnssec($view, $package, $service, ?array $post)
    {
        $context = $this->getTabContext($package, $service);
        $this->view = $this->makeView($view);
        Loader::loadHelpers($this, ['Form', 'Html']);
        $this->view->set('service', $service);

        if (!$context) {
            // The error (e.g. domain not found) is shown by Blesta
            return '';
        }
        list($row, $domain, $info) = $context;
        $api = $this->getApi($row);

        $vars = new stdClass();
        if (!empty($post)) {
            if ($this->isPendingState($info)) {
                $this->setError('domain', 'pending', Language::_('DomainResellerApi.!error.domain.pending', true));
            } else {
                $enabled = ($post['dnssec_enabled'] ?? '0') == '1';
                $dnskeys = empty($info['useManagedDns'])
                    ? DomainResellerApiHelper::parseDnskeys($post['dnskeys'] ?? '')
                    : [];

                if ($enabled && empty($info['useManagedDns']) && empty($dnskeys)) {
                    $this->setError('dnskeys', 'empty', Language::_('DomainResellerApi.!error.dnskeys.empty', true));
                    $vars = (object) $post;
                } else {
                    $response = $this->call($api, 'updateDnssec', [$domain, $enabled, $dnskeys]);
                    if ($response->success()) {
                        $this->setMessage('success', Language::_('DomainResellerApi.!success.dnssec_updated', true));
                    } else {
                        $this->setApiError($response);
                        $vars = (object) $post;
                    }
                }
            }

            $this->forgetDomain($row, $domain);
            $info = $this->getDomainInfo($domain, $row->id) ?: $info;
        }

        $dnssec = [];
        $response = $this->call($api, 'getDnssec', [$domain]);
        if ($response->success()) {
            $dnssec = (array) $response->data([]);
        } else {
            $this->setApiError($response);
        }

        if (!isset($vars->dnssec_enabled)) {
            $vars->dnssec_enabled = !empty($dnssec['dnssecEnabled']) ? '1' : '0';
        }
        if (!isset($vars->dnskeys)) {
            $vars->dnskeys = implode("\n", (array) ($dnssec['dnskeys'] ?? []));
        }

        $this->view->set('domain', $domain);
        $this->view->set('info', $info);
        $this->view->set('dnssec', $dnssec);
        $this->view->set('pending', $this->isPendingState($info));
        $this->view->set('vars', $vars);

        return $this->view->fetch();
    }

    /**
     * Handles the glue record (hosts) tabs
     *
     * @param string $view The view to render
     * @param stdClass $package The package
     * @param stdClass $service The service
     * @param array|null $post Any POST parameters
     * @return string The rendered tab
     */
    private function manageHosts($view, $package, $service, ?array $post)
    {
        $context = $this->getTabContext($package, $service);
        $this->view = $this->makeView($view);
        Loader::loadHelpers($this, ['Form', 'Html']);
        $this->view->set('service', $service);

        if (!$context) {
            // The error (e.g. domain not found) is shown by Blesta
            return '';
        }
        list($row, $domain, $info) = $context;
        $api = $this->getApi($row);

        $hosts = [];
        $response = $this->call($api, 'getHosts', [$domain]);
        if ($response->success()) {
            $hosts = (array) ($response->data([])['hosts'] ?? []);
        } elseif (empty($post)) {
            $this->setApiError($response);
        }

        $vars = new stdClass();
        if (!empty($post['action'])) {
            if ($this->isPendingState($info)) {
                $this->setError('domain', 'pending', Language::_('DomainResellerApi.!error.domain.pending', true));
            } else {
                $hostname = DomainResellerApiHelper::normalizeDomain($post['hostname'] ?? '');
                $ipv4 = trim((string) ($post['ipv4'] ?? ''));
                $ipv6 = trim((string) ($post['ipv6'] ?? ''));
                $response = null;
                $message = null;

                if ($post['action'] == 'delete') {
                    $response = $this->call($api, 'deleteHost', [$domain, $hostname]);
                    $message = 'host_delete';
                } elseif ($this->validateHostInput($domain, $hostname, $ipv4, $ipv6)) {
                    // Saving an existing host updates its addresses
                    $known = array_map('strtolower', array_column($hosts, 'hostname'));
                    $exists = in_array($hostname, $known, true);
                    $response = $this->call(
                        $api,
                        $exists ? 'updateHost' : 'createHost',
                        [$domain, $hostname, $ipv4 ?: null, $ipv6 ?: null]
                    );
                    $message = $exists ? 'host_update' : 'host_create';
                }

                if ($response !== null && $response->success()) {
                    $this->setMessage('success', Language::_('DomainResellerApi.!success.' . $message, true, $hostname));
                    $refreshed = $this->call($api, 'getHosts', [$domain]);
                    if ($refreshed->success()) {
                        $hosts = (array) ($refreshed->data([])['hosts'] ?? []);
                    }
                } else {
                    if ($response !== null) {
                        $this->setApiError($response);
                    }
                    $vars = (object) $post;
                }
            }
        }

        $this->view->set('domain', $domain);
        $this->view->set('info', $info);
        $this->view->set('hosts', $hosts);
        $this->view->set('pending', $this->isPendingState($info));
        $this->view->set('vars', $vars);

        return $this->view->fetch();
    }

    /**
     * Validates glue record input, setting errors on failure
     *
     * @param string $domain The domain
     * @param string $hostname The host name
     * @param string $ipv4 The IPv4 address (may be empty)
     * @param string $ipv6 The IPv6 address (may be empty)
     * @return bool True if valid
     */
    private function validateHostInput($domain, $hostname, $ipv4, $ipv6)
    {
        $errors = [];
        $suffix = '.' . $domain;
        if (!DomainResellerApiHelper::isValidHostname($hostname)
            || strlen($hostname) <= strlen($suffix)
            || substr($hostname, -strlen($suffix)) !== $suffix
        ) {
            $errors['hostname']['invalid'] = Language::_('DomainResellerApi.!error.host.hostname', true, $domain);
        }
        if ($ipv4 === '' && $ipv6 === '') {
            $errors['ipv4']['empty'] = Language::_('DomainResellerApi.!error.host.ip_empty', true);
        }
        if ($ipv4 !== '' && filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            $errors['ipv4']['invalid'] = Language::_('DomainResellerApi.!error.host.ipv4', true);
        }
        if ($ipv6 !== '' && filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            $errors['ipv6']['invalid'] = Language::_('DomainResellerApi.!error.host.ipv6', true);
        }

        if (!empty($errors)) {
            $this->Input->setErrors($errors);

            return false;
        }

        return true;
    }

    /**
     * Fetches the transfer lock of a domain without raising errors
     *
     * @param stdClass $row The module row
     * @param string $domain The domain
     * @return array|null The lock state (supported, locked) or null if unavailable
     */
    private function fetchTransferLock($row, $domain)
    {
        $response = $this->call($this->getApi($row), 'getTransferLock', [$domain]);

        return $response->success() ? (array) $response->data([]) : null;
    }

    /**
     * Handles the settings tabs (overview, auto-renewal, auth code)
     *
     * @param string $view The view to render
     * @param stdClass $package The package
     * @param stdClass $service The service
     * @param array|null $post Any POST parameters
     * @param bool $client True for the client interface
     * @return string The rendered tab
     */
    private function manageSettings($view, $package, $service, ?array $post, $client)
    {
        $context = $this->getTabContext($package, $service);
        $this->view = $this->makeView($view);
        Loader::loadHelpers($this, ['Form', 'Html']);
        $this->view->set('service', $service);

        if (!$context) {
            // The error (e.g. domain not found) is shown by Blesta
            return '';
        }
        list($row, $domain, $info) = $context;
        $api = $this->getApi($row);

        $tld = DomainResellerApiHelper::getTld($domain);
        $epp_allowed = !$client || $this->clientCanRequestEppCode($package, $service);
        $supports_auth_code = in_array($tld, ['.de', '.eu'], true);
        $supports_reset = $tld === '.de';

        $auth_code = null;
        if (!empty($post['action'])) {
            switch ($post['action']) {
                case 'auto_renew':
                    if (!$client) {
                        $response = $this->call($api, 'updateDomain', [$domain, ['autoRenew' => ($post['auto_renew'] ?? '0') == '1']]);
                        if ($response->success()) {
                            $this->setMessage('success', Language::_('DomainResellerApi.!success.auto_renew_updated', true));
                        } else {
                            $this->setApiError($response);
                        }
                    }
                    break;
                case 'transfer_lock':
                    $response = $this->call($api, 'setTransferLock', [$domain, ($post['locked'] ?? '0') == '1']);
                    if ($response->success()) {
                        $this->setMessage('success', Language::_('DomainResellerApi.!success.transfer_lock_updated', true));
                    } else {
                        $this->setApiError($response);
                    }
                    break;
                case 'get_auth_code':
                    if ($epp_allowed && $supports_auth_code) {
                        $response = $this->call($api, 'getAuthCode', [$domain]);
                        if ($response->success()) {
                            $auth_code = (string) ($response->data([])['authCode'] ?? '');
                        } else {
                            $this->setApiError($response);
                        }
                    }
                    break;
                case 'reset_auth_code':
                    if ($epp_allowed && $supports_reset) {
                        $response = $this->call($api, 'resetAuthCode', [$domain]);
                        if ($response->success()) {
                            $this->setMessage('success', Language::_('DomainResellerApi.!success.auth_code_reset', true));
                        } else {
                            $this->setApiError($response);
                        }
                    }
                    break;
            }

            $this->forgetDomain($row, $domain);
            $info = $this->getDomainInfo($domain, $row->id) ?: $info;
        }

        $this->view->set('domain', $domain);
        $this->view->set('info', $info);
        $this->view->set('state', (array) ($info['stateInfo'] ?? []));
        $this->view->set('auth_code', $auth_code);
        $this->view->set('epp_allowed', $epp_allowed);
        $this->view->set('supports_auth_code', $supports_auth_code);
        $this->view->set('supports_reset', $supports_reset);
        $this->view->set('lock', $this->isPendingState($info) ? null : $this->fetchTransferLock($row, $domain));
        $this->view->set('dates', [
            'registered' => DomainResellerApiHelper::formatDate($info['registeredAt'] ?? null, 'Y-m-d'),
            'expires' => DomainResellerApiHelper::formatDate($info['expiresAt'] ?? null, 'Y-m-d')
        ]);

        return $this->view->fetch();
    }

    // ==================================================================
    // Internal helpers
    // ==================================================================

    /**
     * Creates the API client for the given module row
     *
     * @param stdClass $row The module row
     * @param int|null $timeout The request timeout in seconds
     * @return DomainResellerApiClient The API client
     */
    protected function getApi($row, $timeout = null)
    {
        $meta = $row->meta ?? new stdClass();
        $url = trim((string) ($meta->api_url ?? ''));
        if ($url === '' || !DomainResellerApiClient::isAllowedUrl($url)) {
            $url = DomainResellerApiClient::DEFAULT_URL;
        }

        return new DomainResellerApiClient(
            (string) ($meta->api_key ?? ''),
            $url,
            ($meta->sandbox ?? 'false') == 'true',
            $timeout ?? 120
        );
    }

    /**
     * Calls an API method and writes the request and response to the module log
     *
     * @param DomainResellerApiClient $api The API client
     * @param string $method The client method
     * @param array $args The method arguments
     * @return DomainResellerApiResponse The response
     */
    protected function call(DomainResellerApiClient $api, $method, array $args = [])
    {
        $response = call_user_func_array([$api, $method], $args);

        $request = $api->lastRequest();
        $url = ($request['method'] ?? '') . ' ' . ($request['url'] ?? '') . ($api->isSandbox() ? ' [sandbox]' : '');
        $input = DomainResellerApiHelper::maskSensitive((array) ($request['body'] ?? []));
        $output = $response->body() !== null
            ? json_encode(DomainResellerApiHelper::maskSensitive($response->body()))
            : $response->raw();

        try {
            $this->log($url, json_encode($input), 'input', true);
            $this->log($url, $output, 'output', $response->success());
        } catch (Throwable $e) {
            // Logging must never break a request
        }

        return $response;
    }

    /**
     * Waits before retrying a request
     *
     * @param int $seconds The number of seconds to wait
     */
    protected function pause($seconds)
    {
        sleep($seconds);
    }

    /**
     * Creates a view for this module
     *
     * @param string $name The view name
     * @return View The view
     */
    protected function makeView($name)
    {
        $view = new View($name, 'default');
        $view->base_uri = $this->base_uri ?? null;
        $view->setDefaultView(self::VIEW_PATH);
        // Client tab views share the admin templates
        $view->set('client', strpos($name, 'tab_client_') === 0);

        return $view;
    }

    /**
     * Resolves a module row, falling back to the current or first available row
     *
     * @param int|null $module_row_id The module row ID
     * @return stdClass|null The module row
     */
    private function resolveRow($module_row_id = null)
    {
        $row = $module_row_id ? $this->getModuleRow($module_row_id) : $this->getModuleRow();
        if (!$row) {
            $row = $this->getModuleRow();
        }
        if (!$row) {
            $rows = $this->getModuleRows();
            $row = is_array($rows) && !empty($rows) ? reset($rows) : null;
        }

        return $row ?: null;
    }

    /**
     * Resolves the module row of a service, setting an error if there is none
     *
     * @param stdClass $package The package
     * @param stdClass $service The service
     * @return stdClass|null The module row
     */
    private function getServiceRow($package, $service)
    {
        $row = $this->resolveRow($service->module_row_id ?? ($package->module_row ?? null));
        if (!$row) {
            $this->setError('module_row', 'missing', Language::_('DomainResellerApi.!error.module_row.missing', true));
        }

        return $row;
    }

    /**
     * Fetches the domain information of a service without raising errors
     *
     * @param stdClass $service The service
     * @return array The domain info (empty if not found)
     */
    private function getServiceDomainInfo($service)
    {
        $row = $this->resolveRow($service->module_row_id ?? null);
        if (!$row) {
            return [];
        }

        $info = $this->getDomainInfo($this->getServiceDomain($service), $row->id);
        // Date synchronisation must not surface API errors as module errors
        $this->Input->setErrors([]);

        return $info;
    }

    /**
     * Builds the common context of a service tab
     *
     * @param stdClass $package The package
     * @param stdClass $service The service
     * @return array|null A list of the module row, the domain and its info, or null on failure
     */
    private function getTabContext($package, $service)
    {
        if (!($row = $this->getServiceRow($package, $service))) {
            return null;
        }

        $domain = DomainResellerApiHelper::normalizeDomain($this->getServiceDomain($service));
        $info = $this->getDomainInfo($domain, $row->id);
        if (empty($info)) {
            return null;
        }

        return [$row, $domain, $info];
    }

    /**
     * Removes a domain from the request cache
     *
     * @param stdClass $row The module row
     * @param string $domain The domain
     */
    private function forgetDomain($row, $domain)
    {
        unset($this->domain_cache[$row->id . '|' . DomainResellerApiHelper::normalizeDomain($domain)]);
    }

    /**
     * Fetches the TLD pricing list (cached)
     *
     * @param int|null $module_row_id The module row ID
     * @return array The pricing rows
     */
    private function fetchPricing($module_row_id = null)
    {
        $row = $this->resolveRow($module_row_id) ?: (object) ['id' => null, 'meta' => new stdClass()];
        $api = $this->getApi($row, 30);
        $key = $api->getBaseUrl();

        if (isset($this->pricing_cache[$key])) {
            return $this->pricing_cache[$key];
        }

        $cache_name = 'tld_pricing_' . md5($key);
        $cache_path = Configure::get('Blesta.company_id') . DIRECTORY_SEPARATOR . 'modules'
            . DIRECTORY_SEPARATOR . 'domain_reseller_api' . DIRECTORY_SEPARATOR;

        $cached = Cache::fetchCache($cache_name, $cache_path);
        if ($cached) {
            $pricing = json_decode($cached, true);
            if (is_array($pricing)) {
                return $this->pricing_cache[$key] = $pricing;
            }
        }

        $response = $this->call($api, 'getPricing');
        $pricing = $response->success() ? $response->data([]) : [];
        if (!is_array($pricing) || isset($pricing['tld'])) {
            $pricing = isset($pricing['tld']) ? [$pricing] : [];
        }

        if (!empty($pricing) && Configure::get('Caching.on') && is_writable(CACHEDIR)) {
            try {
                Cache::writeCache(
                    $cache_name,
                    json_encode($pricing),
                    strtotime(Configure::get('Blesta.cache_length')) - time(),
                    $cache_path
                );
            } catch (Throwable $e) {
                // Caching is optional
            }
        }

        return $this->pricing_cache[$key] = $pricing;
    }

    /**
     * Fetches the wallet of an account for the module overview
     *
     * @param stdClass $row The module row
     * @return array|null The wallet data or null if unavailable
     */
    private function fetchWallet($row)
    {
        try {
            $response = $this->call($this->getApi($row, 10), 'getWallet');
        } catch (Throwable $e) {
            return null;
        }

        return $response->success() ? (array) $response->data([]) : null;
    }

    /**
     * Fetches the contacts of a domain with their roles
     *
     * @param string $domain The domain
     * @param int|null $module_row_id The module row ID
     * @return array An array containing roles (role => handle) and contacts (handle => contact)
     */
    private function fetchDomainContacts($domain, $module_row_id = null)
    {
        $result = ['roles' => [], 'contacts' => []];
        if (!($row = $this->resolveRow($module_row_id))) {
            return $result;
        }

        $info = $this->getDomainInfo($domain, $row->id);
        $api = $this->getApi($row);

        foreach (self::CONTACT_ROLES as $role) {
            $handle = $info['contacts'][$role] ?? null;
            if (empty($handle)) {
                continue;
            }

            $result['roles'][$role] = $handle;
            if (isset($result['contacts'][$handle])) {
                continue;
            }

            $response = $this->call($api, 'getContact', [$handle]);
            if ($response->success()) {
                $result['contacts'][$handle] = DomainResellerApiHelper::apiContactToBlesta((array) $response->data([]));
            } else {
                $result['contacts'][$handle] = ['external_id' => $handle];
            }
        }

        return $result;
    }

    /**
     * Assigns other contact handles to the roles of a domain (admin only)
     *
     * @param stdClass $row The module row
     * @param string $domain The domain
     * @param array $info The current domain info
     * @param array $post The submitted data
     */
    private function assignHandles($row, $domain, array $info, array $post)
    {
        $current = (array) ($info['contacts'] ?? []);
        $contacts = [];
        foreach (self::CONTACT_ROLES as $role) {
            $handle = trim((string) ($post['handles'][$role] ?? ''));
            if ($handle !== '' && $handle !== ($current[$role] ?? null)) {
                $contacts[$role] = $handle;
            }
        }

        if (empty($contacts)) {
            $this->setError('handles', 'unchanged', Language::_('DomainResellerApi.!error.handles.unchanged', true));

            return;
        }

        $response = $this->call(
            $this->getApi($row),
            'updateDomainContacts',
            [$domain, $contacts, ($post['confirm_owner_change'] ?? '0') == '1']
        );
        $this->forgetDomain($row, $domain);

        if ($response->success()) {
            $charged = (float) ($response->data([])['charged'] ?? 0);
            $this->setMessage(
                'success',
                Language::_('DomainResellerApi.!success.handles_assigned', true, number_format($charged, 2))
            );

            return;
        }

        $owner_change = $response->get('ownerChange');
        if ($response->status() == 409 && is_array($owner_change)) {
            $this->setError(
                'confirm_owner_change',
                'required',
                Language::_(
                    'DomainResellerApi.!error.owner_change.confirm',
                    true,
                    number_format((float) ($owner_change['price'] ?? 0), 2),
                    $owner_change['currency'] ?? 'EUR',
                    $owner_change['method'] ?? 'update'
                )
            );

            return;
        }

        $this->setApiError($response);
    }

    /**
     * Fetches or creates the owner contact handle for a client
     *
     * Identical contact data reuses the same handle (stored per client in the module client meta).
     *
     * @param stdClass $row The module row
     * @param int|null $client_id The client ID
     * @param bool $protection True to enable the registry WHOIS protection flag
     * @return array|null An array with handle and type, or null on failure (errors are set)
     */
    private function getClientContactHandle($row, $client_id, $protection = false)
    {
        Loader::loadModels($this, ['Clients', 'Contacts']);

        $client = $client_id ? $this->Clients->get($client_id) : null;
        if (!$client) {
            $this->setError('client_id', 'missing', Language::_('DomainResellerApi.!error.client.missing', true));

            return null;
        }

        $phone = '';
        $fax = '';
        foreach ((array) $this->Contacts->getNumbers($client->contact_id ?? null) as $number) {
            if (($number->type ?? 'phone') == 'fax') {
                $fax = $fax ?: $this->formatClientPhone($number->number ?? '', $client->country ?? '');
            } else {
                $phone = $phone ?: $this->formatClientPhone($number->number ?? '', $client->country ?? '');
            }
        }

        $contact = [
            'first_name' => $client->first_name ?? '',
            'last_name' => $client->last_name ?? '',
            'company' => $client->company ?? '',
            'email' => $client->email ?? '',
            'phone' => $phone,
            'fax' => $fax,
            'address1' => $client->address1 ?? '',
            'address2' => $client->address2 ?? '',
            'city' => $client->city ?? '',
            'state' => $client->state ?? '',
            'zip' => $client->zip ?? '',
            'country' => $client->country ?? '',
            'protection' => $protection ? true : null
        ];

        $payload = DomainResellerApiHelper::buildContactPayload($contact, $row->meta->fallback_phone ?? null);
        $invalid = DomainResellerApiHelper::validateContactPayload($payload);
        if (!empty($invalid)) {
            $errors = [];
            foreach ($invalid as $field) {
                $errors['contact'][$field] = Language::_('DomainResellerApi.!error.client.invalid_' . $field, true);
            }
            $this->Input->setErrors($errors);

            return null;
        }

        $handle = $this->getOrCreateContactHandle($row, $payload, $client->id ?? $client_id);
        if ($handle === null) {
            return null;
        }

        return ['handle' => $handle, 'type' => $payload['type']];
    }

    /**
     * Returns an existing handle for identical contact data or creates a new contact
     *
     * @param stdClass $row The module row
     * @param array $payload The contact payload
     * @param int $client_id The client ID
     * @return string|null The contact handle, or null on failure
     */
    private function getOrCreateContactHandle($row, array $payload, $client_id)
    {
        Loader::loadModels($this, ['ModuleClientMeta']);

        $api = $this->getApi($row);
        $fingerprint = DomainResellerApiHelper::contactFingerprint($payload) . ($api->isSandbox() ? '-sandbox' : '');
        $module_id = $this->getModule()->id ?? null;

        $handles = [];
        $meta = $this->ModuleClientMeta->get($client_id, 'contact_handles', $module_id, $row->id);
        if ($meta && !empty($meta->value)) {
            $handles = (array) json_decode($meta->value, true);
        }

        if (!empty($handles[$fingerprint])) {
            if ($this->call($api, 'getContact', [$handles[$fingerprint]])->success()) {
                return $handles[$fingerprint];
            }
            unset($handles[$fingerprint]);
        }

        $response = $this->call($api, 'createContact', [$payload]);
        $handle = $response->success() ? ($response->data([])['handle'] ?? null) : null;
        if (empty($handle)) {
            $this->setApiError($response, 'contact');

            return null;
        }

        // Keep the most recent handles only
        $handles[$fingerprint] = $handle;
        $handles = array_slice($handles, -25, null, true);

        // All handles created for the client (the client may edit these)
        $owned = $this->getClientHandles($row, $client_id);
        $owned[] = $handle;

        $this->ModuleClientMeta->set(
            $client_id,
            $module_id,
            $row->id,
            [
                ['key' => 'contact_handles', 'value' => json_encode($handles), 'encrypted' => 0],
                ['key' => 'owned_handles', 'value' => json_encode(array_values(array_unique($owned))), 'encrypted' => 0]
            ]
        );

        return $handle;
    }

    /**
     * Returns the handles created for a client by this module
     *
     * @param stdClass $row The module row
     * @param int|null $client_id The client ID
     * @return array A list of handles
     */
    private function getClientHandles($row, $client_id)
    {
        if (!$client_id) {
            return [];
        }

        Loader::loadModels($this, ['ModuleClientMeta']);
        $module_id = $this->getModule()->id ?? null;

        $handles = [];
        $owned = $this->ModuleClientMeta->get($client_id, 'owned_handles', $module_id, $row->id);
        if ($owned && !empty($owned->value)) {
            $handles = (array) json_decode($owned->value, true);
        }

        // Handles created before owned handles were tracked
        $map = $this->ModuleClientMeta->get($client_id, 'contact_handles', $module_id, $row->id);
        if ($map && !empty($map->value)) {
            $handles = array_merge($handles, array_values((array) json_decode($map->value, true)));
        }

        return array_values(array_unique(array_filter($handles, 'is_string')));
    }

    /**
     * Stops reusing handles for new orders after their data was edited
     *
     * @param stdClass $row The module row
     * @param int|null $client_id The client ID
     * @param array $edited The edited handles
     */
    private function forgetHandleFingerprints($row, $client_id, array $edited)
    {
        if (!$client_id) {
            return;
        }

        Loader::loadModels($this, ['ModuleClientMeta']);
        $module_id = $this->getModule()->id ?? null;
        $meta = $this->ModuleClientMeta->get($client_id, 'contact_handles', $module_id, $row->id);
        if (!$meta || empty($meta->value)) {
            return;
        }

        $map = (array) json_decode($meta->value, true);
        $remaining = array_filter($map, function ($handle) use ($edited) {
            return !in_array($handle, $edited, true);
        });
        if (count($remaining) === count($map)) {
            return;
        }

        // Keep the handles editable by the client even though they are no longer reused
        $owned = $this->getClientHandles($row, $client_id);
        $this->ModuleClientMeta->set(
            $client_id,
            $module_id,
            $row->id,
            [
                ['key' => 'contact_handles', 'value' => json_encode($remaining), 'encrypted' => 0],
                ['key' => 'owned_handles', 'value' => json_encode($owned), 'encrypted' => 0]
            ]
        );
    }

    /**
     * Enables managed DNS for a domain, retrying while the new zone is not authoritative yet
     *
     * @param DomainResellerApiClient $api The API client
     * @param string $domain The domain
     * @return DomainResellerApiResponse The response
     */
    private function enableManagedDns(DomainResellerApiClient $api, $domain)
    {
        $response = $this->call($api, 'updateDomain', [$domain, ['useManagedDns' => true]]);
        for ($attempt = 1; $attempt <= 2 && $response->status() == 503; $attempt++) {
            $this->pause(15);
            $response = $this->call($api, 'updateDomain', [$domain, ['useManagedDns' => true]]);
        }

        return $response;
    }

    /**
     * Formats a client phone number, falling back to Blesta's number formatting
     *
     * @param string $number The phone number
     * @param string $country The country code
     * @return string The formatted number (or the raw number if it could not be formatted)
     */
    private function formatClientPhone($number, $country)
    {
        // The module's own formatting drops national trunk prefixes (030 1234567 => +49.301234567),
        // which Blesta's Contacts::intlNumber() keeps (+49.0301234567)
        $formatted = DomainResellerApiHelper::formatPhone($number, $country);
        if ($formatted !== null) {
            return $formatted;
        }

        if (isset($this->Contacts) && method_exists($this->Contacts, 'intlNumber')) {
            try {
                $formatted = $this->Contacts->intlNumber($number, $country, '.');
                if (is_string($formatted) && preg_match(DomainResellerApiHelper::PHONE_PATTERN, $formatted)) {
                    return $formatted;
                }
            } catch (Throwable $e) {
                // Fall back to the module's own formatting
            }
        }

        return (string) $number;
    }

    /**
     * Builds the contact roles for a new domain
     *
     * @param stdClass $row The module row
     * @param string $owner The owner handle
     * @return array The contact roles
     */
    private function buildContactRoles($row, $owner)
    {
        $contacts = ['owner' => $owner];
        foreach (['admin', 'tech', 'billing'] as $role) {
            $handle = trim((string) ($row->meta->{$role . '_handle'} ?? ''));
            if ($handle !== '') {
                $contacts[$role] = $handle;
            }
        }

        return $contacts;
    }

    /**
     * Filters a contact role list to the supported roles and non-empty handles
     *
     * @param array $contacts The contact roles
     * @return array The filtered contact roles
     */
    private function filterContactRoles(array $contacts)
    {
        $filtered = [];
        foreach (self::CONTACT_ROLES as $role) {
            if (!empty($contacts[$role])) {
                $filtered[$role] = trim((string) $contacts[$role]);
            }
        }

        return $filtered;
    }

    /**
     * Returns the handles configured as account-wide contacts, which must never be edited per domain
     *
     * @param stdClass $row The module row
     * @return array A list of handles
     */
    private function getProtectedHandles($row)
    {
        $handles = [];
        foreach (['admin', 'tech', 'billing'] as $role) {
            $handle = trim((string) ($row->meta->{$role . '_handle'} ?? ''));
            if ($handle !== '') {
                $handles[] = $handle;
            }
        }

        return array_values(array_unique($handles));
    }

    /**
     * Makes sure a domain can be registered: it must be available and, unless allowed, not premium
     *
     * Premium domains would be charged at their premium price, so they are rejected unless the
     * account explicitly allows them. Failed checks (e.g. timeouts) do not block the registration;
     * the registration request itself validates the domain again.
     *
     * @param stdClass $row The module row
     * @param string $domain The domain
     * @return bool True if the registration may proceed
     */
    private function checkRegistrable($row, $domain)
    {
        if (($row->meta->allow_premium ?? 'false') == 'true') {
            return true;
        }

        $check = $this->call($this->getApi($row, 30), 'checkDomain', [$domain]);
        if (!$check->success()) {
            return true;
        }

        $data = (array) $check->data([]);
        if (!empty($data['premium'])) {
            $this->setError('domain', 'premium', Language::_('DomainResellerApi.!error.domain.premium', true, $domain));

            return false;
        }
        if (array_key_exists('available', $data) && !$data['available']) {
            $this->setError('domain', 'unavailable', Language::_('DomainResellerApi.!error.domain.unavailable', true, $domain));

            return false;
        }

        return true;
    }

    /**
     * Submits a registration/transfer, retrying without WHOIS privacy where the TLD does not support it
     *
     * WHOIS privacy is only offered for OpenProvider routed TLDs; other TLDs use the registry
     * protection flag of the owner contact, which is already set in that case.
     *
     * @param DomainResellerApiClient $api The API client
     * @param string $method The client method (registerDomain or transferDomain)
     * @param array $params The request body
     * @param bool $whois_privacy True to request WHOIS privacy
     * @return DomainResellerApiResponse The response
     */
    private function submitWithPrivacy(DomainResellerApiClient $api, $method, array $params, $whois_privacy)
    {
        if (!$whois_privacy) {
            return $this->call($api, $method, [$params]);
        }

        $response = $this->call($api, $method, [$params + ['whoisPrivacy' => true, 'acceptWppTerms' => true]]);
        if ($response->status() == 400 && stripos((string) $response->error(), 'whois privacy') !== false) {
            $response = $this->call($api, $method, [$params]);
        }

        return $response;
    }

    /**
     * Enables or disables ID protection of a domain
     *
     * Uses WHOIS privacy where the TLD supports it (OpenProvider routed TLDs, natural person
     * owners). Otherwise (HTTP 400) the registry protection flag of the owner contact is used.
     *
     * @param stdClass $row The module row
     * @param string $domain The domain
     * @param bool $protection True to enable the protection
     */
    private function setOwnerProtection($row, $domain, $protection)
    {
        $api = $this->getApi($row);
        $response = $this->call($api, 'setWhoisPrivacy', [$domain, (bool) $protection, (bool) $protection]);
        $this->forgetDomain($row, $domain);
        if ($response->success()) {
            return;
        }
        if ($response->status() != 400) {
            $this->setApiError($response);

            return;
        }

        $info = $this->getDomainInfo($domain, $row->id);
        $owner = $info['contacts']['owner'] ?? null;
        if (empty($owner) || in_array($owner, $this->getProtectedHandles($row), true)) {
            return;
        }

        $response = $this->call($api, 'updateContact', [$owner, ['protection' => (bool) $protection]]);
        if (!$response->success()) {
            $this->setApiError($response);
        }
    }

    /**
     * Enables or disables auto-renewal of a service's domain
     *
     * @param stdClass $package The package
     * @param stdClass $service The service
     * @param bool $auto_renew True to enable auto-renewal
     * @return mixed null on success, void on failure
     */
    private function setServiceAutoRenew($package, $service, $auto_renew)
    {
        if (!($row = $this->getServiceRow($package, $service))) {
            return;
        }

        $domain = $this->getServiceDomain($service);
        $response = $this->call($this->getApi($row), 'updateDomain', [$domain, ['autoRenew' => (bool) $auto_renew]]);
        $this->forgetDomain($row, $domain);

        // Domains that are gone or can not be modified (e.g. expired, pending) need no change
        if (!$response->success() && !in_array($response->status(), [404, 422])) {
            $this->setApiError($response);

            return;
        }

        return null;
    }

    /**
     * Sets the transfer lock of a domain
     *
     * @param string $domain The domain
     * @param int|null $module_row_id The module row ID
     * @param bool $locked True to lock the domain
     * @return bool True on success
     */
    private function setDomainLock($domain, $module_row_id, $locked)
    {
        if (!($row = $this->resolveRow($module_row_id))) {
            $this->setError('module_row', 'missing', Language::_('DomainResellerApi.!error.module_row.missing', true));

            return false;
        }

        $response = $this->call($this->getApi($row), 'setTransferLock', [DomainResellerApiHelper::normalizeDomain($domain), $locked]);
        if (!$response->success()) {
            $this->setApiError($response);

            return false;
        }

        return true;
    }

    /**
     * Finds the domain in the account a host belongs to (e.g. ns1.example.co.uk => example.co.uk)
     *
     * @param stdClass $row The module row
     * @param string $hostname The host name
     * @return string|null The domain, or null if none of the parent domains is in the account
     */
    private function findParentDomain($row, $hostname)
    {
        $labels = explode('.', $hostname);
        for ($i = 1; $i < count($labels) - 1; $i++) {
            $candidate = implode('.', array_slice($labels, $i));
            $response = $this->call($this->getApi($row), 'getDomain', [$candidate]);
            if ($response->success()) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Disables the API's own auto-renewal of a domain (best-effort)
     *
     * Blesta triggers renewals when the client pays; leaving the API's auto-renewal on could
     * renew (and charge) a domain twice. Failures are ignored: e.g. a domain in transfer can
     * not be modified yet and is handled on its first renewal.
     *
     * @param stdClass $row The module row
     * @param string $domain The domain
     */
    private function disableApiAutoRenew($row, $domain)
    {
        $this->call($this->getApi($row), 'updateDomain', [$domain, ['autoRenew' => false]]);
        $this->forgetDomain($row, $domain);
    }

    /**
     * Performs a domain action that only needs the domain name
     *
     * @param string $domain The domain
     * @param int|null $module_row_id The module row ID
     * @param string $method The client method
     * @return bool True on success
     */
    private function simpleDomainAction($domain, $module_row_id, $method)
    {
        if (!($row = $this->resolveRow($module_row_id))) {
            $this->setError('module_row', 'missing', Language::_('DomainResellerApi.!error.module_row.missing', true));

            return false;
        }

        $domain = DomainResellerApiHelper::normalizeDomain($domain);
        $response = $this->call($this->getApi($row), $method, [$domain]);
        $this->forgetDomain($row, $domain);

        if (!$response->success()) {
            $this->setApiError($response);

            return false;
        }

        return true;
    }

    /**
     * Updates the renew date of a service to the domain's expiration date
     *
     * @param stdClass $service The service
     * @param array $info The domain info
     */
    private function syncRenewDate($service, array $info)
    {
        $expires = DomainResellerApiHelper::formatDate($info['expiresAt'] ?? null);
        if (!$expires) {
            $this->setError('expires', 'missing', Language::_('DomainResellerApi.!error.expires.missing', true));

            return;
        }

        Loader::loadModels($this, ['Services']);
        $this->Services->edit($service->id, ['date_renews' => $expires], true);

        if (($errors = $this->Services->errors())) {
            $this->Input->setErrors($errors);

            return;
        }

        $this->setMessage('success', Language::_('DomainResellerApi.!success.renew_date_synced', true, $expires));
    }

    /**
     * Whether the domain is still being registered or transferred
     *
     * @param array $info The domain info
     * @return bool True if pending
     */
    private function isPendingState(array $info)
    {
        return in_array($info['status'] ?? '', ['pending', 'pending_transfer'], true);
    }

    /**
     * Whether the client may manage DNS records of the service
     *
     * @param stdClass $service The service
     * @return bool True if allowed
     */
    private function clientCanManageDns($service)
    {
        return $this->featureServiceEnabled('dns_management', $service)
            || ($this->getPackageMeta($service, 'dns_management') == '1');
    }

    /**
     * Whether the client may request the auth code of the service
     *
     * @param stdClass $package The package
     * @param stdClass $service The service
     * @return bool True if allowed
     */
    private function clientCanRequestEppCode($package, $service)
    {
        return $this->featureServiceEnabled('epp_code', $service)
            || (($package->meta->epp_code ?? '0') == '1')
            || ($this->getPackageMeta($service, 'epp_code') == '1');
    }

    /**
     * Checks if a configurable option feature is enabled for a service
     *
     * @param string $feature The option name (e.g. dns_management, id_protection, epp_code)
     * @param stdClass $service The service
     * @return bool True if enabled
     */
    private function featureServiceEnabled($feature, $service)
    {
        foreach ((array) ($service->options ?? []) as $option) {
            if (($option->option_name ?? null) == $feature && (string) ($option->option_value ?? '1') !== '0') {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetches a package meta value of a service
     *
     * @param stdClass $service The service
     * @param string $key The meta key
     * @return mixed The value or null
     */
    private function getPackageMeta($service, $key)
    {
        if (isset($service->package->meta)) {
            return $service->package->meta->{$key} ?? null;
        }

        // The service does not always carry the package meta, load the package
        $package_id = $service->package_id ?? ($service->package->id ?? null);
        if ($package_id) {
            Loader::loadModels($this, ['Packages']);
            if (isset($this->Packages)) {
                $package = $this->Packages->get($package_id);

                return $package->meta->{$key} ?? null;
            }
        }

        return null;
    }

    /**
     * Whether the order is a transfer
     *
     * @param array $vars The input vars
     * @return bool True for transfers
     */
    private function isTransfer(array $vars)
    {
        return (isset($vars['transfer']) && in_array((string) $vars['transfer'], ['1', 'true'], true))
            || (!isset($vars['transfer']) && !empty($vars['auth']));
    }

    /**
     * Determines the term in years of the selected package pricing
     *
     * @param stdClass $package The package
     * @param int|null $pricing_id The pricing ID
     * @return int The term in years
     */
    private function getTermYears($package, $pricing_id)
    {
        foreach ((array) ($package->pricing ?? []) as $pricing) {
            if ($pricing->id == $pricing_id) {
                $term = (int) ($pricing->term ?? 1);
                if (($pricing->period ?? 'year') == 'month') {
                    $term = (int) floor($term / 12);
                }

                return max(1, min(10, $term));
            }
        }

        return 1;
    }

    /**
     * Adds the package default nameservers to the service vars
     *
     * @param stdClass $package The package
     * @param stdClass|null $vars The vars
     * @return stdClass The vars
     */
    private function withDefaultNameservers($package, $vars)
    {
        $vars = $vars ?? new stdClass();
        if (!isset($vars->ns1) && !empty($package->meta->ns)) {
            $i = 1;
            foreach (DomainResellerApiHelper::normalizeNameservers((array) $package->meta->ns) as $ns) {
                if ($i > self::MAX_NAMESERVERS) {
                    break;
                }
                $vars->{'ns' . $i++} = $ns;
            }
        }

        return $vars;
    }

    /**
     * Builds the service meta fields
     *
     * @param string $domain The domain
     * @param array $nameservers The nameservers
     * @return array The service meta fields
     */
    private function buildServiceMeta($domain, array $nameservers)
    {
        $meta = [['key' => 'domain', 'value' => $domain, 'encrypted' => 0]];
        for ($i = 1; $i <= self::MAX_NAMESERVERS; $i++) {
            $meta[] = ['key' => 'ns' . $i, 'value' => $nameservers[$i - 1] ?? '', 'encrypted' => 0];
        }

        return $meta;
    }

    /**
     * Returns the rules used to validate a service
     *
     * @param array $vars The input vars
     * @param bool $edit True when editing a service
     * @return array The rules
     */
    private function getServiceRules(array $vars, $edit = false)
    {
        $rules = [
            'domain' => [
                'valid' => [
                    'if_set' => $edit,
                    'rule' => [['DomainResellerApiHelper', 'isValidDomain']],
                    'message' => Language::_('DomainResellerApi.!error.domain.valid', true)
                ]
            ]
        ];

        if ($edit) {
            return $rules;
        }

        if ($this->isTransfer($vars)) {
            $rules['auth'] = [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'post_format' => 'trim',
                    'message' => Language::_('DomainResellerApi.!error.auth.empty', true)
                ]
            ];
        } else {
            for ($i = 1; $i <= self::MAX_NAMESERVERS; $i++) {
                $rules['ns' . $i] = [
                    'valid' => [
                        'if_set' => true,
                        'rule' => function ($ns) {
                            $ns = trim((string) $ns);

                            return $ns === '' || DomainResellerApiHelper::isValidHostname(rtrim($ns, '.'));
                        },
                        'message' => Language::_('DomainResellerApi.!error.ns.valid', true, $i)
                    ]
                ];
            }

            $nameservers = DomainResellerApiHelper::nameserversFromVars($vars, self::MAX_NAMESERVERS);
            if (count($nameservers) === 1) {
                $rules['ns2']['count'] = [
                    'rule' => function () {
                        return false;
                    },
                    'message' => Language::_('DomainResellerApi.!error.nameservers.count', true)
                ];
            }
        }

        return $rules;
    }

    /**
     * Validates the module row input and builds its meta fields
     *
     * @param array $vars The input vars
     * @return array|null The meta fields, or null on failure
     */
    private function buildRowMeta(array &$vars)
    {
        $this->setUncheckedBoxes($vars);
        foreach (self::ROW_META_FIELDS as $field) {
            if (isset($vars[$field]) && is_string($vars[$field])) {
                $vars[$field] = trim($vars[$field]);
            }
        }
        if (empty($vars['api_url'])) {
            $vars['api_url'] = DomainResellerApiClient::DEFAULT_URL;
        }
        $vars['api_url'] = rtrim($vars['api_url'], '/');
        if (empty($vars['cancel_action']) || !array_key_exists($vars['cancel_action'], $this->getCancelActions())) {
            $vars['cancel_action'] = 'schedule_delete';
        }

        $this->Input->setRules($this->getRowRules($vars));
        if (!$this->Input->validates($vars)) {
            return;
        }

        $meta = [];
        foreach (self::ROW_META_FIELDS as $field) {
            $meta[] = [
                'key' => $field,
                'value' => (string) ($vars[$field] ?? ''),
                'encrypted' => $field == 'api_key' ? 1 : 0
            ];
        }

        return $meta;
    }

    /**
     * Returns the rules used to validate a module row
     *
     * @param array $vars The input vars
     * @return array The rules
     */
    private function getRowRules(array &$vars)
    {
        $api_url = $vars['api_url'] ?? null;
        $sandbox = $vars['sandbox'] ?? 'false';
        $api_key = $vars['api_key'] ?? null;

        $rules = [
            'label' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('DomainResellerApi.!error.label.empty', true)
                ]
            ],
            'api_url' => [
                'valid' => [
                    'rule' => [['DomainResellerApiClient', 'isAllowedUrl']],
                    'message' => Language::_('DomainResellerApi.!error.api_url.valid', true)
                ]
            ],
            'api_key' => [
                'format' => [
                    'rule' => ['matches', '/^drapi_[A-Za-z0-9_-]{12}_[A-Za-z0-9_-]{32}$/'],
                    'last' => true,
                    'message' => Language::_('DomainResellerApi.!error.api_key.format', true)
                ],
                'valid_connection' => [
                    'rule' => [[$this, 'validateConnection'], $api_url, $sandbox],
                    'final' => true,
                    'message' => Language::_('DomainResellerApi.!error.api_key.valid_connection', true)
                ]
            ],
            'sandbox' => [
                'format' => [
                    'rule' => ['in_array', ['true', 'false']],
                    'message' => Language::_('DomainResellerApi.!error.sandbox.format', true)
                ]
            ],
            'allow_premium' => [
                'format' => [
                    'rule' => ['in_array', ['true', 'false']],
                    'message' => Language::_('DomainResellerApi.!error.allow_premium.format', true)
                ]
            ],
            'fallback_phone' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => [[$this, 'validatePhone']],
                    'message' => Language::_('DomainResellerApi.!error.fallback_phone.valid', true)
                ]
            ]
        ];

        foreach (['admin', 'tech', 'billing'] as $role) {
            $rules[$role . '_handle'] = [
                'exists' => [
                    'if_set' => true,
                    'rule' => [[$this, 'validateHandle'], $api_key, $api_url, $sandbox],
                    'message' => Language::_('DomainResellerApi.!error.handle.exists', true, $role)
                ]
            ];
        }

        return $rules;
    }

    /**
     * Validates package input and builds its meta fields
     *
     * @param array $vars The input vars
     * @return array|null The meta fields, or null on failure
     */
    private function buildPackageMeta(array $vars)
    {
        $meta_input = (array) ($vars['meta'] ?? []);

        foreach (DomainResellerApiHelper::normalizeNameservers((array) ($meta_input['ns'] ?? [])) as $ns) {
            if (!DomainResellerApiHelper::isValidHostname($ns)) {
                $this->setError('meta[ns]', 'valid', Language::_('DomainResellerApi.!error.nameservers.invalid', true, $ns));

                return;
            }
        }

        $meta = [];
        foreach ($meta_input as $key => $value) {
            if ($key == 'ns') {
                $value = DomainResellerApiHelper::normalizeNameservers((array) $value);
            } elseif (in_array($key, ['epp_code', 'dns_management'])) {
                $value = $value == '1' ? '1' : '0';
            }

            $meta[] = ['key' => $key, 'value' => $value, 'encrypted' => 0];
        }

        return $meta;
    }

    /**
     * Sets the unchecked checkboxes of the module row form to "false"
     *
     * @param array $vars The input vars
     */
    private function setUncheckedBoxes(array &$vars)
    {
        foreach (['sandbox', 'allow_premium'] as $checkbox) {
            if (!isset($vars[$checkbox]) || $vars[$checkbox] !== 'true') {
                $vars[$checkbox] = 'false';
            }
        }
    }

    /**
     * Returns the available cancellation actions
     *
     * @return array The cancellation actions
     */
    private function getCancelActions()
    {
        return [
            'schedule_delete' => Language::_('DomainResellerApi.row_meta.cancel_action.schedule_delete', true),
            'disable_autorenew' => Language::_('DomainResellerApi.row_meta.cancel_action.disable_autorenew', true)
        ];
    }

    /**
     * Sets DNS record validation errors
     *
     * @param array $errors The errors returned by DomainResellerApiHelper::buildDnsRecord()
     * @return bool True if there were no errors
     */
    private function setRecordErrors(array $errors)
    {
        if (empty($errors)) {
            return true;
        }

        $input_errors = [];
        foreach ($errors as $field => $type) {
            $input_errors[$field][$type] = Language::_('DomainResellerApi.!error.dns.' . $field . '_' . $type, true);
        }
        $this->Input->setErrors($input_errors);

        return false;
    }

    /**
     * Sets a single Input error
     *
     * @param string $field The field
     * @param string $type The error type
     * @param string $message The message
     */
    private function setError($field, $type, $message)
    {
        $this->Input->setErrors([$field => [$type => $message]]);
    }

    /**
     * Sets the error of a failed API response
     *
     * @param DomainResellerApiResponse $response The response
     * @param string $field The error field
     */
    private function setApiError(DomainResellerApiResponse $response, $field = 'api')
    {
        $this->setError(
            $field,
            'response',
            Language::_('DomainResellerApi.!error.api.response', true, $response->error() ?? '')
        );
    }
}
