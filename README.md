# Malydia Healthcare Transportation

React/Vite site. Run `npm install`, `npm run dev`, `npm run build`, and `npm run lint`.
Booking handler tests: `node --test tests/*.test.mjs` (mocked provider; no email is sent).

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

## Booking notifications — deployment setup required

The previous form never transmitted requests. The replacement posts to `/api/ride-request`, implemented as a Netlify Function with a Resend email notification to **info@malydiahealth.com**. No actual delivery has been verified locally.

1. Deploy to Netlify using the included `netlify.toml` (includes SPA route fallback). If hosted elsewhere, port the handler to that host's server runtime; a static-only deployment cannot send notifications.
2. Configure `RESEND_API_KEY` and `BOOKING_FROM_EMAIL` as server-side Netlify environment variables with Functions scope. The sender must belong to a verified Resend domain. Never use VITE-prefixed variables for secrets.
3. Use `netlify dev` for a local end-to-end run; plain `npm run dev` serves only the frontend. Without the function, submission displays a failure and offers phone contact.
4. After configuration, submit a clearly labeled synthetic request, confirm the message arrives at info@malydiahealth.com, and check provider delivery status/spam filtering. Do not use real rider information for testing.
5. Confirm provider arrangements and access controls are appropriate for the trip/contact information being processed before enabling production intake.

The handler validates required fields, checks same-origin requests, limits request size, applies Netlify rate limiting, ignores unrecognized fields, and uses idempotency keys for retries. It sends a generic subject with trip details in the email body; it does not log request bodies. The frontend stores no rider data in URLs or browser storage and only reports success after provider acceptance. Acceptance is not proof of inbox delivery or a confirmed ride. Delivery failures retain form values and offer retry/phone contact.

Provider references: [Netlify Functions API](https://docs.netlify.com/build/functions/api/), [environment variables](https://docs.netlify.com/build/functions/environment-variables/), [Resend idempotency](https://resend.com/docs/dashboard/emails/idempotency-keys).

## Content privacy check

Page titles, static metadata, image alt text, and route paths do not contain rider information or coverage status. No Medicaid/FAMIS references are currently present in public site source. Keep future reviews, metadata, and image descriptions free of identifiable rider coverage details. This source review is not a HIPAA compliance certification.
