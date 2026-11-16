# Privacy and Cookie Policy - TWD Template Spec

A reference for building DUAA-compliant combined Privacy and Cookie Policies
for UK therapy and counselling clients. Updated for the Data (Use and Access)
Act 2025, in force from 19 June 2026.

Paste the finished HTML into an Elementor HTML widget on the client's privacy
policy page. No shortcodes, no PHP. The whole document (privacy + cookie) goes
in one widget.

---

## Variables to swap per client

| Variable | Example | Notes |
|---|---|---|
| `{THERAPIST_NAME}` | Cheryl Burgess | As they want to appear on the site |
| `{QUALIFICATION}` | DCounsPsych | After the name in the intro |
| `{PROFESSIONAL_BODY}` | BACP | BACP, BACP Accredited, NCPS, BPS, HCPC, COSCA, etc. |
| `{PRACTICE_NAME}` | Achieve Wellbeing Therapy | Trading name, if different from therapist name |
| `{EMAIL}` | cherylburgess@achievewellbeingtherapy.co.uk | Public contact email |
| `{LOCATION}` | Newton Aycliffe, County Durham | Town or city, plus "and online across the UK" if applicable |
| `{ICO_NUMBER}` | C1490384 | If not yet registered: "Our ICO registration details are available on request" - never leave a [placeholder] |
| `{SITE_DOMAIN}` | achievewellbeingtherapy.co.uk | Used in the cookie section |
| `{CSS_PREFIX}` | awb | Two or three letters unique to this site, e.g. awb, pts, an, mc |
| `{LAST_UPDATED}` | 19 June 2026 | ISO or plain English |
| `{GOVERNING_LAW}` | English law | English law / Scots law / Northern Irish law |
| `{RETENTION_THERAPY}` | 7 years | Usually 7 years (BPS/HCPC/BACP guidance); some use 6 years |

---

## Mandatory UK GDPR sections

Every policy needs all of these. Do not omit any.

### 1. Who is the data controller

Name, qualification, professional body registration, ICO registration number.
Contact email and phone (optional).

### 2. What personal data is collected

Split into:
- Contact and identity data (name, email, phone, address)
- Clinical data - special category (health, mental health, session notes, outcome measures)
- Website/technical data (IP, cookies, form submissions)

### 3. Lawful basis for processing

**Ordinary personal data - Article 6:**
- 6(1)(b) Contract: arranging and delivering therapy sessions
- 6(1)(f) Legitimate interests: running the practice safely, insurance records
- 6(1)(c) Legal obligation: where required by law
- 6(1)(a) Consent: mailing list (only where genuinely used)

**Special category clinical data - Article 9:**
- Always cite Article 9(2)(h): provision of health or social care treatment
- Also cite DPA 2018 Schedule 1, Part 1, paragraph 2 (the UK condition for health data)

Both citations are required for clinical data. One without the other is incomplete.

### 4. How data is stored

Storage method (Tresorit, iCloud, paper files, etc.), encryption, access controls.
Mention the website platform and hosting if relevant (20i, Therapy Web Designs).

### 5. How data is shared

Standard list:
- Clinical supervision (first name or unique ID only, anonymised)
- Safeguarding: immediate risk of harm to self or others - may share without consent
- Legal proceedings: court order, coroner, professional body
- Payment processors (if used)
- Mailing list platform (if used, e.g. Mailchimp)
- Insurance/EAP referrals (if applicable)
- Never share for marketing purposes

### 6. Clinical Will (optional but strongly recommended)

A named Clinical Executor (trusted professional colleague) holds minimum contact
details to notify clients if the therapist dies or becomes incapacitated.
Bound by professional confidentiality.

### 7. Retention periods

Minimum to include in a table:

| Record type | Retention period |
|---|---|
| Therapy records | 7 years after last session (or 6 - check professional insurer) |
| Financial records | 6 years (HMRC requirement) |
| Enquiries (non-clients) | 12 months or less |
| Mailing list | Until unsubscribe |

### 8. Subject access and rights

Under UK GDPR, the subject access request must be free of charge and
provided within one month. Never state a fee (e.g. the old £20 charge is
non-compliant). Rights to include:
- Access (free, within one month)
- Rectification
- Erasure (note limits for clinical records)
- Restriction
- Data portability
- Object
- Withdraw consent

### 9. Data protection complaints - DUAA 2025 requirement

This is mandatory from 19 June 2026. The policy must include:
- An internal complaints procedure with a named contact
- **30-day acknowledgement window** (new from DUAA 2025)
- Investigation and response without undue delay
- ICO escalation route: www.ico.org.uk / 0303 123 1113

### 10. Confidentiality exceptions

Must list clearly:
- Court subpoena or legal obligation
- Immediate and serious risk of harm (safeguarding)
- Terrorism (under the Terrorism Act 2000, disclosure is mandatory)
- Clinical supervision (with anonymisation)

### 11. Changes to policy

A line stating the policy is reviewed periodically.

---

## Cookie section

Anchor: always add `<a name="cookies"></a>` immediately before the cookie
section heading. This lets Complianz link directly to it at `#cookies`.

### Cookie categories

- Essential (cannot be switched off)
- Statistics / analytics (require consent)
- Functional (remember preferences)
- Do NOT use advertising or tracking cookies

### Standard cookie table (Complianz stack)

| Cookie | Provider | Purpose | Expiry |
|---|---|---|---|
| `cmplz_*` | Complianz | Stores cookie consent preferences | 1 year |
| `_ga`, `_ga_*` | Google Analytics | Tracks website usage statistics anonymously | 2 years |
| `is_unique` | StatCounter | Distinguishes first-time from returning visitors | Session / persistent |

Add or remove rows to match what is actually on the site. If reCAPTCHA is
active, add: `_GRECAPTCHA` / Google reCAPTCHA / Bot detection / Session.

Complianz will scan and update the table automatically once active.

### Browser history safety note

Always include a short note for clients who may be concerned about anyone
knowing they visited a therapy site: they can delete browser history and
cookies after their visit.

---

## CSS prefix convention

Each site gets a unique two or three letter prefix to avoid class collisions
when multiple HTML widgets appear on the same page.

Pattern: `.{PREFIX}-legal`, `.{PREFIX}-h1`, `.{PREFIX}-h2`, `.{PREFIX}-updated`,
`.{PREFIX}-list`, `.{PREFIX}-ol`, `.{PREFIX}-table`, `.{PREFIX}-divider`

Avoid: `twd-`, `twd-sk-`, `an-` (Alice Nicholls), `pts-` (PTSolutions),
`mom-` (Mind Over Matter), `mc-` (Moor Counselling), `awb-` (Achieve Wellbeing),
`ccl-` (CCL), `kp-` (Kent Psychologist / Kaya Platt), `gp-` (Psychology with Gisele),
`drt-` (DRT Global), `pdp-` (Peak District Psychology), `pp-` (Parental Psychology),
`wp-` (Within Psychology).

---

## ICO registration wording

- **Number known:** "I am registered with the Information Commissioner's Office (ICO)
  as a data controller under registration number C1490384."
- **Number not yet confirmed:** "Our ICO registration details are available on request."
- **Never:** leave `[ICO Reg No]` or any placeholder bracket in the published page.

All UK therapists handling client names and contact details are legally required
to be ICO registered (unless covered by an employer's registration).

---

## Governing law

- England and Wales: "English law"
- Scotland: "Scots law" (note: different from English law; some clauses may need adjustment)
- Northern Ireland: "Northern Irish law"

---

## Reference example

See `docs/legal/privacy-cookie-template.html` for a complete worked example
based on a real client build (details anonymised to match this public repo).

---

## Platforms and hosting (standard for TWD clients)

Most TWD client sites run on:
- WordPress on 20i hosting
- Elementor Pro (or Hello theme + Site Kit)
- Complianz for cookie consent (required for GA/StatCounter)
- Tresorit for clinical record storage (common but not universal)

Include relevant hosting details in the "How I store your data" section.
The website section should mention 20i hosting, SSL/TLS encryption, and
Therapy Web Designs as the site manager where appropriate.
