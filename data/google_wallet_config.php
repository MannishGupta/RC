<?php
// data/google_wallet_config.php — Version: 260916.14
//
// "Add to Google Wallet" configuration. DISABLED by default — the app is
// fully functional without it.
//
// ── SETUP ─────────────────────────────────────────────────────────────────
//  1. console.cloud.google.com -> APIs & Services -> Library ->
//     enable "Google Wallet API".
//  2. pay.google.com/business/console -> register as an Issuer (free).
//     Note the Issuer ID shown there.
//  3. IAM & Admin -> Service Accounts -> create one -> Keys -> Add Key ->
//     JSON. Download it and place it in this data/ folder (already
//     protected: web.config hides the whole data/ segment and denies .json
//     directly — this key never needs its own extra protection).
//  4. Create ONE Generic Class for this app, either via the Google Wallet
//     API or the Business Console — every person's pass is an Object under
//     that shared Class, not a Class of its own. Note the Class ID (just the
//     suffix, e.g. "digital_business_card" — the Issuer ID prefix is added
//     automatically in app/GoogleWallet.php).
//  5. In the Issuer console, share access with the service account's email
//     (from the JSON key's client_email field) as a Wallet Object issuer.
//
// This is unlike Apple Wallet: no $99/year account, no signed ZIP packaging
// to get wrong. The pass is a signed link (JWT) built at request time.
//
// This file returns a plain array and never echoes anything, so even a
// server misconfiguration serving it as plain text would print nothing.

if (!defined('BASE_PATH')) exit('No direct script access');

return [
    'enabled'               => false,
    'issuer_id'             => '',                          // e.g. '3388000000012345678'
    'class_id'              => 'digital_business_card',      // suffix only
    'service_account_json'  => 'google-wallet-sa.json',      // filename within data/
];
