import { useState, useEffect } from '@wordpress/element';
import { Spinner, Button, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import ProductList from './pages/ProductList';
import ProductDetails from './pages/ProductDetails';
import Cart from './pages/Cart';
import MyLearning from './pages/MyLearning';

export default function App() {
    const [view, setView] = useState('shop'); // 'shop' | 'cart' | 'learning' | 'product-details' | 'success'
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [cart, setCart] = useState([]);
    const [products, setProducts] = useState([]);
    const [userAccess, setUserAccess] = useState(null);
    const [loading, setLoading] = useState(true);
    const [notice, setNotice] = useState(null);

    useEffect(() => {
        // Fetch Products & User Access
        Promise.all([
            apiFetch({ path: '/smc/v1/shop/products' }),
            apiFetch({ path: '/smc/v1/shop/access' }).catch(() => null)
        ]).then(([prodData, accessData]) => {
            setProducts(prodData);
            setUserAccess(accessData);

            // Handle Deep Linking
            const params = new URLSearchParams(window.location.search);
            const productSlug = params.get('product'); // using ?product=slug
            if (productSlug) {
                const product = prodData.find(p => p.slug === productSlug);
                if (product) {
                    setSelectedProduct(product);
                    setView('product-details');
                }
            }
        }).finally(() => setLoading(false));

        // Listen for popstate to handle back/forward navigation
        const handlePopState = () => {
            const params = new URLSearchParams(window.location.search);
            const productSlug = params.get('product');
            if (productSlug) {
                // We need products here, but they might not be loaded if this runs separately.
                // However, popstate usually happens after load.
                // For simplicity in this lightweight app, we rely on the logic that products are stable.
                // Ideally, we'd depend on `products` state, but let's just trigger a re-eval via view state if needed.
                // Actually, let's keep it simple: URL drives init state, internal nav drives logic + history pushState.
                setLoading(true); // crude reload to sync
                window.location.reload();
            } else {
                setView('shop');
                setSelectedProduct(null);
            }
        };
        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);

    }, []);

    const navigateToProduct = (product) => {
        setSelectedProduct(product);
        setView('product-details');
        const url = new URL(window.location);
        url.searchParams.set('product', product.slug);
        window.history.pushState({}, '', url);
    };

    const navigateToShop = () => {
        setSelectedProduct(null);
        setView('shop');
        const url = new URL(window.location);
        url.searchParams.delete('product');
        window.history.pushState({}, '', url);
    };

    const addToCart = (product) => {
        if (!cart.find(p => p.id === product.id)) {
            setCart([...cart, product]);
            setNotice({ status: 'success', text: `Added ${product.title} to cart.` });
            setTimeout(() => setNotice(null), 3000);
        }
    };

    const removeFromCart = (productId) => {
        setCart(cart.filter(p => p.id !== productId));
    };

    const handleCheckout = async () => {
        setLoading(true);
        try {
            const order = await apiFetch({
                path: '/smc/v1/shop/checkout',
                method: 'POST',
                data: {
                    items: cart.map(p => p.id),
                    payment_method: 'paynow'
                }
            });

            // Init Payment
            const payment = await apiFetch({
                path: '/smc/v1/shop/paynow/init',
                method: 'POST',
                data: { order_id: order.order_id }
            });

            if (payment.success) {
                // Success! Clear cart and show success
                setCart([]);

                // Refresh access
                const access = await apiFetch({ path: '/smc/v1/shop/access' });
                setUserAccess(access);

                setView('learning');
                setNotice({ status: 'success', text: 'Order successful! content unlocked.' });
            }

        } catch (err) {
            setNotice({ status: 'error', text: err.message || 'Checkout failed' });
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="smc-shop-app">
            <header className="smc-header">
                <div className="smc-brand">
                    <h1>SMC HUB</h1>
                    <p>Business Science for the African Context</p>
                </div>
                <nav className="smc-nav">
                    <button className={`smc-btn-nav ${view === 'shop' ? 'active' : ''}`} onClick={() => setView('shop')}>SHOP</button>
                    <button className={`smc-btn-nav ${view === 'learning' ? 'active' : ''}`} onClick={() => setView('learning')}>MY LEARNING</button>
                    <button className={`smc-btn-nav ${view === 'cart' ? 'active' : ''}`} onClick={() => setView('cart')}>
                        CART {cart.length > 0 && <span className="cart-count">{cart.length}</span>}
                    </button>
                </nav>
            </header>

            {notice && (
                <Notice status={notice.status} onRemove={() => setNotice(null)} className="mb-6">
                    {notice.text}
                </Notice>
            )}

            {loading ? <Spinner /> : (
                <main>
                    {view === 'shop' && (
                        <ProductList
                            products={products}
                            addToCart={(p, viewDetails) => {
                                if (viewDetails) {
                                    navigateToProduct(p);
                                } else {
                                    addToCart(p);
                                }
                            }}
                            userAccess={userAccess}
                        />
                    )}
                    {view === 'product-details' && selectedProduct && (
                        <ProductDetails
                            product={selectedProduct}
                            onBack={navigateToShop}
                            addToCart={addToCart}
                            userAccess={userAccess}
                        />
                    )}
                    {view === 'cart' && <Cart cart={cart} onRemove={removeFromCart} onCheckout={handleCheckout} loading={loading} />}
                    {view === 'learning' && <MyLearning userAccess={userAccess} />}
                </main>
            )}
        </div>
    );
}
