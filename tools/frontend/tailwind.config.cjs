module.exports = {
    content: ['../../resources/views/**/*.blade.php', '../../public/js/*.js', '../../public/assets/js/*.js'],
    theme: {extend: {fontFamily: {sans: ['Arial', 'Helvetica', 'sans-serif']}, colors: {app: '#f4ecda'}}},
    safelist: [
        {pattern: /^(bg|text|border)-(blue|slate|gray|emerald|amber|rose|red|green|yellow|indigo|purple|teal)-(50|100|200|300|400|500|600|700|800|900)$/, variants: ['hover', 'focus']},
        'opacity-0', 'opacity-100', '-translate-y-2', 'translate-y-0', 'hidden', 'flex', 'grid'
    ],
    plugins: []
};
