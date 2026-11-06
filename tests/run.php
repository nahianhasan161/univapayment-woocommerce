<?php
// Plain PHP so it runs anywhere: php tests/run.php
define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/../includes/class-signature.php';

$failed = 0;
function check( $name, $ok ) {
	global $failed;
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $name . PHP_EOL;
	if ( ! $ok ) {
		$failed++;
	}
}

$secret = 'whsec_example';
$body   = '{"event":"payment.completed"}';
$now    = 1800000000;
$sign   = function ( $t, $b = null, $s = null ) use ( $secret, $body ) {
	return 't=' . $t . ',v1=' . hash_hmac( 'sha256', $t . '.' . ( null === $b ? $body : $b ), null === $s ? $secret : $s );
};

check( 'accepts a good signature', UnivaPayment_Signature::is_valid( $sign( $now ), $body, $secret, $now ) );
check( 'accepts a little clock difference', UnivaPayment_Signature::is_valid( $sign( $now - 299 ), $body, $secret, $now ) );
check( 'refuses an old signature', ! UnivaPayment_Signature::is_valid( $sign( $now - 301 ), $body, $secret, $now ) );
check( 'refuses a changed body', ! UnivaPayment_Signature::is_valid( $sign( $now ), $body . ' ', $secret, $now ) );
check( 'refuses the wrong secret', ! UnivaPayment_Signature::is_valid( $sign( $now, null, 'other' ), $body, $secret, $now ) );
check( 'refuses when no secret is set', ! UnivaPayment_Signature::is_valid( $sign( $now, null, '' ), $body, '', $now ) );
check( 'refuses an empty header', ! UnivaPayment_Signature::is_valid( '', $body, $secret, $now ) );
check( 'refuses a missing header', ! UnivaPayment_Signature::is_valid( null, $body, $secret, $now ) );
check( 'refuses a header with no signature', ! UnivaPayment_Signature::is_valid( 't=' . $now, $body, $secret, $now ) );
check( 'refuses a bad timestamp', ! UnivaPayment_Signature::is_valid( 't=abc,v1=00', $body, $secret, $now ) );

exit( $failed ? 1 : 0 );
