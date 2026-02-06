import { ArrowLeft } from 'lucide-react';

export default function ProductDetails({ product, onBack, addToCart, userAccess }) {
    if (!product) return null;

    const isOwned = () => {
        if (!userAccess) return false;
        if (product.type === 'plan') {
            if (userAccess.plan === 'premium') return true;
            if (userAccess.plan === 'basic' && product.plan_level === 'basic') return true;
        }
        // Todo: check single access when implemented in backend
        return false;
    };

    const owned = isOwned();

    return (
        <div className="smc-product-details fade-in">
            <button className="btn-back" onClick={onBack}>
                <ArrowLeft size={20} /> Back to Shop
            </button>

            <div className="details-grid">
                <div className="details-image" style={{ backgroundImage: `url(${product.image})` }}>
                    <div className="details-overlay">
                        <span className="badge">{product.type === 'plan' ? 'Membership Tier' : 'Course Module'}</span>
                    </div>
                </div>

                <div className="details-content">
                    <h1 className="details-title">{product.title}</h1>
                    <div className="details-price">${product.price}</div>

                    <div className="details-actions">
                        {owned ? (
                            <button className="smc-btn-primary disabled" disabled>Already Owned</button>
                        ) : (
                            <button className="smc-btn-primary" onClick={() => addToCart(product)}>Add to Cart</button>
                        )}
                    </div>

                    <div className="details-description">
                        <h3>About this {product.type === 'plan' ? 'Plan' : 'Module'}</h3>
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
