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

// Display content (hidden by pre-script.js)
document.body.style.visibility = "visible"
