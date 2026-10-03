<?php
/**
 * de_de language for the Domain Reseller API module
 */
// Basics
$lang['DomainResellerApi.name'] = 'Domain Reseller API';
$lang['DomainResellerApi.description'] = 'Registrieren, transferieren und verwalten Sie Domains (inkl. .de und .eu, Managed DNS und DNSSEC) über die Domain Reseller API von Wolfscastle.';
$lang['DomainResellerApi.module_row'] = 'Konto';
$lang['DomainResellerApi.module_row_plural'] = 'Konten';
$lang['DomainResellerApi.module_group'] = 'Kontogruppe';


// Module management
$lang['DomainResellerApi.add_module_row'] = 'Konto hinzufügen';
$lang['DomainResellerApi.manage.module_rows_title'] = 'Konten';
$lang['DomainResellerApi.manage.module_rows_heading.label'] = 'Bezeichnung';
$lang['DomainResellerApi.manage.module_rows_heading.api_url'] = 'API-URL';
$lang['DomainResellerApi.manage.module_rows_heading.mode'] = 'Modus';
$lang['DomainResellerApi.manage.module_rows_heading.balance'] = 'Wallet-Guthaben';
$lang['DomainResellerApi.manage.module_rows_heading.options'] = 'Optionen';
$lang['DomainResellerApi.manage.module_rows.edit'] = 'Bearbeiten';
$lang['DomainResellerApi.manage.module_rows.delete'] = 'Löschen';
$lang['DomainResellerApi.manage.module_rows.confirm_delete'] = 'Möchten Sie dieses Konto wirklich löschen?';
$lang['DomainResellerApi.manage.module_rows_no_results'] = 'Es sind keine Konten vorhanden.';
$lang['DomainResellerApi.manage.mode_live'] = 'Live';
$lang['DomainResellerApi.manage.mode_sandbox'] = 'Sandbox';
$lang['DomainResellerApi.manage.postpaid'] = '(Postpaid)';
$lang['DomainResellerApi.manage.balance_unavailable'] = 'Nicht verfügbar';


// Add/edit row
$lang['DomainResellerApi.add_row.box_title'] = 'Domain Reseller API - Konto hinzufügen';
$lang['DomainResellerApi.add_row.add_btn'] = 'Konto hinzufügen';
$lang['DomainResellerApi.edit_row.box_title'] = 'Domain Reseller API - Konto bearbeiten';
$lang['DomainResellerApi.edit_row.edit_btn'] = 'Konto aktualisieren';


// Row meta
$lang['DomainResellerApi.row_meta.heading_account'] = 'API-Zugang';
$lang['DomainResellerApi.row_meta.heading_options'] = 'Optionen';
$lang['DomainResellerApi.row_meta.heading_handles'] = 'Standard-Kontakthandles';
$lang['DomainResellerApi.row_meta.label'] = 'Bezeichnung';
$lang['DomainResellerApi.row_meta.api_key'] = 'API-Schlüssel';
$lang['DomainResellerApi.row_meta.api_key_keep'] = 'Leer lassen, um den aktuellen Schlüssel zu behalten';
$lang['DomainResellerApi.row_meta.api_url'] = 'API-URL';
$lang['DomainResellerApi.row_meta.sandbox'] = 'Sandbox-Modus';
$lang['DomainResellerApi.row_meta.allow_premium'] = 'Premium-Domains erlauben';
$lang['DomainResellerApi.row_meta.cancel_action'] = 'Bei Kündigung';
$lang['DomainResellerApi.row_meta.cancel_action.schedule_delete'] = 'Domain zum Ende der Laufzeit löschen';
$lang['DomainResellerApi.row_meta.cancel_action.disable_autorenew'] = 'Domain auslaufen lassen (keine Löschung)';
$lang['DomainResellerApi.row_meta.fallback_phone'] = 'Ersatz-Telefonnummer';
$lang['DomainResellerApi.row_meta.admin_handle'] = 'Admin-Kontakthandle';
$lang['DomainResellerApi.row_meta.tech_handle'] = 'Tech-Kontakthandle';
$lang['DomainResellerApi.row_meta.billing_handle'] = 'Billing-Kontakthandle';
$lang['DomainResellerApi.row_meta.handles_description'] = 'Optional können Sie eines Ihrer eigenen Kontakthandles als Admin-, Tech- und/oder Billing-Kontakt für jede neue Domain verwenden. Leer lassen, um den Domaininhaber zu verwenden. Diese Handles werden über Blesta niemals geändert.';
$lang['DomainResellerApi.row_meta.tooltip.label'] = 'Ein Name, um dieses Konto in Blesta zu erkennen.';
$lang['DomainResellerApi.row_meta.tooltip.api_key'] = 'Ihr API-Schlüssel (drapi_...) aus dem Domain Reseller API Dashboard. Eingeschränkte Schlüssel benötigen die Berechtigungen domains, contacts, dns und pricing.';
$lang['DomainResellerApi.row_meta.tooltip.api_url'] = 'Die Basis-URL der API. Nur nach Aufforderung ändern.';
$lang['DomainResellerApi.row_meta.tooltip.sandbox'] = 'Im Sandbox-Modus werden keine Domains registriert und keine Kosten berechnet.';
$lang['DomainResellerApi.row_meta.tooltip.allow_premium'] = 'Premium-Domains werden zum Premium-Preis berechnet. Ist die Option deaktiviert, werden Premium-Domains als nicht verfügbar gemeldet.';
$lang['DomainResellerApi.row_meta.tooltip.cancel_action'] = 'Was mit der Domain geschieht, wenn der Service in Blesta gekündigt wird. Eine geplante Löschung kann bis zum Ablauf der Domain rückgängig gemacht werden; andernfalls wird die Domain einfach nicht mehr verlängert.';
$lang['DomainResellerApi.row_meta.tooltip.fallback_phone'] = 'Wird für Domainkontakte verwendet, wenn beim Kunden keine gültige Telefonnummer hinterlegt ist, im Format +49.301234567.';


// Errors
$lang['DomainResellerApi.!error.label.empty'] = 'Bitte geben Sie eine Bezeichnung ein.';
$lang['DomainResellerApi.!error.api_key.format'] = 'Bitte geben Sie einen gültigen API-Schlüssel ein (drapi_xxxxxxxxxxxx_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx).';
$lang['DomainResellerApi.!error.api_key.valid_connection'] = 'Mit dem angegebenen API-Schlüssel konnte keine Verbindung zur Domain Reseller API hergestellt werden.';
$lang['DomainResellerApi.!error.api_url.valid'] = 'Die API-URL muss eine gültige HTTPS-URL sein.';
$lang['DomainResellerApi.!error.sandbox.format'] = 'Sandbox muss entweder "true" oder "false" sein.';
$lang['DomainResellerApi.!error.allow_premium.format'] = 'Premium-Domains erlauben muss entweder "true" oder "false" sein.';
$lang['DomainResellerApi.!error.fallback_phone.valid'] = 'Bitte geben Sie die Ersatz-Telefonnummer im internationalen Format ein, z. B. +49.301234567.';
$lang['DomainResellerApi.!error.handle.exists'] = 'Das %1$s-Kontakthandle existiert in diesem Konto nicht.';
$lang['DomainResellerApi.!error.module_row.missing'] = 'Ein interner Fehler ist aufgetreten. Das Konto ist nicht verfügbar.';
$lang['DomainResellerApi.!error.api.response'] = 'Domain Reseller API: %1$s';
$lang['DomainResellerApi.!error.domain.valid'] = 'Bitte geben Sie einen gültigen Domainnamen ein.';
$lang['DomainResellerApi.!error.domain.premium'] = 'Die Domain %1$s ist eine Premium-Domain und kann nicht bestellt werden.';
$lang['DomainResellerApi.!error.domain.unavailable'] = 'Die Domain %1$s ist nicht zur Registrierung verfügbar.';
$lang['DomainResellerApi.!error.domain.pending'] = 'Die Domain wird noch registriert oder transferiert und kann noch nicht geändert werden.';
$lang['DomainResellerApi.!error.auth.empty'] = 'Bitte geben Sie den Auth-Code (EPP-Code) für den Transfer ein.';
$lang['DomainResellerApi.!error.ns.valid'] = 'Nameserver %1$s ist kein gültiger Hostname.';
$lang['DomainResellerApi.!error.nameservers.count'] = 'Bitte geben Sie mindestens zwei Nameserver ein.';
$lang['DomainResellerApi.!error.nameservers.invalid'] = '%1$s ist kein gültiger Nameserver-Hostname.';
$lang['DomainResellerApi.!error.contacts.missing'] = 'Das Kontakthandle des Inhabers fehlt.';
$lang['DomainResellerApi.!error.contacts.unknown_handle'] = 'Das Kontakthandle %1$s ist dieser Domain nicht zugeordnet.';
$lang['DomainResellerApi.!error.contacts.protected_handle'] = 'Das Kontakthandle %1$s wird in den Moduleinstellungen verwaltet und kann hier nicht geändert werden.';
$lang['DomainResellerApi.!error.contacts.invalid_country'] = 'Kontakt %1$s: Bitte geben Sie einen gültigen zweistelligen Ländercode ein.';
$lang['DomainResellerApi.!error.contacts.invalid_email'] = 'Kontakt %1$s: Bitte geben Sie eine gültige E-Mail-Adresse ein (nur Buchstaben, Ziffern, ".", "-" und "_").';
$lang['DomainResellerApi.!error.contacts.invalid_phone'] = 'Kontakt %1$s: Bitte geben Sie die Telefonnummer im internationalen Format ein, z. B. +49.301234567.';
$lang['DomainResellerApi.!error.client.missing'] = 'Der Kunde konnte nicht gefunden werden.';
$lang['DomainResellerApi.!error.client.invalid_country'] = 'Beim Kunden ist kein gültiges Land hinterlegt.';
$lang['DomainResellerApi.!error.client.invalid_email'] = 'Die E-Mail-Adresse des Kunden wird von der Registry nicht akzeptiert (nur Buchstaben, Ziffern, ".", "-" und "_").';
$lang['DomainResellerApi.!error.client.invalid_phone'] = 'Beim Kunden ist keine gültige Telefonnummer hinterlegt. Bitte ergänzen Sie eine Telefonnummer beim Kunden oder hinterlegen Sie eine Ersatz-Telefonnummer in den Moduleinstellungen.';
$lang['DomainResellerApi.!error.renew.redemption'] = 'Die Domain %1$s befindet sich in der Redemption-Phase. Bitte stellen Sie sie im Tab "Aktionen" wieder her (es fällt eine Wiederherstellungsgebühr an).';
$lang['DomainResellerApi.!error.renew.state'] = 'Die Domain %1$s kann im aktuellen Status (%2$s) nicht verlängert werden.';
$lang['DomainResellerApi.!error.handles.unchanged'] = 'Bitte wählen Sie mindestens ein Kontakthandle zum Zuweisen aus.';
$lang['DomainResellerApi.!error.owner_change.confirm'] = 'Dies ist ein Inhaberwechsel. Preis: %1$s %2$s (Verfahren: %3$s). Aktivieren Sie "Inhaberwechsel bestätigen" und senden Sie das Formular erneut ab.';
$lang['DomainResellerApi.!error.confirm_domain.mismatch'] = 'Bitte geben Sie den Domainnamen ein, um die sofortige Löschung zu bestätigen.';
$lang['DomainResellerApi.!error.action.unavailable'] = 'Diese Aktion ist im aktuellen Status der Domain nicht verfügbar.';
$lang['DomainResellerApi.!error.expires.missing'] = 'Die Domain hat noch kein Ablaufdatum.';
$lang['DomainResellerApi.!error.zone_file.empty'] = 'Bitte geben Sie eine Zonendatei ein.';
$lang['DomainResellerApi.!error.dnskeys.empty'] = 'Bitte geben Sie mindestens einen DNSKEY-Eintrag ein, um DNSSEC für eigene Nameserver zu aktivieren.';
$lang['DomainResellerApi.!error.dns.type_invalid'] = 'Bitte wählen Sie einen gültigen Eintragstyp.';
$lang['DomainResellerApi.!error.dns.name_invalid'] = 'Bitte geben Sie einen gültigen Namen ein (z. B. @, www oder *.beispiel).';
$lang['DomainResellerApi.!error.dns.name_apex_ns'] = 'NS-Einträge an der Zonenspitze werden automatisch verwaltet.';
$lang['DomainResellerApi.!error.dns.content_empty'] = 'Bitte geben Sie den Inhalt des Eintrags ein.';
$lang['DomainResellerApi.!error.dns.ttl_invalid'] = 'Die TTL muss zwischen 60 und 86400 Sekunden liegen.';
$lang['DomainResellerApi.!error.dns.priority_invalid'] = 'Die Priorität muss eine Zahl zwischen 0 und 65535 sein.';
$lang['DomainResellerApi.!error.dns.priority_empty'] = 'Bitte geben Sie für MX- und SRV-Einträge eine Priorität ein.';


// Notices
$lang['DomainResellerApi.!notice.pending'] = 'Die Domain wird noch registriert oder transferiert. Die Verwaltungsfunktionen stehen nach Abschluss des Vorgangs zur Verfügung.';


// Success messages
$lang['DomainResellerApi.!success.contacts_updated'] = 'Die Kontakte wurden aktualisiert.';
$lang['DomainResellerApi.!success.handles_assigned'] = 'Die Kontakthandles wurden zugewiesen (berechnet: %1$s EUR).';
$lang['DomainResellerApi.!success.nameservers_updated'] = 'Die Nameserver wurden aktualisiert.';
$lang['DomainResellerApi.!success.managed_dns_enabled'] = 'Managed DNS ist für diese Domain aktiviert.';
$lang['DomainResellerApi.!success.dns_record_added'] = 'Der DNS-Eintrag wurde hinzugefügt.';
$lang['DomainResellerApi.!success.dns_record_updated'] = 'Der DNS-Eintrag wurde aktualisiert.';
$lang['DomainResellerApi.!success.dns_record_deleted'] = 'Der DNS-Eintrag wurde gelöscht.';
$lang['DomainResellerApi.!success.zone_imported'] = 'Die Zonendatei wurde importiert.';
$lang['DomainResellerApi.!success.dnssec_updated'] = 'Die DNSSEC-Einstellungen wurden aktualisiert.';
$lang['DomainResellerApi.!success.auto_renew_updated'] = 'Die automatische Verlängerung wurde aktualisiert.';
$lang['DomainResellerApi.!success.auth_code_reset'] = 'Der Auth-Code wurde zurückgesetzt. Ein neuer Auth-Code kann jetzt angefordert werden.';
$lang['DomainResellerApi.!success.renew_date_synced'] = 'Das Verlängerungsdatum des Services wurde auf %1$s gesetzt.';
$lang['DomainResellerApi.!success.action_restore'] = 'Die Domain %1$s wurde wiederhergestellt.';
$lang['DomainResellerApi.!success.action_cancel_delete'] = 'Die geplante Löschung von %1$s wurde aufgehoben.';
$lang['DomainResellerApi.!success.action_schedule_delete'] = 'Die Domain %1$s wird zum Ende der Laufzeit gelöscht.';
$lang['DomainResellerApi.!success.action_delete_immediate'] = 'Die Domain %1$s wurde gelöscht.';
$lang['DomainResellerApi.!success.action_transfer_out_approve'] = 'Der ausgehende Transfer von %1$s wurde bestätigt.';
$lang['DomainResellerApi.!success.action_transfer_out_reject'] = 'Der ausgehende Transfer von %1$s wurde abgelehnt.';
$lang['DomainResellerApi.!success.action_hold'] = 'Die Domain %1$s wurde in den Status HOLD versetzt.';
$lang['DomainResellerApi.!success.action_unhold'] = 'Die Domain %1$s wurde aus dem Status HOLD freigegeben.';


// Domain states
$lang['DomainResellerApi.status.pending'] = 'Registrierung läuft';
$lang['DomainResellerApi.status.pending_transfer'] = 'Transfer läuft';
$lang['DomainResellerApi.status.active'] = 'Aktiv';
$lang['DomainResellerApi.status.expired'] = 'Abgelaufen';
$lang['DomainResellerApi.status.redemption'] = 'Redemption-Phase';
$lang['DomainResellerApi.status.pending_delete'] = 'Löschung geplant';
$lang['DomainResellerApi.status.transferred_away'] = 'Wegtransferiert';
$lang['DomainResellerApi.status.suspended'] = 'Im Status HOLD';


// Service fields
$lang['DomainResellerApi.service_fields.domain'] = 'Domain';
$lang['DomainResellerApi.service_fields.transfer'] = 'Domainaktion';
$lang['DomainResellerApi.service_fields.transfer_register'] = 'Registrieren';
$lang['DomainResellerApi.service_fields.transfer_transfer'] = 'Transferieren';
$lang['DomainResellerApi.service_fields.auth'] = 'Auth-Code (EPP-Code)';
$lang['DomainResellerApi.service_fields.ns1'] = 'Nameserver 1';
$lang['DomainResellerApi.service_fields.ns2'] = 'Nameserver 2';
$lang['DomainResellerApi.service_fields.ns3'] = 'Nameserver 3';
$lang['DomainResellerApi.service_fields.ns4'] = 'Nameserver 4';
$lang['DomainResellerApi.service_fields.ns5'] = 'Nameserver 5';


// Package fields
$lang['DomainResellerApi.package_fields.tld_options'] = 'TLDs';
$lang['DomainResellerApi.package_fields.ns1'] = 'Nameserver 1';
$lang['DomainResellerApi.package_fields.ns2'] = 'Nameserver 2';
$lang['DomainResellerApi.package_fields.ns3'] = 'Nameserver 3';
$lang['DomainResellerApi.package_fields.ns4'] = 'Nameserver 4';
$lang['DomainResellerApi.package_fields.ns5'] = 'Nameserver 5';
$lang['DomainResellerApi.package_fields.tooltip.ns'] = 'Standard-Nameserver für neue Registrierungen. Leer lassen, um das Managed DNS der Domain Reseller API (ns1/ns2.domain-reseller-api.de) zu verwenden.';
$lang['DomainResellerApi.package_fields.epp_code'] = 'Auth-Code-Abfrage erlauben';
$lang['DomainResellerApi.package_fields.tooltip.epp_code'] = 'Ob Kunden den Auth-Code (EPP-Code) ihrer Domains im Kundenbereich abrufen dürfen.';
$lang['DomainResellerApi.package_fields.dns_management'] = 'DNS-Verwaltung erlauben';
$lang['DomainResellerApi.package_fields.tooltip.dns_management'] = 'Ob Kunden DNS-Einträge und DNSSEC im Kundenbereich verwalten dürfen (zusätzlich zur konfigurierbaren Option "dns_management").';


// Contacts
$lang['DomainResellerApi.contacts.handle'] = 'Handle %1$s';
$lang['DomainResellerApi.contacts.protected'] = 'Dieser Kontakt wird von Ihrem Anbieter verwaltet und kann hier nicht geändert werden.';
$lang['DomainResellerApi.contacts.role_owner'] = 'Inhaber';
$lang['DomainResellerApi.contacts.role_admin'] = 'Administrativ';
$lang['DomainResellerApi.contacts.role_tech'] = 'Technisch';
$lang['DomainResellerApi.contacts.role_billing'] = 'Rechnung';
$lang['DomainResellerApi.contacts.field_first_name'] = 'Vorname';
$lang['DomainResellerApi.contacts.field_last_name'] = 'Nachname';
$lang['DomainResellerApi.contacts.field_company'] = 'Firma';
$lang['DomainResellerApi.contacts.field_email'] = 'E-Mail-Adresse';
$lang['DomainResellerApi.contacts.field_phone'] = 'Telefonnummer';
$lang['DomainResellerApi.contacts.field_address1'] = 'Straße und Hausnummer';
$lang['DomainResellerApi.contacts.field_address2'] = 'Adresszusatz';
$lang['DomainResellerApi.contacts.field_city'] = 'Ort';
$lang['DomainResellerApi.contacts.field_state'] = 'Bundesland';
$lang['DomainResellerApi.contacts.field_zip'] = 'Postleitzahl';
$lang['DomainResellerApi.contacts.field_country'] = 'Land (ISO-Code)';


// Tab: contacts
$lang['DomainResellerApi.tab_contacts.title'] = 'Kontakte';
$lang['DomainResellerApi.tab_contacts.heading'] = 'Domainkontakte';
$lang['DomainResellerApi.tab_contacts.no_contacts'] = 'Dieser Domain sind keine Kontakte zugeordnet.';
$lang['DomainResellerApi.tab_contacts.field_update'] = 'Kontakte aktualisieren';
$lang['DomainResellerApi.tab_contacts.heading_assign'] = 'Kontakthandles zuweisen';
$lang['DomainResellerApi.tab_contacts.assign_description'] = 'Weisen Sie den Rollen dieser Domain vorhandene Kontakthandles Ihres Kontos zu. Ein geänderter Inhaber ist ein Inhaberwechsel, der kostenpflichtig sein kann (z. B. ein Trade bei .eu); der Preis wird angezeigt, bevor etwas ausgeführt wird.';
$lang['DomainResellerApi.tab_contacts.keep_current'] = '-- Beibehalten --';
$lang['DomainResellerApi.tab_contacts.confirm_owner_change'] = 'Inhaberwechsel bestätigen (ggf. kostenpflichtig)';
$lang['DomainResellerApi.tab_contacts.field_assign'] = 'Handles zuweisen';


// Tab: nameservers
$lang['DomainResellerApi.tab_nameservers.title'] = 'Nameserver';
$lang['DomainResellerApi.tab_nameservers.heading'] = 'Nameserver';
$lang['DomainResellerApi.tab_nameservers.use_managed_dns'] = 'Managed DNS verwenden';
$lang['DomainResellerApi.tab_nameservers.use_managed_dns_description'] = 'Die Domain verwendet die Nameserver %1$s und ihre DNS-Einträge können im Tab DNS verwaltet werden.';
$lang['DomainResellerApi.tab_nameservers.field_update'] = 'Nameserver aktualisieren';


// Tab: DNS
$lang['DomainResellerApi.tab_dns.title'] = 'DNS-Einträge';
$lang['DomainResellerApi.tab_dns.heading'] = 'DNS-Einträge';
$lang['DomainResellerApi.tab_dns.text_dns_disabled'] = 'Managed DNS ist für diese Domain nicht aktiviert. Beim Aktivieren wird die Domain auf die Managed-Nameserver umgestellt, danach können die DNS-Einträge hier verwaltet werden.';
$lang['DomainResellerApi.tab_dns.text_no_dns_records'] = 'Es sind keine DNS-Einträge vorhanden.';
$lang['DomainResellerApi.tab_dns.heading_type'] = 'Typ';
$lang['DomainResellerApi.tab_dns.heading_name'] = 'Name';
$lang['DomainResellerApi.tab_dns.heading_content'] = 'Inhalt';
$lang['DomainResellerApi.tab_dns.heading_ttl'] = 'TTL';
$lang['DomainResellerApi.tab_dns.heading_priority'] = 'Priorität';
$lang['DomainResellerApi.tab_dns.heading_options'] = 'Optionen';
$lang['DomainResellerApi.tab_dns.heading_add_record'] = 'Eintrag hinzufügen';
$lang['DomainResellerApi.tab_dns.heading_edit_record'] = 'Eintrag bearbeiten';
$lang['DomainResellerApi.tab_dns.heading_zone'] = 'Zonendatei';
$lang['DomainResellerApi.tab_dns.managed_record'] = 'Verwaltet';
$lang['DomainResellerApi.tab_dns.field_type'] = 'Typ';
$lang['DomainResellerApi.tab_dns.field_name'] = 'Name';
$lang['DomainResellerApi.tab_dns.field_content'] = 'Inhalt';
$lang['DomainResellerApi.tab_dns.field_ttl'] = 'TTL (Sekunden)';
$lang['DomainResellerApi.tab_dns.field_priority'] = 'Priorität';
$lang['DomainResellerApi.tab_dns.field_zone_file'] = 'Zonendatei importieren (BIND-Format)';
$lang['DomainResellerApi.tab_dns.field_enable'] = 'Managed DNS aktivieren';
$lang['DomainResellerApi.tab_dns.field_add'] = 'Eintrag hinzufügen';
$lang['DomainResellerApi.tab_dns.field_update'] = 'Eintrag aktualisieren';
$lang['DomainResellerApi.tab_dns.field_edit'] = 'Bearbeiten';
$lang['DomainResellerApi.tab_dns.field_delete'] = 'Löschen';
$lang['DomainResellerApi.tab_dns.field_cancel'] = 'Abbrechen';
$lang['DomainResellerApi.tab_dns.field_export'] = 'Zonendatei anzeigen';
$lang['DomainResellerApi.tab_dns.field_import'] = 'Importieren';
$lang['DomainResellerApi.tab_dns.text_zone_export'] = 'Aktuelle Zone im BIND-Format:';
$lang['DomainResellerApi.tab_dns.tooltip_name'] = 'Relativ zur Domain: @ für die Domain selbst, www für www.example.com oder * für einen Wildcard-Eintrag.';
$lang['DomainResellerApi.tab_dns.tooltip_content'] = 'SRV: "Gewichtung Port Ziel" (die Priorität wird separat eingegeben). CAA: "Flags Tag Wert", z. B. 0 issue "letsencrypt.org".';
$lang['DomainResellerApi.tab_dns.tooltip_priority'] = 'Erforderlich für MX- und SRV-Einträge.';
$lang['DomainResellerApi.tab_dns.tooltip_zone_file'] = 'Die Einträge der Zonendatei werden zur bestehenden Zone hinzugefügt.';


// Tab: DNSSEC
$lang['DomainResellerApi.tab_dnssec.title'] = 'DNSSEC';
$lang['DomainResellerApi.tab_dnssec.heading'] = 'DNSSEC';
$lang['DomainResellerApi.tab_dnssec.heading_update'] = 'DNSSEC aktualisieren';
$lang['DomainResellerApi.tab_dnssec.text_managed'] = 'Die Domain verwendet Managed DNS: Die Zone wird automatisch signiert und die Schlüssel werden bei der Registry hinterlegt.';
$lang['DomainResellerApi.tab_dnssec.text_custom'] = 'Die Domain verwendet eigene Nameserver: Geben Sie die DNSKEY-Einträge Ihrer signierten Zone ein, um sie bei der Registry zu hinterlegen.';
$lang['DomainResellerApi.tab_dnssec.status'] = 'Status';
$lang['DomainResellerApi.tab_dnssec.status_enabled'] = 'Aktiviert';
$lang['DomainResellerApi.tab_dnssec.status_disabled'] = 'Deaktiviert';
$lang['DomainResellerApi.tab_dnssec.heading_keytype'] = 'Schlüssel';
$lang['DomainResellerApi.tab_dnssec.heading_algorithm'] = 'Algorithmus';
$lang['DomainResellerApi.tab_dnssec.heading_ds'] = 'DS-Einträge';
$lang['DomainResellerApi.tab_dnssec.field_enabled'] = 'DNSSEC aktivieren';
$lang['DomainResellerApi.tab_dnssec.field_dnskeys'] = 'DNSKEY-Einträge';
$lang['DomainResellerApi.tab_dnssec.tooltip_dnskeys'] = 'Ein DNSKEY-Eintrag pro Zeile, z. B. "example.com. IN DNSKEY 257 3 13 mdsswUyr3DPW..."';
$lang['DomainResellerApi.tab_dnssec.field_update'] = 'DNSSEC aktualisieren';


// Tab: settings
$lang['DomainResellerApi.tab_settings.title'] = 'Einstellungen';
$lang['DomainResellerApi.tab_settings.heading'] = 'Domainübersicht';
$lang['DomainResellerApi.tab_settings.domain'] = 'Domain';
$lang['DomainResellerApi.tab_settings.status'] = 'Status';
$lang['DomainResellerApi.tab_settings.registered'] = 'Registriert';
$lang['DomainResellerApi.tab_settings.expires'] = 'Läuft ab';
$lang['DomainResellerApi.tab_settings.auto_renew'] = 'Automatische Verlängerung';
$lang['DomainResellerApi.tab_settings.managed_dns'] = 'Managed DNS';
$lang['DomainResellerApi.tab_settings.dnssec'] = 'DNSSEC';
$lang['DomainResellerApi.tab_settings.nameservers'] = 'Nameserver';
$lang['DomainResellerApi.tab_settings.yes'] = 'Ja';
$lang['DomainResellerApi.tab_settings.no'] = 'Nein';
$lang['DomainResellerApi.tab_settings.heading_auto_renew'] = 'Automatische Verlängerung';
$lang['DomainResellerApi.tab_settings.auto_renew_description'] = 'Verlängerungen löst Blesta aus, sobald der Kunde zahlt. Daher hält das Modul die automatische Verlängerung der API deaktiviert, um doppelte Verlängerungen zu vermeiden. Nur aktivieren, wenn die API diese Domain unabhängig von Blesta verlängern soll.';
$lang['DomainResellerApi.tab_settings.auto_renew_on'] = 'Aktiviert';
$lang['DomainResellerApi.tab_settings.auto_renew_off'] = 'Deaktiviert';
$lang['DomainResellerApi.tab_settings.field_update'] = 'Aktualisieren';
$lang['DomainResellerApi.tab_settings.heading_auth_code'] = 'Auth-Code (EPP-Code)';
$lang['DomainResellerApi.tab_settings.auth_code'] = 'Auth-Code';
$lang['DomainResellerApi.tab_settings.auth_code_unsupported'] = 'Der Auth-Code kann über die API nur für .de- und .eu-Domains abgerufen werden. Für andere TLDs fordern Sie ihn bitte im Domain Reseller API Dashboard an.';
$lang['DomainResellerApi.tab_settings.auth_code_unsupported_client'] = 'Der Auth-Code für diese Domain kann nicht automatisch abgerufen werden. Bitte wenden Sie sich an den Support.';
$lang['DomainResellerApi.tab_settings.field_get_auth_code'] = 'Auth-Code anzeigen';
$lang['DomainResellerApi.tab_settings.field_reset_auth_code'] = 'Auth-Code zurücksetzen';


// Tab: actions
$lang['DomainResellerApi.tab_actions.title'] = 'Aktionen';
$lang['DomainResellerApi.tab_actions.heading'] = 'Domainaktionen';
$lang['DomainResellerApi.tab_actions.restore_description'] = 'Die Domain befindet sich in der Redemption-Phase und kann wiederhergestellt werden. Die Wiederherstellungsgebühr wird Ihrem Wallet belastet.';
$lang['DomainResellerApi.tab_actions.field_restore'] = 'Domain wiederherstellen';
$lang['DomainResellerApi.tab_actions.cancel_delete_description'] = 'Die Domain ist zur Löschung zum Ende der Laufzeit vorgemerkt. Wird die Löschung aufgehoben, bleibt die Domain bestehen und die automatische Verlängerung wird aktiviert.';
$lang['DomainResellerApi.tab_actions.field_cancel_delete'] = 'Löschung aufheben';
$lang['DomainResellerApi.tab_actions.schedule_delete_description'] = 'Die Domain zum Ende der aktuellen Laufzeit löschen. Dies kann bis zum Ablauf der Domain rückgängig gemacht werden.';
$lang['DomainResellerApi.tab_actions.field_schedule_delete'] = 'Zum Laufzeitende löschen';
$lang['DomainResellerApi.tab_actions.delete_immediate_description'] = 'Die Domain sofort bei der Registry löschen. Dies kann nicht rückgängig gemacht werden und es erfolgt keine Erstattung.';
$lang['DomainResellerApi.tab_actions.field_confirm_domain'] = 'Zur Bestätigung %1$s eingeben';
$lang['DomainResellerApi.tab_actions.field_delete_immediate'] = 'Sofort löschen';
$lang['DomainResellerApi.tab_actions.heading_transfer_out'] = 'Ausgehender Transfer';
$lang['DomainResellerApi.tab_actions.transfer_out_pending'] = 'Am %1$s wurde ein ausgehender Transfer angefordert.';
$lang['DomainResellerApi.tab_actions.transfer_out_none'] = 'Für diese Domain liegt kein ausgehender Transfer vor. Bestätigen oder Ablehnen wirkt sich nur aus, solange ein Transfer aussteht.';
$lang['DomainResellerApi.tab_actions.field_transfer_out_approve'] = 'Transfer bestätigen';
$lang['DomainResellerApi.tab_actions.field_transfer_out_reject'] = 'Transfer ablehnen';
$lang['DomainResellerApi.tab_actions.heading_hold'] = 'HOLD (.de)';
$lang['DomainResellerApi.tab_actions.hold_description'] = 'Eine .de-Domain im Status HOLD wird bei der DENIC vom DNS getrennt (z. B. bei Missbrauch), bis sie wieder freigegeben wird.';
$lang['DomainResellerApi.tab_actions.field_hold'] = 'In HOLD versetzen';
$lang['DomainResellerApi.tab_actions.field_unhold'] = 'Aus HOLD freigeben';
$lang['DomainResellerApi.tab_actions.heading_sync'] = 'Synchronisation';
$lang['DomainResellerApi.tab_actions.sync_description'] = 'Das Verlängerungsdatum dieses Services auf das von der Registry gemeldete Ablaufdatum setzen.';
$lang['DomainResellerApi.tab_actions.field_sync_renew_date'] = 'Verlängerungsdatum synchronisieren';
$lang['DomainResellerApi.tab_actions.no_actions'] = 'Im aktuellen Status der Domain sind keine Lebenszyklus-Aktionen verfügbar.';


// Glue records (hosts) and transfer lock
$lang['DomainResellerApi.tab_hosts.title'] = 'Hosts';
$lang['DomainResellerApi.tab_hosts.heading'] = 'Glue-Records';
$lang['DomainResellerApi.tab_hosts.description'] = 'Glue-Records hinterlegen Nameserver unterhalb von %1$s (z. B. ns1.%1$s) mit ihren IP-Adressen bei der Registry.';
$lang['DomainResellerApi.tab_hosts.heading_hostname'] = 'Host';
$lang['DomainResellerApi.tab_hosts.heading_ipv4'] = 'IPv4';
$lang['DomainResellerApi.tab_hosts.heading_ipv6'] = 'IPv6';
$lang['DomainResellerApi.tab_hosts.heading_options'] = 'Optionen';
$lang['DomainResellerApi.tab_hosts.no_hosts'] = 'Es sind keine Glue-Records vorhanden.';
$lang['DomainResellerApi.tab_hosts.heading_add'] = 'Host hinzufügen oder ändern';
$lang['DomainResellerApi.tab_hosts.add_description'] = 'Um die Adressen eines bestehenden Hosts zu ändern, geben Sie seinen Namen erneut mit den neuen Adressen ein.';
$lang['DomainResellerApi.tab_hosts.field_hostname'] = 'Hostname';
$lang['DomainResellerApi.tab_hosts.field_ipv4'] = 'IPv4-Adresse';
$lang['DomainResellerApi.tab_hosts.field_ipv6'] = 'IPv6-Adresse';
$lang['DomainResellerApi.tab_hosts.field_add'] = 'Host speichern';
$lang['DomainResellerApi.tab_hosts.field_delete_short'] = 'Löschen';
$lang['DomainResellerApi.!success.host_create'] = 'Der Host %1$s wurde angelegt.';
$lang['DomainResellerApi.!success.host_update'] = 'Der Host %1$s wurde aktualisiert.';
$lang['DomainResellerApi.!success.host_delete'] = 'Der Host %1$s wurde gelöscht.';
$lang['DomainResellerApi.!success.transfer_lock_updated'] = 'Die Transfersperre wurde aktualisiert.';
$lang['DomainResellerApi.!error.host.hostname'] = 'Der Hostname muss ein gültiger Host unterhalb von %1$s sein.';
$lang['DomainResellerApi.!error.host.ip_empty'] = 'Bitte geben Sie mindestens eine IP-Adresse ein.';
$lang['DomainResellerApi.!error.host.ipv4'] = 'Bitte geben Sie eine gültige IPv4-Adresse ein.';
$lang['DomainResellerApi.!error.host.ipv6'] = 'Bitte geben Sie eine gültige IPv6-Adresse ein.';
$lang['DomainResellerApi.!error.host.parent'] = 'Der Host %1$s gehört zu keiner Domain in diesem Konto oder hat keine gültige IP-Adresse.';
$lang['DomainResellerApi.tab_settings.heading_transfer_lock'] = 'Transfersperre';
$lang['DomainResellerApi.tab_settings.transfer_lock_description'] = 'Eine gesperrte Domain kann nicht zu einem anderen Registrar transferiert werden.';
$lang['DomainResellerApi.tab_settings.transfer_lock_on'] = 'Gesperrt';
$lang['DomainResellerApi.tab_settings.transfer_lock_off'] = 'Entsperrt';
