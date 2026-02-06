export default function ProductList({ products, addToCart, userAccess }) {

    // Helper to check if user already owns the plan
    const hasAccess = (plan) => {
        if (!userAccess) return false;
        if (userAccess.plan === 'premium') return true;
        if (userAccess.plan === 'basic' && plan === 'basic') return true;
        return false;
    };

    return (
        <div className="grid">
            {products.map(product => {
                const isOwned = product.type === 'plan' && hasAccess(product.plan_level);

                return (
                    <div key={product.id} className="card" onClick={() => addToCart(product, true)}>
                        <div className="card-image-wrapper">
                            <div className="card-image" style={{ backgroundImage: `url(${product.image})` }}></div>
                            {isOwned && <div className="card-badge owned">Owned</div>}
                        </div>

                        <div className="card-body">
                            <div className="card-meta">
                                <span className={`type-tag ${product.type}`}>{product.type === 'plan' ? 'Membership' : 'Module'}</span>
                            </div>
                            <h3>{product.title}</h3>
                            <div className="card-footer">
                                <span className="price">${product.price}</span>
                                <button className="btn-add">
                                    <span className="icon">+</span>
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
