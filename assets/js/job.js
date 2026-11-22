/**
 * Job progress: keeps a running job ([data-bwc-job]) moving while the screen
 * is open. It asks the server for one short step at a time, writes the line
 * and the bar, and reloads the page when the job ends so the result shows.
 * The first step is short, so the line moves soon after the page opens.
 * A failed step backs off (3 s, 6 s … up to 30 s). The line is announced at
 * most once every 10 seconds. Without the script, WP-Cron finishes the job.
 */
bwc.behaviour( '[data-bwc-job]', ( card ) => {
	const jobs = bwc.config.jobs;
	if ( ! jobs ) {
		return;
	}

	const job = card.dataset.bwcJob;
	const text = card.querySelector( '[data-bwc-job-text]' );
	const bar = card.querySelector( '[data-bwc-job-bar]' );
	let failures = 0;
	let said = 0;
	let first = true;

	const state = ( name ) => {
		[ 'is-running', 'is-retrying', 'is-waiting', 'is-done' ].forEach( ( one ) => card.classList.toggle( one, one === name || ( 'is-waiting' === name && 'is-running' === one ) ) );
	};

	const step = async () => {
		try {
			const response = await window.fetch( bwc.config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: new URLSearchParams( { action: jobs.action, job, _ajax_nonce: jobs.nonce, first: first ? '1' : '' } ),
			} );
			first = false;
			const json = await response.json();
			if ( ! json.success ) {
				throw new Error( 'step refused' );
			}

			failures = 0;
			const data = json.data;
			if ( text ) {
				text.textContent = data.text;
			}
			if ( bar ) {
				if ( null === data.percent ) {
					bar.removeAttribute( 'value' );
					bar.textContent = '';
				} else {
					bar.value = data.percent;
					bar.textContent = data.percent + '%';
				}
			}
			if ( Date.now() - said > 10000 ) {
				said = Date.now();
				bwc.announce( card, data.text );
			}

			if ( data.running ) {
				state( data.wait > 1000 ? 'is-waiting' : 'is-running' );
				window.setTimeout( step, data.wait || 1000 );
				return;
			}

			state( 'is-done' );
			card.removeAttribute( 'aria-busy' );
			if ( text ) {
				text.textContent = bwc.string( 'job_done' );
			}
			window.location.reload();
		} catch ( error ) {
			failures++;
			state( 'is-retrying' );
			if ( text ) {
				text.textContent = bwc.string( 'retrying' );
			}
			window.setTimeout( step, Math.min( 30000, 3000 * failures ) );
		}
	};

	step();
} );
