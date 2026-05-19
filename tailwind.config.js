import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
    ],

    theme: {
        extend: {
            fontFamily: {
                maven: ['"Maven Pro"', "sans-serif"],
                inter: ["Inter", "sans-serif"],
            },
            colors: {
                "gkr-dark": "#011936", // Je knop-kleur
                "gkr-accent": "#94b8ff", // Je accent-kleur
            },
        },
    },

    plugins: [forms],
};
