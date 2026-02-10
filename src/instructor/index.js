import { createRoot } from '@wordpress/element';
import App from './App';
import './style.scss';

document.addEventListener('DOMContentLoaded', () => {
    const rootElement = document.getElementById('smc-instructor-root');
    if (rootElement) {
        createRoot(rootElement).render(<App />);
    }
});
