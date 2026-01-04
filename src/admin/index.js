/**
 * Admin Entry Point
 */
import { createRoot } from '@wordpress/element';
import App from './App';

import './style.scss';

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('smc-quiz-admin-root');
    if (container) {
        if (createRoot) {
            const root = createRoot(container);
            root.render(<App />);
        } else {
            // Fallback for older WP versions
            const { render } = require('@wordpress/element');
            render(<App />, container);
        }
    }
});
