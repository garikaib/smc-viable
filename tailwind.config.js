/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        "./src/**/*.{js,jsx,ts,tsx}",
    ],
    // Important strategy to increase specificity and avoid theme conflicts
    // We will wrap our app in a specific ID or class
    important: true,
    theme: {
        extend: {},
    },
    plugins: [
        require('daisyui'),
    ],
    daisyui: {
        logs: false,
        themes: ["light"], // Enforce light theme or make configurable
    },
    corePlugins: {
        preflight: false, // Disable base reset to avoid breaking WP admin
    }
}
