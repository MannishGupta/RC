<?php
// data/whatsapp_config.php — Version: 260916.14
//
// WhatsApp Business Platform (Cloud API), for AUTOMATED, server-sent
// messages — e.g. a compliance alert sent without any human tapping a
// wa.me link. DISABLED by default. Every wa.me / Click-to-Chat link
// elsewhere in this app (vehicle tags, business cards) is unaffected either
// way — those need no API and no account, and keep working exactly as
// before regardless of this setting.
//
// ── WHY THIS IS A SEPARATE, HARDER STEP ─────────────────────────────────
// Meta requires ALL business-initiated messages (i.e. anything your server
// sends first, not a reply to an incoming message) to use a pre-approved
// MESSAGE TEMPLATE — free-form text is rejected outside a 24-hour customer
// service window. So enabling this is two steps, not one:
//   1. Get API access: business.facebook.com -> WhatsApp -> API Setup.
//      Needs a Meta Business verification (can take days), a phone number
//      dedicated to the API (cannot also be used in the regular WhatsApp
//      app), and a permanent access token.
//   2. Get a template approved for whatever message you intend to send
//      (e.g. "Your {{1}} expires on {{2}}") via the same dashboard —
//      typically approved within hours, sometimes longer.
//
// This file only stores the credentials for step 1. Template names/ids are
// passed per-call from whichever feature uses this (e.g. a future
// compliance-alert job), not configured here.

if (!defined('BASE_PATH')) exit('No direct script access');

return [
    'enabled'             => false,
    'phone_number_id'     => '',   // from WhatsApp > API Setup
    'business_account_id' => '',
    'access_token'        => '',   // permanent token (System User), not the 24h test token
    'api_version'         => 'v21.0',
];
