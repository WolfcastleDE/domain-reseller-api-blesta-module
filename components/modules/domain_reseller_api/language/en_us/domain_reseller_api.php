<?php
/**
 * en_us language for the Domain Reseller API module
 */
// Basics
$lang['DomainResellerApi.name'] = 'Domain Reseller API';
$lang['DomainResellerApi.description'] = 'Register, transfer and manage domains (incl. .de and .eu, managed DNS and DNSSEC) through the Domain Reseller API by Wolfscastle.';
$lang['DomainResellerApi.module_row'] = 'Account';
$lang['DomainResellerApi.module_row_plural'] = 'Accounts';
$lang['DomainResellerApi.module_group'] = 'Account Group';


// Module management
$lang['DomainResellerApi.add_module_row'] = 'Add Account';
$lang['DomainResellerApi.manage.module_rows_title'] = 'Accounts';
$lang['DomainResellerApi.manage.module_rows_heading.label'] = 'Label';
$lang['DomainResellerApi.manage.module_rows_heading.api_url'] = 'API URL';
$lang['DomainResellerApi.manage.module_rows_heading.mode'] = 'Mode';
$lang['DomainResellerApi.manage.module_rows_heading.balance'] = 'Wallet Balance';
$lang['DomainResellerApi.manage.module_rows_heading.options'] = 'Options';
$lang['DomainResellerApi.manage.module_rows.edit'] = 'Edit';
$lang['DomainResellerApi.manage.module_rows.delete'] = 'Delete';
$lang['DomainResellerApi.manage.module_rows.confirm_delete'] = 'Are you sure you want to delete this account?';
$lang['DomainResellerApi.manage.module_rows_no_results'] = 'There are no accounts.';
$lang['DomainResellerApi.manage.mode_live'] = 'Live';
$lang['DomainResellerApi.manage.mode_sandbox'] = 'Sandbox';
$lang['DomainResellerApi.manage.postpaid'] = '(postpaid)';
$lang['DomainResellerApi.manage.balance_unavailable'] = 'Unavailable';


// Add/edit row
$lang['DomainResellerApi.add_row.box_title'] = 'Domain Reseller API - Add Account';
$lang['DomainResellerApi.add_row.add_btn'] = 'Add Account';
$lang['DomainResellerApi.edit_row.box_title'] = 'Domain Reseller API - Edit Account';
$lang['DomainResellerApi.edit_row.edit_btn'] = 'Update Account';


// Row meta
$lang['DomainResellerApi.row_meta.heading_account'] = 'API Access';
$lang['DomainResellerApi.row_meta.heading_options'] = 'Options';
$lang['DomainResellerApi.row_meta.heading_handles'] = 'Default Contact Handles';
$lang['DomainResellerApi.row_meta.label'] = 'Label';
$lang['DomainResellerApi.row_meta.api_key'] = 'API Key';
$lang['DomainResellerApi.row_meta.api_key_keep'] = 'Leave empty to keep the current key';
$lang['DomainResellerApi.row_meta.api_url'] = 'API URL';
$lang['DomainResellerApi.row_meta.sandbox'] = 'Sandbox Mode';
$lang['DomainResellerApi.row_meta.allow_premium'] = 'Allow Premium Domains';
$lang['DomainResellerApi.row_meta.cancel_action'] = 'On Cancellation';
$lang['DomainResellerApi.row_meta.cancel_action.schedule_delete'] = 'Delete the domain at the end of its term';
$lang['DomainResellerApi.row_meta.cancel_action.disable_autorenew'] = 'Let the domain expire (no deletion)';
$lang['DomainResellerApi.row_meta.fallback_phone'] = 'Fallback Phone Number';
$lang['DomainResellerApi.row_meta.admin_handle'] = 'Admin Contact Handle';
$lang['DomainResellerApi.row_meta.tech_handle'] = 'Tech Contact Handle';
$lang['DomainResellerApi.row_meta.billing_handle'] = 'Billing Contact Handle';
$lang['DomainResellerApi.row_meta.handles_description'] = 'Optionally use one of your own contact handles as admin, tech and/or billing contact for every new domain. Leave empty to use the domain owner. These handles are never modified through Blesta.';
$lang['DomainResellerApi.row_meta.tooltip.label'] = 'A name to identify this account in Blesta.';
$lang['DomainResellerApi.row_meta.tooltip.api_key'] = 'Your API key (drapi_...) from the Domain Reseller API dashboard. Restricted keys need the domains, contacts, dns and pricing scopes.';
$lang['DomainResellerApi.row_meta.tooltip.api_url'] = 'The API base URL. Only change this when instructed to do so.';
$lang['DomainResellerApi.row_meta.tooltip.sandbox'] = 'In sandbox mode no domains are registered and nothing is charged.';
$lang['DomainResellerApi.row_meta.tooltip.allow_premium'] = 'Premium domains are charged at their premium price. When disabled, premium domains are reported as unavailable.';
$lang['DomainResellerApi.row_meta.tooltip.cancel_action'] = 'What happens with the domain when the service is canceled in Blesta. A scheduled deletion can be reverted until the domain expires; otherwise the domain is simply no longer renewed.';
$lang['DomainResellerApi.row_meta.tooltip.fallback_phone'] = 'Used for domain contacts when the client has no valid phone number on file, in the format +49.301234567.';


// Errors
$lang['DomainResellerApi.!error.label.empty'] = 'Please enter a label.';
$lang['DomainResellerApi.!error.api_key.format'] = 'Please enter a valid API key (drapi_xxxxxxxxxxxx_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx).';
$lang['DomainResellerApi.!error.api_key.valid_connection'] = 'Unable to connect to the Domain Reseller API with the given API key.';
$lang['DomainResellerApi.!error.api_url.valid'] = 'The API URL must be a valid HTTPS URL.';
$lang['DomainResellerApi.!error.sandbox.format'] = 'Sandbox must be either "true" or "false".';
$lang['DomainResellerApi.!error.allow_premium.format'] = 'Allow premium domains must be either "true" or "false".';
$lang['DomainResellerApi.!error.fallback_phone.valid'] = 'Please enter the fallback phone number in international format, e.g. +49.301234567.';
$lang['DomainResellerApi.!error.handle.exists'] = 'The %1$s contact handle does not exist in this account.';
$lang['DomainResellerApi.!error.module_row.missing'] = 'An internal error occurred. The module row is unavailable.';
$lang['DomainResellerApi.!error.api.response'] = 'Domain Reseller API: %1$s';
$lang['DomainResellerApi.!error.domain.valid'] = 'Please enter a valid domain name.';
$lang['DomainResellerApi.!error.domain.premium'] = 'The domain %1$s is a premium domain and can not be ordered.';
$lang['DomainResellerApi.!error.domain.unavailable'] = 'The domain %1$s is not available for registration.';
$lang['DomainResellerApi.!error.domain.pending'] = 'The domain is still being registered or transferred and can not be modified yet.';
$lang['DomainResellerApi.!error.auth.empty'] = 'Please enter the auth code (EPP code) for the transfer.';
$lang['DomainResellerApi.!error.ns.valid'] = 'Name server %1$s is not a valid hostname.';
$lang['DomainResellerApi.!error.nameservers.count'] = 'Please enter at least two name servers.';
$lang['DomainResellerApi.!error.nameservers.invalid'] = '%1$s is not a valid name server hostname.';
$lang['DomainResellerApi.!error.contacts.missing'] = 'The owner contact handle is missing.';
$lang['DomainResellerApi.!error.contacts.unknown_handle'] = 'The contact handle %1$s is not assigned to this domain.';
$lang['DomainResellerApi.!error.contacts.protected_handle'] = 'The contact handle %1$s is managed in the module settings and can not be changed here.';
$lang['DomainResellerApi.!error.contacts.invalid_country'] = 'Contact %1$s: please enter a valid two-letter country code.';
$lang['DomainResellerApi.!error.contacts.invalid_email'] = 'Contact %1$s: please enter a valid email address (letters, digits, ".", "-" and "_" only).';
$lang['DomainResellerApi.!error.contacts.invalid_phone'] = 'Contact %1$s: please enter the phone number in international format, e.g. +49.301234567.';
$lang['DomainResellerApi.!error.client.missing'] = 'The client could not be found.';
$lang['DomainResellerApi.!error.client.invalid_country'] = 'The client has no valid country set.';
$lang['DomainResellerApi.!error.client.invalid_email'] = 'The email address of the client is not accepted by the registry (letters, digits, ".", "-" and "_" only).';
$lang['DomainResellerApi.!error.client.invalid_phone'] = 'The client has no valid phone number. Please add a phone number to the client or set a fallback phone number in the module settings.';
$lang['DomainResellerApi.!error.renew.redemption'] = 'The domain %1$s is in its redemption period. Please restore it in the "Actions" tab (a restore fee applies).';
$lang['DomainResellerApi.!error.renew.state'] = 'The domain %1$s can not be renewed in its current state (%2$s).';
$lang['DomainResellerApi.!error.handles.unchanged'] = 'Please select at least one contact handle to assign.';
$lang['DomainResellerApi.!error.owner_change.confirm'] = 'This changes the domain owner. Price: %1$s %2$s (method: %3$s). Tick "Confirm owner change" and submit again to proceed.';
$lang['DomainResellerApi.!error.confirm_domain.mismatch'] = 'Please type the domain name to confirm the immediate deletion.';
$lang['DomainResellerApi.!error.action.unavailable'] = 'This action is not available in the current state of the domain.';
$lang['DomainResellerApi.!error.expires.missing'] = 'The domain has no expiration date yet.';
$lang['DomainResellerApi.!error.zone_file.empty'] = 'Please enter a zone file.';
$lang['DomainResellerApi.!error.dnskeys.empty'] = 'Please enter at least one DNSKEY record to enable DNSSEC for custom name servers.';
$lang['DomainResellerApi.!error.dns.type_invalid'] = 'Please select a valid record type.';
$lang['DomainResellerApi.!error.dns.name_invalid'] = 'Please enter a valid record name (e.g. @, www or *.example).';
$lang['DomainResellerApi.!error.dns.name_apex_ns'] = 'NS records at the zone apex are managed automatically.';
$lang['DomainResellerApi.!error.dns.content_empty'] = 'Please enter the record content.';
$lang['DomainResellerApi.!error.dns.ttl_invalid'] = 'The TTL must be between 60 and 86400 seconds.';
$lang['DomainResellerApi.!error.dns.priority_invalid'] = 'The priority must be a number between 0 and 65535.';
$lang['DomainResellerApi.!error.dns.priority_empty'] = 'Please enter a priority for MX and SRV records.';


// Notices
$lang['DomainResellerApi.!notice.pending'] = 'The domain is still being registered or transferred. Management options become available once the process has completed.';


// Success messages
$lang['DomainResellerApi.!success.contacts_updated'] = 'The contacts have been updated.';
$lang['DomainResellerApi.!success.handles_assigned'] = 'The contact handles have been assigned (charged: %1$s EUR).';
$lang['DomainResellerApi.!success.nameservers_updated'] = 'The name servers have been updated.';
$lang['DomainResellerApi.!success.managed_dns_enabled'] = 'Managed DNS is enabled for this domain.';
$lang['DomainResellerApi.!success.dns_record_added'] = 'The DNS record has been added.';
$lang['DomainResellerApi.!success.dns_record_updated'] = 'The DNS record has been updated.';
$lang['DomainResellerApi.!success.dns_record_deleted'] = 'The DNS record has been deleted.';
$lang['DomainResellerApi.!success.zone_imported'] = 'The zone file has been imported.';
$lang['DomainResellerApi.!success.dnssec_updated'] = 'The DNSSEC settings have been updated.';
$lang['DomainResellerApi.!success.auto_renew_updated'] = 'Auto-renewal has been updated.';
$lang['DomainResellerApi.!success.auth_code_reset'] = 'The auth code has been reset. A new auth code can now be requested.';
$lang['DomainResellerApi.!success.renew_date_synced'] = 'The renew date of the service has been set to %1$s.';
$lang['DomainResellerApi.!success.action_restore'] = 'The domain %1$s has been restored.';
$lang['DomainResellerApi.!success.action_cancel_delete'] = 'The scheduled deletion of %1$s has been canceled.';
$lang['DomainResellerApi.!success.action_schedule_delete'] = 'The domain %1$s will be deleted at the end of its term.';
$lang['DomainResellerApi.!success.action_delete_immediate'] = 'The domain %1$s has been deleted.';
$lang['DomainResellerApi.!success.action_transfer_out_approve'] = 'The outgoing transfer of %1$s has been approved.';
$lang['DomainResellerApi.!success.action_transfer_out_reject'] = 'The outgoing transfer of %1$s has been rejected.';
$lang['DomainResellerApi.!success.action_hold'] = 'The domain %1$s has been put on hold.';
$lang['DomainResellerApi.!success.action_unhold'] = 'The domain %1$s has been released from hold.';


// Domain states
$lang['DomainResellerApi.status.pending'] = 'Registration in progress';
$lang['DomainResellerApi.status.pending_transfer'] = 'Transfer in progress';
$lang['DomainResellerApi.status.active'] = 'Active';
$lang['DomainResellerApi.status.expired'] = 'Expired';
$lang['DomainResellerApi.status.redemption'] = 'Redemption period';
$lang['DomainResellerApi.status.pending_delete'] = 'Scheduled for deletion';
$lang['DomainResellerApi.status.transferred_away'] = 'Transferred away';
$lang['DomainResellerApi.status.suspended'] = 'On hold';


// Service fields
$lang['DomainResellerApi.service_fields.domain'] = 'Domain';
$lang['DomainResellerApi.service_fields.transfer'] = 'Domain Action';
$lang['DomainResellerApi.service_fields.transfer_register'] = 'Register';
$lang['DomainResellerApi.service_fields.transfer_transfer'] = 'Transfer';
$lang['DomainResellerApi.service_fields.auth'] = 'Auth Code (EPP Code)';
$lang['DomainResellerApi.service_fields.ns1'] = 'Name Server 1';
$lang['DomainResellerApi.service_fields.ns2'] = 'Name Server 2';
$lang['DomainResellerApi.service_fields.ns3'] = 'Name Server 3';
$lang['DomainResellerApi.service_fields.ns4'] = 'Name Server 4';
$lang['DomainResellerApi.service_fields.ns5'] = 'Name Server 5';


// Package fields
$lang['DomainResellerApi.package_fields.tld_options'] = 'TLDs';
$lang['DomainResellerApi.package_fields.ns1'] = 'Name Server 1';
$lang['DomainResellerApi.package_fields.ns2'] = 'Name Server 2';
$lang['DomainResellerApi.package_fields.ns3'] = 'Name Server 3';
$lang['DomainResellerApi.package_fields.ns4'] = 'Name Server 4';
$lang['DomainResellerApi.package_fields.ns5'] = 'Name Server 5';
$lang['DomainResellerApi.package_fields.tooltip.ns'] = 'Default name servers for new registrations. Leave empty to use the managed DNS of the Domain Reseller API (ns1/ns2.domain-reseller-api.de).';
$lang['DomainResellerApi.package_fields.epp_code'] = 'Allow Auth Code Requests';
$lang['DomainResellerApi.package_fields.tooltip.epp_code'] = 'Whether clients may request the auth code (EPP code) of their domains in the client area.';
$lang['DomainResellerApi.package_fields.dns_management'] = 'Allow DNS Management';
$lang['DomainResellerApi.package_fields.tooltip.dns_management'] = 'Whether clients may manage DNS records and DNSSEC in the client area (in addition to the "dns_management" configurable option).';


// Contacts
$lang['DomainResellerApi.contacts.handle'] = 'Handle %1$s';
$lang['DomainResellerApi.contacts.protected'] = 'This contact is managed by your provider and can not be changed here.';
$lang['DomainResellerApi.contacts.role_owner'] = 'Owner';
$lang['DomainResellerApi.contacts.role_admin'] = 'Administrative';
$lang['DomainResellerApi.contacts.role_tech'] = 'Technical';
$lang['DomainResellerApi.contacts.role_billing'] = 'Billing';
$lang['DomainResellerApi.contacts.field_first_name'] = 'First Name';
$lang['DomainResellerApi.contacts.field_last_name'] = 'Last Name';
$lang['DomainResellerApi.contacts.field_company'] = 'Company';
$lang['DomainResellerApi.contacts.field_email'] = 'Email Address';
$lang['DomainResellerApi.contacts.field_phone'] = 'Phone Number';
$lang['DomainResellerApi.contacts.field_address1'] = 'Street and Number';
$lang['DomainResellerApi.contacts.field_address2'] = 'Address Line 2';
$lang['DomainResellerApi.contacts.field_city'] = 'City';
$lang['DomainResellerApi.contacts.field_state'] = 'State';
$lang['DomainResellerApi.contacts.field_zip'] = 'Postal Code';
$lang['DomainResellerApi.contacts.field_country'] = 'Country (ISO code)';


// Tab: contacts
$lang['DomainResellerApi.tab_contacts.title'] = 'Contacts';
$lang['DomainResellerApi.tab_contacts.heading'] = 'Domain Contacts';
$lang['DomainResellerApi.tab_contacts.no_contacts'] = 'No contacts are assigned to this domain.';
$lang['DomainResellerApi.tab_contacts.field_update'] = 'Update Contacts';
$lang['DomainResellerApi.tab_contacts.heading_assign'] = 'Assign Contact Handles';
$lang['DomainResellerApi.tab_contacts.assign_description'] = 'Assign existing contact handles of your account to the roles of this domain. Changing the owner is an owner change, which may be charged (e.g. a trade for .eu); you will be shown the price before anything is executed.';
$lang['DomainResellerApi.tab_contacts.keep_current'] = '-- Keep current --';
$lang['DomainResellerApi.tab_contacts.confirm_owner_change'] = 'Confirm owner change (may be charged)';
$lang['DomainResellerApi.tab_contacts.field_assign'] = 'Assign Handles';


// Tab: nameservers
$lang['DomainResellerApi.tab_nameservers.title'] = 'Name Servers';
$lang['DomainResellerApi.tab_nameservers.heading'] = 'Name Servers';
$lang['DomainResellerApi.tab_nameservers.use_managed_dns'] = 'Use managed DNS';
$lang['DomainResellerApi.tab_nameservers.use_managed_dns_description'] = 'The domain uses the name servers %1$s and its DNS records can be managed in the DNS tab.';
$lang['DomainResellerApi.tab_nameservers.field_update'] = 'Update Name Servers';


// Tab: DNS
$lang['DomainResellerApi.tab_dns.title'] = 'DNS Records';
$lang['DomainResellerApi.tab_dns.heading'] = 'DNS Records';
$lang['DomainResellerApi.tab_dns.text_dns_disabled'] = 'Managed DNS is not enabled for this domain. Enabling it switches the domain to the managed name servers, after which DNS records can be managed here.';
$lang['DomainResellerApi.tab_dns.text_no_dns_records'] = 'There are no DNS records.';
$lang['DomainResellerApi.tab_dns.heading_type'] = 'Type';
$lang['DomainResellerApi.tab_dns.heading_name'] = 'Name';
$lang['DomainResellerApi.tab_dns.heading_content'] = 'Content';
$lang['DomainResellerApi.tab_dns.heading_ttl'] = 'TTL';
$lang['DomainResellerApi.tab_dns.heading_priority'] = 'Priority';
$lang['DomainResellerApi.tab_dns.heading_options'] = 'Options';
$lang['DomainResellerApi.tab_dns.heading_add_record'] = 'Add Record';
$lang['DomainResellerApi.tab_dns.heading_edit_record'] = 'Edit Record';
$lang['DomainResellerApi.tab_dns.heading_zone'] = 'Zone File';
$lang['DomainResellerApi.tab_dns.managed_record'] = 'Managed';
$lang['DomainResellerApi.tab_dns.field_type'] = 'Type';
$lang['DomainResellerApi.tab_dns.field_name'] = 'Name';
$lang['DomainResellerApi.tab_dns.field_content'] = 'Content';
$lang['DomainResellerApi.tab_dns.field_ttl'] = 'TTL (seconds)';
$lang['DomainResellerApi.tab_dns.field_priority'] = 'Priority';
$lang['DomainResellerApi.tab_dns.field_zone_file'] = 'Import Zone File (BIND format)';
$lang['DomainResellerApi.tab_dns.field_enable'] = 'Enable Managed DNS';
$lang['DomainResellerApi.tab_dns.field_add'] = 'Add Record';
$lang['DomainResellerApi.tab_dns.field_update'] = 'Update Record';
$lang['DomainResellerApi.tab_dns.field_edit'] = 'Edit';
$lang['DomainResellerApi.tab_dns.field_delete'] = 'Delete';
$lang['DomainResellerApi.tab_dns.field_cancel'] = 'Cancel';
$lang['DomainResellerApi.tab_dns.field_export'] = 'Show zone file';
$lang['DomainResellerApi.tab_dns.field_import'] = 'Import';
$lang['DomainResellerApi.tab_dns.text_zone_export'] = 'Current zone in BIND format:';
$lang['DomainResellerApi.tab_dns.tooltip_name'] = 'Relative to the domain: use @ for the domain itself, www for www.example.com or * for a wildcard.';
$lang['DomainResellerApi.tab_dns.tooltip_content'] = 'SRV: "weight port target" (the priority is entered separately). CAA: "flags tag value", e.g. 0 issue "letsencrypt.org".';
$lang['DomainResellerApi.tab_dns.tooltip_priority'] = 'Required for MX and SRV records.';
$lang['DomainResellerApi.tab_dns.tooltip_zone_file'] = 'Records from the zone file are added to the existing zone.';


// Tab: DNSSEC
$lang['DomainResellerApi.tab_dnssec.title'] = 'DNSSEC';
$lang['DomainResellerApi.tab_dnssec.heading'] = 'DNSSEC';
$lang['DomainResellerApi.tab_dnssec.heading_update'] = 'Update DNSSEC';
$lang['DomainResellerApi.tab_dnssec.text_managed'] = 'The domain uses managed DNS: the zone is signed automatically and the keys are published at the registry.';
$lang['DomainResellerApi.tab_dnssec.text_custom'] = 'The domain uses custom name servers: enter the DNSKEY records of your signed zone to publish them at the registry.';
$lang['DomainResellerApi.tab_dnssec.status'] = 'Status';
$lang['DomainResellerApi.tab_dnssec.status_enabled'] = 'Enabled';
$lang['DomainResellerApi.tab_dnssec.status_disabled'] = 'Disabled';
$lang['DomainResellerApi.tab_dnssec.heading_keytype'] = 'Key';
$lang['DomainResellerApi.tab_dnssec.heading_algorithm'] = 'Algorithm';
$lang['DomainResellerApi.tab_dnssec.heading_ds'] = 'DS Records';
$lang['DomainResellerApi.tab_dnssec.field_enabled'] = 'Enable DNSSEC';
$lang['DomainResellerApi.tab_dnssec.field_dnskeys'] = 'DNSKEY Records';
$lang['DomainResellerApi.tab_dnssec.tooltip_dnskeys'] = 'One DNSKEY record per line, e.g. "example.com. IN DNSKEY 257 3 13 mdsswUyr3DPW..."';
$lang['DomainResellerApi.tab_dnssec.field_update'] = 'Update DNSSEC';


// Tab: settings
$lang['DomainResellerApi.tab_settings.title'] = 'Settings';
$lang['DomainResellerApi.tab_settings.heading'] = 'Domain Overview';
$lang['DomainResellerApi.tab_settings.domain'] = 'Domain';
$lang['DomainResellerApi.tab_settings.status'] = 'Status';
$lang['DomainResellerApi.tab_settings.registered'] = 'Registered';
$lang['DomainResellerApi.tab_settings.expires'] = 'Expires';
$lang['DomainResellerApi.tab_settings.auto_renew'] = 'Auto-Renewal';
$lang['DomainResellerApi.tab_settings.managed_dns'] = 'Managed DNS';
$lang['DomainResellerApi.tab_settings.dnssec'] = 'DNSSEC';
$lang['DomainResellerApi.tab_settings.nameservers'] = 'Name Servers';
$lang['DomainResellerApi.tab_settings.yes'] = 'Yes';
$lang['DomainResellerApi.tab_settings.no'] = 'No';
$lang['DomainResellerApi.tab_settings.heading_auto_renew'] = 'Auto-Renewal';
$lang['DomainResellerApi.tab_settings.auto_renew_description'] = 'Renewals are triggered by Blesta when the client pays, so the module keeps the API\'s own auto-renewal disabled to avoid double renewals. Only enable it to let the API renew this domain independently of Blesta.';
$lang['DomainResellerApi.tab_settings.auto_renew_on'] = 'Enabled';
$lang['DomainResellerApi.tab_settings.auto_renew_off'] = 'Disabled';
$lang['DomainResellerApi.tab_settings.field_update'] = 'Update';
$lang['DomainResellerApi.tab_settings.heading_auth_code'] = 'Auth Code (EPP Code)';
$lang['DomainResellerApi.tab_settings.auth_code'] = 'Auth Code';
$lang['DomainResellerApi.tab_settings.auth_code_unsupported'] = 'The auth code can only be retrieved through the API for .de and .eu domains. For other TLDs please request it in the Domain Reseller API dashboard.';
$lang['DomainResellerApi.tab_settings.auth_code_unsupported_client'] = 'The auth code for this domain can not be requested automatically. Please contact support.';
$lang['DomainResellerApi.tab_settings.field_get_auth_code'] = 'Show Auth Code';
$lang['DomainResellerApi.tab_settings.field_reset_auth_code'] = 'Reset Auth Code';


// Tab: actions
$lang['DomainResellerApi.tab_actions.title'] = 'Actions';
$lang['DomainResellerApi.tab_actions.heading'] = 'Domain Actions';
$lang['DomainResellerApi.tab_actions.restore_description'] = 'The domain is in its redemption period and can be restored. A restore fee is charged to your wallet.';
$lang['DomainResellerApi.tab_actions.field_restore'] = 'Restore Domain';
$lang['DomainResellerApi.tab_actions.cancel_delete_description'] = 'The domain is scheduled for deletion at the end of its term. Canceling the deletion keeps the domain and enables auto-renewal.';
$lang['DomainResellerApi.tab_actions.field_cancel_delete'] = 'Cancel Deletion';
$lang['DomainResellerApi.tab_actions.schedule_delete_description'] = 'Delete the domain at the end of its current term. This can be reverted until the domain expires.';
$lang['DomainResellerApi.tab_actions.field_schedule_delete'] = 'Delete at End of Term';
$lang['DomainResellerApi.tab_actions.delete_immediate_description'] = 'Delete the domain at the registry immediately. This can not be undone and no refund is given.';
$lang['DomainResellerApi.tab_actions.field_confirm_domain'] = 'Type %1$s to confirm';
$lang['DomainResellerApi.tab_actions.field_delete_immediate'] = 'Delete Immediately';
$lang['DomainResellerApi.tab_actions.heading_transfer_out'] = 'Outgoing Transfer';
$lang['DomainResellerApi.tab_actions.transfer_out_pending'] = 'An outgoing transfer was requested on %1$s.';
$lang['DomainResellerApi.tab_actions.transfer_out_none'] = 'There is no pending outgoing transfer for this domain. Approving or rejecting only has an effect while a transfer is pending.';
$lang['DomainResellerApi.tab_actions.field_transfer_out_approve'] = 'Approve Transfer';
$lang['DomainResellerApi.tab_actions.field_transfer_out_reject'] = 'Reject Transfer';
$lang['DomainResellerApi.tab_actions.heading_hold'] = 'Hold (.de)';
$lang['DomainResellerApi.tab_actions.hold_description'] = 'A .de domain on hold is disconnected from DNS at DENIC (e.g. for abuse cases) until it is released.';
$lang['DomainResellerApi.tab_actions.field_hold'] = 'Put on Hold';
$lang['DomainResellerApi.tab_actions.field_unhold'] = 'Release from Hold';
$lang['DomainResellerApi.tab_actions.heading_sync'] = 'Synchronization';
$lang['DomainResellerApi.tab_actions.sync_description'] = 'Set the renew date of this service to the expiration date reported by the registry.';
$lang['DomainResellerApi.tab_actions.field_sync_renew_date'] = 'Sync Renew Date';
$lang['DomainResellerApi.tab_actions.no_actions'] = 'No lifecycle actions are available in the current state of the domain.';


// Glue records (hosts) and transfer lock
$lang['DomainResellerApi.tab_hosts.title'] = 'Hosts';
$lang['DomainResellerApi.tab_hosts.heading'] = 'Glue Records';
$lang['DomainResellerApi.tab_hosts.description'] = 'Glue records register name servers below %1$s (e.g. ns1.%1$s) with their IP addresses at the registry.';
$lang['DomainResellerApi.tab_hosts.heading_hostname'] = 'Host';
$lang['DomainResellerApi.tab_hosts.heading_ipv4'] = 'IPv4';
$lang['DomainResellerApi.tab_hosts.heading_ipv6'] = 'IPv6';
$lang['DomainResellerApi.tab_hosts.heading_options'] = 'Options';
$lang['DomainResellerApi.tab_hosts.no_hosts'] = 'There are no glue records.';
$lang['DomainResellerApi.tab_hosts.heading_add'] = 'Add or Update Host';
$lang['DomainResellerApi.tab_hosts.add_description'] = 'To change the addresses of an existing host, enter its name again with the new addresses.';
$lang['DomainResellerApi.tab_hosts.field_hostname'] = 'Host Name';
$lang['DomainResellerApi.tab_hosts.field_ipv4'] = 'IPv4 Address';
$lang['DomainResellerApi.tab_hosts.field_ipv6'] = 'IPv6 Address';
$lang['DomainResellerApi.tab_hosts.field_add'] = 'Save Host';
$lang['DomainResellerApi.tab_hosts.field_delete_short'] = 'Delete';
$lang['DomainResellerApi.!success.host_create'] = 'The host %1$s has been created.';
$lang['DomainResellerApi.!success.host_update'] = 'The host %1$s has been updated.';
$lang['DomainResellerApi.!success.host_delete'] = 'The host %1$s has been deleted.';
$lang['DomainResellerApi.!success.transfer_lock_updated'] = 'The transfer lock has been updated.';
$lang['DomainResellerApi.!error.host.hostname'] = 'The host name must be a valid host below %1$s.';
$lang['DomainResellerApi.!error.host.ip_empty'] = 'Please enter at least one IP address.';
$lang['DomainResellerApi.!error.host.ipv4'] = 'Please enter a valid IPv4 address.';
$lang['DomainResellerApi.!error.host.ipv6'] = 'Please enter a valid IPv6 address.';
$lang['DomainResellerApi.!error.host.parent'] = 'The host %1$s does not belong to a domain in this account or has no valid IP address.';
$lang['DomainResellerApi.tab_settings.heading_transfer_lock'] = 'Transfer Lock';
$lang['DomainResellerApi.tab_settings.transfer_lock_description'] = 'A locked domain can not be transferred to another registrar.';
$lang['DomainResellerApi.tab_settings.transfer_lock_on'] = 'Locked';
$lang['DomainResellerApi.tab_settings.transfer_lock_off'] = 'Unlocked';
