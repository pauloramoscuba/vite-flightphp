// Importar todas las imágenes de la carpeta assets
const images = import.meta.glob("../assets/*.{png,jpg,jpeg,svg,gif,webp}", {
    eager: true,
    query: "?url",
});

// Hacer que las imágenes estén disponibles globalmente si es necesario
// Por ejemplo, si quieres acceder a ellas desde PHP o en otro lugar
window.assetsImages = images;

// Si necesitas exportar una imagen específica, puedes hacerlo así:
// import flightLogo from "../assets/logo.png";
// window.flightLogo = flightLogo;
