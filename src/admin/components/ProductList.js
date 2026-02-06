import { useState, useEffect } from '@wordpress/element';
import { Button, Card, CardHeader, CardBody, CardFooter, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

// Use standard wp.apiFetch
const apiFetch = window.wp ? window.wp.apiFetch : null;

export default function ProductList({ onEdit, onCreate }) {
    const [products, setProducts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [notice, setNotice] = useState(null);

    const fetchProducts = () => {
        setLoading(true);
        apiFetch({ path: '/smc/v1/shop/admin/products' })
            .then(data => setProducts(data))
            .catch(err => setNotice({ status: 'error', text: err.message }))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        if (apiFetch) {
            fetchProducts();
        }
    }, []);

    const handleDelete = (id) => {
        if (!confirm(__('Are you sure you want to delete this product?', 'smc-viable'))) return;

        apiFetch({
            path: `/smc/v1/shop/admin/products/${id}`,
            method: 'DELETE'
        }).then(() => {
            setNotice({ status: 'success', text: __('Product deleted.', 'smc-viable') });
            fetchProducts();
        }).catch(err => setNotice({ status: 'error', text: err.message }));
    };

    if (loading) return <Spinner />;

    return (
        <div className="smc-admin-products">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                <h2 style={{ margin: 0 }}>{__('Shop Products', 'smc-viable')}</h2>
                <Button variant="primary" onClick={onCreate}>{__('Add New Product', 'smc-viable')}</Button>
            </div>

            {notice && (
                <Notice status={notice.status} onRemove={() => setNotice(null)} className="mb-4">
                    {notice.text}
                </Notice>
            )}

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: '20px' }}>
                {products.map(product => (
                    <Card key={product.id}>
                        <CardHeader>
                            <h4 style={{ margin: 0, fontWeight: 'bold' }}>{product.title}</h4>
                            <span style={{ fontSize: '0.8em', padding: '2px 6px', background: '#eee', borderRadius: '4px' }}>
                                {product.status}
                            </span>
                        </CardHeader>
                        <CardBody>
                            <div style={{ fontSize: '1.5em', fontWeight: 'bold', marginBottom: '10px' }}>${product.price}</div>
                            <div style={{ fontSize: '0.9em', color: '#666', minHeight: '3em' }}>
                                {product.product_type === 'plan' ? (__('Access Plan: ', 'smc-viable') + product.plan_level) : __('One-off Product', 'smc-viable')}
                            </div>
                        </CardBody>
                        <CardFooter>
                            <Button variant="secondary" onClick={() => onEdit(product.id)}>{__('Edit', 'smc-viable')}</Button>
                            <Button isDestructive variant="link" onClick={() => handleDelete(product.id)}>{__('Delete', 'smc-viable')}</Button>
                        </CardFooter>
                    </Card>
                ))}
            </div>

            {products.length === 0 && <p>{__('No products found.', 'smc-viable')}</p>}
        </div>
    );
}
