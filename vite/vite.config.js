import { resolve } from "node:path";
import tailwindcss from "@tailwindcss/vite";
import { defineConfig } from "vite";
import liveReload from "vite-plugin-live-reload";

const PORT = 5173;

// https://vitejs.dev/config/
export default defineConfig({
    plugins: [
        tailwindcss(),
        liveReload([
            `${__dirname}/../(app|config|controllers|middlewares|views)/**/*.php`,
            `${__dirname}/../public/*.php`,
            `${__dirname}/src/**/**`,
        ]),
    ],

    // config
    root: "src",
    base: process.env.APP_ENV === "development" ? "/" : "/dist/",

    build: {
        // output dir for production build
        outDir: "../../public/dist",
        emptyOutDir: true,

        // emit manifest so PHP can find the hashed files
        manifest: true,

        // Set to 0 to disable all asset inlining
        assetsInlineLimit: 0,

        // our entry
        rollupOptions: {
            input: resolve(__dirname, "src/main.js"),
            output: {
                manualChunks(id) {
                    // all third-party code will be in vendor chunk
                    if (id.includes("node_modules")) {
                        return "vendor";
                    }
                    // example on how to create another chunk
                    // if (id.includes('src/'components')) {
                    //   return 'components'
                    // }
                    // console.log(id)
                },
            },
        },
    },

    server: {
        // we need a strict port to match on PHP side
        // change freely, but update on PHP to match the same port
        // tip: choose a different port per project to run them at the same time
        strictPort: true,
        port: PORT,
        origin: `http://localhost:${PORT}`,
        cors: {
            origin: "http://localhost:8000",
        },
    },
});
