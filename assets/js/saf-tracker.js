/**
 * SAF Tracker – sledování kliknutí na odchozí a interní linky.
 * Spouští se na single stránkách CPT streamů.
 */
(function () {
	'use strict';

	if (typeof safTracker === 'undefined') return;

	var config   = safTracker;
	var postId   = parseInt(config.postId, 10);
	var sent     = false;

	/**
	 * Odešle click event na REST endpoint pluginu.
	 */
	function sendClick() {
		if (sent) return;
		sent = true;

		var data = JSON.stringify({ post_id: postId, nonce: config.nonce });

		// Preferuj sendBeacon (funguje i při zavírání stránky).
		if (navigator.sendBeacon) {
			var blob = new Blob([data], { type: 'application/json' });
			navigator.sendBeacon(config.restUrl, blob);
		} else {
			fetch(config.restUrl, {
				method:      'POST',
				headers:     { 'Content-Type': 'application/json' },
				body:        data,
				keepalive:   true
			}).catch(function () {});
		}
	}

	/**
	 * Sleduj kliknutí na všechny linky na stránce.
	 * Počítáme klik = uživatel kliknul na odkaz který ho odvede z aktuální stránky.
	 */
	document.addEventListener('click', function (e) {
		var el = e.target;
		// Najdi nejbližší <a> rodič.
		while (el && el.tagName !== 'A') {
			el = el.parentElement;
		}
		if (!el || !el.href) return;

		var href = el.href;

		// Interní odkaz na jinou stránku nebo odchozí = počítáme jako click.
		if (href && href !== window.location.href && !href.startsWith('#')) {
			sendClick();
		}
	});

	// Sleduj opuštění stránky (pagehide / beforeunload).
	window.addEventListener('pagehide', sendClick);

}());
