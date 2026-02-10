const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        ...defaultConfig.entry,
        index: path.resolve(process.cwd(), 'src', 'index.js'),
        view: path.resolve(process.cwd(), 'src', 'view.js'),
        admin: path.resolve(process.cwd(), 'src', 'admin', 'index.js'),
        shop: path.resolve(process.cwd(), 'src', 'shop', 'index.js'),
        student: path.resolve(process.cwd(), 'src', 'student', 'index.js'),
        instructor: path.resolve(process.cwd(), 'src', 'instructor', 'index.js'),
    },
};
