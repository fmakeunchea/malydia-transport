# Malydia Healthcare Transportation

React/Vite site. Run `npm install`, `npm run dev`, `npm run build`, and `npm run lint`.
Booking handler tests: `php tests/booking.test.php` (PHP 8.2+, mocked sender; no email is sent).

## Verified business content

Edit `src/business.js` after owner confirmation:

- `licensedAndInsured`: confirm Virginia DMV NEMT carrier registration plus commercial auto and general liability coverage before enabling.
- `screenedAndCertifiedDrivers`: confirm background checks, drug screening, and current CPR/First Aid certification before enabling.
- `adaVehicles`: enable only once applicable and verified.
- `founderExperience`: add approved founder names, roles, and specific healthcare, disability services, and operations experience. No bios or experience have been invented.
- `hours`: supply actual operating days/hours in Eastern Time, including any distinction between dispatch and ride availability.
- `responseTime`: supply an achievable booking response target, with business-hours/after-hours context.
- `reviews`: add real quotes and permissioned attribution; omit identifiable trip, medical, and coverage details. An honest empty state is displayed until reviews exist.

Until verified, the site invites credential questions and calls for availability instead of asserting unverified credentials or promising invented hours. Private-pay pricing uses “Call for a quote.”

## Hostinger Web/Cloud + Zoho booking notifications

The form posts to `/api/ride-request`. Hostinger routes this to the PHP endpoint in `public/api/ride-request.php` using `public/.htaccess`. Notifications are sent from and to **info@malydiahealth.com** using authenticated Zoho SMTP. The prior Netlify/Resend integration has been removed.

### Deploy

Use PHP 8.2 or newer with OpenSSL enabled. Run `npm run build`, then upload the **contents** of `dist/` to the domain's `public_html/`, including the hidden `.htaccess` and `api/` directory. Uploading source code or pushing to GitHub alone does not deploy the built site unless your deployment pipeline performs these steps.

Place the contents of `server/` in a sibling folder named `malydia-private`, outside `public_html`:

```text
domain-folder/
  malydia-private/
    booking.php
    composer.json
    composer.lock  (generated on first Composer install)
    config.php
    vendor/
  public_html/
    .htaccess
    index.html
    assets/
    api/ride-request.php
```

In `malydia-private`, run `composer2 install --no-dev --optimize-autoloader` over Hostinger SSH. Composer 2 is available on supported Hostinger Web/Cloud plans. The folder must be writable by PHP for its private rate-limit/deduplication state file. Keep the folder private to the hosting account; set `config.php` permissions to `600`.

Copy `config.example.php` to `config.php` in that private folder. Set:

- `origins`: the exact HTTPS website origin(s), with no trailing slash, including www if used.
- `smtp_host`: the exact outgoing host shown in **Zoho Mail → Settings → Mail Accounts → Server Configuration**. Account type and region determine the host; do not guess.
- `smtp_port`: `465` for implicit TLS or `587` for STARTTLS.
- `smtp_username`: `info@malydiahealth.com`.
- `smtp_password`: the Zoho application-specific password, entered privately on the server. Never paste it into chat, commit it, or put it in a VITE variable/public file.

No credentials or hosting access are present in this repository, so live delivery has not been verified. After deployment, submit a clearly labeled synthetic request and confirm its arrival in the company inbox. Check success, retry, and direct navigation to `/book-a-ride`. Missing configuration or SMTP failures must display the form's failure message. A plain Vite dev/preview server cannot execute PHP.

### Request behavior

The PHP handler validates trip fields, restricts origins, limits payload size, and sends a generic email subject with trip details in a plain-text body. It keeps no rider data in application logs or local state. The private state file stores hashes/timestamps for five attempts per IP per minute, fifty attempts total per minute, and 24-hour duplicate suppression. Expired state entries are pruned on the next accepted attempt. One nonblocking file lock serializes submissions for this small-volume site; concurrent requests can receive a retryable unavailable response.

Confirmation means Zoho accepted the message, not that it reached the inbox or the ride is booked. SMTP disconnections after acceptance, or a failure to save the success marker, can still cause duplicates on retry; the team should verify requests before scheduling. Zoho may retain sent mail in its Sent folder; manage mailbox access and retention for booking information.

References: [Zoho SMTP settings](https://www.zoho.com/mail/help/zoho-smtp.html), [Hostinger Composer](https://www.hostinger.com/support/5792078-how-to-use-composer-at-hostinger/), [PHPMailer](https://github.com/PHPMailer/PHPMailer).

## Content privacy check

Page titles, static metadata, image alt text, and route paths do not contain rider information or coverage status. No Medicaid/FAMIS references are currently present in public site source. Keep future reviews, metadata, and image descriptions free of identifiable rider coverage details. This source review is not a HIPAA compliance certification.

## Google search setup

The build generates route-specific HTML titles, descriptions, canonical links,
`robots.txt`, and `sitemap.xml`. React still renders the page body in the browser.
Upload the full contents of `dist/`, including the route folders and `.htaccess`,
to `public_html`. Keep the private SMTP configuration outside that folder.

After uploading, verify `https://malydiahealth.com/` in Google Search Console,
submit `sitemap.xml`, and use URL Inspection to request homepage indexing.
Account verification must be completed by the owner. Indexing and ranking are
controlled by Google and are not guaranteed by submitting a sitemap.

The owner confirmed service in Fredericksburg, Spotsylvania, Stafford, and
Caroline, with regular-vehicle passenger transport only at present.

## Driver waiting list

`/owner-drivers` now uses the owner's 38-question waiting-list form in five sections. `server/driver-waitlist.json` is the shared source for wording, required/optional flags, options, and the exact acknowledgment. Availability supports multiple selections; work interest is one choice. Question 12 appears when experience is Yes and remains optional. Optional inspection, accessibility, training, referral, and free-text questions may be skipped. No answers are automatically rejected for screening reasons.

The only acknowledgment checkbox is required. The earlier license-number, VIN, signature, and detailed safety checklist inputs are no longer collected. The public form contains no internal compensation planning. The waiting list is managed manually in the company mailbox, not a dashboard. No approval, position, earnings, or response deadline is promised.

Deploy the updated `dist/` contents (including `.htaccess` and both PHP endpoints) to `public_html`. Upload the full private ZIP to `malydia-private`; it includes `booking.php`, `owner-driver.php`, `driver-waitlist.json`, `driver-options.json`, `driver-internal.example.txt`, and the private `driver-internal.txt`. Preserve your private `config.php` and `vendor/`. The old `driver-program.json` is no longer needed. Private compensation planning remains gitignored and must never be copied into `public/` or imported by frontend code.

Requests post to `/api/owner-driver` and use the same Zoho configuration as booking requests. Emails to info@malydiahealth.com contain all 38 numbered answers (unanswered optional fields read “Not provided”), the exact acknowledgment, and separate blank office-review materials. Unknown and legacy fields, including signature/identity identifiers, office status, and pay rates, are discarded. Applicant details are not written to browser storage, URLs, or private deduplication state.

Run `php tests/owner-driver.test.php` and `php tests/booking.test.php`. Tests use synthetic data and do not send email. Live Hostinger deployment, SMTP acceptance, and inbox delivery still require verification.
