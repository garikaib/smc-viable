import { __ } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { fetchQuizzes } from '../utils/api';

export default function QuizList({ onEdit, onCreate }) {
    const [quizzes, setQuizzes] = useState([]);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        fetchQuizzes()
            .then((data) => {
                setQuizzes(data);
            })
            .catch((err) => console.error(err))
            .finally(() => setIsLoading(false));
    }, []);

    if (isLoading) {
        return <Spinner />;
    }

    return (
        <div className="smc-quiz-list">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                <h2>{__('All Quizzes', 'smc-viable')}</h2>
                <Button isPrimary onClick={onCreate}>{__('Create New Quiz', 'smc-viable')}</Button>
            </div>

            {quizzes.length === 0 ? (
                <p>{__('No quizzes found. Create one!', 'smc-viable')}</p>
            ) : (
                <table className="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>{__('Title', 'smc-viable')}</th>
                            <th>{__('Date', 'smc-viable')}</th>
                            <th>{__('Actions', 'smc-viable')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {quizzes.map((quiz) => (
                            <tr key={quiz.id}>
                                <td>
                                    <strong>{quiz.title?.rendered || __('Untitled', 'smc-viable')}</strong>
                                </td>
                                <td>{new Date(quiz.date).toLocaleDateString()}</td>
                                <td>
                                    <Button isLink onClick={() => onEdit(quiz.id)}>{__('Edit', 'smc-viable')}</Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}
