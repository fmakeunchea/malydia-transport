import { useRef, useState } from 'react'
import driverOptions from '../server/driver-options.json'

const availabilityOptions = ['Weekdays', 'Evenings', 'Weekends', 'Full-time', 'Part-time']
function Field({ name, label, ...props }) {
  return <label>{label}<input name={name} required {...props} /></label>
}
function YesNo({ name, question }) {
  return <fieldset className="driver-choice"><legend>{question}</legend>{['Yes', 'No'].map(value => <label key={value}><input type="radio" name={name} value={value} required />{value}</label>)}</fieldset>
}
export default function OwnerDrivers() {
  const [status, setStatus] = useState('idle')
  const [vehicleType, setVehicleType] = useState('')
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
        body: JSON.stringify({ ...Object.fromEntries(form), availability: form.getAll('availability'), safetyChecks: form.getAll('safetyChecks'), requestId: requestId.current }),
        signal: AbortSignal.timeout(20000),
      })
      const result = await response.json()
      if (!response.ok || result.ok !== true) throw new Error('Not accepted')
      setStatus('sent')
    } catch { setStatus('error') } finally { sending.current = false }
  }
  return <main>
    <section className="page-intro"><div className="container"><p className="eyebrow">Malydia Healthcare Transportation LLC</p><h1 className="driver-title">Owner-driver application<br /><em>&amp; waiting list.</em></h1><p className="intro-copy">Ambulatory Non-Emergency Medical Transportation (NEMT) - Virginia</p></div></section>
    <section className="section white"><div className="container booking-grid"><div className="driver-overview"><p className="eyebrow">Purpose</p><h2>A first step toward<br />driving with us.</h2><p>This form collects preliminary information from individuals interested in future owner-driver opportunities with Malydia Healthcare Transportation LLC. Submission does not guarantee employment, independent-contractor status, trip assignments, or approval to transport passengers.</p><ol className="driver-steps"><li><strong>Tell us about yourself.</strong><p>Share your applicant, driver, and vehicle information.</p></li><li><strong>Join our waiting list.</strong><p>Our team reviews applications and keeps interested drivers on file for future opportunities.</p></li><li><strong>Discuss the next steps.</strong><p>If there is a potential fit, we will contact you about requirements before activation.</p></li></ol><p>Questions? <a className="text-link" href="tel:5404241852">Call (540) 424-1852</a>.</p></div>
    {status === 'sent' ? <div className="success" role="status"><span aria-hidden="true">✓</span><h2>Your application has been sent.</h2><p>Thank you for your interest in driving with Malydia. Your application has been submitted to our team for waiting-list review. We will reach out if an opportunity may be a fit.</p><p>To update your details or leave the waiting list, contact <a href="mailto:info@malydiahealth.com">info@malydiahealth.com</a>.</p></div> : <form className="ride-form driver-form" onSubmit={submit} aria-busy={status === 'sending'}>
      <h2>Owner-driver application</h2><p className="small-copy">Complete each field unless marked optional and select Yes or No for each question. For the safety checklist, check only the statements you can confirm. Your application is sent to the Malydia team by email for review. Please do not include Social Security numbers, bank details, or document copies.</p>
      <fieldset className="driver-group"><legend>1. Applicant information</legend>
        <Field name="fullName" label="Full legal name" autoComplete="name" maxLength={160} />
        <Field name="streetAddress" label="Street address" autoComplete="street-address" maxLength={200} />
        <Field name="city" label="City" autoComplete="address-level2" maxLength={100} />
        <div className="form-row"><Field name="state" label="State" autoComplete="address-level1" maxLength={60} /><Field name="zip" label="ZIP code" autoComplete="postal-code" inputMode="numeric" pattern="[0-9]{5}(-[0-9]{4})?" maxLength={10} /></div>
        <Field name="phone" label="Phone number" type="tel" autoComplete="tel" maxLength={30} />
        <Field name="email" label="Email address" type="email" autoComplete="email" maxLength={254} />
      </fieldset>
      <fieldset className="driver-group"><legend>2. Driver information</legend>
        <Field name="licenseState" label="Driver’s license state" maxLength={60} />
        <Field name="licenseNumber" label="Driver’s license number" autoComplete="off" maxLength={40} />
        <Field name="licenseExpiration" label="Expiration date" type="date" />
        <Field name="yearsLicensed" label="Years continuously licensed" type="number" min="0" max="100" step="1" />
        <YesNo name="atLeast18" question="Are you at least 18 years old?" />
        <YesNo name="licensedTwoYears" question="Have you held a valid driver’s license for at least two years?" />
        <YesNo name="authorizeChecks" question="Are you willing to authorize required motor-vehicle-record and background/credential checks?" />
        <YesNo name="completeTraining" question="Are you willing to complete required NEMT/safety training before activation?" />
        <label>Relevant driving, NEMT, healthcare, DSP, caregiver, or transportation experience<textarea name="experience" rows="4" maxLength={1000} required placeholder="Describe your experience, or enter None." /></label>
        <fieldset className="driver-areas" ref={availabilityGroup} aria-describedby="availability-help"><legend>Availability</legend><p id="availability-help" className="small-copy">Select all that apply; choose at least one.</p>{availabilityOptions.map(value => <label key={value}><input type="checkbox" name="availability" value={value} />{value}</label>)}</fieldset>
        {status === 'availability' && <p role="alert" className="form-error">Please choose at least one availability option.</p>}
        <Field name="serviceAreas" label="Preferred service area(s)" maxLength={300} placeholder="For example, Fredericksburg, Spotsylvania, Stafford, Caroline" />
      </fieldset>
      <fieldset className="driver-group"><legend>3. Owner-driver vehicle information</legend>
        <YesNo name="vehicleAuthority" question="Do you own or have lawful authority to use the vehicle you propose for NEMT service?" />
        <Field name="vehicleYear" label="Year" type="number" min="1900" max={new Date().getFullYear() + 1} />
        <div className="form-row"><Field name="vehicleMake" label="Make" maxLength={60} /><Field name="vehicleModel" label="Model" maxLength={60} /></div>
        <Field name="vin" label="VIN" autoComplete="off" minLength={6} maxLength={17} pattern="[A-Za-z0-9]{6,17}" />
        <Field name="licensePlate" label="License plate / state" autoComplete="off" maxLength={60} />
        <Field name="mileage" label="Current mileage" type="number" min="0" max="9999999" step="1" />
        <Field name="seatingCapacity" label="Seating capacity including driver" type="number" min="1" max="100" step="1" />
        <label>Vehicle type<select name="vehicleType" required value={vehicleType} onChange={event => setVehicleType(event.target.value)}><option value="" disabled>Select a vehicle type</option>{['Sedan', 'SUV', 'Minivan', 'Van', 'Other'].map(value => <option key={value}>{value}</option>)}</select></label>
        {vehicleType === 'Other' && <Field name="vehicleOther" label="Other vehicle type" maxLength={100} />}
        <YesNo name="wheelchairAccessible" question="Wheelchair accessible?" />
        <YesNo name="currentRegistration" question="Current registration?" />
        <YesNo name="currentInspection" question="Current inspection (if applicable)?" />
        <YesNo name="currentInsurance" question="Current auto insurance?" />
        <p className="notice"><strong>Important:</strong> A personal auto policy or ordinary registration is not automatically sufficient for paid NEMT service. No applicant may use a vehicle for Malydia trips until Malydia confirms applicable operating-authority, registration/plate, insurance, broker/provider, inspection, and credentialing requirements.</p>
      </fieldset>
      <fieldset className="driver-group"><legend>4. Preliminary vehicle safety checklist</legend>
        <p className="small-copy">Check each statement that is true for your vehicle. Leave any unconfirmed item unchecked. This preliminary checklist does not approve a vehicle for passenger transportation.</p>
        <div className="driver-safety">{Object.entries(driverOptions.safety).map(([value, label]) => <label key={value}><input name="safetyChecks" type="checkbox" value={value} /><span>{label}</span></label>)}</div>
      </fieldset>
      <fieldset className="driver-group"><legend>5. Training / credentials</legend>
        {Object.entries(driverOptions.credentials).map(([name, label]) => <fieldset className="driver-choice" key={name}><legend>{label}</legend>{driverOptions.trainingStatuses.map(value => <label key={value}><input name={name} type="radio" value={value} required />{value}</label>)}</fieldset>)}
        <label>Other relevant certifications (optional)<textarea name="otherCertifications" rows="3" maxLength={500} /></label>
      </fieldset>
      <aside className="notice"><strong>6. Malydia onboarding checklist — office use</strong><br />Our team completes the onboarding checklist and assigns an application status after review. You do not need to complete this section.</aside>
      <label className="form-honeypot" aria-hidden="true">Leave empty<input name="website" tabIndex={-1} autoComplete="off" /></label>
      <label className="driver-consent"><input name="consent" type="checkbox" value="yes" required /><span>I confirm these details are accurate and agree that Malydia may contact me about my application and keep it for waiting-list consideration. I can request removal by emailing info@malydiahealth.com. I have read the <a href="/privacy-policy">Privacy Policy</a>.</span></label>
      {status === 'error' && <p className="form-error" role="alert">We could not confirm your application was sent. Your details remain here. Please retry or call <a href="tel:5404241852">(540) 424-1852</a>.</p>}
      <button className="button" disabled={status === 'sending'} type="submit">{status === 'sending' ? 'Sending application…' : 'Apply & join the waiting list ↗'}</button>
    </form>}</div></section>
  </main>
}
