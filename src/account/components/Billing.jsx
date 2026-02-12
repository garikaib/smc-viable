import { FileText, ExternalLink } from 'lucide-react';

const Billing = ({ data }) => {
    const orders = data.orders || [];

    return (
        <div className="billing-pane">
            <h3 className="section-title">Order History & Billing</h3>

            <div className="dash-card" style={{ padding: 0, overflow: 'hidden' }}>
                <table className="smc-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Order ID</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th style={{ textAlign: 'right' }}>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {orders.length > 0 ? (
                            orders.map(order => (
                                <tr key={order.id}>
                                    <td>{order.date}</td>
                                    <td>#{order.id}</td>
                                    <td style={{ maxWidth: '250px' }}>
                                        <div style={{ fontWeight: 600 }}>{order.summary}</div>
                                        <div style={{ fontSize: '11px', color: 'var(--dash-text-muted)' }}>{order.items_count} item(s)</div>
                                    </td>
                                    <td>${order.total}</td>
                                    <td>
                                        <span className={`smc-badge-status ${order.status.toLowerCase()}`} style={{
                                            padding: '4px 10px', borderRadius: '100px',
                                            background: 'var(--dash-card-bg)', fontSize: '11px',
                                            fontWeight: 800, border: '1px solid var(--dash-divider)'
                                        }}>
                                            {order.status.toUpperCase()}
                                        </span>
                                    </td>
                                    <td style={{ textAlign: 'right' }}>
                                        <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
                                            <a href={order.view_url} className="smc-btn smc-btn-outline smc-btn-small" title="View Details">
                                                <ExternalLink size={14} />
                                            </a>
                                            <a href={order.invoice_url} className="smc-btn smc-btn-outline smc-btn-small" title="Download Invoice">
                                                <FileText size={14} />
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan="6" style={{ padding: '60px', textAlign: 'center', color: 'var(--dash-text-faint)' }}>
                                    No purchase history found.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div style={{ marginTop: '30px', padding: '20px', background: 'rgba(14,118,115,0.05)', borderRadius: '15px', border: '1px solid rgba(14,118,115,0.1)' }}>
                <p style={{ margin: 0, fontSize: '13px', color: 'var(--dash-text-muted)', lineHeight: '1.6' }}>
                    <strong>Need Help with Billing?</strong> If you have questions about your invoices or need to update your payment method, contact us at <a href="mailto:support@smcviable.com" style={{ color: '#0E7673', fontWeight: 700 }}>support@smcviable.com</a> or WhatsApp <a href="https://wa.me/263776207487" style={{ color: '#0E7673', fontWeight: 700 }}>+263 77 620 7487</a>.
                </p>
            </div>
        </div>
    );
};

export default Billing;
