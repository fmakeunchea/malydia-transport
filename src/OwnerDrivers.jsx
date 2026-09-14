import { useRef, useState } from 'react'

const areas = ['Fredericksburg', 'Spotsylvania', 'Stafford', 'Caroline']
function Select({ name, label, options }) {
  return <label>{label}<select name={name} required defaultValue=""><option value="" disabled>Select an option</option>{options.map(option => <option key={option}>{option}</option>)}</select></label>
}
export default function OwnerDrivers() {
  const [status, setStatus] = useState('idle')
  const requestId = useRef(crypto.randomUUID())
  const sending = useRef(false)
  async function submit(event) {
    event.preventDefault()
    if (sending.current) return
    const form = new FormData(event.currentTarget)
    if (!form.getAll('areas').length) { setStatus('areas'); return }
    sending.current = true
    setStatus('sending')
    try {
      const response = await fetch('/api/owner-driver', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...Object.fromEntries(form), areas: form.getAll('areas'), requestId: requestId.current }),
        signal: AbortSignal.timeout(20000),
      })
      const result = await response.json()
      if (!response.ok || result.ok !== true) throw new Error('Not accepted')
      setStatus('sent')
    } catch { setStatus('error') } finally { sending.current = false }
  }
  return <main>
    <section className="page-intro"><div className="container"><p className="eyebrow">Drive with Malydia</p><h1>Your vehicle.<br /><em>A caring purpose.</em></h1><p className="intro-copy">Owner-Driver Application &amp; Waiting List</p><p className="intro-copy">Interested in helping our Virginia neighbors reach the care they need? Tell us about yourself, your vehicle, and where you would like to drive.</p></div></section>
    <section className="section white"><div className="container booking-grid"><div className="driver-overview"><p className="eyebrow">Express your interest</p><h2>A first step toward<br />driving with us.</h2><p>We welcome expressions of interest from owner-drivers serving Fredericksburg, Spotsylvania, Stafford, and Caroline.</p><ol className="driver-steps"><li><strong>Tell us about yourself.</strong><p>Share your contact details, vehicle information, and availability.</p></li><li><strong>Join our waiting list.</strong><p>Our team reviews applications and keeps interested drivers on file for future opportunities.</p></li><li><strong>Talk through the next steps.</strong><p>If there is a potential fit, we will contact you to discuss vehicle suitability, screening, insurance, and onboarding requirements.</p></li></ol><p className="notice">Submitting this form does not guarantee approval, work, trips, income, or a start date. Any arrangement and compensation will be discussed separately before you agree to proceed.</p><p>Questions? <a className="text-link" href="tel:5404241852">Call (540) 424-1852</a>.</p></div>
    {status === 'sent' ? <div className="success" role="status"><span aria-hidden="true">✓</span><h2>Your application has been sent.</h2><p>Thank you for your interest in driving with Malydia. Your application has been submitted to our team for waiting-list review. We will reach out if an opportunity may be a fit.</p><p>To update your details or leave the waiting list, contact <a href="mailto:info@malydiahealth.com">info@malydiahealth.com</a>.</p></div> : <form className="ride-form driver-form" onSubmit={submit} aria-busy={status === 'sending'}>
      <h2>Owner-driver application</h2><p className="small-copy">All fields are required unless marked optional. Please do not provide Social Security numbers, license numbers, bank details, or document copies here.</p>
      <fieldset className="driver-group"><legend>Contact details</legend><div className="form-row"><label>First name<input name="firstName" autoComplete="given-name" maxLength={80} required /></label><label>Last name<input name="lastName" autoComplete="family-name" maxLength={80} required /></label></div><label>Email<input name="email" type="email" autoComplete="email" maxLength={254} required /></label><label>Phone<input name="phone" type="tel" autoComplete="tel" maxLength={30} required /></label><label>Home city / county<input name="city" maxLength={100} required /></label></fieldset>
      <fieldset className="driver-group"><legend>Where and when you can drive</legend><fieldset className="driver-areas" aria-describedby="areas-help"><legend>Preferred service areas</legend><p id="areas-help" className="small-copy">Choose at least one.</p>{areas.map(area => <label key={area}><input type="checkbox" name="areas" value={area} />{area}</label>)}</fieldset>{status === 'areas' && <p role="alert" className="form-error">Please choose at least one service area.</p>}<Select name="availability" label="Usual availability" options={['Weekdays', 'Evenings', 'Weekends', 'Flexible']} /><label>Availability details (optional)<input name="availabilityDetails" maxLength={300} placeholder="For example, Mondays and Wednesdays, 8 am–2 pm" /></label></fieldset>
      <fieldset className="driver-group"><legend>Your vehicle</legend><Select name="vehicleAccess" label="Do you have a vehicle available for this work?" options={['I own a vehicle', 'I lease a vehicle', 'I am planning to obtain a vehicle']} /><div className="form-row"><label>Vehicle make<input name="vehicleMake" maxLength={60} required /></label><label>Vehicle model<input name="vehicleModel" maxLength={60} required /></label></div><p className="small-copy">If you are planning to obtain a vehicle, enter the make and model you are considering.</p><div className="form-row"><label>Vehicle year<input name="vehicleYear" type="number" min="1900" max={new Date().getFullYear() + 1} required /></label><label>Passenger seats (excluding driver)<input name="seats" type="number" min="1" max="30" required /></label></div><Select name="vehicleType" label="Vehicle type" options={['Sedan', 'SUV', 'Minivan', 'Passenger van', 'Wheelchair-accessible vehicle', 'Other']} /></fieldset>
      <fieldset className="driver-group"><legend>Driving background</legend><Select name="licenseStatus" label="Do you currently hold a valid driver’s license?" options={['Yes', 'No']} /><Select name="insuranceStatus" label="Current vehicle insurance" options={['Personal auto coverage', 'Commercial auto coverage', 'No current coverage', 'Unsure']} /><Select name="experience" label="Passenger transportation experience" options={['New to passenger transportation', 'Less than 1 year', '1–3 years', 'More than 3 years']} /><p className="notice">These are preliminary details. Our team will discuss applicable coverage, vehicle, and screening requirements before any approval.</p></fieldset>
      <label className="form-honeypot" aria-hidden="true">Leave empty<input name="website" tabIndex={-1} autoComplete="off" /></label>
      <label className="driver-consent"><input name="consent" type="checkbox" value="yes" required /><span>I confirm these details are accurate and agree that Malydia may contact me about my application and keep it for waiting-list consideration. I can request removal by emailing info@malydiahealth.com. I have read the <a href="/privacy-policy">Privacy Policy</a>.</span></label>
      {status === 'error' && <p className="form-error" role="alert">We could not confirm your application was sent. Your details remain here. Please retry or call <a href="tel:5404241852">(540) 424-1852</a>.</p>}
      <button className="button" disabled={status === 'sending'} type="submit">{status === 'sending' ? 'Sending application…' : 'Apply & join the waiting list ↗'}</button>
    </form>}</div></section>
  </main>
}
