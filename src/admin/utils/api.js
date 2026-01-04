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
        method: 'POST',
        data,
    });
};

export const deleteQuiz = (id) => {
    return apiFetch({
        path: `/smc/v1/quizzes/${id}`,
        method: 'DELETE',
    });
};

export const exportQuizzes = async () => {
    const quizzes = await fetchQuizzes();
    return {
        version: '1.0.0',
        exportedAt: new Date().toISOString(),
        assessments: quizzes.map(q => ({
            title: q.title?.rendered || 'Untitled',
            questions: q.meta?._smc_quiz_questions || []
        }))
    };
};

export const importQuizzes = async (jsonData) => {
    const results = [];
    for (const assessment of jsonData.assessments) {
        const result = await apiFetch({
            path: '/smc/v1/quizzes',
            method: 'POST',
            data: {
                title: assessment.title,
                questions: assessment.questions
            }
        });
        results.push(result);
    }
    return results;
};
