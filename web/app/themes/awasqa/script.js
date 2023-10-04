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

// Display content (hidden by pre-script.js)
document.body.style.visibility = "visible"
