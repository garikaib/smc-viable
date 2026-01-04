import { useState } from '@wordpress/element';
import QuizList from './components/QuizList';
import QuizEditor from './components/QuizEditor';

export default function App() {
    const [view, setView] = useState('list'); // 'list' | 'create' | 'edit'
    const [currentQuizId, setCurrentQuizId] = useState(null);

    const handleEdit = (id) => {
        setCurrentQuizId(id);
        setView('edit');
    };

    const handleCreate = () => {
        setCurrentQuizId(null);
        setView('create');
    };

    const handleBack = () => {
        setView('list');
        setCurrentQuizId(null);
    };

    return (
        <div className="wrap">
            <h1 className="wp-heading-inline">SMC Quiz Manager</h1>
            <hr className="wp-header-end" />

            <div style={{ marginTop: '20px', width: '100%' }}>
                {view === 'list' && (
                    <QuizList onEdit={handleEdit} onCreate={handleCreate} />
                )}
                {(view === 'edit' || view === 'create') && (
                    <QuizEditor quizId={currentQuizId} onBack={handleBack} />
                )}
            </div>
        </div>
    );
}
