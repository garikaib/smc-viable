import { render } from '@wordpress/element';
import QuizRunner from './frontend/components/QuizRunner';

document.addEventListener('DOMContentLoaded', () => {
    const quizElements = document.querySelectorAll('.smc-quiz-root');
    quizElements.forEach((el) => {
        const quizId = el.getAttribute('data-quiz-id');
        if (quizId) {
            render(<QuizRunner quizId={quizId} />, el);
        }
    });
});
