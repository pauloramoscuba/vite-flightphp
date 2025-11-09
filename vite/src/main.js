// https://vite.dev/config/build-options.html#build-modulepreload
import "vite/modulepreload-polyfill";

// Styles
import "./styles/base.css";

// Images
import "./components/images.js";

import viteLogo from "./assets/vite.svg";
import { setupCounter } from "./components/counter.js";

// Get the FlightPHP logo from the global window object
const flightLogo = window.flightLogo;

document.querySelector("#app").innerHTML = `
  <div class="flex flex-col items-center space-y-8">
    <div class="flex gap-8">
      <a href="https://vite.dev" target="_blank">
      <img src="${viteLogo}" alt="Vite logo" loading="lazy" class="size-16 transition-shadow duration-200 hover:drop-shadow-2xl hover:drop-shadow-[#646cffaa]" />
      </a>
      <a href="https://flightphp.com" target="_blank">
      <img src="${flightLogo}" alt="FlightPHP" loading="lazy" class="h-16 dark:invert transition-shadow duration-200 hover:drop-shadow-2xl hover:drop-shadow-[#666666aa]" />
      </a>
    </div>
    <h1 class="text-3xl font-bold dark:text-[#cdd6f4]">Hello Vite + FlightPHP!</h1>
    <div class="dark:text-[#cdd6f4]">
      <button id="counter" type="button" class="bg-[#181825] hover:bg-[#11111b] text-white font-bold py-2 px-4 rounded cursor-pointer">Counter</button>
    </div>
    <p class="dark:text-[#cdd6f4]">
      Click on the Vite logo to learn more
    </p>
  </div>
`;

setupCounter(document.querySelector("#counter"));

// Dark mode toggle logic
document.querySelectorAll(".hs-dark-mode").forEach((btn) => {
    btn.addEventListener("click", function () {
        const mode = this.getAttribute("data-hs-theme-click-value");
        if (mode === "dark") {
            html.classList.add("dark");
            localStorage.setItem("hs_theme", "dark");
        } else if (mode === "light") {
            html.classList.remove("dark");
            localStorage.setItem("hs_theme", "light");
        }
        // Actualiza visibilidad de los botones
        updateDarkModeButtons();
    });
});
const html = document.querySelector("html");
function updateDarkModeButtons() {
    const isDark = html.classList.contains("dark");
    document.querySelectorAll(".hs-dark-mode").forEach((btn) => {
        const mode = btn.getAttribute("data-hs-theme-click-value");
        if (mode === "dark") {
            btn.classList.toggle("hidden", isDark);
            btn.classList.toggle("block", !isDark);
        } else if (mode === "light") {
            btn.classList.toggle("hidden", !isDark);
            btn.classList.toggle("block", isDark);
        }
    });
}
updateDarkModeButtons();

// Alpine.js
// import Alpine from 'alpinejs';
// window.Alpine = Alpine;
// Alpine.start();
