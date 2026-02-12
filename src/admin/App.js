import { useState, useEffect } from '@wordpress/element';
import QuizList from './components/QuizList';
import QuizEditor from './components/QuizEditor';
import LeadList from './components/LeadList';
import ProductList from './components/ProductList';
import ProductEditor from './components/ProductEditor';
import OrderList from './components/OrderList';
import { __ } from '@wordpress/i18n';

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
        }
    }, []);

    const handleQuizEdit = (id) => {
        setCurrentId(id);
        setView('edit');
    };

    const handleQuizCreate = () => {
        setCurrentId(null);
        setView('create');
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
                    <QuizEditor quizId={currentId} onBack={handleBack} />
                )}
            </div>
        </div>
    );
}
