<?php
// data/registry_config.php — Version: 260916.14
//
// Vehicle registry (RC) lookup configuration. DISABLED by default: the app
// works fully without it, using the free offline decoder for state/RTO. This
// file only adds the paid compliance fields — PUC, insurance, fitness, RC
// status.
//
// Lives in data/ alongside auth_config.php because it holds a secret. That
// directory is already protected by web.config (hidden segment + .json
// denied), and this file returns an array without echoing anything, so even a
// misconfigured server that served it as plain text would print nothing.
//
// ── WHY NOT SCRAPE PARIVAHAN ─────────────────────────────────────────────
// Direct scraping of parivahan.gov.in is not viable. NIC enforces session
// tokens, OTP logins and rotating CAPTCHAs, and firewalls subnets that query
// at volume. Routing CAPTCHAs through a solver adds 10–20s per request and
// breaks without warning. Use one of the two supported routes below.
//
// ── OPTION 1: COMMERCIAL GATEWAY (works today) ───────────────────────────
// Surepass, Attestr, Signzy, Cashfree, Sandbox and others resell authorised
// MoRTH/VAHAN feeds over plain REST. ₹0.50–₹2.00 per call.
//
//   'enabled'       => true,
//   'provider'      => 'commercial',
//   'endpoint'      => 'https://api.your-provider.com/v1/vehicle/rc-verify',
//   'api_key'       => 'YOUR_PRODUCTION_KEY',
//   'request_field' => 'vehicle_number',   // some use 'rc_number' / 'reg_no'
//
// ── OPTION 2: API SETU (free, but gated) ─────────────────────────────────
// apisetu.gov.in — MoRTH "Registration of Vehicles". Zero per-call cost, but
// access is granted only to registered entities, startups, educational
// institutions and government portals. Apply first; approval is not instant.
//
//   'enabled'   => true,
//   'provider'  => 'apisetu',
//   'token_url' => 'https://apisetu.gov.in/oauth/token',
//   'endpoint'  => 'https://apisetu.gov.in/certificate/v3/transport/rvcr',
//   'client_id' => 'YOUR_CLIENT_ID',
//   'api_key'   => 'YOUR_CLIENT_SECRET',
//
// ── COST CONTROL ─────────────────────────────────────────────────────────
// Lookups run ONLY on explicit admin action or a scheduled refresh, and are
// cached 30 days. The public scan page never triggers one — a paid call
// reachable from an anonymous QR scan is a cost-amplification vector.
//
// ── PRIVACY ──────────────────────────────────────────────────────────────
// Only whitelisted compliance fields are stored (see KEEP in
// app/VehicleRegistry.php). The raw provider response is never persisted: it
// can carry owner name, father's name and address, which this system has no
// purpose for and which would create a DPDP Act liability. Chassis and engine
// numbers are masked to their last four characters.
//
// Note also that Parivahan stopped exposing owner names publicly in 2019.
// A provider still returning them is generally serving pre-restriction cached
// data — treat that as a signal about the provider.

if (!defined('BASE_PATH')) exit('No direct script access');

return [
    'enabled'       => false,
    'provider'      => 'commercial',
    'endpoint'      => '',
    'api_key'       => '',
    'client_id'     => '',
    'token_url'     => '',
    'request_field' => 'vehicle_number',
];
