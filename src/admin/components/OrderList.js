import { useState, useEffect } from '@wordpress/element';
import { Spinner, Notice, Card, CardHeader, CardBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const apiFetch = window.wp ? window.wp.apiFetch : null;

export default function OrderList() {
    const [orders, setOrders] = useState([]);
    const [loading, setLoading] = useState(true);
    const [notice, setNotice] = useState(null);

    useEffect(() => {
        if (apiFetch) {
            apiFetch({ path: '/smc/v1/shop/admin/orders' })
                .then(data => setOrders(data))
                .catch(err => setNotice({ status: 'error', text: err.message }))
                .finally(() => setLoading(false));
        }
    }, []);

    if (loading) return <Spinner />;

    return (
        <div className="smc-admin-orders">
            <h2 className="mb-6">{__('Shop Orders', 'smc-viable')}</h2>

            {notice && <Notice status={notice.status} onRemove={() => setNotice(null)}>{notice.text}</Notice>}

            <table className="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>{__('Order ID', 'smc-viable')}</th>
                        <th>{__('Customer', 'smc-viable')}</th>
                        <th>{__('Date', 'smc-viable')}</th>
                        <th>{__('Total', 'smc-viable')}</th>
                        <th>{__('Status', 'smc-viable')}</th>
                        <th>{__('Items', 'smc-viable')}</th>
                    </tr>
                </thead>
                <tbody>
                    {orders.map(order => (
                        <tr key={order.id}>
                            <td><strong>{order.title}</strong></td>
                            <td>
                                {order.customer_name}<br />
                                <small>{order.customer_email}</small>
                            </td>
                            <td>{new Date(order.date).toLocaleDateString()}</td>
                            <td><strong>${order.total.toFixed(2)}</strong></td>
                            <td>
                                <span className={`status-badge status-${order.status}`} style={{
                                    padding: '2px 8px',
                                    borderRadius: '10px',
                                    fontSize: '0.85em',
                                    background: order.status === 'completed' ? '#d4edda' : '#fff3cd',
                                    color: order.status === 'completed' ? '#155724' : '#856404'
                                }}>
                                    {order.status}
                                </span>
                            </td>
                            <td>
                                <ul style={{ margin: 0, padding: 0, listStyle: 'none', fontSize: '0.9em' }}>
                                    {Array.isArray(order.items) && order.items.map((item, idx) => (
                                        <li key={idx}>{item.name} (${item.price})</li>
                                    ))}
                                </ul>
                            </td>
                        </tr>
                    ))}
                    {orders.length === 0 && (
                        <tr>
                            <td colSpan="6" style={{ textAlign: 'center' }}>{__('No orders found.', 'smc-viable')}</td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
