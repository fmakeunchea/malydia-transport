import { business } from './business'

export function TrustBadges() {
  const points = [
    business.licensedAndInsured ? 'Licensed & Insured' : 'Safety-Focused Service',
    business.screenedAndCertifiedDrivers ? 'Background-Checked Drivers' : 'Personalized Ride Planning',
    business.adaVehicles ? 'ADA-Compliant Vehicles' : 'Clear Communication',
    'Locally Owned in Spotsylvania, VA',
  ]
  return <section className="trust-band" aria-label="Why choose Malydia"><div className="container trust-points">{points.map(point => <div key={point}><span aria-hidden="true">✦</span>{point}</div>)}</div></section>
}

export function Credentials() {
  return <section className="section credentials"><div className="container"><p className="eyebrow">Confidence in every mile</p><h2>Care you can ask about.</h2><div className="credential-grid">
    <article><h3>Licensing & insurance</h3><p>{business.licensedAndInsured ? 'Malydia is a licensed and insured NEMT carrier registered with the Virginia DMV, with commercial auto and general liability insurance coverage.' : 'Ask our team about Virginia DMV NEMT carrier registration, commercial auto insurance, and general liability coverage before arranging your ride.'}</p></article>
    <article><h3>Driver preparation</h3><p>{business.screenedAndCertifiedDrivers ? 'Our drivers complete background checks and drug screening and hold CPR and First Aid certification.' : 'We welcome questions about driver background checks, drug screening, and CPR and First Aid certification when you speak with our team.'}</p></article>
    <article><h3>The people behind Malydia</h3><p>{business.founderExperience || 'Locally owned in Spotsylvania, we serve our neighbors in Fredericksburg, Spotsylvania, and Stafford. Contact us to meet our founders and learn about the experience behind our approach to transportation.'}</p></article>
  </div></div></section>
}

export function Reviews() {
  return <section className="section white"><div className="container"><p className="eyebrow">Rider & family experiences</p><h2 className="trust-title">Your experience matters.</h2>{business.reviews.length ? <div className="credential-grid">{business.reviews.map(({ quote, attribution }, index) => <figure className="review-card" key={index}><blockquote>{quote}</blockquote><figcaption>{attribution}</figcaption></figure>)}</div> : <div className="review-empty"><p>There are no published rider reviews yet. We look forward to sharing feedback from riders and families, with their permission.</p><a className="text-link" href="/contact">Share your experience with our team ↗</a><p className="small-copy">Please leave out medical details, trip addresses, and insurance information when sharing feedback.</p></div>}</div></section>
}

export function Pricing() {
  return <section className="section white"><div className="container pricing-panel"><div><p className="eyebrow">Private-pay pricing</p><h2>A clear quote.<br /><em>Before you book.</em></h2><p>Call for a quote for trips in Fredericksburg, Spotsylvania, Stafford, and surrounding communities.</p></div><div><p>Share your pickup and drop-off addresses, requested date and time, mobility needs, and whether you need a return ride.</p><p>Ask for the total trip price, including any base or per-mile charges, waiting time, return travel, and cancellation terms. Our team will confirm the quote and availability before you agree to a ride.</p><a className="button" href="tel:5404241852">Call for a quote ↗</a><p className="small-copy">Wheelchair and stretcher requests are subject to vehicle and service availability. A request is not a confirmed booking.</p></div></div></section>
}

export function OperatingDetails() {
  return <><div className="contact-detail"><span>Operating hours</span><div>{business.hours || 'Please call to confirm current operating hours and availability for your requested trip.'}</div></div><div className="contact-detail"><span>Booking response</span><div>{business.responseTime || 'Response times vary. For a time-sensitive request, please call our team directly.'} Your ride is confirmed only after our team contacts you.</div></div></>
}
