# Intake form fields

One Elementor Pro form on one page, using the Step field to split it into stages. Because a form like this cannot be saved half way and resumed, keep it short and tell clients at the top that it takes about 10 minutes and that they can leave photos for later.

Put the page out of the menu and the sitemap, and set it to noindex.

**Top of the form, wording:** "About your practice. Please give business and professional details only. Do not write anything about people you work with."

Field IDs below are chosen to match the site brief so the export maps across. Set each one as the field's **ID** in Advanced.

## Step 1: You and your practice

| Field ID | Label | Type | Required |
|---|---|---|---|
| `consent` | I agree to you keeping these details to build my website, and to delete them when you have finished. | Acceptance | Yes |
| `site_name` | Practice name (as it should appear on the site) | Text | Yes |
| `person_name` | Your name as you want it shown | Text | Yes |
| `person_job` | Your job title (for example counsellor, psychotherapist) | Text | Yes |
| `area_served` | Where do you work? Online, in person, or both, and which area | Text | Yes |
| `one_line` | In one sentence, what do you do and for whom? | Textarea | No |

## Step 2: Contact and registration (typed, please check twice)

| Field ID | Label | Type | Required |
|---|---|---|---|
| `email` | Email address for the website | Email | Yes |
| `phone` | Phone number, if you want one shown | Tel | No |
| `address` | Address, if you want one shown (one line per line) | Textarea | No |
| `registration` | Professional body and membership or registration number, one per line | Textarea | No |
| `same_as` | Links to your professional profiles, one per line | Textarea | No |

Under the registration field add: "We check these against the official register before they go on your site."

## Step 3: Your pages

| Field ID | Label | Type | Required |
|---|---|---|---|
| `pages_wanted` | Which pages do you want? | Checkbox: Home, About, Contact, FAQ, Services | Yes |
| `services` | Name each thing you offer, one per line | Textarea | No |
| `faq_start` | Questions people often ask you before they book | Textarea | No |

## Step 4: Look and feel

| Field ID | Label | Type | Required |
|---|---|---|---|
| `colours` | Any colours you like or want to avoid | Text | No |
| `likes` | A website you like, and what you like about it | Textarea | No |
| `avoid` | Anything you do not want on your site | Textarea | No |

## Step 5: Photos (optional, can be sent later)

| Field ID | Label | Type | Required |
|---|---|---|---|
| `photo_portrait` | A clear photo of you | File (jpg or png, up to 5 MB) | No |
| `photo_logo` | Your logo, if you have one | File | No |
| `photo_other` | Any others (see the shot list) | File | No |
| `photo_rights` | I own these photos or have permission to use them. | Acceptance | Only if a photo is sent |

## After the form
- Send the thank-you page to a short message and the booking link for the call.
- Elementor > Submissions has a CSV export. Save the export into `01-intake/` and then delete the submission and the uploaded files from the form site once the client site is live.
- Turn on an email alert to yourself for each new submission.
