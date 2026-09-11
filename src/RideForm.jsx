import { useRef, useState } from 'react'

export default function RideForm() {
  const [status, setStatus] = useState('idle')
  const submitting = useRef(false)
  const requestId = useRef(crypto.randomUUID())
  const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/New_York', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date())

  async function submit(event) {
    event.preventDefault()
    if (submitting.current) return
    submitting.current = true
    setStatus('sending')
    const data = Object.fromEntries(new FormData(event.currentTarget))
    try {
      const response = await fetch('/api/ride-request', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...data, requestId: requestId.current }),
        signal: AbortSignal.timeout(20000),
      })
      const result = await response.json()
      if (!response.ok || result.ok !== true) throw new Error('Request not accepted')
      setStatus('sent')
    } catch {
      setStatus('error')
    } finally {
      submitting.current = false
    }
  }

  if (status === 'sent') return <div className="success" role="status"><span aria-hidden="true">✓</span><h2>Your request has been sent.</h2><p>Your request has been submitted to the Malydia team. We will contact you to discuss availability, pricing, and trip details. Your ride is not yet confirmed.</p><p>If your request is time-sensitive, call <a href="tel:5404241852">(540) 424-1852</a>.</p><button className="text-link" onClick={() => { requestId.current = crypto.randomUUID(); setStatus('idle') }}>Submit another request ↗</button></div>

  return <form className="ride-form" onSubmit={submit} aria-busy={status === 'sending'}>
    <p className="small-copy">Fields are required unless marked optional. All times are local Virginia time.</p>
    <div className="form-row"><label>First name<input name="firstName" autoComplete="given-name" maxLength={80} required /></label><label>Last name<input name="lastName" autoComplete="family-name" maxLength={80} required /></label></div>
    <div className="form-row"><label>Contact phone<input name="phone" autoComplete="tel" required type="tel" maxLength={30} /></label><label>Email (optional)<input name="email" autoComplete="email" type="email" maxLength={254} /></label></div>
    <label>Pickup address<input name="pickup" required maxLength={300} placeholder="Street address, city, state, ZIP" /></label>
    <label>Drop-off address<input name="dropoff" required maxLength={300} placeholder="Street address, city, state, ZIP; facility if known" /></label>
    <div className="form-row"><label>Pickup date<input name="date" required type="date" min={today} /></label><label>Pickup time<input name="time" required type="time" /></label></div>
    <fieldset><legend>Return ride needed?</legend><label><input type="radio" name="returnRide" value="yes" required /> Yes</label><label><input type="radio" name="returnRide" value="no" required /> No</label></fieldset>
    <label>Mobility needs<select name="mobility" required defaultValue=""><option value="" disabled>Select a mobility need</option><option value="ambulatory">Able to ride in a regular vehicle</option></select></label>
    <p className="notice">We currently transport passengers who can ride in a regular vehicle. Wheelchair-accessible and stretcher transportation are unavailable. This is non-emergency transportation.</p>
    <label className="form-honeypot" aria-hidden="true">Leave this field empty<input name="website" tabIndex={-1} autoComplete="off" /></label>
    <p className="notice">Please do not include diagnoses, insurance or coverage details, or other unnecessary medical information. We use these details to coordinate your request and send them to our team through our notification provider. Read our <a href="/privacy-policy">Privacy Policy</a>.</p>
    <p className="notice">Submitting a request does not guarantee transportation. Our team will confirm availability and the trip price with you.</p>
    {status === 'error' && <p className="form-error" role="alert">We could not confirm that your request was sent. Your details remain here. Please retry or call <a href="tel:5404241852">(540) 424-1852</a> to arrange your ride.</p>}
    <button className="button" type="submit" disabled={status === 'sending'}>{status === 'sending' ? 'Sending request…' : 'Submit ride request ↗'}</button>
  </form>
}
