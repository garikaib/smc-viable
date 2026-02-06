import { useState, useEffect } from '@wordpress/element';
import { Button, TextControl, TextareaControl, SelectControl, PanelBody, Spinner, Notice, ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const apiFetch = window.wp ? window.wp.apiFetch : null;

export default function ProductEditor({ productId, onBack }) {
    const [loading, setLoading] = useState(productId ? true : false);
    const [saving, setSaving] = useState(false);
    const [notice, setNotice] = useState(null);

    const [title, setTitle] = useState('');
    const [content, setContent] = useState('');
    const [price, setPrice] = useState(0);
    const [type, setType] = useState('plan');
    const [planLevel, setPlanLevel] = useState('basic');
    const [status, setStatus] = useState('publish');

    useEffect(() => {
        if (productId && apiFetch) {
            apiFetch({ path: `/smc/v1/shop/admin/products/${productId}` })
                .then(product => {
                    setTitle(product.title);
                    setContent(product.content);
                    setPrice(product.price);
                    setType(product.product_type);
                    setPlanLevel(product.plan_level);
                    setStatus(product.status);
                })
                .catch(err => setNotice({ status: 'error', text: err.message }))
                .finally(() => setLoading(false));
        }
    }, [productId]);

    const handleSave = () => {
        setSaving(true);
        const data = {
            title,
            content,
            price,
            product_type: type,
            plan_level: planLevel,
            status
        };

        const path = productId
            ? `/smc/v1/shop/admin/products/${productId}`
            : '/smc/v1/shop/admin/products';

        const method = productId ? 'PUT' : 'POST';

        apiFetch({ path, method, data })
            .then(() => {
                setNotice({ status: 'success', text: __('Product saved.', 'smc-viable') });
                setTimeout(onBack, 1500);
            })
            .catch(err => setNotice({ status: 'error', text: err.message }))
            .finally(() => setSaving(false));
    };

    if (loading) return <Spinner />;

    return (
        <div style={{ maxWidth: '800px' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '15px', marginBottom: '20px' }}>
                <Button variant="tertiary" onClick={onBack}>&larr; {__('Back', 'smc-viable')}</Button>
                <h2 style={{ margin: 0 }}>{productId ? __('Edit Product', 'smc-viable') : __('Add New Product', 'smc-viable')}</h2>
            </div>

            {notice && <Notice status={notice.status} onRemove={() => setNotice(null)}>{notice.text}</Notice>}

            <PanelBody title={__('Product Details', 'smc-viable')}>
                <TextControl
                    label={__('Product Title', 'smc-viable')}
                    value={title}
                    onChange={setTitle}
                />
                <TextareaControl
                    label={__('Description', 'smc-viable')}
                    value={content}
                    onChange={setContent}
                    rows={5}
                />
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                    <TextControl
                        label={__('Price ($)', 'smc-viable')}
                        type="number"
                        value={price}
                        onChange={val => setPrice(parseFloat(val))}
                    />
                    <SelectControl
                        label={__('Status', 'smc-viable')}
                        value={status}
                        options={[
                            { label: 'Published', value: 'publish' },
                            { label: 'Draft', value: 'draft' },
                        ]}
                        onChange={setStatus}
                    />
                </div>
            </PanelBody>

            <PanelBody title={__('Product Logic', 'smc-viable')} initialOpen={true}>
                <SelectControl
                    label={__('Product Type', 'smc-viable')}
                    value={type}
                    options={[
                        { label: 'Membership Plan', value: 'plan' },
                        { label: 'One-off / Single Module', value: 'single' },
                    ]}
                    onChange={setType}
                />

                {type === 'plan' && (
                    <SelectControl
                        label={__('Plan Level Granted', 'smc-viable')}
                        value={planLevel}
                        options={[
                            { label: 'Basic', value: 'basic' },
                            { label: 'Premium', value: 'premium' },
                        ]}
                        onChange={setPlanLevel}
                        help={__('Users who buy this will be upgraded to this plan level.', 'smc-viable')}
                    />
                )}

                {type === 'single' && (
                    <p style={{ fontStyle: 'italic', color: '#666' }}>
                        {__('Single module access control can be linked via Training Material meta.', 'smc-viable')}
                    </p>
                )}
            </PanelBody>

            <div style={{ marginTop: '20px' }}>
                <Button variant="primary" onClick={handleSave} isBusy={saving}>
                    {__('Save Product', 'smc-viable')}
                </Button>
            </div>
        </div>
    );
}
