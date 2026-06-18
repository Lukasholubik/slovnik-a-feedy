/**
 * SAF Template Builder – vizuální editor šablony obsahu.
 * Interakce: klikni na makro → klikni na blok → přidá se do šablony.
 */
(function () {
	'use strict';

	var textarea     = document.getElementById('saf-template');
	var previewEl    = document.getElementById('saf-preview-rendered');
	var selectedMacro = null;

	if ( ! textarea ) return;

	// ── Makro čipy ────────────────────────────────────────────────────────────

	document.querySelectorAll('.saf-macro-chip').forEach(function (chip) {
		chip.addEventListener('click', function () {
			// Odeber výběr z ostatních
			document.querySelectorAll('.saf-macro-chip').forEach(function (c) {
				c.classList.remove('saf-macro-chip--selected');
			});
			// Vyber toto makro
			selectedMacro = this.dataset.macro;
			this.classList.add('saf-macro-chip--selected');

			// Aktualizuj stav tlačítek bloků
			document.querySelectorAll('.saf-block-btn').forEach(function (btn) {
				btn.disabled = false;
				btn.title = 'Vložit ' + selectedMacro + ' jako ' + btn.dataset.label;
			});

			// Info text
			var info = document.getElementById('saf-builder-info');
			if (info) info.textContent = 'Vybráno: {{' + selectedMacro + '}} – nyní klikni na typ bloku →';
		});
	});

	// ── Tlačítko „vložit jen makro" ───────────────────────────────────────────

	document.querySelectorAll('.saf-insert-raw').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!selectedMacro) return;
			insertAtCursor('{{' + selectedMacro + '}}');
			updatePreview();
		});
	});

	// ── Blok tlačítka ─────────────────────────────────────────────────────────

	document.querySelectorAll('.saf-block-btn').forEach(function (btn) {
		btn.disabled = !selectedMacro;
		btn.addEventListener('click', function () {
			if (!selectedMacro) {
				alert('Nejdříve klikni na makro vlevo, pak vyber typ bloku.');
				return;
			}
			var block = generateBlock(this.dataset.block, selectedMacro);
			appendToTemplate(block);
			updatePreview();

			// Vizuální feedback
			this.classList.add('saf-block-btn--inserted');
			setTimeout(function () {
				btn.classList.remove('saf-block-btn--inserted');
			}, 600);
		});
	});

	// ── Tlačítko „Smazat šablonu" ─────────────────────────────────────────────

	var clearBtn = document.getElementById('saf-clear-template');
	if (clearBtn) {
		clearBtn.addEventListener('click', function () {
			if (confirm('Opravdu smazat celou šablonu?')) {
				textarea.value = '';
				updatePreview();
			}
		});
	}

	// ── Live preview ──────────────────────────────────────────────────────────

	textarea.addEventListener('input', function () {
		updatePreview();
	});

	function updatePreview() {
		if (!previewEl) return;
		var tmpl = textarea.value;
		var row  = window.safPreviewRow || {};

		// Jednoduchá substituce (jen pro preview – bez #if podmínek).
		var rendered = tmpl;
		Object.keys(row).forEach(function (macro) {
			rendered = rendered.split('{{' + macro + '}}').join(escHtml(row[macro]));
		});
		// Odstraň nesubstituované makra a blokové komentáře pro čistý preview.
		rendered = rendered.replace(/\{\{[^}]+\}\}/g, '<span style="color:#aaa;font-style:italic">[prázdné]</span>');
		rendered = rendered.replace(/<!--\s*wp:[^>]*-->/g, '');
		rendered = rendered.replace(/<!--\s*\/wp:[^>]*-->/g, '');

		previewEl.innerHTML = rendered;
	}

	function escHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	// ── Generátor bloků ───────────────────────────────────────────────────────

	function generateBlock(blockType, macro) {
		var m = '{{' + macro + '}}';
		var wrap = '\n{{#if ' + macro + '}}\n%BLOCK%\n{{/if}}\n';

		var blocks = {
			'heading-2':    '<!-- wp:heading {"level":2} -->\n<h2 class="wp-block-heading">' + m + '</h2>\n<!-- /wp:heading -->',
			'heading-3':    '<!-- wp:heading {"level":3} -->\n<h3 class="wp-block-heading">' + m + '</h3>\n<!-- /wp:heading -->',
			'heading-4':    '<!-- wp:heading {"level":4} -->\n<h4 class="wp-block-heading">' + m + '</h4>\n<!-- /wp:heading -->',
			'paragraph':    '<!-- wp:paragraph -->\n<p>' + m + '</p>\n<!-- /wp:paragraph -->',
			'quote':        '<!-- wp:quote -->\n<blockquote class="wp-block-quote"><p>' + m + '</p></blockquote>\n<!-- /wp:quote -->',
			'list':         '<!-- wp:list -->\n<ul class="wp-block-list">{{#each ' + macro + '}}<!-- wp:list-item --><li>{{item}}</li><!-- /wp:list-item -->{{/each}}</ul>\n<!-- /wp:list -->',
			'list-num':     '<!-- wp:list {"ordered":true} -->\n<ol class="wp-block-list">{{#each ' + macro + '}}<!-- wp:list-item --><li>{{item}}</li><!-- /wp:list-item -->{{/each}}</ol>\n<!-- /wp:list -->',
			'separator':    '<!-- wp:separator -->\n<hr class="wp-block-separator"/>\n<!-- /wp:separator -->',
			'preformatted': '<!-- wp:preformatted -->\n<pre class="wp-block-preformatted">' + m + '</pre>\n<!-- /wp:preformatted -->',
		};

		var inner = blocks[blockType] || m;
		return wrap.replace('%BLOCK%', inner);
	}

	function appendToTemplate(text) {
		textarea.value = textarea.value.trimEnd() + '\n' + text;
		textarea.scrollTop = textarea.scrollHeight;
	}

	function insertAtCursor(text) {
		var start = textarea.selectionStart;
		var end   = textarea.selectionEnd;
		textarea.value = textarea.value.substring(0, start) + text + textarea.value.substring(end);
		textarea.selectionStart = textarea.selectionEnd = start + text.length;
		textarea.focus();
	}

	// Inicializuj preview
	updatePreview();

}());
