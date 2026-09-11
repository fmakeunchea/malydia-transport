import { createHash } from 'node:crypto'

export const config = { path: '/api/ride-request', rateLimit: { action: 'rate_limit', windowLimit: 5, windowSize: 60, aggregateBy: ['ip', 'domain'] } }
const reply = (status, ok = false) => Response.json({ ok }, { status, headers: { 'Cache-Control': 'no-store' } })

export default async function handler(request) {
  if (request.method !== 'POST') return reply(405)
  if (request.headers.get('origin') !== new URL(request.url).origin) return reply(403)
  if (!request.headers.get('content-type')?.startsWith('application/json')) return reply(415)
  let data
  try {
    const body = await request.text()
    if (body.length > 6000) return reply(413)
    data = JSON.parse(body)
  } catch { return reply(400) }
  if (!data || typeof data !== 'object' || Array.isArray(data)) return reply(400)
  if (data.website) return reply(400)
  const limits = { firstName: 80, lastName: 80, phone: 30, pickup: 300, dropoff: 300, date: 10, time: 5, mobility: 20, returnRide: 3, requestId: 36 }
  for (const [key, limit] of Object.entries(limits)) {
    if (typeof data[key] !== 'string' || !data[key].trim() || data[key].length > limit || Array.from(data[key]).some(char => char.charCodeAt(0) < 32)) return reply(400)
  }
  const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/New_York', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date())
  if (!/^\d{4}-\d{2}-\d{2}$/.test(data.date) || !Number.isFinite(Date.parse(data.date)) || new Date(data.date).toISOString().slice(0, 10) !== data.date || data.date < today || !/^([01]\d|2[0-3]):[0-5]\d$/.test(data.time)) return reply(400)
  if (!['ambulatory', 'wheelchair', 'stretcher'].includes(data.mobility) || !['yes', 'no'].includes(data.returnRide) || !/^[a-f0-9-]{36}$/i.test(data.requestId) || data.phone.replace(/\D/g, '').length < 10) return reply(400)
  if (data.email !== undefined && (typeof data.email !== 'string' || data.email.length > 254 || (data.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)))) return reply(400)
  const { RESEND_API_KEY, BOOKING_FROM_EMAIL } = process.env
  if (!RESEND_API_KEY || !BOOKING_FROM_EMAIL) return reply(503)
  const text = [
    'New ride request — contact the requester to confirm pricing and availability.',
    `Name: ${data.firstName} ${data.lastName}`, `Phone: ${data.phone}`, `Email: ${data.email || 'Not provided'}`,
    `Pickup: ${data.pickup}`, `Drop-off: ${data.dropoff}`, `Pickup date/time (Virginia): ${data.date} ${data.time}`,
    `Mobility: ${data.mobility}`, `Return ride: ${data.returnRide}`,
    'This request is not a confirmed booking.',
  ].join('\n')
  try {
    const response = await fetch('https://api.resend.com/emails', {
      method: 'POST',
      headers: { Authorization: `Bearer ${RESEND_API_KEY}`, 'Content-Type': 'application/json', 'Idempotency-Key': createHash('sha256').update(data.requestId + text).digest('hex') },
      body: JSON.stringify({ from: BOOKING_FROM_EMAIL, to: ['info@malydiahealth.com'], subject: 'New website ride request', text }),
      signal: AbortSignal.timeout(10000),
    })
    if (!response.ok) return reply(502)
    const result = await response.json()
    return result.id ? reply(200, true) : reply(502)
  } catch { return reply(502) }
}
