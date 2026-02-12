export default function ProductList({ products, onViewDetails, onProductAction, getProductAction, userAccess }) {

    const isCurrentPlan = (plan) => {
        if (!userAccess || !userAccess.plan) return false;
        return userAccess.plan === plan;
    };

    const isProductOwned = (product) => {
        if (product?.is_owned === true) return true;
        if (product?.type === 'plan') return isCurrentPlan(product.plan_level);
        return false;
    };

    const getOwnedLabel = (product) => {
        if (product?.type === 'plan') return 'Current Plan';
        return 'In Your Library';
    };

    return (
        <div className="grid">
            {products.map(product => {
            const isOwned = isProductOwned(product);
            const action = getProductAction(product);
            const buttonLabel = action?.label || 'Add to Cart';
            const buttonDisabled = Boolean(action?.disabled);

                return (
                    <div key={product.id} className="card" onClick={() => onViewDetails(product)}>
                        <div className="card-image-wrapper">
                            <div className="card-image" style={{ backgroundImage: `url(${product.image})` }}></div>
                            {isOwned && <div className="card-badge owned">{getOwnedLabel(product)}</div>}
                        </div>

                        <div className="card-body">
                            <div className="card-meta">
                                <span className={`type-tag ${product.type}`}>
                                    {product.type === 'plan'
                                        ? 'Membership'
                                        : (product.type === 'course'
                                            ? 'Module'
                                            : (product.type === 'assessment' ? 'Assessment' : 'Service'))}
                                </span>
                            </div>
                            <h3>{product.title}</h3>
                            <div className="card-footer">
                                <span className="price">${product.price}</span>
                                <button
                                    className={`btn-action ${buttonDisabled ? 'disabled' : ''}`}
                                    disabled={buttonDisabled}
                                    onClick={(event) => {
                                        event.preventDefault();
                                        event.stopPropagation();
                                        onProductAction(product);
                                    }}
                                >
                                    {buttonLabel}
                                </button>
                            </div>
                        </div>
                    </div>
                );
            })}

            {products.length === 0 && <p className="no-products">Loading catalog...</p>}
        </div>
    );
}
