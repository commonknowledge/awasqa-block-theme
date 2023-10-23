const LOCALE = document.documentElement.lang || 'en-GB'
const USER_DATA = window.USER_DATA

// Apply orange filter to Radical Resilience block
document.querySelectorAll(".awasqa-radical-resilience").forEach((block) => {
    const figure = block.querySelector("figure")
    const image = block.querySelector("img")
    if (figure && image) {
        image.style.display = "none"
        const url = image.getAttribute("src")

        figure.style.background = `
            linear-gradient(0deg, rgba(253, 167, 0, 0.80) 0%, rgba(253, 167, 0, 0.80) 100%),
            url(${url}) 100% / cover no-repeat
        `
        figure.style.backgroundBlendMode = "multiply"
        figure.style.mixBlendMode = "multiply"
    }
})

// Internationalise post dates
document.querySelectorAll(".wp-block-post-date").forEach(block => {
    const datetime = block.querySelector("time")?.getAttribute("datetime")
    block.innerText = (new Date(datetime)).toLocaleDateString(LOCALE, { month: "short", day: "numeric", year: "numeric" })
})

// Hide related posts section if there are no posts
document.querySelectorAll('.awasqa-related-posts').forEach(block => {
    const posts = block.querySelectorAll('.wp-block-post')
    if (!posts.length) {
        block.style.display = 'none'
    }
})

// Set up Edit Organisation button
document.querySelectorAll('.awasqa-edit-organisation-action').forEach(button => {
    // Hide button if user has no organisations
    if (!USER_DATA.organisations.length) {
        button.style.display = "none"
    }
    const link = button.querySelector('a')
    let href = link.getAttribute('href')
    const queryGlue = href.includes('?') ? '&' : '?'

    // If user only has one org, link to that one
    if (USER_DATA.organisations.length === 1) {
        href = `${href}${queryGlue}org_id=${USER_DATA.organisations[0].ID}`
        link.setAttribute('href', href)
        return
    }

    // Otherwise display a dropdown
    const ul = document.createElement('ul')
    ul.style.display = "none"
    button.appendChild(ul)

    for (const org of USER_DATA.organisations) {
        const orgHref = `${href}${queryGlue}org_id=${org.ID}`
        const orgLink = document.createElement('a')
        const orgItem = document.createElement('li')
        orgLink.setAttribute('href', orgHref)
        orgLink.innerText = org.post_title
        orgItem.appendChild(orgLink)
        ul.appendChild(orgItem)
    }

    link.addEventListener("click", (e) => {
        e.preventDefault()
        ul.style.display = "block"
    })
})

// Add About Contents
const about = document.querySelector('.page-about')
if (about) {
    const headings = about.querySelectorAll('h1,h2,h3,h4,h5,h6')
    const contents = about.querySelector('.page-about-contents')
    const contentsList = document.createElement('ul')
    contents.appendChild(contentsList)
    headings.forEach((heading) => {
        // When this code runs, the document is hidden, so heading.innerText is empty
        // Create a dummy <span> to convert the heading HTML to text
        const span = document.createElement('span')
        span.innerHTML = heading.innerHTML
        const title = span.innerText

        const slug = title.replace(/[^a-z]/gi, '').toLowerCase()
        heading.setAttribute('id', slug)
        const link = document.createElement('a')
        link.setAttribute('href', `#${slug}`)
        link.innerText = title
        const listItem = document.createElement('li')
        listItem.appendChild(link)
        contentsList.appendChild(listItem)
    })

    // Add "active" class to links when they are clicked
    const links = contentsList.querySelectorAll('a')
    links.forEach(link => {
        link.addEventListener('click', () => {
            links.forEach(link => link.classList.remove('active'))
            link.classList.add('active')
        })
        if (link.getAttribute('href') === location.hash) {
            link.classList.add('active')
        }
    })
}

const eventForm = document.querySelector('#gform_7')
if (eventForm) {
    const dateField = eventForm.querySelector('.datepicker')
    const hourField = eventForm.querySelector('.gfield_time_hour input')
    const minuteField = eventForm.querySelector('.gfield_time_minute input')
    const hiddenField = eventForm.querySelector('#input_7_11')

    const updateUTCTime = () => {
        const localTime = new Date()
        const dateStr = dateField.value
        if (dateStr) {
            const [year, month, day] = dateStr.split('-')
            localTime.setFullYear(year, Number(month) - 1, day)
            localTime.setHours(hourField.value)
            localTime.setMinutes(minuteField.value)
            hiddenField.setAttribute("value", localTime.toISOString())
        } else {
            hiddenField.setAttribute("value", "")
        }
    }

    dateField.addEventListener('blur', updateUTCTime)
    hourField.addEventListener('change', updateUTCTime)
    minuteField.addEventListener('change', updateUTCTime)
}

// Hide headings for empty query loops
document.querySelectorAll(
    '.wp-block-heading + .wp-block-query:empty,.wp-block-heading + .awasqa-organisation-authors:empty'
).forEach(query => {
    query.previousElementSibling.style.display = "none"
})

// Display content (hidden by pre-script.js)
document.body.style.visibility = "visible"
