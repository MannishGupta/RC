<?php
// data/apple_wallet_config.php — Version: 260916.14
//
// Apple Wallet (.pkpass) reference fields. Stored here for record-keeping and
// so the Settings tab has somewhere to show your Pass Type ID once you have
// one — this file does NOT enable pass generation. See the note below for
// why, and see app/GoogleWallet.php for the wallet integration that IS fully
// built: Google Wallet needs no paid account and is genuinely working code,
// not a placeholder.
//
// ── WHY APPLE WALLET STOPS HERE ──────────────────────────────────────────
// A .pkpass is a signed ZIP archive. Apple's Wallet app refuses to open one
// unless it is signed with a Pass Type ID certificate that ONLY Apple issues,
// which requires an Apple Developer Program membership — $99/year, no
// workaround, no free tier, no way to generate a valid signature without it.
//
// If you obtain that membership and certificate, generating passes is
// well-documented and mechanical (a JSON manifest, SHA-1 file hashes, a
// PKCS#7 detached signature via openssl_pkcs7_sign, zipped together). It was
// deliberately not built speculatively here: shipping cryptographic signing
// code with no real certificate to test it against risks a subtly broken
// pass that LOOKS right in code review and fails silently on a real iPhone —
// worse than not having the feature. Once you have the certificate, this is
// a good next step to build and can be verified properly.

if (!defined('BASE_PATH')) exit('No direct script access');

return [
    'pass_type_id' => '',   // e.g. pass.com.digisofts.card  (once registered with Apple)
    'team_id'      => '',   // your 10-character Apple Developer Team ID
    'note'         => 'Pass generation requires an Apple Developer certificate. See file header.',
];
