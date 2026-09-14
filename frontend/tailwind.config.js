/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      backdropBlur: { glass: '12px' },
      backgroundColor: {
        glass: 'rgba(255, 255, 255, 0.08)',
        'glass-hover': 'rgba(255, 255, 255, 0.12)',
      },
      borderColor: {
        glass: 'rgba(255, 255, 255, 0.15)',
      },
      boxShadow: {
        glass: '0 8px 32px rgba(0, 0, 0, 0.37)',
        'glass-inset': 'inset 0 1px 0 rgba(255,255,255,0.1)',
      },
    },
  },
  plugins: [],
};
