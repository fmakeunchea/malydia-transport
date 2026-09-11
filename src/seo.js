export const siteUrl = 'https://malydiahealth.com'
export const pages = {
  '/': ['Medical Transportation in Fredericksburg, VA | Malydia', 'Non-emergency transportation for passengers who can ride in a regular vehicle. Serving Fredericksburg, Spotsylvania, Stafford and Caroline. Call for a quote.'],
  '/about': ['About Malydia Healthcare Transportation | Virginia', 'Meet Malydia Healthcare Transportation, serving passengers and families in Fredericksburg, Spotsylvania, Stafford and Caroline, Virginia.'],
  '/services': ['Medical Appointment & Senior Transportation | Malydia', 'Request regular-vehicle rides for medical appointments, dialysis, therapy and senior transportation in the Fredericksburg region. Call for availability.'],
  '/service-area': ['Fredericksburg, Spotsylvania, Stafford & Caroline Rides | Malydia', 'Malydia serves Fredericksburg, Spotsylvania County, Stafford County and Caroline County with non-emergency transportation. Request availability and a quote.'],
  '/book-a-ride': ['Request a Ride | Malydia Healthcare Transportation', 'Request non-emergency transportation with Malydia in the Fredericksburg region. Our team will confirm availability and pricing before your ride is booked.'],
  '/contact': ['Contact Malydia Transportation | (540) 424-1852', 'Call Malydia Healthcare Transportation at (540) 424-1852 for ride availability and quotes in Fredericksburg, Spotsylvania, Stafford and Caroline.'],
  '/privacy-policy': ['Privacy Policy | Malydia Healthcare Transportation', 'Learn how Malydia Healthcare Transportation handles information submitted through its website and ride request form.'],
  '/terms': ['Terms & Conditions | Malydia Healthcare Transportation', 'Read the terms for using the Malydia Healthcare Transportation website and requesting a ride.'],
}
export function updateSeo(path) {
  const route = path.replace(/\/$/, '') || '/'
  const [title, description] = pages[route] || pages['/']
  document.title = title
  document.querySelector('meta[name="description"]').content = description
  let canonical = document.querySelector('link[rel="canonical"]')
  if (!canonical) {
    canonical = document.createElement('link')
    canonical.rel = 'canonical'
    document.head.append(canonical)
  }
  canonical.href = siteUrl + route
}
