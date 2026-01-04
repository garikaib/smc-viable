import { createRoot } from '@wordpress/element';
import QuizRunner from './frontend/components/QuizRunner';

import './index.scss'; // Main styles
import './frontend/components/style.scss'; // Component styles

document.addEventListener('DOMContentLoaded', () => {
    const quizElements = document.querySelectorAll('.smc-quiz-root');
    quizElements.forEach((el) => {
        const quizId = el.getAttribute('data-quiz-id');
        if (quizId) {
            if (createRoot) {
                const root = createRoot(el);
                root.render(<QuizRunner quizId={quizId} />);
            } else {
                // Fallback
                const { render } = require('@wordpress/element');
                render(<QuizRunner quizId={quizId} />, el);
            }
        }
    });
});
