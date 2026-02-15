<?php
/**
 * Account order view and invoice document rendering.
 *
 * @package SMC\Viable
 */

declare(strict_types=1);

namespace SMC\Viable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle account order pages and invoice PDF downloads.
 */
class Account_Documents {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'template_redirect', [ __CLASS__, 'maybe_handle_account_route' ], 0 );
	}

	/**
	 * Serve /my-account/view-order/{id}/ and /my-account/invoice/{id}/ routes.
	 */
	public static function maybe_handle_account_route(): void {
		$action = sanitize_key( (string) get_query_var( 'smc_account_action', '' ) );
		if ( ! in_array( $action, [ 'view-order', 'invoice' ], true ) ) {
			return;
		}

		$order_id = absint( get_query_var( 'smc_account_order_id', 0 ) );
		if ( $order_id <= 0 ) {
			status_header( 404 );
			wp_die( esc_html__( 'Order not found.', 'smc-viable' ) );
		}

		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		$order = get_post( $order_id );
		if ( ! ( $order instanceof \WP_Post ) || 'smc_order' !== $order->post_type ) {
			status_header( 404 );
			wp_die( esc_html__( 'Order not found.', 'smc-viable' ) );
		}

		$customer_id = (int) get_post_meta( $order_id, '_customer_id', true );
		$user_id     = get_current_user_id();
		if ( $customer_id <= 0 || ( $user_id !== $customer_id && ! current_user_can( 'manage_options' ) ) ) {
			status_header( 403 );
			wp_die( esc_html__( 'You are not allowed to access this order.', 'smc-viable' ) );
		}

		$data = self::build_order_data( $order_id, $customer_id );

		if ( 'invoice' === $action ) {
			self::render_invoice_pdf( $data );
			exit;
		}

		self::render_view_order_page( $data );
		exit;
	}

	/**
	 * Build normalized order details used by both templates.
	 *
	 * @return array<string, mixed>
	 */
	private static function build_order_data( int $order_id, int $customer_id ): array {
		$items_meta = get_post_meta( $order_id, '_order_items', true );
		$items_meta = is_array( $items_meta ) ? $items_meta : [];

		if ( isset( $items_meta['product_id'] ) || isset( $items_meta['id'] ) ) {
			$items_meta = [ $items_meta ];
		}

		$items = [];
		foreach ( $items_meta as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			$name       = '';

			if ( isset( $item['name'] ) && is_string( $item['name'] ) && '' !== $item['name'] ) {
				$name = $item['name'];
			} elseif ( $product_id > 0 ) {
				$name = get_the_title( $product_id );
			}

			$price = isset( $item['price'] ) ? (float) $item['price'] : 0.0;
			if ( '' === $name ) {
				$name = __( 'Product', 'smc-viable' );
			}

			$items[] = [
				'product_id' => $product_id,
				'name'       => $name,
				'price'      => $price,
				'qty'        => 1,
				'line_total' => $price,
			];
		}

		$total = (float) get_post_meta( $order_id, '_order_total', true );
		if ( $total <= 0 && ! empty( $items ) ) {
			$total = (float) array_sum( array_map( static fn( $i ) => (float) $i['line_total'], $items ) );
		}

		$status         = sanitize_key( (string) get_post_meta( $order_id, '_order_status', true ) );
		$payment_method = (string) get_post_meta( $order_id, '_payment_method', true );
		$customer       = get_userdata( $customer_id );
		$customer_name  = ( $customer instanceof \WP_User ) ? $customer->display_name : __( 'Customer', 'smc-viable' );
		$customer_email = ( $customer instanceof \WP_User ) ? $customer->user_email : '';

		return [
			'order_id'        => $order_id,
			'invoice_number'  => 'SMC-' . $order_id,
			'date'            => get_the_date( 'M d, Y', $order_id ),
			'status'          => ucfirst( $status ),
			'payment_method'  => '' !== $payment_method ? ucfirst( $payment_method ) : __( 'N/A', 'smc-viable' ),
			'customer_name'   => $customer_name,
			'customer_email'  => $customer_email,
			'items'           => $items,
			'subtotal'        => $total,
			'total'           => $total,
			'currency_symbol' => '$',
			'logo_url'        => self::resolve_logo_url(),
			'invoice_url'     => home_url( '/my-account/invoice/' . $order_id . '/' ),
			'account_url'     => home_url( '/my-account/' ),
		];
	}

	/**
	 * Resolve a stable SMC logo URL.
	 */
	private static function resolve_logo_url(): string {
		// 1. Try custom logo from theme
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id ) {
			$logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
			if ( $logo_url ) {
				return $logo_url;
			}
		}

		// 2. Try the candidates
		$candidates = [
			'wp-content/uploads/2026/01/smc_logo_cropped-1.png',
			'wp-content/uploads/2025/12/SMC-logo-07-1.png',
		];

		foreach ( $candidates as $relative ) {
			$path = ABSPATH . ltrim( $relative, '/' );
			if ( file_exists( $path ) ) {
				return content_url( ltrim( str_replace( 'wp-content/', '', $relative ), '/' ) );
			}
		}

		return '';
	}

	/**
	 * Render order details view.
	 *
	 * @param array<string, mixed> $data Order data.
	 */
	private static function render_view_order_page( array $data ): void {
		$symbol = (string) $data['currency_symbol'];
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo esc_html( sprintf( __( 'Order #%d', 'smc-viable' ), (int) $data['order_id'] ) ); ?></title>
			<style>
				:root {
					--smc-teal: #0e7673;
					--smc-red: #a1232a;
					--ink: #0f172a;
					--muted: #64748b;
					--line: #dbe3ea;
					--bg: #f2f5f8;
					--card: #ffffff;
				}
				* { box-sizing: border-box; }
				body {
					margin: 0;
					background: radial-gradient(circle at 0% 0%, #eaf6f5 0%, transparent 35%), radial-gradient(circle at 100% 100%, #f9ecee 0%, transparent 35%), var(--bg);
					color: var(--ink);
					font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
					padding: 28px 16px;
				}
				.smc-wrap {
					max-width: 900px;
					margin: 0 auto;
					background: var(--card);
					border-radius: 20px;
					border: 1px solid var(--line);
					overflow: hidden;
					box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
				}
				.smc-head {
					padding: 28px 30px;
					display: flex;
					justify-content: space-between;
					align-items: flex-start;
					gap: 16px;
					border-bottom: 1px solid var(--line);
				}
				.smc-brand img { max-width: 190px; height: auto; display: block; }
				.smc-kicker {
					margin: 8px 0 0;
					color: var(--muted);
					font-size: 12px;
					letter-spacing: 0.09em;
					text-transform: uppercase;
					font-weight: 700;
				}
				.smc-title h1 {
					margin: 0;
					font-size: 30px;
					letter-spacing: 0.03em;
				}
				.smc-tag {
					margin-top: 10px;
					display: inline-block;
					background: rgba(14, 118, 115, 0.12);
					color: var(--smc-teal);
					padding: 6px 12px;
					border-radius: 999px;
					font-size: 12px;
					font-weight: 800;
					text-transform: uppercase;
				}
				.smc-main { padding: 28px 30px 34px; }
				.smc-meta {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
					gap: 14px;
					margin-bottom: 24px;
				}
				.smc-meta-card {
					border: 1px solid var(--line);
					border-radius: 14px;
					padding: 12px 14px;
					background: #fcfdff;
				}
				.smc-meta-label {
					font-size: 11px;
					font-weight: 800;
					letter-spacing: 0.09em;
					color: var(--muted);
					text-transform: uppercase;
					margin-bottom: 8px;
				}
				.smc-meta-value { font-size: 15px; font-weight: 700; }
				table {
					width: 100%;
					border-collapse: collapse;
					border: 1px solid var(--line);
					border-radius: 14px;
					overflow: hidden;
				}
				th, td { padding: 13px 14px; border-bottom: 1px solid var(--line); text-align: left; }
				th {
					font-size: 11px;
					letter-spacing: 0.09em;
					text-transform: uppercase;
					color: var(--muted);
					background: #f8fbfd;
				}
				tr:last-child td { border-bottom: 0; }
				td:last-child, th:last-child { text-align: right; }
				.smc-total {
					margin-top: 16px;
					display: flex;
					justify-content: flex-end;
				}
				.smc-total-card {
					min-width: 260px;
					border: 1px solid var(--line);
					border-radius: 14px;
					padding: 14px 16px;
					background: #fbfffe;
				}
				.smc-total-row {
					display: flex;
					justify-content: space-between;
					font-size: 15px;
				}
				.smc-total-row strong { font-size: 20px; color: var(--smc-teal); }
				.smc-actions {
					margin-top: 26px;
					display: flex;
					gap: 10px;
					flex-wrap: wrap;
				}
				.smc-btn {
					display: inline-flex;
					align-items: center;
					justify-content: center;
					padding: 11px 16px;
					border-radius: 999px;
					font-size: 12px;
					font-weight: 800;
					letter-spacing: 0.06em;
					text-transform: uppercase;
					text-decoration: none;
				}
				.smc-btn-primary {
					background: linear-gradient(135deg, var(--smc-teal), #148d8a);
					color: #fff;
				}
				.smc-btn-secondary {
					background: #fff;
					color: var(--ink);
					border: 1px solid var(--line);
				}
			</style>
		</head>
		<body>
			<div class="smc-wrap">
				<div class="smc-head">
					<div class="smc-brand">
						<?php if ( '' !== (string) $data['logo_url'] ) : ?>
							<img src="<?php echo esc_url( (string) $data['logo_url'] ); ?>" alt="<?php esc_attr_e( 'SMC Logo', 'smc-viable' ); ?>">
						<?php endif; ?>
						<p class="smc-kicker"><?php esc_html_e( 'Social Marketing Centre', 'smc-viable' ); ?></p>
					</div>
					<div class="smc-title">
						<h1><?php esc_html_e( 'Order Details', 'smc-viable' ); ?></h1>
						<div class="smc-tag"><?php echo esc_html( (string) $data['status'] ); ?></div>
					</div>
				</div>
				<div class="smc-main">
					<div class="smc-meta">
						<div class="smc-meta-card"><div class="smc-meta-label"><?php esc_html_e( 'Order ID', 'smc-viable' ); ?></div><div class="smc-meta-value">#<?php echo esc_html( (string) $data['order_id'] ); ?></div></div>
						<div class="smc-meta-card"><div class="smc-meta-label"><?php esc_html_e( 'Invoice', 'smc-viable' ); ?></div><div class="smc-meta-value"><?php echo esc_html( (string) $data['invoice_number'] ); ?></div></div>
						<div class="smc-meta-card"><div class="smc-meta-label"><?php esc_html_e( 'Date', 'smc-viable' ); ?></div><div class="smc-meta-value"><?php echo esc_html( (string) $data['date'] ); ?></div></div>
						<div class="smc-meta-card"><div class="smc-meta-label"><?php esc_html_e( 'Payment', 'smc-viable' ); ?></div><div class="smc-meta-value"><?php echo esc_html( (string) $data['payment_method'] ); ?></div></div>
					</div>

					<table>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Item', 'smc-viable' ); ?></th>
								<th><?php esc_html_e( 'Qty', 'smc-viable' ); ?></th>
								<th><?php esc_html_e( 'Total', 'smc-viable' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $data['items'] as $item ) : ?>
								<tr>
									<td><?php echo esc_html( (string) $item['name'] ); ?></td>
									<td><?php echo esc_html( (string) $item['qty'] ); ?></td>
									<td><?php echo esc_html( $symbol . number_format( (float) $item['line_total'], 2 ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<div class="smc-total">
						<div class="smc-total-card">
							<div class="smc-total-row">
								<span><?php esc_html_e( 'Order Total', 'smc-viable' ); ?></span>
								<strong><?php echo esc_html( $symbol . number_format( (float) $data['total'], 2 ) ); ?></strong>
							</div>
						</div>
					</div>

					<div class="smc-actions">
						<a class="smc-btn smc-btn-primary" href="<?php echo esc_url( (string) $data['invoice_url'] ); ?>"><?php esc_html_e( 'Download PDF Invoice', 'smc-viable' ); ?></a>
						<a class="smc-btn smc-btn-secondary" href="<?php echo esc_url( (string) $data['account_url'] ); ?>"><?php esc_html_e( 'Back To Billing', 'smc-viable' ); ?></a>
					</div>
				</div>
			</div>
		</body>
		</html>
		<?php
	}

	/**
	 * Render invoice PDF as download.
	 *
	 * @param array<string, mixed> $data Invoice data.
	 */
	private static function render_invoice_pdf( array $data ): void {
		$pdf = self::generate_invoice_pdf( $data );

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="invoice-' . (int) $data['order_id'] . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf ) );

		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Build a one-page PDF invoice.
	 *
	 * @param array<string, mixed> $data Invoice data.
	 */
	private static function generate_invoice_pdf( array $data ): string {
		$logo = self::load_logo_jpeg();
		$has_logo = null !== $logo;

		$content_lines   = [];
		$content_lines[] = '1 1 1 rg';
		$content_lines[] = '0 0 595.28 841.89 re f';

		// Top hero area.
		$content_lines[] = '0.040 0.133 0.278 rg';
		$content_lines[] = '0 756 595.28 86 re f';
		$content_lines[] = '0.055 0.463 0.451 rg';
		$content_lines[] = '370 756 225.28 86 re f';
		$content_lines[] = '0.780 0.153 0.220 rg';
		$content_lines[] = '352 756 18 86 re f';

		if ( $has_logo ) {
			$logo_width  = 170.0;
			$logo_height = (float) $logo['height'] * ( $logo_width / (float) $logo['width'] );
			$content_lines[] = sprintf( 'q %.2F 0 0 %.2F 44 778 cm /Im1 Do Q', $logo_width, $logo_height );
		}

		$content_lines[] = self::pdf_text_cmd( 384, 814, 9, 'Invoice Number', [ 0.90, 0.96, 0.97 ] );
		$content_lines[] = self::pdf_text_cmd( 384, 795, 20, (string) $data['invoice_number'], [ 1, 1, 1 ] );
		$content_lines[] = self::pdf_text_cmd( 384, 777, 10, (string) $data['date'], [ 0.90, 0.96, 0.97 ] );

		$content_lines[] = self::pdf_text_cmd( 40, 716, 29, 'INVOICE', [ 0.040, 0.133, 0.278 ] );
		$content_lines[] = self::pdf_text_cmd( 40, 697, 11, 'Social Marketing Centre', [ 0.36, 0.43, 0.51 ] );

		// Bill-to and order-info cards.
		$content_lines[] = '0.975 0.983 0.995 rg';
		$content_lines[] = '40 610 246 72 re f';
		$content_lines[] = '0.965 0.980 0.980 rg';
		$content_lines[] = '309 610 246 72 re f';
		$content_lines[] = '0.86 0.90 0.93 RG 1 w';
		$content_lines[] = '40 610 246 72 re S';
		$content_lines[] = '309 610 246 72 re S';

		$content_lines[] = self::pdf_text_cmd( 52, 666, 9, 'Bill To', [ 0.36, 0.43, 0.51 ] );
		$content_lines[] = self::pdf_text_cmd( 52, 646, 14, (string) $data['customer_name'] );
		$content_lines[] = self::pdf_text_cmd( 52, 628, 11, (string) $data['customer_email'], [ 0.36, 0.43, 0.51 ] );

		$content_lines[] = self::pdf_text_cmd( 321, 666, 9, 'Order Details', [ 0.36, 0.43, 0.51 ] );
		$content_lines[] = self::pdf_text_cmd( 321, 646, 12, 'Order ID: #' . (string) $data['order_id'] );
		$content_lines[] = self::pdf_text_cmd( 321, 628, 11, 'Status: ' . (string) $data['status'], [ 0.36, 0.43, 0.51 ] );

		// Table header row.
		$content_lines[] = '0.945 0.963 0.980 rg';
		$content_lines[] = '40 570 515 27 re f';
		$content_lines[] = '0.86 0.90 0.93 RG 1 w';
		$content_lines[] = '40 570 515 27 re S';
		$content_lines[] = self::pdf_text_cmd( 50, 579, 10, 'Item', [ 0.27, 0.34, 0.45 ] );
		$content_lines[] = self::pdf_text_cmd( 428, 579, 10, 'Qty', [ 0.27, 0.34, 0.45 ] );
		$content_lines[] = self::pdf_text_right_cmd( 542, 579, 10, 'Amount', [ 0.27, 0.34, 0.45 ] );

		$y    = 549;
		$rows = 0;
		foreach ( $data['items'] as $item ) {
			if ( $rows >= 10 || $y < 340 ) {
				break;
			}

			if ( 0 === ( $rows % 2 ) ) {
				$content_lines[] = '0.989 0.994 0.998 rg';
				$content_lines[] = '40 ' . ( $y - 7 ) . ' 515 22 re f';
			}
			$content_lines[] = self::pdf_text_cmd( 50, $y, 11, (string) $item['name'] );
			$content_lines[] = self::pdf_text_cmd( 431, $y, 11, (string) $item['qty'] );
			$content_lines[] = self::pdf_text_right_cmd( 542, $y, 11, self::format_money( (float) $item['line_total'] ) );
			$content_lines[] = '0.90 0.93 0.96 RG 1 w';
			$content_lines[] = '40 ' . ( $y - 12 ) . ' m 555 ' . ( $y - 12 ) . ' l S';

			$y   -= 26;
			$rows++;
		}

		$summary_top = max( 300, $y - 12 );
		$content_lines[] = '0.95 0.985 0.985 rg';
		$content_lines[] = '316 ' . ( $summary_top - 58 ) . ' 239 90 re f';
		$content_lines[] = '0.83 0.90 0.93 RG 1 w';
		$content_lines[] = '316 ' . ( $summary_top - 58 ) . ' 239 90 re S';
		$content_lines[] = self::pdf_text_cmd( 330, $summary_top + 16, 10, 'Subtotal', [ 0.36, 0.43, 0.51 ] );
		$content_lines[] = self::pdf_text_right_cmd( 542, $summary_top + 16, 11, self::format_money( (float) $data['subtotal'] ) );
		$content_lines[] = '0.85 0.91 0.91 RG 0.8 w';
		$content_lines[] = '328 ' . ( $summary_top + 8 ) . ' m 543 ' . ( $summary_top + 8 ) . ' l S';
		$content_lines[] = self::pdf_text_cmd( 330, $summary_top - 14, 12, 'Total Due', [ 0.055, 0.463, 0.451 ] );
		$content_lines[] = self::pdf_text_right_cmd( 542, $summary_top - 14, 22, self::format_money( (float) $data['total'] ), [ 0.055, 0.463, 0.451 ] );

		// Footer band.
		$content_lines[] = '0.040 0.133 0.278 rg';
		$content_lines[] = '0 36 595.28 44 re f';
		$content_lines[] = self::pdf_text_cmd( 40, 56, 10, 'Thank you for your business.', [ 0.93, 0.97, 1 ] );
		$content_lines[] = self::pdf_text_right_cmd( 555, 56, 9, 'support@smcviable.com | WhatsApp: +263 77 620 7487', [ 0.93, 0.97, 1 ] );

		$content_stream = implode( "\n", $content_lines ) . "\n";
		$objects        = [];

		$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';

		$resources = '<< /Font << /F1 5 0 R >>';
		if ( $has_logo ) {
			$resources .= ' /XObject << /Im1 6 0 R >>';
		}
		$resources .= ' >>';

		$objects[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources ' . $resources . ' /Contents 4 0 R >>';
		$objects[4] = '<< /Length ' . strlen( $content_stream ) . " >>\nstream\n" . $content_stream . "endstream";
		$objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

		if ( $has_logo ) {
			$objects[6] = '<< /Type /XObject /Subtype /Image /Width ' . (int) $logo['width'] . ' /Height ' . (int) $logo['height'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen( (string) $logo['data'] ) . " >>\nstream\n" . (string) $logo['data'] . "\nendstream";
		}

		return self::assemble_pdf( $objects );
	}

	/**
	 * Assemble PDF body with xref and trailer.
	 *
	 * @param array<int, string> $objects PDF objects keyed by object id.
	 */
	private static function assemble_pdf( array $objects ): string {
		ksort( $objects );

		$pdf     = "%PDF-1.4\n";
		$offsets = [ 0 ];

		foreach ( $objects as $id => $object ) {
			$offsets[ $id ] = strlen( $pdf );
			$pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
		}

		$start_xref = strlen( $pdf );
		$pdf       .= 'xref' . "\n";
		$pdf       .= '0 ' . ( count( $objects ) + 1 ) . "\n";
		$pdf       .= "0000000000 65535 f \n";

		for ( $i = 1; $i <= count( $objects ); $i++ ) {
			$offset = $offsets[ $i ] ?? 0;
			$pdf   .= sprintf( "%010d 00000 n \n", $offset );
		}

		$pdf .= 'trailer' . "\n";
		$pdf .= '<< /Size ' . ( count( $objects ) + 1 ) . ' /Root 1 0 R >>' . "\n";
		$pdf .= 'startxref' . "\n";
		$pdf .= $start_xref . "\n";
		$pdf .= "%%EOF";

		return $pdf;
	}

	/**
	 * Build text drawing command.
	 *
	 * @param array<int, float> $rgb Optional RGB values (0..1).
	 */
	private static function pdf_text_cmd( int $x, int $y, int $font_size, string $text, array $rgb = [ 0.06, 0.09, 0.16 ] ): string {
		$safe_text = self::escape_pdf_text( $text );
		return sprintf(
			'%.3F %.3F %.3F rg BT /F1 %d Tf 1 0 0 1 %d %d Tm (%s) Tj ET',
			(float) $rgb[0],
			(float) $rgb[1],
			(float) $rgb[2],
			$font_size,
			$x,
			$y,
			$safe_text
		);
	}

	/**
	 * Build right-aligned text drawing command.
	 *
	 * @param array<int, float> $rgb Optional RGB values (0..1).
	 */
	private static function pdf_text_right_cmd( int $right_x, int $y, int $font_size, string $text, array $rgb = [ 0.06, 0.09, 0.16 ] ): string {
		$width = (int) ceil( strlen( self::escape_pdf_text( $text ) ) * ( $font_size * 0.52 ) );
		$x     = max( 10, $right_x - $width );
		return self::pdf_text_cmd( $x, $y, $font_size, $text, $rgb );
	}

	/**
	 * Escape text for PDF stream.
	 */
	private static function escape_pdf_text( string $text ): string {
		$ascii = $text;
		if ( function_exists( 'iconv' ) ) {
			$converted = iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $text );
			if ( false !== $converted ) {
				$ascii = $converted;
			}
		}

		$ascii = preg_replace( '/[^\x20-\x7E]/', '', $ascii ) ?? '';
		$ascii = str_replace( [ '\\', '(', ')' ], [ '\\\\', '\(', '\)' ], $ascii );

		return trim( $ascii );
	}

	/**
	 * Format amount as USD-like value.
	 */
	private static function format_money( float $amount ): string {
		return '$' . number_format( $amount, 2 );
	}

	/**
	 * Load logo as JPEG stream for PDF embedding.
	 *
	 * @return array{data: string, width: int, height: int}|null
	 */
	private static function load_logo_jpeg(): ?array {
		$logo_url = self::resolve_logo_url();
		if ( '' === $logo_url ) {
			return null;
		}

		$relative = str_replace( home_url( '/' ), '', $logo_url );
		$path     = ABSPATH . ltrim( $relative, '/' );
		if ( ! file_exists( $path ) ) {
			return null;
		}

		$ext = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
		$jpeg_data = null;

		if ( 'jpg' === $ext || 'jpeg' === $ext ) {
			$raw = file_get_contents( $path );
			$jpeg_data = false !== $raw ? $raw : null;
		} elseif ( 'png' === $ext && function_exists( 'imagecreatefrompng' ) && function_exists( 'imagejpeg' ) ) {
			$image = imagecreatefrompng( $path );
			if ( false !== $image ) {
				$width  = imagesx( $image );
				$height = imagesy( $image );
				$canvas = imagecreatetruecolor( $width, $height );
				if ( false !== $canvas ) {
					// Match the deep-navy hero background so transparent logo edges blend cleanly.
					$bg = imagecolorallocate( $canvas, 10, 34, 71 );
					imagefilledrectangle( $canvas, 0, 0, $width, $height, $bg );
					imagealphablending( $canvas, true );
					imagecopy( $canvas, $image, 0, 0, 0, 0, $width, $height );
				}

				ob_start();
				imagejpeg( false !== $canvas ? $canvas : $image, null, 90 );
				$jpeg_data = (string) ob_get_clean();
				if ( false !== $canvas ) {
					imagedestroy( $canvas );
				}
				imagedestroy( $image );
			}
		}

		if ( ! is_string( $jpeg_data ) || '' === $jpeg_data ) {
			return null;
		}

		$size = getimagesizefromstring( $jpeg_data );
		if ( ! is_array( $size ) || empty( $size[0] ) || empty( $size[1] ) ) {
			return null;
		}

		return [
			'data'   => $jpeg_data,
			'width'  => (int) $size[0],
			'height' => (int) $size[1],
		];
	}
}
