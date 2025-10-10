/** @type {import('tailwindcss').Config} */
module.exports = {
  mode: "jit",
  content: ["./*.{html,php,js}"],
  theme: {
    extend: {
      colors: {
        darkblue: "#1E3A8A",
        darkerblue: "#1B365D",
        orange: "#F97316",
      },
      fontFamily: {
        flama: "'Flama', sans-serif",
      },
    },
  },
  plugins: [],
};
