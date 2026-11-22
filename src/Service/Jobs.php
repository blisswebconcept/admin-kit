<?php
/**
 * The plugin's background jobs, and the requests that move or stop them.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Service;

use BlissWebConcept\AdminKit\Component\JobProgress;
use BlissWebConcept\AdminKit\Component\Notice;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Jobs. Registering a job hooks its
 * WP-Cron tick; the first registration also hooks the screen's step request
 * (admin-ajax {hook}_bwc_job_step) and the Stop action (job_cancel).
 */
final class Jobs {

	/**
	 * Seconds of work in the screen's first step, so the line moves soon.
	 */
	private const FIRST_SECONDS = 2;

	/**
	 * Registered jobs: id => class name.
	 *
	 * @var array<string, class-string<Job>>
	 */
	private array $jobs = array();

	/**
	 * Constructor.
	 *
	 * @param Kit $kit The kit.
	 */
	public function __construct( private Kit $kit ) {}

	/**
	 * Registers a job.
	 *
	 * @param class-string<Job> $job Job class.
	 * @throws \InvalidArgumentException When the class isn't a Job or its id is malformed.
	 */
	public function register( string $job ): void {
		if ( ! is_subclass_of( $job, Job::class ) ) {
			throw new \InvalidArgumentException( esc_html( "Admin Kit: {$job} doesn't extend the kit's Job class." ) );
		}

		$id = $job::id();
		if ( ! preg_match( '/^[a-z][a-z0-9_]*$/', $id ) ) {
			throw new \InvalidArgumentException( esc_html( "Admin Kit: the job id \"{$id}\" must be lowercase letters, digits and underscores." ) );
		}

		if ( isset( $this->jobs[ $id ] ) ) {
			return;
		}

		if ( ! $this->jobs ) {
			add_action( 'wp_ajax_' . $this->kit->name( 'job_step' ), array( $this, 'handle_step' ) );
			$this->kit->actions()->register(
				'job_cancel',
				function ( array $input ): ?array {
					$job = $this->get( (string) $input['job'] );
					if ( null !== $job ) {
						$job::cancel();
					}
					return null;
				},
				array( 'input' => array( 'job' => 'key' ) )
			);
			$this->kit->assets()->add_script_config(
				'jobs',
				fn(): array => array(
					'action' => $this->kit->name( 'job_step' ),
					'nonce'  => wp_create_nonce( $this->kit->name( 'job_step' ) ),
				)
			);
		}

		$this->jobs[ $id ] = $job;
		add_action( $job::hook(), array( $job, 'on_cron' ) );
	}

	/**
	 * A registered job's class.
	 *
	 * @param string $id Job id.
	 * @return class-string<Job>|null
	 */
	public function get( string $id ): ?string {
		return $this->jobs[ $id ] ?? null;
	}

	/**
	 * One step of a running job, asked for by the screen about every second.
	 */
	public function handle_step(): void {
		if ( ! $this->kit->can( 'jobs' ) ) {
			wp_send_json_error( array( 'message' => $this->kit->strings()->get( 'action_not_allowed' ) ), 403 );
		}

		check_ajax_referer( $this->kit->name( 'job_step' ) );

		$job = $this->get( isset( $_POST['job'] ) ? sanitize_key( wp_unslash( $_POST['job'] ) ) : '' );
		if ( null === $job ) {
			wp_send_json_error( array( 'message' => $this->kit->strings()->get( 'action_unknown' ) ), 400 );
		}

		wp_send_json_success( $this->step( $job, ! empty( $_POST['first'] ) ) );
	}

	/**
	 * A screen step and the answer the script reads.
	 *
	 * @param class-string<Job> $job   Job class.
	 * @param bool              $first The screen's first step: a short one, so the line moves soon after the page opens.
	 * @return array{running: bool, text: string, percent: int|null, wait: int}
	 */
	public function step( string $job, bool $first = false ): array {
		$before = $job::state();
		$state  = $job::step( $first ? min( self::FIRST_SECONDS, $job::SCREEN_SECONDS ) : $job::SCREEN_SECONDS );

		return array(
			'running' => $job::is_running( $state ),
			'text'    => null === $state ? '' : $job::progress( $state ),
			'percent' => null === $state ? 100 : $job::percent( $state ),
			// Nothing moved: another request holds the job, so ask less often.
			'wait'    => $before === $state ? 5000 : 1000,
		);
	}

	/**
	 * The job's progress card while it runs; '' otherwise.
	 *
	 * @param class-string<Job> $job   Job class.
	 * @param string            $title What it's doing: "Checking destinations".
	 */
	public function card( string $job, string $title ): string {
		$state = $job::state();
		if ( ! $job::is_running( $state ) ) {
			return '';
		}

		return JobProgress::markup(
			array(
				'job'   => $job::id(),
				'title' => $title,
				'state' => array(
					'stage'   => 'running',
					'text'    => $job::progress( (array) $state ),
					'percent' => $job::percent( (array) $state ),
				),
			)
		);
	}

	/**
	 * The notice for a job this user started, once, after it ended; '' otherwise.
	 * With failed items and a retry action, it offers to do them again.
	 *
	 * @param class-string<Job>    $job   Job class.
	 * @param array<string, mixed> $retry label (with %d for the count) and action (an Actions action that restarts the failed items).
	 */
	public function result_notice( string $job, array $retry = array() ): string {
		$state = $job::take_result( get_current_user_id() );
		if ( null === $state ) {
			return '';
		}

		$result = $job::result( $state );
		$failed = count( (array) ( $state['failed'] ?? array() ) );
		$props  = array(
			'tone' => $result['tone'],
			'text' => $result['text'],
		);

		if ( $failed > 0 && ! empty( $retry['action'] ) && $this->kit->actions()->has( (string) $retry['action'] ) ) {
			$props['actions_html'] = $this->kit->actions()->button(
				(string) $retry['action'],
				sprintf( (string) ( $retry['label'] ?? $this->kit->strings()->count( 'retry_failed', $failed ) ), $failed ),
				array(
					'size'    => 'small',
					'variant' => 'outline',
				)
			);
		}

		return Notice::markup( $props );
	}
}
