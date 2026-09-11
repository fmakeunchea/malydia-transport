import test from 'node:test'
import assert from 'node:assert/strict'
import handler from '../netlify/functions/ride-request.mjs'

const valid = { firstName: 'Test', lastName: 'Requester', phone: '5405550100', email: '', pickup: '100 Example St, Spotsylvania, VA', dropoff: '200 Example St, Stafford, VA', date: '2099-01-01', time: '09:00', mobility: 'wheelchair', returnRide: 'yes', requestId: 'a1234567-1234-1234-1234-123456789012', website: '' }
function request(data = valid, origin = 'https://example.com') { return new Request('https://example.com/api/ride-request', { method: 'POST', headers: { origin, 'Content-Type': 'application/json' }, body: JSON.stringify(data) }) }

test('rejects invalid requests without contacting the provider', async () => {
  for (const data of [null, {}, { ...valid, pickup: '' }, { ...valid, mobility: 'unknown' }, { ...valid, date: '2020-01-01' }, { ...valid, date: '2099-02-30' }, { ...valid, time: '25:00' }, { ...valid, website: 'spam' }, { ...valid, email: 'bad' }]) {
    assert.equal((await handler(request(data))).status, 400)
  }
  assert.equal((await handler(request(valid, 'https://other.example'))).status, 403)
})

test('configuration and delivery failures cannot report success; successful sends are addressed to Malydia', async () => {
  const previousFetch = globalThis.fetch
  const previousKey = process.env.RESEND_API_KEY
  const previousFrom = process.env.BOOKING_FROM_EMAIL
  try {
    delete process.env.RESEND_API_KEY
    assert.equal((await handler(request())).status, 503)
    process.env.RESEND_API_KEY = 'test-only'
    process.env.BOOKING_FROM_EMAIL = 'booking@example.com'
    globalThis.fetch = async () => new Response('{}', { status: 500 })
    assert.equal((await handler(request())).status, 502)
    globalThis.fetch = async () => { throw new Error('network unavailable') }
    assert.equal((await handler(request())).status, 502)
    const keys = []
    globalThis.fetch = async (url, options) => {
      assert.equal(url, 'https://api.resend.com/emails')
      const payload = JSON.parse(options.body)
      assert.deepEqual(payload.to, ['info@malydiahealth.com'])
      assert.equal(payload.subject, 'New website ride request')
      for (const field of ['pickup', 'dropoff', 'phone', 'date', 'time', 'mobility']) assert.ok(payload.text.includes(valid[field]))
      keys.push(options.headers['Idempotency-Key'])
      return Response.json({ id: 'test-message-id' })
    }
    assert.deepEqual(await (await handler(request())).json(), { ok: true })
    await handler(request())
    assert.equal(keys[0], keys[1], 'retries use the same idempotency key')
  } finally {
    globalThis.fetch = previousFetch
    if (previousKey === undefined) delete process.env.RESEND_API_KEY; else process.env.RESEND_API_KEY = previousKey
    if (previousFrom === undefined) delete process.env.BOOKING_FROM_EMAIL; else process.env.BOOKING_FROM_EMAIL = previousFrom
  }
})
