/**
 * Admin Entry Point
 */
import { render } from '@wordpress/element';
import App from './App';

import './style.scss'; // Assuming we might add some styles later

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('smc-quiz-admin-root');
    if (root) {
        render(<App />, root);
    }
});
