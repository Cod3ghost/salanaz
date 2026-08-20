<?php
/**
 * Demo / seed data.
 *
 * Runs automatically only once, on activation, and only when the site is
 * genuinely empty — no estates and no transactions — so a new install has
 * something to look at and edit. It never touches a site that already holds
 * real work.
 *
 * Otherwise it is invoked explicitly: from the Playground blueprint, from
 * WP-CLI (`wp salanaz seed`), or from Salanaz -> Settings, where it can also be
 * removed again.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates sample estates, plots, pages and test accounts.
 */
class Salanaz_Demo_Data {

	/** Marks every seeded object so the seeder can be re-run cleanly. */
	private const SEED_FLAG = '_salanaz_demo';

	/**
	 * Run the full seed. Idempotent — existing seeded content is reused.
	 *
	 * @return array<string, int> Counts of what was created.
	 */
	public static function seed(): array {
		$created = array(
			'estates' => 0,
			'plots'   => 0,
			'users'   => 0,
			'pages'   => 0,
			'posts'   => 0,
		);

		self::seed_pages( $created );
		self::seed_users( $created );
		self::seed_estates( $created );
		self::seed_news( $created );
		self::seed_reminder_scenario();

		delete_transient( 'salanaz_global_stats' );
		update_option( 'salanaz_demo_seeded', gmdate( 'c' ), false );

		return $created;
	}

	/**
	 * Whether this site still looks untouched.
	 *
	 * Used to decide if demo content can be added automatically. A site with any
	 * transaction, or any estate that was not seeded, is somebody's real work and
	 * is left alone.
	 *
	 * @return bool
	 */
	public static function site_is_empty(): bool {
		global $wpdb;

		$table = salanaz_table( 'transactions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) > 0 ) {
			return false;
		}

		$estates = get_posts(
			array(
				'post_type'      => Salanaz_Post_Types::ESTATE,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			)
		);

		return empty( $estates );
	}

	/**
	 * Remove everything the seeder created.
	 *
	 * Only touches objects carrying the seed flag, so anything added by hand
	 * survives. Refuses to run if real payments exist, because a demo plot may
	 * by then be attached to a genuine transaction.
	 *
	 * @return array{posts:int, users:int}|WP_Error
	 */
	public static function remove() {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'salanaz_forbidden', __( 'You are not allowed to do that.', 'salanaz' ) );
		}

		$table = salanaz_table( 'transactions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$payments = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		if ( $payments > 0 ) {
			return new WP_Error(
				'salanaz_has_payments',
				__( 'There are payment records on this site, so the demo content was not removed. Delete the demo estates and accounts by hand once you are sure nothing real is attached to them.', 'salanaz' )
			);
		}

		$removed = array(
			'posts' => 0,
			'users' => 0,
		);

		$posts = get_posts(
			array(
				'post_type'      => array( Salanaz_Post_Types::ESTATE, Salanaz_Post_Types::PLOT, 'post', 'page' ),
				'posts_per_page' => 1000,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => self::SEED_FLAG,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( $posts as $post_id ) {
			// Leave the pages the system depends on in place.
			$slug = get_post_field( 'post_name', $post_id );

			if ( in_array( $slug, array( 'login', 'register', 'dashboard' ), true ) ) {
				delete_post_meta( $post_id, self::SEED_FLAG );
				continue;
			}

			if ( wp_delete_post( $post_id, true ) ) {
				++$removed['posts'];
			}
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';

		$users = get_users(
			array(
				'meta_key'   => self::SEED_FLAG, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 1, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'     => 'ID',
			)
		);

		foreach ( $users as $user_id ) {
			if ( (int) $user_id === get_current_user_id() ) {
				continue;
			}

			if ( wp_delete_user( (int) $user_id ) ) {
				++$removed['users'];
			}
		}

		foreach ( array( 'salanaz_demo_seeded', 'salanaz_demo_reminder_seeded' ) as $option ) {
			delete_option( $option );
		}

		delete_transient( 'salanaz_global_stats' );
		Salanaz_Reports::flush();

		return $removed;
	}

	/* =====================================================================
	 * Estates and plots
	 * ================================================================== */

	/**
	 * Estate definitions. Prices reflect realistic Nigerian market ranges.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function estate_definitions(): array {
		return array(
			array(
				'slug'      => 'salanaz-gardens-ibeju-lekki',
				'title'     => 'Salanaz Gardens, Ibeju-Lekki',
				'location'  => 'Lagos',
				'address'   => 'Off Lekki-Epe Expressway, Ibeju-Lekki, Lagos State',
				'lat'       => 6.4698,
				'lng'       => 3.9470,
				'title_doc' => 'Registered Survey + Deed of Assignment',
				'amenities' => array( 'Perimeter Fencing', 'Tarred Roads', 'Drainage System', 'Green Areas', '24/7 Security', 'Street Lights' ),
				'excerpt'   => 'Dry, fenced and fully surveyed land minutes from the Lekki Free Trade Zone and the Dangote Refinery corridor.',
				'body'      => "Salanaz Gardens sits along the fastest-appreciating stretch of the Lekki-Epe corridor, within reach of the Lekki Deep Sea Port, the Dangote Refinery and the new international airport site.\n\nThe estate is fully fenced with a gated entrance, internal tarred access roads and a functioning drainage system. Every plot is dry land — no reclamation required before you build.\n\nTitle is a Registered Survey backed by a Deed of Assignment, and allocation happens within 14 working days of full payment.",
				'plots'     => array(
					array( 'size' => 300, 'price' => 12500000, 'count' => 8, 'status' => 'available' ),
					array( 'size' => 500, 'price' => 19800000, 'count' => 5, 'status' => 'available' ),
					array( 'size' => 500, 'price' => 19800000, 'count' => 2, 'status' => 'reserved' ),
					array( 'size' => 1000, 'price' => 37500000, 'count' => 2, 'status' => 'available' ),
					array( 'size' => 300, 'price' => 12500000, 'count' => 3, 'status' => 'sold' ),
				),
			),
			array(
				'slug'      => 'salanaz-court-kuje-abuja',
				'title'     => 'Salanaz Court, Kuje',
				'location'  => 'Abuja (FCT)',
				'address'   => 'Kuje Area Council, Federal Capital Territory, Abuja',
				'lat'       => 8.8792,
				'lng'       => 7.2276,
				'title_doc' => 'Certificate of Occupancy (C of O)',
				'amenities' => array( 'C of O Title', 'Tarred Roads', 'Electricity', 'Water Borehole', 'Perimeter Fencing' ),
				'excerpt'   => 'C of O land in the FCT, twenty minutes from Nnamdi Azikiwe International Airport.',
				'body'      => "Salanaz Court is a residential scheme in Kuje Area Council, roughly twenty minutes from Nnamdi Azikiwe International Airport and well served by the Abuja–Lokoja expressway.\n\nThe land carries a full Certificate of Occupancy, which remains the strongest title you can hold in the Federal Capital Territory. Infrastructure already on site includes graded and tarred internal roads, an electricity connection and a functioning borehole.\n\nThis estate suits buyers who want to build immediately rather than hold purely for appreciation.",
				'plots'     => array(
					array( 'size' => 300, 'price' => 5200000, 'count' => 10, 'status' => 'available' ),
					array( 'size' => 500, 'price' => 8400000, 'count' => 6, 'status' => 'available' ),
					array( 'size' => 500, 'price' => 8400000, 'count' => 1, 'status' => 'reserved' ),
					array( 'size' => 1000, 'price' => 15900000, 'count' => 3, 'status' => 'available' ),
					array( 'size' => 300, 'price' => 5200000, 'count' => 4, 'status' => 'sold' ),
				),
			),
			array(
				'slug'      => 'salanaz-hilltop-enugu',
				'title'     => 'Salanaz Hilltop Estate, Enugu',
				'location'  => 'Enugu',
				'address'   => 'Independence Layout Extension, Enugu State',
				'lat'       => 6.4584,
				'lng'       => 7.5464,
				'title_doc' => 'Governor\'s Consent',
				'amenities' => array( 'Gated Entrance', 'Paved Roads', 'Green Areas', 'Recreation Park', 'Security Post' ),
				'excerpt'   => 'Elevated, well-drained plots in a quiet residential pocket of Enugu with excellent road access.',
				'body'      => "Salanaz Hilltop Estate occupies elevated ground on the Independence Layout Extension, giving it natural drainage and cooler evenings than the city centre.\n\nThe estate is laid out around a central recreation park, with paved internal roads and a manned security post at the single gated entrance.\n\nTitle is perfected through Governor's Consent. This is a strong entry point for diaspora buyers and first-time landowners — plots here carry our lowest deposit requirement.",
				'plots'     => array(
					array( 'size' => 300, 'price' => 3800000, 'count' => 12, 'status' => 'available' ),
					array( 'size' => 450, 'price' => 5400000, 'count' => 7, 'status' => 'available' ),
					array( 'size' => 600, 'price' => 7100000, 'count' => 4, 'status' => 'available' ),
					array( 'size' => 300, 'price' => 3800000, 'count' => 2, 'status' => 'sold' ),
				),
			),
			array(
				'slug'      => 'salanaz-legacy-port-harcourt',
				'title'     => 'Salanaz Legacy Estate, Port Harcourt',
				'location'  => 'Rivers',
				'address'   => 'Off Airport Road, Omagwa, Port Harcourt, Rivers State',
				'lat'       => 5.0154,
				'lng'       => 6.9497,
				'title_doc' => 'Registered Survey + Deed of Assignment',
				'amenities' => array( 'Perimeter Fencing', 'Drainage System', 'Electricity', 'Security Post' ),
				'excerpt'   => 'Sand-filled plots on the Omagwa airport corridor, priced for early-stage entry.',
				'body'      => "Salanaz Legacy Estate sits on the Omagwa corridor off Airport Road, an area absorbing steady residential demand from Port Harcourt's expansion northwards.\n\nAll plots are sand-filled and ready for construction, with perimeter fencing and a drainage system already installed. Electricity runs to the estate boundary.\n\nBecause the estate is at an early development stage, pricing is set below comparable Port Harcourt schemes, with the six- and twelve-month plans attracting no interest.",
				'plots'     => array(
					array( 'size' => 300, 'price' => 4500000, 'count' => 9, 'status' => 'available' ),
					array( 'size' => 500, 'price' => 7200000, 'count' => 5, 'status' => 'available' ),
					array( 'size' => 500, 'price' => 7200000, 'count' => 1, 'status' => 'reserved' ),
					array( 'size' => 1000, 'price' => 13800000, 'count' => 2, 'status' => 'available' ),
				),
			),
		);
	}

	/**
	 * Create estates, their taxonomy terms and their plots.
	 *
	 * @param array<string, int> $created Running totals, by reference.
	 */
	private static function seed_estates( array &$created ): void {
		foreach ( self::estate_definitions() as $index => $definition ) {
			$estate = get_page_by_path( $definition['slug'], OBJECT, Salanaz_Post_Types::ESTATE );

			if ( $estate ) {
				$estate_id = $estate->ID;
			} else {
				$estate_id = wp_insert_post(
					array(
						'post_type'    => Salanaz_Post_Types::ESTATE,
						'post_title'   => $definition['title'],
						'post_name'    => $definition['slug'],
						'post_content' => $definition['body'],
						'post_excerpt' => $definition['excerpt'],
						'post_status'  => 'publish',
						'menu_order'   => $index,
					)
				);

				if ( is_wp_error( $estate_id ) ) {
					continue;
				}

				++$created['estates'];
			}

			update_post_meta( $estate_id, self::SEED_FLAG, 1 );
			update_post_meta( $estate_id, Salanaz_Post_Types::META_ESTATE_ADDRESS, $definition['address'] );
			update_post_meta( $estate_id, Salanaz_Post_Types::META_ESTATE_LAT, $definition['lat'] );
			update_post_meta( $estate_id, Salanaz_Post_Types::META_ESTATE_LNG, $definition['lng'] );
			update_post_meta( $estate_id, Salanaz_Post_Types::META_ESTATE_TITLE_DOC, $definition['title_doc'] );

			wp_set_object_terms( $estate_id, $definition['location'], Salanaz_Post_Types::TAX_LOCATION );
			wp_set_object_terms( $estate_id, $definition['amenities'], Salanaz_Post_Types::TAX_AMENITY );

			self::seed_plots( $estate_id, $definition, $created );

			Salanaz_Inventory::flush_estate_stats( $estate_id );
		}
	}

	/**
	 * Create the plots for one estate.
	 *
	 * @param int                  $estate_id  Estate post ID.
	 * @param array<string, mixed> $definition Estate definition.
	 * @param array<string, int>   $created    Running totals, by reference.
	 */
	private static function seed_plots( int $estate_id, array $definition, array &$created ): void {
		$block  = 'A';
		$number = 1;

		foreach ( $definition['plots'] as $group ) {
			for ( $i = 0; $i < $group['count']; $i++ ) {
				$plot_ref = sprintf( '%s%02d', $block, $number );
				$slug     = $definition['slug'] . '-plot-' . strtolower( $plot_ref );
				++$number;

				if ( $number > 20 ) {
					$number = 1;
					++$block;
				}

				if ( get_page_by_path( $slug, OBJECT, Salanaz_Post_Types::PLOT ) ) {
					continue;
				}

				$plot_id = wp_insert_post(
					array(
						'post_type'    => Salanaz_Post_Types::PLOT,
						'post_title'   => sprintf(
							/* translators: 1: plot reference, 2: size, 3: estate name */
							__( 'Plot %1$s — %2$dsqm, %3$s', 'salanaz' ),
							$plot_ref,
							$group['size'],
							$definition['title']
						),
						'post_name'    => $slug,
						'post_content' => sprintf(
							/* translators: 1: size, 2: estate name, 3: title document */
							__( 'A %1$dsqm residential plot at %2$s. Title: %3$s. Allocation follows within 14 working days of full payment.', 'salanaz' ),
							$group['size'],
							$definition['title'],
							$definition['title_doc']
						),
						'post_status'  => 'publish',
					)
				);

				if ( is_wp_error( $plot_id ) ) {
					continue;
				}

				update_post_meta( $plot_id, self::SEED_FLAG, 1 );
				update_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_ESTATE, $estate_id );
				update_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_NUMBER, $plot_ref );
				update_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_SIZE_SQM, $group['size'] );
				update_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_PRICE, $group['price'] );
				update_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_STATUS, $group['status'] );

				++$created['plots'];
			}
		}
	}

	/**
	 * Put one client on a live installment plan with dates worth reminding on.
	 *
	 * Without this there is nothing for the reminder sweep to act on until a
	 * month has passed, which makes the automation impossible to demonstrate or
	 * test. Creates a plan whose instalments fall overdue, due tomorrow and due
	 * in seven days.
	 */
	private static function seed_reminder_scenario(): void {
		global $wpdb;

		if ( get_option( 'salanaz_demo_reminder_seeded' ) ) {
			return;
		}

		$client = get_user_by( 'login', 'client2' );

		if ( ! $client instanceof WP_User ) {
			return;
		}

		// An available plot from the Port Harcourt estate.
		$estate = get_page_by_path( 'salanaz-legacy-port-harcourt', OBJECT, Salanaz_Post_Types::ESTATE );

		if ( ! $estate ) {
			return;
		}

		$plots = Salanaz_Inventory::get_plots_for_estate(
			$estate->ID,
			array( 'posts_per_page' => 1 )
		);

		if ( ! $plots ) {
			return;
		}

		$plot  = $plots[0];
		$price = Salanaz_Inventory::plot_price( $plot->ID );
		$quote = Salanaz_Plans::quote( Salanaz_Plans::TWELVE, $price );

		if ( ! $quote ) {
			return;
		}

		update_post_meta( $plot->ID, Salanaz_Post_Types::META_PLOT_STATUS, Salanaz_Post_Types::STATUS_RESERVED );
		update_post_meta( $plot->ID, Salanaz_Post_Types::META_PLOT_RESERVED_BY, $client->ID );

		$plan_id = Salanaz_Plans::create_plan( $client->ID, $plot->ID, Salanaz_Plans::TWELVE, $price );

		if ( is_wp_error( $plan_id ) ) {
			return;
		}

		// A verified deposit, so the plan is genuinely live.
		$now = current_time( 'mysql', true );

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			salanaz_table( 'transactions' ),
			array(
				'reference'      => salanaz_generate_reference( 'TXN' ),
				'client_id'      => $client->ID,
				'plot_id'        => $plot->ID,
				'plan_id'        => $plan_id,
				'amount'         => $quote['deposit'],
				'currency'       => 'NGN',
				'payment_method' => Salanaz_Schema::METHOD_MANUAL,
				'payment_type'   => Salanaz_Schema::TYPE_DOWNPAYMENT,
				'status'         => Salanaz_Schema::TXN_VERIFIED,
				'verified_at'    => $now,
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);

		// Pull the first three instalments into the reminder windows.
		$schedule = Salanaz_Plans::schedule( (int) $plan_id );
		$offsets  = array( '-6 days', '+1 day', '+7 days' );

		foreach ( $offsets as $index => $offset ) {
			if ( empty( $schedule[ $index ] ) ) {
				continue;
			}

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				salanaz_table( 'installments' ),
				array( 'due_date' => gmdate( 'Y-m-d', strtotime( $offset, current_time( 'timestamp' ) ) ) ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
				array( 'id' => (int) $schedule[ $index ]->id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		Salanaz_Inventory::flush_estate_stats( $estate->ID );
		update_option( 'salanaz_demo_reminder_seeded', gmdate( 'c' ), false );
	}

	/* =====================================================================
	 * Users
	 * ================================================================== */

	/**
	 * Test accounts, one per role plus a pending client.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function user_definitions(): array {
		return array(
			array(
				'login'  => 'cofounder',
				'email'  => 'cofounder@salanaz.test',
				'first'  => 'Amina',
				'last'   => 'Salihu',
				'role'   => Salanaz_Roles::COFOUNDER,
				'status' => Salanaz_Profile::STATUS_APPROVED,
				'phone'  => '08031234567',
			),
			array(
				'login'  => 'staff1',
				'email'  => 'grace.staff@salanaz.test',
				'first'  => 'Grace',
				'last'   => 'Okonkwo',
				'role'   => Salanaz_Roles::STAFF,
				'status' => Salanaz_Profile::STATUS_APPROVED,
				'phone'  => '08052345678',
			),
			array(
				'login'  => 'staff2',
				'email'  => 'musa.staff@salanaz.test',
				'first'  => 'Musa',
				'last'   => 'Ibrahim',
				'role'   => Salanaz_Roles::STAFF,
				'status' => Salanaz_Profile::STATUS_APPROVED,
				'phone'  => '08063456789',
			),
			array(
				'login'  => 'client1',
				'email'  => 'chidi.client@salanaz.test',
				'first'  => 'Chidi',
				'last'   => 'Nwosu',
				'role'   => Salanaz_Roles::CLIENT,
				'status' => Salanaz_Profile::STATUS_APPROVED,
				'phone'  => '08074567890',
			),
			array(
				'login'  => 'client2',
				'email'  => 'fatima.client@salanaz.test',
				'first'  => 'Fatima',
				'last'   => 'Bello',
				'role'   => Salanaz_Roles::CLIENT,
				'status' => Salanaz_Profile::STATUS_APPROVED,
				'phone'  => '08085678901',
			),
			array(
				'login'  => 'client3',
				'email'  => 'tunde.pending@salanaz.test',
				'first'  => 'Tunde',
				'last'   => 'Adeyemi',
				'role'   => Salanaz_Roles::CLIENT,
				'status' => Salanaz_Profile::STATUS_PENDING,
				'phone'  => '08096789012',
			),
		);
	}

	/**
	 * Create the test accounts. All share the password "password".
	 *
	 * @param array<string, int> $created Running totals, by reference.
	 */
	private static function seed_users( array &$created ): void {
		foreach ( self::user_definitions() as $definition ) {
			$user = get_user_by( 'login', $definition['login'] );

			if ( ! $user ) {
				$user_id = wp_insert_user(
					array(
						'user_login'   => $definition['login'],
						'user_email'   => $definition['email'],
						'user_pass'    => 'password',
						'first_name'   => $definition['first'],
						'last_name'    => $definition['last'],
						'display_name' => $definition['first'] . ' ' . $definition['last'],
						'role'         => $definition['role'],
					)
				);

				if ( is_wp_error( $user_id ) ) {
					continue;
				}

				++$created['users'];
			} else {
				$user_id = $user->ID;
			}

			update_user_meta( $user_id, Salanaz_Profile::META_ACCOUNT_STATUS, $definition['status'] );
			update_user_meta( $user_id, Salanaz_Profile::META_PHONE, salanaz_normalize_phone( $definition['phone'] ) );
			update_user_meta( $user_id, Salanaz_Profile::META_REGISTERED_AT, gmdate( 'Y-m-d H:i:s' ) );
			update_user_meta( $user_id, self::SEED_FLAG, 1 );
		}
	}

	/* =====================================================================
	 * Pages and news
	 * ================================================================== */

	/**
	 * Create the standard site pages and set the front page.
	 *
	 * @param array<string, int> $created Running totals, by reference.
	 */
	private static function seed_pages( array &$created ): void {
		$pages = array(
			'home'     => array( 'Home', '' ),
			'news'     => array( 'News & Insights', '' ),
			'about'    => array( 'About Us', "Salanaz Global Services Ltd is a Nigerian real estate company built on a simple promise: land you can verify, documents you can hold, and a payment record you can check at any time.\n\nWe acquire, verify and develop land across Lagos, Abuja, Enugu and Port Harcourt, then sell it in surveyed plots with clear title and flexible payment plans." ),
			'contact'  => array( 'Contact', "We would be glad to hear from you. Call, send a message, or book an inspection and we will meet you on site." ),
			'faq'      => array( 'Frequently Asked Questions', "Answers to the questions we are asked most often about title, payment plans, allocation and inspections." ),
			'dashboard' => array( 'My Dashboard', '[salanaz_dashboard]' ),
			'login'    => array( 'Sign In', '[salanaz_login]' ),
			'register' => array( 'Create an Account', '[salanaz_register]' ),
			'terms'    => array( 'Terms & Conditions', 'These terms govern the sale and allocation of land by Salanaz Global Services Ltd.' ),
			'privacy'  => array( 'Privacy Policy', 'How Salanaz Global Services Ltd collects, stores and protects your personal data in line with the Nigeria Data Protection Act.' ),
		);

		$ids = array();

		foreach ( $pages as $slug => list( $title, $content ) ) {
			$existing = get_page_by_path( $slug );

			if ( $existing ) {
				$ids[ $slug ] = $existing->ID;
				continue;
			}

			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_content' => $content,
					'post_status'  => 'publish',
				)
			);

			if ( ! is_wp_error( $page_id ) ) {
				$ids[ $slug ] = $page_id;
				update_post_meta( $page_id, self::SEED_FLAG, 1 );
				++$created['pages'];
			}
		}

		// A static front page so front-page.php is used rather than the blog index.
		if ( isset( $ids['home'] ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $ids['home'] );
		}

		if ( isset( $ids['news'] ) ) {
			update_option( 'page_for_posts', $ids['news'] );
		}

		if ( isset( $ids['privacy'] ) ) {
			update_option( 'wp_page_for_privacy_policy', $ids['privacy'] );
		}

		self::seed_menus( $ids );
	}

	/**
	 * Build the primary, footer and legal menus.
	 *
	 * @param array<string, int> $page_ids Page IDs keyed by slug.
	 */
	private static function seed_menus( array $page_ids ): void {
		$menus = array(
			'primary' => array(
				array( 'title' => 'Home', 'url' => home_url( '/' ) ),
				array( 'title' => 'Estates', 'url' => home_url( '/estates/' ) ),
				array( 'title' => 'About', 'page' => 'about' ),
				array( 'title' => 'News', 'url' => home_url( '/news/' ) ),
				array( 'title' => 'FAQ', 'page' => 'faq' ),
				array( 'title' => 'Contact', 'page' => 'contact' ),
			),
			'footer'  => array(
				array( 'title' => 'About Us', 'page' => 'about' ),
				array( 'title' => 'Estates', 'url' => home_url( '/estates/' ) ),
				array( 'title' => 'Contact', 'page' => 'contact' ),
				array( 'title' => 'FAQ', 'page' => 'faq' ),
			),
			'legal'   => array(
				array( 'title' => 'Terms & Conditions', 'page' => 'terms' ),
				array( 'title' => 'Privacy Policy', 'page' => 'privacy' ),
			),
		);

		$locations = get_theme_mod( 'nav_menu_locations', array() );

		foreach ( $menus as $location => $items ) {
			$name    = 'Salanaz ' . ucfirst( $location );
			$menu    = wp_get_nav_menu_object( $name );
			$menu_id = $menu ? (int) $menu->term_id : (int) wp_create_nav_menu( $name );

			if ( ! $menu_id || is_wp_error( $menu_id ) ) {
				continue;
			}

			// Only populate a freshly created menu.
			if ( ! $menu && ! wp_get_nav_menu_items( $menu_id ) ) {
				foreach ( $items as $item ) {
					if ( isset( $item['page'] ) && isset( $page_ids[ $item['page'] ] ) ) {
						wp_update_nav_menu_item(
							$menu_id,
							0,
							array(
								'menu-item-title'     => $item['title'],
								'menu-item-object'    => 'page',
								'menu-item-object-id' => $page_ids[ $item['page'] ],
								'menu-item-type'      => 'post_type',
								'menu-item-status'    => 'publish',
							)
						);
					} elseif ( isset( $item['url'] ) ) {
						wp_update_nav_menu_item(
							$menu_id,
							0,
							array(
								'menu-item-title'  => $item['title'],
								'menu-item-url'    => $item['url'],
								'menu-item-type'   => 'custom',
								'menu-item-status' => 'publish',
							)
						);
					}
				}
			}

			$locations[ $location ] = $menu_id;
		}

		set_theme_mod( 'nav_menu_locations', $locations );
		set_theme_mod( 'salanaz_office_address', '14 Adeola Odeku Street, Victoria Island, Lagos' );
		set_theme_mod( 'salanaz_office_phone', '+234 803 123 4567' );
		set_theme_mod( 'salanaz_office_email', 'hello@salanaz.test' );

		// Corporate account shown on the manual payment page.
		update_option( 'salanaz_bank_account_name', 'Salanaz Global Services Ltd', false );
		update_option( 'salanaz_bank_name', 'Guaranty Trust Bank (GTBank)', false );
		update_option( 'salanaz_bank_account_number', '0123456789', false );
	}

	/**
	 * A few news posts so the blog loop is not empty.
	 *
	 * @param array<string, int> $created Running totals, by reference.
	 */
	private static function seed_news( array &$created ): void {
		$posts = array(
			array(
				'title'   => 'Allocation exercise completed at Salanaz Gardens, Ibeju-Lekki',
				'excerpt' => 'Thirty-one subscribers received physical allocation and signed their deeds on site last weekend.',
				'body'    => "Thirty-one subscribers at Salanaz Gardens received physical allocation over the weekend, with survey pillars planted and deeds of assignment signed on site.\n\nAllocation exercises are held monthly. Subscribers who have completed payment are notified through the client portal and by email at least ten days ahead.",
			),
			array(
				'title'   => 'How to verify land title in Nigeria before you pay',
				'excerpt' => 'A practical checklist: search at the land registry, confirm the survey, and never pay into a personal account.',
				'body'    => "Before any money changes hands, three checks protect you.\n\nFirst, conduct a search at the state land registry to confirm the title and that the land is not under government acquisition. Second, take the survey plan to the Surveyor-General's office for a chart to confirm the coordinates. Third, never pay into an individual's personal account — a legitimate company will issue an invoice and receive payment corporately.\n\nEvery Salanaz estate publishes its title type on the estate page, and our portal issues a receipt for every payment automatically.",
			),
			array(
				'title'   => 'Understanding our installment plans',
				'excerpt' => 'Six, twelve and twenty-four month options, what deposit each requires, and how the schedule is generated.',
				'body'    => "Our installment plans run over six, twelve or twenty-four months. The six and twelve month plans attract no interest.\n\nOnce your deposit is verified, the portal generates your full payment schedule with each due date and amount. You will receive a reminder seven days and one day before each due date, and your assigned sales officer sees the same schedule you do.\n\nYou can pay ahead of schedule at any time without penalty.",
			),
		);

		foreach ( $posts as $index => $item ) {
			$slug = sanitize_title( $item['title'] );

			if ( get_page_by_path( $slug, OBJECT, 'post' ) ) {
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => 'post',
					'post_title'   => $item['title'],
					'post_name'    => $slug,
					'post_excerpt' => $item['excerpt'],
					'post_content' => $item['body'],
					'post_status'  => 'publish',
					'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( "-{$index} weeks" ) ),
				)
			);

			if ( ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, self::SEED_FLAG, 1 );
				++$created['posts'];
			}
		}
	}
}
