/**
 * Hide whole page until script.js has loaded + executed.
 */

const style = document.createElement("style")
style.innerText = "body { visibility: hidden; }"
document.head.appendChild(style)
