/** @type {import('tailwindcss').Config} */
module.exports = {
  mode: "jit",
  content: ["./*.{html,php,js}"],
  theme: {
    extend: {
      colors: {
        grey: "f#2f2f2;",
        darkblue: "#292d4e",
        darkerblue: "#171c40",
        orange: "#e09e40",
      },
      fontFamily: {
        flama: "'Flama', sans-serif",
      },
    },
  },
  plugins: [],
};
