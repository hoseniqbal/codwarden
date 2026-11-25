<?php
/**
 * Admin screens.
 *
 * @package CODWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Overview, Settings and Phone list.
 */
class CODWarden_Admin {

	const PAGE = 'codwarden';
	const CAP  = 'manage_woocommerce';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_codwarden_phone', array( __CLASS__, 'handle_phone' ) );
		add_filter( 'option_page_capability_codwarden', array( __CLASS__, 'capability' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CODWARDEN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Capability for saving settings.
	 *
	 * @return string
	 */
	public static function capability() {
		return self::CAP;
	}

	/**
	 * Tabs.
	 *
	 * @return array
	 */
	private static function tabs() {
		return array(
			'overview' => __( 'Overview', 'codwarden' ),
			'settings' => __( 'Settings', 'codwarden' ),
			'phones'   => __( 'Phone list', 'codwarden' ),
		);
	}

	/**
	 * Page URL.
	 *
	 * @param string $tab  Tab.
	 * @param array  $args Extra args.
	 * @return string
	 */
	public static function url( $tab = 'overview', $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Plugins screen link.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Dashboard', 'codwarden' ) . '</a>' );
		return $links;
	}

	/**
	 * Menu.
	 *
	 * @return void
	 */
	public static function menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- menu icon.
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M10 1.5 2.8 4.2v5.2c0 4.4 3 7.7 7.2 8.9 4.2-1.2 7.2-4.5 7.2-8.9V4.2L10 1.5Zm-3 5h6v1.4h-2.3v5.6H9.3V7.9H7V6.5Z"/></svg>'
		);
		add_menu_page( __( 'CODWarden', 'codwarden' ), __( 'CODWarden', 'codwarden' ), self::CAP, self::PAGE, array( __CLASS__, 'render' ), $icon, 57 );
		add_submenu_page( self::PAGE, __( 'Overview', 'codwarden' ), __( 'Overview', 'codwarden' ), self::CAP, self::PAGE, array( __CLASS__, 'render' ) );
		foreach ( array( 'settings', 'phones' ) as $tab ) {
			$tabs = self::tabs();
			add_submenu_page( self::PAGE, $tabs[ $tab ], $tabs[ $tab ], self::CAP, 'admin.php?page=' . self::PAGE . '&tab=' . $tab );
		}
	}

	/**
	 * Highlight the open tab in the menu.
	 *
	 * @param string|null $file Submenu file.
	 * @return string|null
	 */
	public static function submenu_file( $file ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- menu highlight.
		if ( self::PAGE !== $page ) {
			return $file;
		}
		$tab = self::current_tab();
		return 'overview' === $tab ? self::PAGE : 'admin.php?page=' . self::PAGE . '&tab=' . $tab;
	}

	/**
	 * Styles.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		$file = CODWARDEN_PATH . 'assets/css/admin.css';
		wp_enqueue_style( 'codwarden-admin', CODWARDEN_URL . 'assets/css/admin.css', array(), CODWARDEN_VERSION . '.' . (int) filemtime( $file ) );
	}

	/**
	 * Settings registration.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'codwarden',
			CODWarden_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'CODWarden_Settings', 'sanitize' ),
				'default'           => CODWarden_Settings::defaults(),
			)
		);
	}

	/**
	 * Current tab.
	 *
	 * @return string
	 */
	private static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab switch.
		return isset( self::tabs()[ $tab ] ) ? $tab : 'overview';
	}

	/**
	 * Reason labels.
	 *
	 * @param string $reason Reason.
	 * @return string
	 */
	public static function reason_label( $reason ) {
		$labels = array(
			'bad_phone'      => __( 'Phone number is not a real mobile number', 'codwarden' ),
			'blocked_phone'  => __( 'Phone is on the refuser list', 'codwarden' ),
			'over_total'     => __( 'Order total above the cash on delivery limit', 'codwarden' ),
			'new_over_total' => __( 'New customer above the first-order limit', 'codwarden' ),
			'duplicate'      => __( 'Same order placed again', 'codwarden' ),
			'limit_phone'    => __( 'Too many orders from this phone', 'codwarden' ),
			'limit_ip'       => __( 'Too many orders from this address', 'codwarden' ),
		);
		return isset( $labels[ $reason ] ) ? $labels[ $reason ] : $reason;
	}

	/**
	 * Shield logo.
	 *
	 * @return void
	 */
	private static function logo() {
		?>
		<svg width="34" height="34" viewBox="0 0 32 32" aria-hidden="true" focusable="false"><path d="M16 2.5 4.5 6.8v8.4c0 7.1 4.9 12.4 11.5 14.3 6.6-1.9 11.5-7.2 11.5-14.3V6.8L16 2.5Z" fill="#12704c"/><path d="M11 11.5h10M16 11.5v10" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/><circle cx="23.5" cy="21.5" r="4" fill="#e8772e" stroke="#fff" stroke-width="1.5"/></svg>
		<?php
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$tab = self::current_tab();
		?>
		<div class="wrap codwarden">
			<div class="ow-hero">
				<div class="codwarden-header">
					<span class="ow-logo"><?php self::logo(); ?></span>
					<div class="ow-title">
						<h1><?php esc_html_e( 'CODWarden', 'codwarden' ); ?></h1>
						<p class="ow-tagline"><?php esc_html_e( 'Stops fake cash on delivery orders before you pay the courier.', 'codwarden' ); ?></p>
					</div>
					<span class="codwarden-version"><?php echo esc_html( 'v' . CODWARDEN_VERSION ); ?></span>
				</div>
				<nav class="codwarden-tabs" aria-label="<?php esc_attr_e( 'CODWarden sections', 'codwarden' ); ?>">
					<?php foreach ( self::tabs() as $key => $label ) : ?>
						<a href="<?php echo esc_url( self::url( $key ) ); ?>"<?php echo $key === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</nav>
			</div>
			<hr class="wp-header-end">
			<?php
			if ( ! isset( WC()->payment_gateways ) || ! array_key_exists( 'cod', WC()->payment_gateways()->payment_gateways() ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'The WooCommerce Cash on delivery payment method was not found. CODWarden only acts on that method.', 'codwarden' ) . '</p></div>';
			}
			if ( 'settings' === $tab ) {
				self::render_settings();
			} elseif ( 'phones' === $tab ) {
				self::render_phones();
			} else {
				self::render_overview();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Overview.
	 *
	 * @return void
	 */
	private static function render_overview() {
		$on      = CODWarden_Settings::on( 'enabled' );
		$cod     = WC()->payment_gateways()->payment_gateways();
		$cod_on  = isset( $cod['cod'] ) && 'yes' === $cod['cod']->enabled;
		$state   = ( $on && $cod_on ) ? 'is-on' : ( $on ? 'is-learning' : 'is-off' );
		$recent  = CODWarden_Store::recent_stopped( 20 );
		$figures = array(
			__( 'COD orders this week', 'codwarden' )   => CODWarden_Store::count_since( 'placed', 168 ),
			__( 'Stopped this week', 'codwarden' )      => CODWarden_Store::count_since( 'stopped', 168 ),
			__( 'Stopped today', 'codwarden' )          => CODWarden_Store::count_since( 'stopped', 24 ),
			__( 'Phones on refuser list', 'codwarden' ) => CODWarden_Store::blocked_count(),
		);
		?>
		<section class="codwarden-banner <?php echo esc_attr( $state ); ?>">
			<div>
				<?php if ( 'is-on' === $state ) : ?>
					<h2><?php esc_html_e( 'Cash on delivery is protected', 'codwarden' ); ?></h2>
					<p><?php esc_html_e( 'Fake numbers, repeat refusers, duplicate and runaway orders are turned away before they reach your courier. Real customers see a short message only when something is wrong.', 'codwarden' ); ?></p>
				<?php elseif ( 'is-learning' === $state ) : ?>
					<h2><?php esc_html_e( 'Cash on delivery is turned off in WooCommerce', 'codwarden' ); ?></h2>
					<p><?php esc_html_e( 'CODWarden is ready and will start working as soon as the Cash on delivery payment method is enabled.', 'codwarden' ); ?></p>
				<?php else : ?>
					<h2><?php esc_html_e( 'Protection is off', 'codwarden' ); ?></h2>
					<p><?php esc_html_e( 'Turn CODWarden on in Settings to start checking cash on delivery orders.', 'codwarden' ); ?></p>
				<?php endif; ?>
			</div>
		</section>

		<section class="codwarden-panel">
			<dl class="codwarden-figures">
				<?php foreach ( $figures as $label => $value ) : ?>
					<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( number_format_i18n( $value ) ); ?></dd></div>
				<?php endforeach; ?>
			</dl>
		</section>

		<section class="codwarden-panel">
			<div class="codwarden-panel-head">
				<h2><?php esc_html_e( 'Recently stopped', 'codwarden' ); ?></h2>
				<p><?php esc_html_e( 'Phone numbers are shortened for privacy.', 'codwarden' ); ?></p>
			</div>
			<?php if ( empty( $recent ) ) : ?>
				<div class="codwarden-empty">
					<strong><?php esc_html_e( 'Nothing stopped yet', 'codwarden' ); ?></strong>
					<?php esc_html_e( 'When a cash on delivery order breaks one of your rules, it shows up here with the reason.', 'codwarden' ); ?>
				</div>
			<?php else : ?>
				<table class="codwarden-table">
					<thead><tr>
						<th scope="col"><?php esc_html_e( 'When', 'codwarden' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Why', 'codwarden' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Checkout', 'codwarden' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Phone', 'codwarden' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $recent as $row ) : ?>
						<tr>
							<td class="is-time">
								<?php
								/* translators: %s: time difference */
								echo esc_html( sprintf( __( '%s ago', 'codwarden' ), human_time_diff( strtotime( $row['created_at'] . ' UTC' ) ) ) );
								?>
							</td>
							<td><span class="codwarden-tag is-blocked"><?php echo esc_html( self::reason_label( $row['reason'] ) ); ?></span></td>
							<td><?php echo esc_html( 'store_api' === $row['channel'] ? __( 'Block checkout', 'codwarden' ) : __( 'Classic checkout', 'codwarden' ) ); ?></td>
							<td><code><?php echo esc_html( '' !== $row['phone_masked'] ? $row['phone_masked'] : __( 'not valid', 'codwarden' ) ); ?></code></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Switch.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @return void
	 */
	private static function field_switch( $key, $label ) {
		?>
		<label class="codwarden-switch">
			<input type="checkbox" name="<?php echo esc_attr( CODWarden_Settings::OPTION . '[' . $key . ']' ); ?>" value="yes" <?php checked( CODWarden_Settings::get( $key ), 'yes' ); ?>>
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	/**
	 * Number input inside a sentence.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $step  Step.
	 * @return string
	 */
	private static function field_number( $key, $label, $step = '1' ) {
		return sprintf(
			'<input type="number" min="0" step="%1$s" name="%2$s" value="%3$s" aria-label="%4$s">',
			esc_attr( $step ),
			esc_attr( CODWarden_Settings::OPTION . '[' . $key . ']' ),
			esc_attr( CODWarden_Settings::get( $key ) ),
			esc_attr( $label )
		);
	}

	/**
	 * Allowed tags for sentences with inputs.
	 *
	 * @return array
	 */
	private static function kses() {
		return array(
			'input' => array(
				'type'       => true,
				'min'        => true,
				'step'       => true,
				'name'       => true,
				'value'      => true,
				'aria-label' => true,
			),
		);
	}

	/**
	 * Settings.
	 *
	 * @return void
	 */
	private static function render_settings() {
		$cur = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES );
		settings_errors();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'codwarden' ); ?>
			<section class="codwarden-panel">
				<div class="codwarden-panel-head"><h2><?php esc_html_e( 'Checks', 'codwarden' ); ?></h2></div>
				<div class="codwarden-row">
					<div class="codwarden-row-label"><?php esc_html_e( 'Protection', 'codwarden' ); ?><p><?php esc_html_e( 'Only cash on delivery orders are checked.', 'codwarden' ); ?></p></div>
					<div class="codwarden-row-control"><?php self::field_switch( 'enabled', __( 'Check cash on delivery orders', 'codwarden' ) ); ?></div>
				</div>
				<div class="codwarden-row">
					<div class="codwarden-row-label"><?php esc_html_e( 'Phone number', 'codwarden' ); ?><p><?php esc_html_e( 'The courier must be able to call the customer.', 'codwarden' ); ?></p></div>
					<div class="codwarden-row-control">
						<?php self::field_switch( 'phone_check', __( 'Require a real mobile number', 'codwarden' ) ); ?>
						<p class="codwarden-help"><?php esc_html_e( 'Built-in rules for Bangladesh, India, Pakistan, UAE, Saudi Arabia, Egypt, Morocco, Nigeria, Kenya, Nepal and Sri Lanka. Other countries get a length check.', 'codwarden' ); ?></p>
					</div>
				</div>
				<div class="codwarden-row">
					<div class="codwarden-row-label"><?php esc_html_e( 'Returning customers', 'codwarden' ); ?><p><?php esc_html_e( 'People who already received a parcel.', 'codwarden' ); ?></p></div>
					<div class="codwarden-row-control"><?php self::field_switch( 'trust_customers', __( 'Never check logged-in customers with a completed order', 'codwarden' ) ); ?></div>
				</div>
			</section>

			<section class="codwarden-panel">
				<div class="codwarden-panel-head"><h2><?php esc_html_e( 'Limits', 'codwarden' ); ?></h2></div>
				<div class="codwarden-row">
					<div class="codwarden-row-label"><?php esc_html_e( 'Orders per phone', 'codwarden' ); ?><p><?php esc_html_e( 'Stops prank orders and floods.', 'codwarden' ); ?></p></div>
					<div class="codwarden-row-control">
						<?php self::field_switch( 'limit_enabled', __( 'Limit cash on delivery orders', 'codwarden' ) ); ?>
						<p>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: 1: number of orders, 2: hours */
									esc_html__( 'At most %1$s orders from the same phone or the same IP address within %2$s hours.', 'codwarden' ),
									self::field_number( 'limit_orders', __( 'Orders', 'codwarden' ) ),
									self::field_number( 'limit_hours', __( 'Hours', 'codwarden' ) )
								),
								self::kses()
							);
							?>
						</p>
					</div>
				</div>
				<div class="codwarden-row">
					<div class="codwarden-row-label"><?php esc_html_e( 'Duplicate orders', 'codwarden' ); ?><p><?php esc_html_e( 'Customers who press the button twice or come back to order the same thing.', 'codwarden' ); ?></p></div>
					<div class="codwarden-row-control">
						<?php self::field_switch( 'duplicate_guard', __( 'Stop the same order from the same phone', 'codwarden' ) ); ?>
						<p>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: minutes */
									esc_html__( 'Within %s minutes of the first one.', 'codwarden' ),
									self::field_number( 'duplicate_mins', __( 'Minutes', 'codwarden' ) )
								),
								self::kses()
							);
							?>
						</p>
					</div>
				</div>
			</section>

			<section class="codwarden-panel">
				<div class="codwarden-panel-head"><h2><?php esc_html_e( 'Refusals', 'codwarden' ); ?></h2></div>
				<div class="codwarden-row">
					<div class="codwarden-row-label"><?php esc_html_e( 'Refuser list', 'codwarden' ); ?><p><?php esc_html_e( 'Set an order to the new status "Refused" when the customer does not take the parcel.', 'codwarden' ); ?></p></div>
					<div class="codwarden-row-control">
						<?php self::field_switch( 'strikes_enabled', __( 'Remember phones that refuse parcels', 'codwarden' ) ); ?>
						<p>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: number of refusals */
									esc_html__( 'Turn off cash on delivery for a phone after %s refused orders.', 'codwarden' ),
									self::field_number( 'strikes_to_block', __( 'Refusals', 'codwarden' ) )
								),
								self::kses()
							);
							?>
						</p>
					</div>
				</div>
			</section>

			<section class="codwarden-panel">
				<div class="codwarden-panel-head"><h2><?php esc_html_e( 'Order amount', 'codwarden' ); ?></h2></div>
				<div class="codwarden-row">
					<div class="codwarden-row-label"><?php esc_html_e( 'Maximum for cash on delivery', 'codwarden' ); ?><p><?php esc_html_e( 'Big orders are the most expensive to lose.', 'codwarden' ); ?></p></div>
					<div class="codwarden-row-control">
						<p>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: 1: amount, 2: currency */
									esc_html__( 'Hide cash on delivery above %1$s %2$s. Use 0 for no limit.', 'codwarden' ),
									self::field_number( 'max_total', __( 'Amount', 'codwarden' ), '0.01' ),
									esc_html( $cur )
								),
								self::kses()
							);
							?>
						</p>
					</div>
				</div>
				<div class="codwarden-row">
					<div class="codwarden-row-label"><?php esc_html_e( 'First orders', 'codwarden' ); ?><p><?php esc_html_e( 'New customers have no delivery history yet.', 'codwarden' ); ?></p></div>
					<div class="codwarden-row-control">
						<p>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: 1: amount, 2: currency */
									esc_html__( 'For customers without a completed order, hide cash on delivery above %1$s %2$s. Use 0 for no limit.', 'codwarden' ),
									self::field_number( 'new_max_total', __( 'Amount', 'codwarden' ), '0.01' ),
									esc_html( $cur )
								),
								self::kses()
							);
							?>
						</p>
					</div>
				</div>
			</section>

			<section class="codwarden-panel">
				<div class="codwarden-panel-head"><h2><?php esc_html_e( 'Message and data', 'codwarden' ); ?></h2></div>
				<div class="codwarden-row">
					<div class="codwarden-row-label"><label for="cw-msg"><?php esc_html_e( 'Message to customers', 'codwarden' ); ?></label><p><?php esc_html_e( 'Shown when cash on delivery is refused.', 'codwarden' ); ?></p></div>
					<div class="codwarden-row-control">
						<input id="cw-msg" type="text" class="cw-wide" name="<?php echo esc_attr( CODWarden_Settings::OPTION ); ?>[message]" value="<?php echo esc_attr( CODWarden_Settings::get( 'message' ) ); ?>" placeholder="<?php echo esc_attr( CODWarden_Guard::message() ); ?>">
						<p class="codwarden-help"><?php esc_html_e( 'Leave empty to use the default. Phone and duplicate problems always get their own clear message.', 'codwarden' ); ?></p>
					</div>
				</div>
				<div class="codwarden-row">
					<div class="codwarden-row-label"><?php esc_html_e( 'Activity log', 'codwarden' ); ?></div>
					<div class="codwarden-row-control">
						<p>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: days */
									esc_html__( 'Keep the order log for %s days.', 'codwarden' ),
									self::field_number( 'retention_days', __( 'Days', 'codwarden' ) )
								),
								self::kses()
							);
							?>
						</p>
					</div>
				</div>
				<div class="codwarden-row">
					<div class="codwarden-row-label"><?php esc_html_e( 'When you delete the plugin', 'codwarden' ); ?></div>
					<div class="codwarden-row-control"><?php self::field_switch( 'delete_data', __( 'Also delete its settings, log and phone list', 'codwarden' ) ); ?></div>
				</div>
			</section>

			<div class="codwarden-actions">
				<?php submit_button( __( 'Save settings', 'codwarden' ), 'codwarden-btn', 'submit', false ); ?>
			</div>
		</form>
		<?php
	}

	/**
	 * Phone list.
	 *
	 * @return void
	 */
	private static function render_phones() {
		$rows = CODWarden_Store::list_phones();
		$msg  = isset( $_GET['cw_msg'] ) ? sanitize_key( wp_unslash( $_GET['cw_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$msgs = array(
			'blocked'   => array( 'success', __( 'Phone added to the refuser list.', 'codwarden' ) ),
			'unblocked' => array( 'success', __( 'Phone removed from the refuser list.', 'codwarden' ) ),
			'invalid'   => array( 'error', __( 'That does not look like a valid mobile number for the chosen country.', 'codwarden' ) ),
		);
		if ( isset( $msgs[ $msg ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $msgs[ $msg ][0] ), esc_html( $msgs[ $msg ][1] ) );
		}
		$base = WC()->countries->get_base_country();
		?>
		<section class="codwarden-panel">
			<div class="codwarden-panel-head"><h2><?php esc_html_e( 'Add a phone to the refuser list', 'codwarden' ); ?></h2></div>
			<div class="codwarden-panel-body">
				<form class="cw-add" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="codwarden_phone">
					<input type="hidden" name="do" value="block">
					<?php wp_nonce_field( 'codwarden_phone' ); ?>
					<select name="country" aria-label="<?php esc_attr_e( 'Country', 'codwarden' ); ?>">
						<?php foreach ( WC()->countries->get_countries() as $code => $name ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $base, $code ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="text" name="phone" placeholder="<?php esc_attr_e( 'Phone number', 'codwarden' ); ?>" aria-label="<?php esc_attr_e( 'Phone number', 'codwarden' ); ?>" required>
					<?php submit_button( __( 'Add to list', 'codwarden' ), 'codwarden-btn', 'submit', false ); ?>
				</form>
				<p class="codwarden-help"><?php esc_html_e( 'Numbers are stored only as a scrambled fingerprint plus a shortened label.', 'codwarden' ); ?></p>
			</div>
		</section>

		<section class="codwarden-panel">
			<div class="codwarden-panel-head"><h2><?php esc_html_e( 'Phones with refusals', 'codwarden' ); ?></h2></div>
			<?php if ( empty( $rows ) ) : ?>
				<div class="codwarden-empty">
					<strong><?php esc_html_e( 'No refusals yet', 'codwarden' ); ?></strong>
					<?php esc_html_e( 'Set an order to "Refused" when a parcel comes back and the phone appears here.', 'codwarden' ); ?>
				</div>
			<?php else : ?>
				<table class="codwarden-table">
					<thead><tr>
						<th scope="col"><?php esc_html_e( 'Phone', 'codwarden' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Refusals', 'codwarden' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Cash on delivery', 'codwarden' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Updated', 'codwarden' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Action', 'codwarden' ); ?></span></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><code><?php echo esc_html( $row['phone_masked'] ); ?></code></td>
							<td><?php echo esc_html( number_format_i18n( (int) $row['strikes'] ) ); ?></td>
							<td>
								<?php if ( (int) $row['blocked'] ) : ?>
									<span class="codwarden-tag is-blocked"><?php esc_html_e( 'Turned off', 'codwarden' ); ?></span>
								<?php else : ?>
									<span class="codwarden-tag is-would"><?php esc_html_e( 'Watching', 'codwarden' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="is-time">
								<?php
								/* translators: %s: time difference */
								echo esc_html( sprintf( __( '%s ago', 'codwarden' ), human_time_diff( strtotime( $row['updated_at'] . ' UTC' ) ) ) );
								?>
							</td>
							<td class="cw-right">
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="codwarden_phone">
									<input type="hidden" name="do" value="<?php echo (int) $row['blocked'] ? 'unblock' : 'block'; ?>">
									<input type="hidden" name="hash" value="<?php echo esc_attr( $row['phone_hash'] ); ?>">
									<?php wp_nonce_field( 'codwarden_phone' ); ?>
									<?php submit_button( (int) $row['blocked'] ? __( 'Allow again', 'codwarden' ) : __( 'Turn off now', 'codwarden' ), 'codwarden-btn-secondary', 'submit', false ); ?>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Block or unblock a phone.
	 *
	 * @return void
	 */
	public static function handle_phone() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'codwarden' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'codwarden_phone' );
		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';

		if ( ! empty( $_POST['hash'] ) ) {
			$hash = preg_replace( '/[^a-f0-9]/', '', sanitize_text_field( wp_unslash( $_POST['hash'] ) ) );
			$row  = CODWarden_Store::phone( $hash );
			if ( $row ) {
				CODWarden_Store::set_blocked( $hash, $row['phone_masked'], 'block' === $do );
			}
			wp_safe_redirect( self::url( 'phones', array( 'cw_msg' => 'block' === $do ? 'blocked' : 'unblocked' ) ) );
			exit;
		}

		$country = isset( $_POST['country'] ) ? strtoupper( substr( sanitize_key( wp_unslash( $_POST['country'] ) ), 0, 2 ) ) : '';
		$phone   = CODWarden_Phone::normalize( isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '', $country );
		if ( '' === $phone ) {
			wp_safe_redirect( self::url( 'phones', array( 'cw_msg' => 'invalid' ) ) );
			exit;
		}
		CODWarden_Store::set_blocked( CODWarden_Phone::hash( $phone ), CODWarden_Phone::mask( $phone ), true );
		wp_safe_redirect( self::url( 'phones', array( 'cw_msg' => 'blocked' ) ) );
		exit;
	}
}
