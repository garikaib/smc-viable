import { useState, useEffect } from '@wordpress/element';
import QuizList from './components/QuizList';
import QuizEditor from './components/QuizEditor';
import LeadList from './components/LeadList';
import ProductList from './components/ProductList';
import ProductEditor from './components/ProductEditor';
import OrderList from './components/OrderList';
import { __ } from '@wordpress/i18n';

const getQuizIdFromParams = (params) => {
    const raw = params.get('quiz_id');
    const parsed = Number.parseInt(raw, 10);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
};

const toSlug = (input = '') => {
    const plain = String(input).replace(/<[^>]+>/g, '').trim().toLowerCase();
    return plain
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 80);
};

const updateQuizUrlState = ({ view, quizId = null, title = '' }) => {
    const params = new URLSearchParams(window.location.search);

    if (view === 'edit' && quizId) {
        params.set('smc_view', 'edit');
        params.set('quiz_id', String(quizId));
        const slug = toSlug(title);
        if (slug) params.set('smc_slug', slug);
        else params.delete('smc_slug');
        params.delete('smc_quiz');
    } else if (view === 'create') {
        params.set('smc_view', 'create');
        params.delete('quiz_id');
        params.delete('smc_slug');
        params.delete('smc_quiz');
    } else {
        params.delete('smc_view');
        params.delete('quiz_id');
        params.delete('smc_slug');
        params.delete('smc_quiz');
    }

    const query = params.toString();
    const nextUrl = query ? `${window.location.pathname}?${query}` : window.location.pathname;
    window.history.replaceState({}, '', nextUrl);
};

export default function App() {
    const [view, setView] = useState('list'); // 'list' | 'create' | 'edit' | 'leads' | 'products' | 'product-create' | 'product-edit' | 'orders'
    const [currentId, setCurrentId] = useState(null);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const page = params.get('page');
        if (page === 'smc-leads') {
            setView('leads');
        } else if (page === 'smc-products') {
            setView('products');
        } else if (page === 'smc-orders') {
            setView('orders');
        } else {
            const requestedQuizId = getQuizIdFromParams(params);
            const requestedView = params.get('smc_view');
            if (requestedQuizId) {
                setCurrentId(requestedQuizId);
                setView('edit');
            } else if (requestedView === 'create') {
                setCurrentId(null);
                setView('create');
            }
        }
    }, []);

    const handleQuizEdit = (id, meta = {}) => {
        setCurrentId(id);
        setView('edit');
        updateQuizUrlState({ view: 'edit', quizId: id, title: meta.title || '' });
    };

    const handleQuizCreate = () => {
        setCurrentId(null);
        setView('create');
        updateQuizUrlState({ view: 'create' });
    };

    const handleQuizPersisted = (id, meta = {}) => {
        if (!id) return;
        setCurrentId(id);
        setView('edit');
        updateQuizUrlState({ view: 'edit', quizId: id, title: meta.title || '' });
    };

    const handleProductEdit = (id) => {
        setCurrentId(id);
        setView('product-edit');
    };

    const handleProductCreate = () => {
        setCurrentId(null);
        setView('product-create');
    };

    const handleBack = () => {
        const params = new URLSearchParams(window.location.search);
        const page = params.get('page');

        if (page === 'smc-products') {
            setView('products');
        } else if (page === 'smc-orders') {
            setView('orders');
        } else {
            setView('list');
            updateQuizUrlState({ view: 'list' });
        }
        setCurrentId(null);
    };

    return (
        <div className="smc-hub-admin">
            <section className="smc-admin-hero">
                <div>
                    <p className="smc-admin-eyebrow">{__('SMC Assessment Center', 'smc-viable')}</p>
                    <h1>{__('Visual Quiz Builder', 'smc-viable')}</h1>
                    <p>{__('Design, configure and publish assessment journeys with stronger control over scoring, plan access, and report behavior.', 'smc-viable')}</p>
                </div>
            </section>

            <div className="smc-admin-shell">
                {/* Leads View */}
                {view === 'leads' && <LeadList />}

                {/* Orders View */}
                {view === 'orders' && <OrderList />}

                {/* Products Views */}
                {view === 'products' && (
                    <ProductList onEdit={handleProductEdit} onCreate={handleProductCreate} />
                )}
                {(view === 'product-edit' || view === 'product-create') && (
                    <ProductEditor productId={currentId} onBack={handleBack} />
                )}

                {/* Quiz Views */}
                {view === 'list' && (
                    <QuizList onEdit={handleQuizEdit} onCreate={handleQuizCreate} />
                )}
                {(view === 'edit' || view === 'create') && (
                    <QuizEditor quizId={currentId} onBack={handleBack} onPersistedQuiz={handleQuizPersisted} />
                )}
            </div>
        </div>
    );
}
