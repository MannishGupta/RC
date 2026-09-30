# TERMINOLOGY

Naming standard for this codebase. The rule is simple:

> **Use the word the source document uses.**

If a value appears on an RC book, a PAN card, a GST certificate or a payslip,
the field name and the on-screen label should match that document. Someone
cross-checking our screen against the original paper should never have to
translate.

---

## 1. Vehicle (MoRTH / VAHAN / Parivahan)

Authority: Central Motor Vehicles Rules, and the field names VAHAN itself
returns.

| Use this | Not this | Notes |
|---|---|---|
| **Registration Number** | Plate, Plate No., Number Plate | "Plate" is colloquial; the RC book says Registration Number |
| **Registering Authority** | RTO Code, RTO | The full official term; `UP16` is the authority code |
| **Registering State** | State | |
| **Vehicle Class** | Vehicle Type, Category | VAHAN field is `vh_class_desc` |
| **Maker** / **Model** | Brand, Make | VAHAN uses `maker_desc` / `maker_model` |
| **Fuel Type** | Fuel | |
| **Chassis Number** / **Engine Number** | VIN, Frame No. | VIN is the US/ISO term; Indian RC says Chassis Number |
| **Registration Date** | Purchase date, Reg. date | |
| **Fitness Upto** | Fitness expiry | VAHAN phrasing is literally "Upto" |
| **Insurance Upto** / **PUCC Upto** | Insurance expiry, Pollution cert. | Note **PUCC**, two Cs — Pollution Under Control Certificate |
| **Hypothecation** / **Financier** | Loan, Financed by | "Hypothecation" is the term on the RC |
| **Owner Serial Number** | Owner count | e.g. "2nd owner" |
| **Bharat Series (BH)** | BH plate | |

### Vehicle class values
Use the VAHAN class description with its RC abbreviation in brackets, so the
stored value reads identically to the certificate:

`Motor Car (LMV)` · `Motorcycle (MCWG)` · `Scooter (MCWOG)` ·
`Goods Vehicle (LGV/HGV)` · `Passenger Vehicle (LPV/HPV)` ·
`Three Wheeler` · `Tractor` · `Other`

---

## 2. People & organisation (Indian HR / statutory convention)

| Use this | Not this | Notes |
|---|---|---|
| **Designation** | Job Title, Role, Position | Standard on Indian appointment letters |
| **Department** | Team, Division, Dept. | |
| **Employee Code** | Employee ID, Staff No. | |
| **Mobile Number** | Cell, Cell Phone | "Mobile" is the Indian usage |
| **Email ID** | Email Address | Common Indian phrasing on forms |
| **Date of Birth** | DOB, Birthday | Spell out in labels; `dob` is fine as a key |
| **Blood Group** | Blood Type | "Group" is the Indian/UK convention |
| **Base Location** | Site, Office, Branch | Already used consistently |
| **Registered Office** | HO, Head Office | Companies Act term |

---

## 3. Statutory identifiers

These are acronyms with exact official capitalisation. Never expand them in a
label, and never lower-case them.

| Correct | Expansion (for reference only) |
|---|---|
| **PAN** | Permanent Account Number |
| **TAN** | Tax Deduction & Collection Account Number |
| **GSTIN** | Goods & Services Tax Identification Number — *not* "GST No." |
| **CIN** | Corporate Identity Number |
| **RERA** | Real Estate (Regulation and Development) Act registration |
| **MSME / Udyam** | Udyam is the current registration; MSME is the category |
| **ESIC** | Employees' State Insurance Corporation |
| **EPF / PF Code** | Employees' Provident Fund |
| **ISIN** | International Securities Identification Number |
| **IFSC** | Indian Financial System Code — *not* "IFSC Code" (the C is Code) |
| **UPI ID** | Unified Payments Interface |

---

## 4. Naming rules for code

- **Storage keys:** `snake_case`, matching the official term —
  `registration_number`, `vehicle_class`, `blood_group`, `base_location`.
- **Display labels:** Title Case of the official term — "Registration Number".
- **Never abbreviate a stored key** to save characters. `reg_no` costs nothing
  in storage and costs comprehension in every file that touches it.
- **Renaming an existing field:** add the old → new mapping to
  `self::$aliases` in `app/Optimizer.php`. The optimizer copies the value and
  drops the old key on its next run, so live data migrates itself with no
  manual edit. This is how `plate` → `registration_number` and
  `vehicle_type` → `vehicle_class` were done in 260906.14.

---

## 5. Known deviations

Documented rather than silently inconsistent:

| Field | Current | Why not renamed |
|---|---|---|
| `cartags` (namespace) | — | Renaming a namespace renames its JSON file and every reference; the display label already reads "Vehicle Tags". Cosmetic only. |
| `tag_id` | — | This is *our* identifier for a printed sticker, not a government field. "Tag ID" is correct. |
| `docs`, `stat`, `bank` | — | Internal namespace keys; display labels are already Documents / Statutory / Banking. |
| `slug` | — | A web convention, not a business term. Correct as-is. |
