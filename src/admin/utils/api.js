import apiFetch from '@wordpress/api-fetch';

/**
 * Fetch all quizzes.
 */
export const fetchQuizzes = () => {
    return apiFetch({ path: '/smc/v1/quizzes' });
};

/**
 * Fetch single quiz.
 * @param {number} id Quiz ID.
 */
export const fetchQuiz = (id) => {
    return apiFetch({ path: `/smc/v1/quizzes/${id}` });
};

/**
 * Create or Update Quiz.
 * @param {Object} data Quiz data.
 */
export const saveQuiz = (data) => {
    return apiFetch({
        path: '/smc/v1/quizzes' + (data.id ? `/${data.id}` : ''),
        method: 'POST', // or POST/PUT depending on your controller logic, usually REST uses POST for create, PUT/POST for update
        data,
    });
};
