<?php
/**
 * Home page.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

get_header();

$has_plugin = salanaz_plugin_active();
$stats      = $has_plugin ? Salanaz_Inventory::global_stats() : array();
$estates    = $has_plugin ? Salanaz_Inventory::get_estates( array( 'posts_per_page' => 3 ) ) : array();
?>

<!-- ================= Hero ================= -->
<section class="slz-hero">
	<div class="slz-container slz-hero__inner">

		<div class="slz-hero__content">
			<span class="slz-hero__eyebrow"><?php echo esc_html( salanaz_text( 'hero_eyebrow', __( 'Verified title · Documented allocation', 'salanaz' ) ) ); ?></span>

			<h1><?php echo esc_html( salanaz_text( 'hero_heading', __( 'Own land you can actually build on.', 'salanaz' ) ) ); ?></h1>

			<p class="slz-hero__lede">
				<?php echo esc_html( salanaz_text( 'hero_lede', __( 'Salanaz sells surveyed, dry, titled plots across Lagos, Abuja, Enugu and Port Harcourt — with flexible payment plans and a portal that shows you exactly what you have paid and what is left.', 'salanaz' ) ) ); ?>
			</p>

			<div class="slz-hero__actions">
				<a class="slz-btn slz-btn--gold" href="<?php echo esc_url( salanaz_estates_url() ); ?>">
					<?php echo esc_html( salanaz_text( 'hero_cta', __( 'Browse available plots', 'salanaz' ) ) ); ?>
				</a>
				<a class="slz-btn slz-btn--ghost" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">
					<?php esc_html_e( 'Book an inspection', 'salanaz' ); ?>
				</a>
			</div>

			<ul class="slz-hero__trust">
				<li><?php esc_html_e( 'Registered survey & deed', 'salanaz' ); ?></li>
				<li><?php esc_html_e( 'No hidden charges', 'salanaz' ); ?></li>
				<li><?php esc_html_e( 'Corporate account only', 'salanaz' ); ?></li>
			</ul>
		</div>

		<?php if ( $has_plugin ) : ?>
			<form class="slz-search" role="search" method="get" action="<?php echo esc_url( salanaz_estates_url() ); ?>">
				<h2 class="slz-search__title"><?php esc_html_e( 'Find your plot', 'salanaz' ); ?></h2>

				<p class="slz-search__field">
					<label for="slz-loc"><?php esc_html_e( 'Location', 'salanaz' ); ?></label>
					<select name="estate_location" id="slz-loc">
						<option value=""><?php esc_html_e( 'All locations', 'salanaz' ); ?></option>
						<?php foreach ( salanaz_active_locations() as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<p class="slz-search__field">
					<label for="slz-size"><?php esc_html_e( 'Plot size', 'salanaz' ); ?></label>
					<select name="min_size" id="slz-size">
						<option value=""><?php esc_html_e( 'Any size', 'salanaz' ); ?></option>
						<option value="300"><?php esc_html_e( '300 sqm and above', 'salanaz' ); ?></option>
						<option value="500"><?php esc_html_e( '500 sqm and above', 'salanaz' ); ?></option>
						<option value="1000"><?php esc_html_e( '1,000 sqm and above', 'salanaz' ); ?></option>
					</select>
				</p>

				<p class="slz-search__field">
					<label for="slz-budget"><?php esc_html_e( 'Budget', 'salanaz' ); ?></label>
					<select name="max_price" id="slz-budget">
						<option value=""><?php esc_html_e( 'Any budget', 'salanaz' ); ?></option>
						<option value="5000000"><?php esc_html_e( 'Under ₦5 million', 'salanaz' ); ?></option>
						<option value="10000000"><?php esc_html_e( 'Under ₦10 million', 'salanaz' ); ?></option>
						<option value="20000000"><?php esc_html_e( 'Under ₦20 million', 'salanaz' ); ?></option>
						<option value="50000000"><?php esc_html_e( 'Under ₦50 million', 'salanaz' ); ?></option>
					</select>
				</p>

				<button type="submit" class="slz-btn slz-btn--primary slz-search__submit">
					<?php esc_html_e( 'Search plots', 'salanaz' ); ?>
				</button>
			</form>
		<?php endif; ?>

	</div>
</section>

<?php if ( $has_plugin && ! empty( $stats['plots'] ) ) : ?>
<!-- ================= Stat bar ================= -->
<section class="slz-statbar">
	<div class="slz-container slz-statbar__inner">
		<div class="slz-stat">
			<span class="slz-stat__value"><?php echo esc_html( number_format( $stats['estates'] ) ); ?></span>
			<span class="slz-stat__label"><?php esc_html_e( 'Estates', 'salanaz' ); ?></span>
		</div>
		<div class="slz-stat">
			<span class="slz-stat__value"><?php echo esc_html( number_format( $stats['available'] ) ); ?></span>
			<span class="slz-stat__label"><?php esc_html_e( 'Plots available', 'salanaz' ); ?></span>
		</div>
		<div class="slz-stat">
			<span class="slz-stat__value"><?php echo esc_html( number_format( $stats['sold'] ) ); ?></span>
			<span class="slz-stat__label"><?php esc_html_e( 'Plots allocated', 'salanaz' ); ?></span>
		</div>
		<div class="slz-stat">
			<span class="slz-stat__value">4</span>
			<span class="slz-stat__label"><?php esc_html_e( 'States covered', 'salanaz' ); ?></span>
		</div>
	</div>
</section>
<?php endif; ?>

<!-- ================= Featured estates ================= -->
<section class="slz-section" id="estates">
	<div class="slz-container">

		<header class="slz-section__head">
			<div>
				<span class="slz-eyebrow"><?php esc_html_e( 'Our estates', 'salanaz' ); ?></span>
				<h2><?php echo esc_html( salanaz_text( 'estates_title', __( 'Land available right now', 'salanaz' ) ) ); ?></h2>
				<p class="slz-section__lede">
					<?php echo esc_html( salanaz_text( 'estates_lede', __( 'Every estate below is fenced, surveyed and documented. Prices shown are current and update as plots are taken.', 'salanaz' ) ) ); ?>
				</p>
			</div>
			<a class="slz-btn slz-btn--ghost" href="<?php echo esc_url( salanaz_estates_url() ); ?>">
				<?php esc_html_e( 'See all estates', 'salanaz' ); ?>
			</a>
		</header>

		<?php if ( ! $has_plugin ) : ?>
			<div class="slz-notice slz-notice--warning">
				<?php esc_html_e( 'The Salanaz Estate Management plugin is not active, so inventory cannot be displayed.', 'salanaz' ); ?>
			</div>
		<?php elseif ( $estates ) : ?>
			<div class="slz-grid slz-grid--3">
				<?php foreach ( $estates as $estate ) : ?>
					<?php get_template_part( 'template-parts/card', 'estate', array( 'estate' => $estate ) ); ?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="slz-notice slz-notice--info">
				<?php esc_html_e( 'No estates have been published yet. Add one under Estates in the admin, or run the demo-data seeder.', 'salanaz' ); ?>
			</div>
		<?php endif; ?>

	</div>
</section>

<!-- ================= Why Salanaz ================= -->
<section class="slz-section slz-section--tint">
	<div class="slz-container">

		<header class="slz-section__head slz-section__head--center">
			<div>
				<span class="slz-eyebrow"><?php esc_html_e( 'Why Salanaz', 'salanaz' ); ?></span>
				<h2><?php echo esc_html( salanaz_text( 'why_title', __( 'Built on TRUST. Driven by EXCELLENCE.', 'salanaz' ) ) ); ?></h2>
				<p class="slz-section__lede">
					<?php echo esc_html( salanaz_text( 'why_lede', __( 'Land fraud is the single biggest fear for Nigerian buyers. Everything we do is designed to remove that risk.', 'salanaz' ) ) ); ?>
				</p>
			</div>
		</header>

		<div class="slz-grid slz-grid--4">
			<?php
			$features = array(
				array(
					'icon'  => 'shield',
					'title' => __( 'Verified title, every time', 'salanaz' ),
					'body'  => __( 'Each estate publishes its exact title type — C of O, Governor\'s Consent or Registered Survey with Deed. We hand you the documents, not promises.', 'salanaz' ),
				),
				array(
					'icon'  => 'wallet',
					'title' => __( 'Payment plans that fit', 'salanaz' ),
					'body'  => __( 'Pay outright, or spread it over 6, 12 or 24 months. The 6 and 12 month plans carry no interest at all.', 'salanaz' ),
				),
				array(
					'icon'  => 'receipt',
					'title' => __( 'A receipt for every naira', 'salanaz' ),
					'body'  => __( 'Every verified payment generates a PDF receipt automatically. Your outstanding balance is always visible in your portal.', 'salanaz' ),
				),
				array(
					'icon'  => 'user',
					'title' => __( 'A named officer, not a call centre', 'salanaz' ),
					'body'  => __( 'You are assigned a sales officer by name and phone number who follows your file from reservation through to allocation.', 'salanaz' ),
				),
			);

			foreach ( $features as $feature ) :
				?>
				<div class="slz-feature">
					<span class="slz-feature__icon" aria-hidden="true"><?php echo salanaz_icon( $feature['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<h3><?php echo esc_html( $feature['title'] ); ?></h3>
					<p><?php echo esc_html( $feature['body'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>

	</div>
</section>

<!-- ================= How it works ================= -->
<section class="slz-section">
	<div class="slz-container">

		<header class="slz-section__head slz-section__head--center">
			<div>
				<span class="slz-eyebrow"><?php esc_html_e( 'The process', 'salanaz' ); ?></span>
				<h2><?php esc_html_e( 'From first look to land in your name', 'salanaz' ); ?></h2>
			</div>
		</header>

		<ol class="slz-steps">
			<?php
			$steps = array(
				array(
					__( 'Browse and inspect', 'salanaz' ),
					__( 'Look through the estates online, then book a free site inspection. We meet you there — no obligation to buy.', 'salanaz' ),
				),
				array(
					__( 'Register and get approved', 'salanaz' ),
					__( 'Create your account. A co-founder reviews and approves it, then assigns you a named sales officer.', 'salanaz' ),
				),
				array(
					__( 'Reserve and pay', 'salanaz' ),
					__( 'Pick your plot and a payment plan. Pay by card through Paystack, or upload proof of a bank transfer for verification.', 'salanaz' ),
				),
				array(
					__( 'Receive allocation', 'salanaz' ),
					__( 'Once payment completes, we issue your receipt and allocation letter, and plant your survey pillars on site.', 'salanaz' ),
				),
			);

			foreach ( $steps as $index => list( $title, $body ) ) :
				?>
				<li class="slz-step">
					<span class="slz-step__number"><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
					<h3 class="slz-step__title"><?php echo esc_html( $title ); ?></h3>
					<p><?php echo esc_html( $body ); ?></p>
				</li>
			<?php endforeach; ?>
		</ol>

	</div>
</section>

<!-- ================= Payment plans ================= -->
<section class="slz-section slz-section--navy">
	<div class="slz-container">

		<header class="slz-section__head slz-section__head--center">
			<div>
				<span class="slz-eyebrow slz-eyebrow--gold"><?php esc_html_e( 'Payment plans', 'salanaz' ); ?></span>
				<h2><?php esc_html_e( 'Spread the cost, keep the title', 'salanaz' ); ?></h2>
				<p class="slz-section__lede">
					<?php esc_html_e( 'Your plot is reserved in your name the moment your deposit is verified. Allocation follows full payment.', 'salanaz' ); ?>
				</p>
			</div>
		</header>

		<div class="slz-grid slz-grid--3">
			<?php
			$plans = array(
				array(
					'name'     => __( 'Outright', 'salanaz' ),
					'headline' => __( 'Best value', 'salanaz' ),
					'featured' => true,
					'points'   => array(
						__( 'Pay 100% up front', 'salanaz' ),
						__( 'Immediate allocation', 'salanaz' ),
						__( 'Best price per sqm', 'salanaz' ),
						__( 'Documents within 14 working days', 'salanaz' ),
					),
				),
				array(
					'name'     => __( '6 – 12 months', 'salanaz' ),
					'headline' => __( 'Interest free', 'salanaz' ),
					'featured' => false,
					'points'   => array(
						__( '30% deposit to reserve', 'salanaz' ),
						__( 'No interest charged', 'salanaz' ),
						__( 'Monthly schedule generated for you', 'salanaz' ),
						__( 'Pay ahead any time, no penalty', 'salanaz' ),
					),
				),
				array(
					'name'     => __( '24 months', 'salanaz' ),
					'headline' => __( 'Lowest monthly', 'salanaz' ),
					'featured' => false,
					'points'   => array(
						__( '20% deposit to reserve', 'salanaz' ),
						__( 'Smallest monthly commitment', 'salanaz' ),
						__( 'Reminders 7 days and 1 day before', 'salanaz' ),
						__( 'Allocation on final payment', 'salanaz' ),
					),
				),
			);

			foreach ( $plans as $plan ) :
				?>
				<div class="slz-plan <?php echo $plan['featured'] ? 'slz-plan--featured' : ''; ?>">
					<span class="slz-plan__headline"><?php echo esc_html( $plan['headline'] ); ?></span>
					<h3 class="slz-plan__name"><?php echo esc_html( $plan['name'] ); ?></h3>
					<ul class="slz-plan__points">
						<?php foreach ( $plan['points'] as $point ) : ?>
							<li><?php echo esc_html( $point ); ?></li>
						<?php endforeach; ?>
					</ul>
					<a class="slz-btn <?php echo $plan['featured'] ? 'slz-btn--gold' : 'slz-btn--ghost'; ?>"
						href="<?php echo esc_url( salanaz_estates_url() ); ?>">
						<?php esc_html_e( 'Choose a plot', 'salanaz' ); ?>
					</a>
				</div>
			<?php endforeach; ?>
		</div>

	</div>
</section>

<!-- ================= Testimonials ================= -->
<section class="slz-section">
	<div class="slz-container">

		<header class="slz-section__head slz-section__head--center">
			<div>
				<span class="slz-eyebrow"><?php esc_html_e( 'Our clients', 'salanaz' ); ?></span>
				<h2><?php esc_html_e( 'People who now own land', 'salanaz' ); ?></h2>
			</div>
		</header>

		<div class="slz-grid slz-grid--3">
			<?php
			$testimonials = array(
				array(
					'quote'  => __( 'I was in Manchester the whole time. My officer sent photos of the pillars going in, and every payment showed up in the portal the same day. I collected my documents when I flew home in December.', 'salanaz' ),
					'name'   => __( 'Ngozi E.', 'salanaz' ),
					'detail' => __( 'Salanaz Hilltop, Enugu', 'salanaz' ),
				),
				array(
					'quote'  => __( 'What sold me was the search at the land registry. They handed me the file and told me to go and verify it myself. Nobody had ever said that to me before.', 'salanaz' ),
					'name'   => __( 'Babatunde A.', 'salanaz' ),
					'detail' => __( 'Salanaz Gardens, Ibeju-Lekki', 'salanaz' ),
				),
				array(
					'quote'  => __( 'The twelve-month plan meant I could start without touching my savings. Reminders came before each due date, so I never fell behind.', 'salanaz' ),
					'name'   => __( 'Hauwa M.', 'salanaz' ),
					'detail' => __( 'Salanaz Court, Kuje', 'salanaz' ),
				),
			);

			foreach ( $testimonials as $testimonial ) :
				?>
				<figure class="slz-quote">
					<blockquote><?php echo esc_html( $testimonial['quote'] ); ?></blockquote>
					<figcaption>
						<span class="slz-quote__name"><?php echo esc_html( $testimonial['name'] ); ?></span>
						<span class="slz-quote__detail"><?php echo esc_html( $testimonial['detail'] ); ?></span>
					</figcaption>
				</figure>
			<?php endforeach; ?>
		</div>

	</div>
</section>

<!-- ================= FAQ ================= -->
<section class="slz-section slz-section--tint">
	<div class="slz-container slz-container--narrow">

		<header class="slz-section__head slz-section__head--center">
			<div>
				<span class="slz-eyebrow"><?php esc_html_e( 'Questions', 'salanaz' ); ?></span>
				<h2><?php esc_html_e( 'Before you buy', 'salanaz' ); ?></h2>
			</div>
		</header>

		<div class="slz-faq">
			<?php
			$faqs = array(
				array(
					__( 'What title does the land carry?', 'salanaz' ),
					__( 'It varies by estate and is stated openly on every estate page — Certificate of Occupancy, Governor\'s Consent, or a Registered Survey with a Deed of Assignment. We encourage every buyer to run an independent search at the state land registry before paying.', 'salanaz' ),
				),
				array(
					__( 'Can I inspect the land before I pay?', 'salanaz' ),
					__( 'Yes, and we recommend it. Site inspections are free and we meet you on the land. Book through the contact page or ask your assigned sales officer.', 'salanaz' ),
				),
				array(
					__( 'How do I pay, and is it safe?', 'salanaz' ),
					__( 'Pay by card or transfer through Paystack inside your portal, or transfer to our corporate account and upload the proof. We never ask anyone to pay into a personal account. Every verified payment produces a PDF receipt.', 'salanaz' ),
				),
				array(
					__( 'What happens if I miss an installment?', 'salanaz' ),
					__( 'You get a reminder seven days and one day before each due date. If a payment is missed, you, your sales officer and management are all notified so it can be resolved. Your plot stays reserved while the plan is active.', 'salanaz' ),
				),
				array(
					__( 'When do I get my documents?', 'salanaz' ),
					__( 'Your receipt is issued immediately on each verified payment. The allocation letter and survey documents follow within 14 working days of completing payment.', 'salanaz' ),
				),
			);

			foreach ( $faqs as $index => list( $question, $answer ) ) :
				?>
				<details class="slz-faq__item" <?php echo 0 === $index ? 'open' : ''; ?>>
					<summary><?php echo esc_html( $question ); ?></summary>
					<div class="slz-faq__answer"><p><?php echo esc_html( $answer ); ?></p></div>
				</details>
			<?php endforeach; ?>
		</div>

	</div>
</section>

<!-- ================= News ================= -->
<?php
$news = new WP_Query(
	array(
		'post_type'           => 'post',
		'posts_per_page'      => 3,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
?>
<?php if ( $news->have_posts() ) : ?>
<section class="slz-section">
	<div class="slz-container">

		<header class="slz-section__head">
			<div>
				<span class="slz-eyebrow"><?php esc_html_e( 'News & insights', 'salanaz' ); ?></span>
				<h2><?php esc_html_e( 'From the Salanaz desk', 'salanaz' ); ?></h2>
			</div>
			<?php $blog = get_option( 'page_for_posts' ); ?>
			<a class="slz-btn slz-btn--ghost" href="<?php echo esc_url( $blog ? get_permalink( $blog ) : home_url( '/news/' ) ); ?>">
				<?php esc_html_e( 'All articles', 'salanaz' ); ?>
			</a>
		</header>

		<div class="slz-grid slz-grid--3">
			<?php
			while ( $news->have_posts() ) :
				$news->the_post();
				?>
				<article class="slz-card slz-card--post">
					<a class="slz-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
						<?php echo salanaz_card_image( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
					<div class="slz-card__body">
						<p class="slz-card__meta"><?php echo esc_html( get_the_date() ); ?></p>
						<h3 class="slz-card__title">
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h3>
						<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
						<div class="slz-card__footer">
							<a class="slz-card__link" href="<?php the_permalink(); ?>">
								<?php esc_html_e( 'Read article', 'salanaz' ); ?>
								<span aria-hidden="true">&rarr;</span>
							</a>
						</div>
					</div>
				</article>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		</div>

	</div>
</section>
<?php endif; ?>

<!-- ================= Closing CTA ================= -->
<section class="slz-cta">
	<div class="slz-container slz-cta__inner">
		<div>
			<h2><?php echo esc_html( salanaz_text( 'cta_title', __( 'Ready to secure your plot?', 'salanaz' ) ) ); ?></h2>
			<p><?php echo esc_html( salanaz_text( 'cta_lede', __( 'Create an account, get approved, and reserve your land today. Or call us and we will walk you through it.', 'salanaz' ) ) ); ?></p>
		</div>
		<div class="slz-cta__actions">
			<a class="slz-btn slz-btn--gold" href="<?php echo esc_url( home_url( '/register/' ) ); ?>">
				<?php esc_html_e( 'Create an account', 'salanaz' ); ?>
			</a>
			<?php $phone = get_theme_mod( 'salanaz_office_phone' ); ?>
			<?php if ( $phone ) : ?>
				<a class="slz-btn slz-btn--ghost" href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $phone ) ); ?>">
					<?php echo esc_html( $phone ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php
get_footer();
