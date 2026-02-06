import { createRoot } from '@wordpress/element';
import App from './App';
import './style.scss';

const container = document.getElementById('smc-shop-root');
if (container) {
    const root = createRoot(container);
    root.render(<App />);
}
