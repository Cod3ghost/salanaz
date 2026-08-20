<?php
/**
 * WP-CLI commands.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manage the Salanaz estate system.
 */
class Salanaz_CLI {

	/**
	 * Create the demo estates, plots and test accounts.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp salanaz seed
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags.
	 */
	public function seed( $args, $assoc_args ): void {
		WP_CLI::confirm(
			'This creates demo estates, plots and test accounts with the password "password". Continue?',
			$assoc_args
		);

		$created = Salanaz_Demo_Data::seed();

		WP_CLI::success(
			sprintf(
				'Seeded %d estates, %d plots, %d users, %d pages, %d posts.',
				$created['estates'],
				$created['plots'],
				$created['users'],
				$created['pages'],
				$created['posts']
			)
		);

		WP_CLI::warning( 'Test accounts all use the password "password". Remove them before going live.' );
	}

	/**
	 * Run the installment reminder sweep now.
	 *
	 * ## EXAMPLES
	 *
	 *     wp salanaz reminders
	 */
	public function reminders(): void {
		$counts = ( new Salanaz_Cron() )->run();

		WP_CLI::success(
			sprintf(
				'%d due in 7 days, %d due tomorrow, %d overdue.',
				$counts['due_7'],
				$counts['due_1'],
				$counts['overdue']
			)
		);
	}

	/**
	 * Create any missing pages the system depends on.
	 *
	 * ## EXAMPLES
	 *
	 *     wp salanaz pages
	 */
	public function pages(): void {
		$map = Salanaz_Pages::ensure();

		foreach ( $map as $slug => $id ) {
			WP_CLI::log( sprintf( '  /%s/ -> post %d', $slug, $id ) );
		}

		$problems = Salanaz_Pages::problems();

		if ( $problems ) {
			foreach ( $problems as $problem ) {
				WP_CLI::warning( sprintf( '/%s/ — %s', $problem['slug'], $problem['reason'] ) );
			}

			return;
		}

		WP_CLI::success( 'All required pages are present.' );
	}

	/**
	 * Show a short status report.
	 *
	 * ## EXAMPLES
	 *
	 *     wp salanaz status
	 */
	public function status(): void {
		$portfolio = Salanaz_Reports::portfolio();
		$pending   = Salanaz_Reports::pending_verification();
		$overdue   = Salanaz_Reports::overdue();

		WP_CLI::log( 'Collected:    ' . salanaz_money( $portfolio['collected'] ) );
		WP_CLI::log( 'Outstanding:  ' . salanaz_money( $portfolio['outstanding'] ) );
		WP_CLI::log( 'Plots sold:   ' . $portfolio['plots_sold'] );
		WP_CLI::log( 'Awaiting:     ' . $pending['count'] . ' payment(s), ' . salanaz_money( $pending['value'] ) );
		WP_CLI::log( 'Overdue:      ' . $overdue['count'] . ' instalment(s), ' . salanaz_money( $overdue['value'] ) );
		WP_CLI::log( 'Paystack:     ' . ( Salanaz_Paystack::is_available() ? 'enabled' : 'not configured' ) );
		WP_CLI::log( 'Next sweep:   ' . ( Salanaz_Cron::next_run() ? gmdate( 'Y-m-d H:i', Salanaz_Cron::next_run() ) . ' UTC' : 'not scheduled' ) );
		WP_CLI::log( 'Proof store:  ' . salanaz_private_dir() );

		if ( salanaz_private_dir_is_web_reachable() ) {
			WP_CLI::warning( 'Proof storage is inside the web root. Confirm your server blocks direct access.' );
		}
	}
}

WP_CLI::add_command( 'salanaz', 'Salanaz_CLI' );
