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
        themes: [
            {
                light: {
                    ...require("daisyui/src/theming/themes")["light"],
                    "primary": "#0E7673",
                    "primary-focus": "#0b5e5c",
                    "secondary": "#A1232A",
                    "accent": "#D48900",
                    "neutral": "#2a323c",
                    "base-100": "#ffffff",
                    "base-200": "#F9FAFB",
                    "base-300": "#F3F4F6",
                    "info": "#3abff8",
                    "success": "#36d399",
                    "warning": "#fbbd23",
                    "error": "#f87272",
                },
            },
            {
                dark: {
                    ...require("daisyui/src/theming/themes")["dark"],
                    "primary": "#0E7673",
                    "primary-focus": "#0b5e5c",
                    "secondary": "#A1232A",
                    "accent": "#D48900",
                    "neutral": "#2a323c",
                    "base-100": "#0D1316", // Deep Teal/Charcoal (Black Model)
                    "base-200": "#151C20", // Surface
                    "base-300": "#1d262b", // Highlight
                    "base-content": "#eef1f5", // Off-white text
                    "info": "#3abff8",
                    "success": "#36d399",
                    "warning": "#fbbd23",
                    "error": "#f87272",
                },
            },
        ],
    },
    theme: {
        extend: {
            colors: {
                primary: "#0E7673", // Explicit fallback
            }
        }
    },
    corePlugins: {
        preflight: false, // Disable base reset to avoid breaking WP admin
    }
}
