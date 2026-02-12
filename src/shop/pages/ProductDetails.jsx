import { ArrowLeft } from 'lucide-react';

export default function ProductDetails({ product, onBack, onProductAction, getProductAction }) {
    if (!product) return null;

    const action = getProductAction(product);
    const buttonLabel = action?.label || 'Add to Cart';
    const buttonDisabled = Boolean(action?.disabled);

    return (
        <div className="smc-product-details fade-in">
            <button className="btn-back" onClick={onBack}>
                <ArrowLeft size={20} /> Back to Shop
            </button>

            <div className="details-grid">
                <div className="details-image" style={{ backgroundImage: `url(${product.image})` }}>
                    <div className="details-overlay">
                        <span className="badge">
                            {product.type === 'plan' ? 'Membership Tier' : (product.type === 'course' ? 'Course Module' : 'Professional Service')}
                        </span>
                    </div>
                </div>

                <div className="details-content">
                    <h1 className="details-title">{product.title}</h1>
                    <div className="details-price">${product.price}</div>

                    <div className="details-actions">
                        <button
                            className={`smc-btn-primary ${buttonDisabled ? 'disabled' : ''}`}
                            disabled={buttonDisabled}
                            onClick={() => onProductAction(product)}
                        >
                            {buttonLabel}
                        </button>
                    </div>

                    <div className="details-description">
                        <h3>About this {product.type === 'plan' ? 'Plan' : (product.type === 'course' ? 'Module' : (product.type === 'assessment' ? 'Assessment' : 'Service'))}</h3>
                        <p>{product.long_description || product.description}</p>
                    </div>

                    {product.features && product.features.length > 0 && (
                        <div className="details-features">
                            <h3>What's Included</h3>
                            <ul>
                                {product.features.map((feature, i) => (
                                    <li key={i}>{feature}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
