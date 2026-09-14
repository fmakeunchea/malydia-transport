import { useRef, useState } from 'react'
import waitingList from '../server/driver-waitlist.json'

function Question({ field, onAnswer, availabilityRef, availabilityError }) {
  const { name, number, label, type, required, options, maxLength, autoComplete, pattern, min, max } = field
  const heading = `${number}. ${label}${required ? ' *' : ' (optional)'}`
  const inputProps = { name, required, maxLength, autoComplete, pattern, min, max: max === 'nextYear' ? new Date().getFullYear() + 1 : max }
  if (type === 'choice' || type === 'multiple') return <fieldset className={type === 'multiple' ? 'driver-areas' : 'driver-choice'} ref={type === 'multiple' ? availabilityRef : undefined} aria-describedby={type === 'multiple' ? 'availability-help' : undefined}>
    <legend>{heading}</legend>
    {type === 'multiple' && <p id="availability-help" className="small-copy">Choose all that apply; select at least one.</p>}
    {options.map(value => <label key={value}><input name={name} type={type === 'multiple' ? 'checkbox' : 'radio'} value={value} required={type === 'choice' && required} onChange={() => onAnswer(name, value)} />{value}</label>)}
    {type === 'multiple' && availabilityError && <p role="alert" className="form-error">Please choose at least one availability option.</p>}
  </fieldset>
  if (type === 'select') return <label>{heading}<select name={name} required={required} defaultValue=""><option value="">{required ? 'Select an option' : 'Select an option (optional)'}</option>{options.map(value => <option key={value}>{value}</option>)}</select></label>
  if (type === 'textarea') return <label>{heading}<textarea {...inputProps} rows="3" /></label>
  return <label>{heading}<input {...inputProps} type={type} step={type === 'number' ? '1' : undefined} /></label>
}
export default function OwnerDrivers() {
  const [status, setStatus] = useState('idle')
  const [answers, setAnswers] = useState({})
  const requestId = useRef(crypto.randomUUID())
  const sending = useRef(false)
  const availabilityGroup = useRef(null)
  async function submit(event) {
    event.preventDefault()
    if (sending.current) return
    const form = new FormData(event.currentTarget)
    if (!form.getAll('availability').length) {
      setStatus('availability')
      availabilityGroup.current?.querySelector('input')?.focus()
      return
    }
    sending.current = true
    setStatus('sending')
    try {
      const response = await fetch('/api/owner-driver', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...Object.fromEntries(form), availability: form.getAll('availability'), requestId: requestId.current }),
        signal: AbortSignal.timeout(20000),
      })
      const result = await response.json()
      if (!response.ok || result.ok !== true) throw new Error('Not accepted')
      setStatus('sent')
    } catch { setStatus('error') } finally { sending.current = false }
  }
  return <main>
    <section className="page-intro"><div className="container"><p className="eyebrow">Malydia Healthcare Transportation LLC</p><h1 className="driver-title">Join our<br /><em>driver waiting list.</em></h1><p className="intro-copy">Express your interest in future owner-driver opportunities for ambulatory non-emergency medical transportation in Virginia.</p></div></section>
    <section className="section white"><div className="container booking-grid"><div className="driver-overview"><p className="eyebrow">Drive with Malydia</p><h2>A first step toward<br />driving with us.</h2><p>Tell us about your driving background, vehicle, and availability. Our team will review your interest and contact you if an opportunity may be a fit.</p><p>Joining the waiting list does not approve you to transport passengers or guarantee work. Driver and vehicle requirements must be completed before approval.</p><p>Questions? <a className="text-link" href="tel:5404241852">Call (540) 424-1852</a>.</p></div>
    {status === 'sent' ? <div className="success" role="status"><span aria-hidden="true">✓</span><h2>Your interest has been submitted.</h2><p>Thank you for your interest in joining the Malydia driver network. Your details have been sent to our team for waiting-list review. We will reach out if an opportunity may be a fit.</p><p>To update your information or leave the waiting list, contact <a href="mailto:info@malydiahealth.com">info@malydiahealth.com</a>.</p></div> : <form className="ride-form driver-form" onSubmit={submit} aria-busy={status === 'sending'}>
      <h2>Driver waiting list questions</h2><p className="small-copy">Fields marked * are required. Your answers are sent to the Malydia team by email for waiting-list review. Please do not include license numbers, Social Security numbers, bank details, or document copies.</p>
      {waitingList.groups.map(group => <fieldset className="driver-group" key={group.title}><legend>{group.title}</legend>{waitingList.fields.filter(field => field.number >= group.start && field.number <= group.end).map(field => {
        if (field.visibleWhen && answers[field.visibleWhen.field] !== field.visibleWhen.value) return null
        return <Question key={field.name} field={field} onAnswer={(name, value) => setAnswers(previous => ({ ...previous, [name]: value }))} availabilityRef={availabilityGroup} availabilityError={status === 'availability'} />
      })}</fieldset>)}
      <label className="form-honeypot" aria-hidden="true">Leave empty<input name="website" tabIndex={-1} autoComplete="off" /></label>
      <label className="driver-consent"><input name="acknowledgment" type="checkbox" value="yes" required /><span>{waitingList.acknowledgment}</span></label>
      <p className="small-copy">Read our <a className="text-link" href="/privacy-policy">Privacy Policy</a> for how we use your information. To update your details or request removal, email info@malydiahealth.com.</p>
      {status === 'error' && <p className="form-error" role="alert">We could not confirm your details were sent. Your answers remain here. Please retry or call <a href="tel:5404241852">(540) 424-1852</a>.</p>}
      <button className="button driver-submit" disabled={status === 'sending'} type="submit">{status === 'sending' ? 'SENDING…' : 'JOIN THE MALYDIA DRIVER WAITING LIST'}</button>
    </form>}</div></section>
  </main>
}
