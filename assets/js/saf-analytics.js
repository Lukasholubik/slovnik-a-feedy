/**
 * SAF Analytics – admin dashboard grafy.
 * Závisí na Chart.js v4.
 */
(function () {
	'use strict';

	if (typeof safAnalytics === 'undefined') return;

	var data   = safAnalytics;
	var labels = data.labels;
	var views  = data.views;
	var clicks = data.clicks;

	var ctx = document.getElementById('saf-chart');
	if (!ctx) return;

	new Chart(ctx, {
		type: 'line',
		data: {
			labels: labels,
			datasets: [
				{
					label: data.i18n.views,
					data: views,
					borderColor: '#1a1a2e',
					backgroundColor: 'rgba(26,26,46,0.08)',
					borderWidth: 2,
					pointRadius: 3,
					tension: 0.3,
					fill: true,
				},
				{
					label: data.i18n.clicks,
					data: clicks,
					borderColor: '#e94560',
					backgroundColor: 'rgba(233,69,96,0.08)',
					borderWidth: 2,
					pointRadius: 3,
					tension: 0.3,
					fill: true,
				},
			],
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			interaction: { mode: 'index', intersect: false },
			plugins: {
				legend: { position: 'top' },
				tooltip: { mode: 'index' },
			},
			scales: {
				x: {
					grid: { color: 'rgba(0,0,0,0.05)' },
					ticks: { maxTicksLimit: 15 },
				},
				y: {
					beginAtZero: true,
					grid: { color: 'rgba(0,0,0,0.05)' },
				},
			},
		},
	});

	// Sparklines – mini SVG grafy v tabulce.
	document.querySelectorAll('[data-sparkline]').forEach(function (el) {
		var vals  = JSON.parse(el.dataset.sparkline);
		var max   = Math.max.apply(null, vals) || 1;
		var w     = 80;
		var h     = 24;
		var step  = w / Math.max(vals.length - 1, 1);

		var points = vals.map(function (v, i) {
			return (i * step).toFixed(1) + ',' + (h - (v / max) * h).toFixed(1);
		}).join(' ');

		el.innerHTML = '<svg width="' + w + '" height="' + h + '" viewBox="0 0 ' + w + ' ' + h + '">'
			+ '<polyline points="' + points + '" fill="none" stroke="#1a1a2e" stroke-width="1.5" stroke-linejoin="round"/>'
			+ '</svg>';
	});

}());
