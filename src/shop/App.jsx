import { useState, useEffect } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { CheckCircle, AlertCircle } from 'lucide-react';
import apiFetch from '@wordpress/api-fetch';
import ProductList from './pages/ProductList';
import ProductDetails from './pages/ProductDetails';
import Cart from './pages/Cart';
import MyLearning from './pages/MyLearning';

const CART_STORAGE_KEY = 'smc_shop_cart_v1';

const readStoredCart = () => {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const raw = window.localStorage.getItem(CART_STORAGE_KEY);
        if (!raw) {
            return [];
        }

        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed.filter((item) => Number(item?.id) > 0) : [];
    } catch (e) {
        return [];
    }
};

export default function App() {
    const [view, setView] = useState('shop'); // 'shop' | 'cart' | 'learning' | 'product-details' | 'success'
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [cart, setCart] = useState(() => readStoredCart());
    const [products, setProducts] = useState([]);
    const [userAccess, setUserAccess] = useState(null);
    const [loading, setLoading] = useState(true);
    const [notice, setNotice] = useState(null);
    const fetchAccess = () => apiFetch({ path: `/smc/v1/shop/access?ts=${Date.now()}` }).catch(() => null);
    const fetchProducts = () => apiFetch({ path: '/smc/v1/shop/products' });
    const planTierLabels = window.smcShopData?.planTiers || {};
    const isLoggedIn = Number(userAccess?.user_id || window.smcShopData?.current_user_id || 0) > 0;
    const shopBasePath = (() => {
        const fallback = '/shop/';
        if (!window.smcShopData?.shop_url) {
            return fallback;
        }

        try {
            const parsed = new URL(window.smcShopData.shop_url, window.location.origin);
            const path = parsed.pathname || fallback;
            return path.endsWith('/') ? path : `${path}/`;
        } catch (e) {
            return fallback;
        }
    })();

    const readProductSlugFromLocation = () => {
        const currentPath = window.location.pathname;
        const normalizedBase = shopBasePath.endsWith('/') ? shopBasePath : `${shopBasePath}/`;

        if (currentPath.startsWith(normalizedBase)) {
            const remainder = currentPath.slice(normalizedBase.length).replace(/^\/+|\/+$/g, '');
            if (remainder !== '' && !remainder.includes('/')) {
                return decodeURIComponent(remainder);
            }
        }

        // Backward compatibility for old links like /shop/?product=slug
        const params = new URLSearchParams(window.location.search);
        return params.get('product');
    };

    const syncRouteFromLocation = (catalog) => {
        const productSlug = readProductSlugFromLocation();
        if (!productSlug) {
            setView('shop');
            setSelectedProduct(null);
            return;
        }

        const product = catalog.find((p) => p.slug === productSlug);
        if (product) {
            setSelectedProduct(product);
            setView('product-details');
            return;
        }

        setSelectedProduct(null);
        setView('shop');
    };

    useEffect(() => {
        // Fetch Products & User Access
        Promise.all([
            fetchProducts(),
            fetchAccess()
        ]).then(([prodData, accessData]) => {
            setProducts(prodData);
            setUserAccess(accessData);
            syncRouteFromLocation(prodData);
        }).finally(() => setLoading(false));

    }, []);

    useEffect(() => {
        // Listen for popstate to handle back/forward navigation
        const handlePopState = () => {
            syncRouteFromLocation(products);
        };
        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);
    }, [products]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        try {
            if (cart.length > 0) {
                window.localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart));
            } else {
                window.localStorage.removeItem(CART_STORAGE_KEY);
            }
        } catch (e) {
            // Ignore storage quota/private mode errors.
        }
    }, [cart]);

    useEffect(() => {
        if (!isLoggedIn && view === 'learning') {
            setView('shop');
        }
    }, [isLoggedIn, view]);

    const navigateToProduct = (product) => {
        setSelectedProduct(product);
        setView('product-details');
        window.history.pushState({}, '', `${shopBasePath}${encodeURIComponent(product.slug)}/`);
    };

    const navigateToShop = () => {
        setSelectedProduct(null);
        setView('shop');
        window.history.pushState({}, '', shopBasePath);
    };

    const showNotice = (status, title, text, duration = 3000) => {
        setNotice({ status, title, text, duration });
        window.setTimeout(() => setNotice(null), duration);
    };

    const refreshShopState = async () => {
        const [nextProducts, nextAccess] = await Promise.all([fetchProducts(), fetchAccess()]);
        setProducts(nextProducts);
        setUserAccess(nextAccess);
        if (selectedProduct) {
            const refreshedSelected = nextProducts.find((product) => product.id === selectedProduct.id) || null;
            setSelectedProduct(refreshedSelected);
            if (!refreshedSelected && view === 'product-details') {
                setView('shop');
            }
        }
        return { nextProducts, nextAccess };
    };

    const isCourseProduct = (product) => Boolean(
        product?.is_course_product ||
        Number(product?.linked_course_id || 0) > 0 ||
        product?.type === 'course' ||
        product?.type === 'single'
    );

    const getProductAction = (product) => {
        if (!product) {
            return { key: 'add_to_cart', label: 'Add to Cart', disabled: false };
        }

        const isEnrollItem = isCourseProduct(product) || product.type === 'assessment';

        // Plan product logic — only for actual membership tiers, never for course-linked products
        const isActualPlan = product.type === 'plan' && !isEnrollItem && Number(product.linked_course_id || 0) <= 0;
        if (isActualPlan) {
            if (product.plan_status?.is_current) {
                return { key: 'owned', label: 'Current Plan', disabled: true };
            }
            if (product.plan_status?.is_lower) {
                return { key: 'downgrade', label: 'Not Available', disabled: false };
            }
            if (product.plan_status?.is_higher) {
                return { key: 'upgrade_plan', label: 'Upgrade Plan', disabled: false };
            }
        }

        if (isEnrollItem) {
            if (product.is_owned) {
                return { key: 'already_enrolled', label: 'Already Enrolled', disabled: true };
            }

            if (product.can_enroll_now) {
                return { key: 'plan_enroll', label: 'Enroll', disabled: false };
            }

            if (product.requires_upgrade || Number(product.recommended_upgrade_plan_product_id || 0) > 0) {
                return { key: 'upgrade', label: 'Upgrade to Enroll', disabled: false };
            }

            return { key: 'standalone_buy', label: 'Buy Course', disabled: false };
        }

        if (product.is_owned) {
            return {
                key: 'owned',
                label: 'In Your Library',
                disabled: true,
            };
        }

        return { key: 'add_to_cart', label: 'Add to Cart', disabled: false };
    };

    const addToCart = (product, options = {}) => {
        const {
            showCart = false,
            noticeTitle = 'Added to Cart',
            noticeText = `Added ${product.title} to cart.`,
        } = options;

        let nextCart = [];
        if (product.type === 'plan') {
            const withoutPlans = cart.filter((item) => item.type !== 'plan');
            const alreadyInCart = withoutPlans.find((item) => item.id === product.id);
            nextCart = alreadyInCart ? [...withoutPlans] : [...withoutPlans, product];
        } else {
            const exists = cart.find((item) => item.id === product.id);
            nextCart = exists ? [...cart] : [...cart, product];
        }

        setCart(nextCart);
        showNotice('success', noticeTitle, noticeText);
        if (showCart) {
            setView('cart');
        }
    };

    const handleProductAction = async (product) => {
        const action = getProductAction(product);

        if (action.key === 'already_enrolled' || action.key === 'owned') {
            showNotice('success', action.label, action.label);
            return;
        }

        if (action.key === 'downgrade') {
            showNotice('error', 'Downgrades Not Allowed', 'You cannot downgrade to a lower tier plan.');
            return;
        }

        if (action.key === 'plan_enroll') {
            if (!isLoggedIn) {
                const accountUrl = window.smcShopData?.account_url || '/my-account/';
                const target = new URL(accountUrl, window.location.origin);
                target.searchParams.set('auth', 'required');
                target.searchParams.set('intent', 'enroll');
                window.location.href = target.toString();
                return;
            }

            setLoading(true);
            try {
                await apiFetch({
                    path: '/smc/v1/shop/enroll',
                    method: 'POST',
                    data: { product_id: product.id },
                });
                showNotice('success', 'Enrolled', 'Enrolled successfully!');
                await refreshShopState();
                setView('learning');
            } catch (err) {
                showNotice('error', 'Error', err?.message || 'Could not enroll');
            } finally {
                setLoading(false);
            }
            return;
        }

        if (action.key === 'upgrade') {
            const upgradePlanId = Number(product.recommended_upgrade_plan_product_id || 0);
            const fallbackLevel = String(product.recommended_upgrade_plan_level || '');
            const planProduct = products.find((item) => (
                item.type === 'plan' && (
                    item.id === upgradePlanId ||
                    (fallbackLevel && item.plan_level === fallbackLevel)
                )
            ));

            if (!planProduct) {
                showNotice('error', 'Error', 'No upgrade plan found for this course.');
                return;
            }

            addToCart(planProduct, {
                showCart: true,
                noticeTitle: 'Upgrade Added',
                noticeText: `Added ${planProduct.title} to cart.`,
            });
            return;
        }

        if (action.key === 'upgrade_plan' || action.key === 'standalone_buy') {
            addToCart(product, {
                showCart: true,
                noticeTitle: action.key === 'upgrade_plan' ? 'Upgrade Added' : 'Added to Cart',
                noticeText: `Added ${product.title} to cart.`,
            });
            return;
        }

        if (action.key === 'add_to_cart') {
            addToCart(product);
        }
    };

    const removeFromCart = (productId) => {
        setCart(cart.filter(p => p.id !== productId));
    };

    const handleCheckout = async () => {
        if (!isLoggedIn) {
            const accountUrl = window.smcShopData?.account_url || '/my-account/';
            const target = new URL(accountUrl, window.location.origin);
            target.searchParams.set('auth', 'required');
            target.searchParams.set('intent', 'checkout');
            window.location.href = target.toString();
            return;
        }

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
                const access = await fetchAccess();
                setUserAccess(access);

                setView('learning');
                showNotice('success', 'Order complete', 'Order successful! content unlocked.');
            }

        } catch (err) {
            showNotice('error', 'Error', err.message || 'Checkout failed');
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
                    <button className={`smc-btn-nav ${view === 'shop' ? 'active' : ''}`} onClick={navigateToShop}>SHOP</button>
                    {isLoggedIn && (
                        <button className={`smc-btn-nav ${view === 'learning' ? 'active' : ''}`} onClick={() => setView('learning')}>MY LEARNING</button>
                    )}
                    <button className={`smc-btn-nav ${view === 'cart' ? 'active' : ''}`} onClick={() => setView('cart')}>
                        CART {cart.length > 0 && <span className="cart-count">{cart.length}</span>}
                    </button>
                </nav>
            </header>

            {notice && (
                <div className="smc-toaster">
                    <div className={`smc-toast ${notice.status}`}>
                        <div className="toast-icon">
                            {notice.status === 'success' ? <CheckCircle size={20} /> : <AlertCircle size={20} />}
                        </div>
                        <div className="toast-content">
                            <h4>{notice.title || (notice.status === 'success' ? 'Success' : 'Error')}</h4>
                            <p>{notice.text}</p>
                        </div>
                        <div className="toast-timer" style={{ animationDuration: `${notice.duration || 3000}ms` }}></div>
                    </div>
                </div>
            )}

            {loading ? <Spinner /> : (
                <main>
                    {view === 'shop' && (
                        <ProductList
                            products={products}
                            onViewDetails={navigateToProduct}
                            onProductAction={handleProductAction}
                            getProductAction={getProductAction}
                            userAccess={userAccess}
                        />
                    )}
                    {view === 'product-details' && selectedProduct && (
                        <ProductDetails
                            product={selectedProduct}
                            onBack={navigateToShop}
                            onProductAction={handleProductAction}
                            getProductAction={getProductAction}
                        />
                    )}
                    {view === 'cart' && <Cart cart={cart} onRemove={removeFromCart} onCheckout={handleCheckout} loading={loading} />}
                    {view === 'learning' && isLoggedIn && <MyLearning userAccess={userAccess} />}
                </main>
            )}
        </div>
    );
}
