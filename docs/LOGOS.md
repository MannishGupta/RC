# Brand Logos — Drop-in Guide

Logos resolve by filename = the record's `slug`. Wrong slug → silent
fallback to a low-res fetched favicon, so match these exactly.

SVG preferred (crisp at any size; trusted static files, so safe). Else
~512px transparent PNG/WebP.

## Banks — assets/logos/banks/{slug}.svg

Present: au, axis, bandhan, bob, boi, bom, canara, central, cub, federal, hdfc, icici

To add:

- sbi (State Bank of India), pnb (Punjab National Bank), kotak (Kotak Mahindra),
- union (Union Bank of India), indian (Indian Bank), indusind (IndusInd Bank),
- idbi (IDBI Bank), iob (Indian Overseas Bank), uco (UCO Bank),
- psb (Punjab & Sind Bank), yes (Yes Bank), idfc (IDFC First Bank), rbl (RBL Bank)

## Vehicle OEMs — assets/logos/oems/{slug}.svg  (canonical path)

- maruti-suzuki, tata, mahindra, hyundai, toyota, honda, suzuki,
- mercedes-benz, bmw, audi, kia, mg, nissan, renault, skoda,
- volkswagen, volvo, jaguar, land-rover, byd

## Adding a brand not listed

Use the exact `slug` from that bank/OEM record in the app's master list;
name the file after it. Files appear instantly (cache-busted by mtime).
