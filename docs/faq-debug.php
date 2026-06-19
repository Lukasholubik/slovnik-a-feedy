<?php
/**
 * Debug: zobrazí raw post_content FAQ bloku.
 * Otevřít přes: reboost-test.local/wp-content/plugins/slovnik-a-feedy/docs/faq-debug.php?post_id=XXX
 */
require dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';
$id   = absint( $_GET['post_id'] ?? 0 );
$post = $id ? get_post($id) : null;
if (!$post) { die('?post_id=<ID> povinné'); }
// Najdi FAQ blok v obsahu.
preg_match('/<!-- wp:rank-math\/faq-block ({.*?}) -->/s', $post->post_content, $m);
if (!$m) { die('FAQ blok nenalezen v post ID=' . $id); }
$json = json_decode($m[1], true);
echo '<pre>JSON valid: ' . ($json ? 'ANO ✓' : 'NE ✗ ' . json_last_error_msg()) . "\n\n";
echo htmlspecialchars($m[1]);
echo '</pre>';
