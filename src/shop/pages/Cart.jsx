export default function Cart({ cart, onRemove, onCheckout, loading }) {
    const total = cart.reduce((acc, item) => acc + item.price, 0);

    if (cart.length === 0) {
        return (
            <div className="empty-cart-state">
                <h2>Your cart is empty.</h2>
                <p>Start your journey by exploring our business modules.</p>
            </div>
        );
    }

    return (
        <div className="cart-container">
            <div className="cart-items">
                <h2>Your Selection</h2>
                {cart.map(item => (
                    <div key={item.id} className="cart-item">
                        <div className="item-info">
                            <h4>{item.title}</h4>
                            <span className="price">${item.price.toFixed(2)}</span>
                        </div>
                        <button onClick={() => onRemove(item.id)} className="btn-remove">Remove</button>
                    </div>
                ))}
            </div>

            <div className="cart-summary">
                <h2>Summary</h2>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '15px' }}>
                    <div className="summary-row">
                        <span>Subtotal</span>
                        <span>${total.toFixed(2)}</span>
                    </div>
                    <div className="summary-row">
                        <span>Tax</span>
                        <span>$0.00</span>
                    </div>
                    <div className="total-row">
                        <span>Total Due</span>
                        <span className="amount">${total.toFixed(2)}</span>
                    </div>
                </div>
                <button className="btn-checkout" onClick={onCheckout} disabled={loading}>
                    {loading ? 'Processing...' : 'Complete Purchase'}
                </button>
                <p className="security-note">
                    Secured by Paynow. Instant access granted upon completion.
                </p>
            </div>
        </div>
    );
}
