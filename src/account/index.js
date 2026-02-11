import { render } from '@wordpress/element';
import MyAccount from './MyAccount';
import './style.scss';

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('smc-account-root');
    if (root) {
        render(<MyAccount />, root);
    }
});
