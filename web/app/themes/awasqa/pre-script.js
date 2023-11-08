/**
 * Hide whole page until script.js has loaded + executed.
 */

const style = document.createElement("style")
style.innerText = "body { visibility: hidden; }"
document.head.appendChild(style)

  // Fallback in case of errors - display page after 5 seconds
  setTimeout(() => {
    document.head.removeChild(style);
  }, 5000);