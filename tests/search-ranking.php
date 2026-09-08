<?php
/** Run with php tests/search-ranking.php. All HTTP and WordPress calls are doubles. */
if ( PHP_SAPI !== 'cli' ) {
	exit;
}
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
class WP_Error {
	private $code;
	private $data;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
}
class MCF_Recipe_Admin {
	public static $debug = false;
	public static $model = 'gpt-4o-mini';
	public static function settings() { return array( 'debug_search' => self::$debug ); }
	public static function get_openai_key() { return 'test-only-placeholder'; }
	public static function get_openai_model() { return self::$model; }
}
class MCF_Recipe_Plugin {
	public static $description = 'Uses plenty of carrots';
	public static function get_recipe( $id ) {
		return array( 'title' => 'Carrot soup', 'description' => self::$description, 'search_terms' => array( 'carrot' ), 'ingredients' => array( '500 g carrots' ) );
	}
}
$cache = array();
$calls = 0;
$response = array();
function absint( $value ) { return abs( (int) $value ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_list_pluck( $items, $field ) { return array_map( function ( $item ) use ( $field ) { return isset( $item[ $field ] ) ? $item[ $field ] : null; }, $items ); }
function get_transient( $key ) { global $cache; return isset( $cache[ $key ] ) ? $cache[ $key ] : false; }
function set_transient( $key, $value, $ttl ) { global $cache; $cache[ $key ] = $value; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function wp_unslash( $value ) { return $value; }
function wp_remote_post( $url, $args ) { global $response, $calls; $calls++; return $response; }
function wp_remote_retrieve_response_code( $value ) { return $value['status']; }
function wp_remote_retrieve_body( $value ) { return $value['body']; }
require __DIR__ . '/../includes/class-mcf-recipe-rest.php';
require __DIR__ . '/../includes/class-mcf-recipe-debug.php';
require __DIR__ . '/../includes/class-mcf-recipe-mealdb.php';
function invoke( $method, ...$args ) {
	$reflection = new ReflectionMethod( 'MCF_Recipe_Rest', $method );
	$reflection->setAccessible( true );
	return $reflection->invokeArgs( null, $args );
}
function invoke_mealdb( $method, ...$args ) {
	$reflection = new ReflectionMethod( 'MCF_Recipe_MealDB', $method );
	$reflection->setAccessible( true );
	return $reflection->invokeArgs( null, $args );
}
function expect( $condition, $name ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $name ); }
	echo 'PASS: ' . $name . PHP_EOL;
}
function decision_response( $data ) {
	return array( 'status' => 200, 'body' => json_encode( array( 'status' => 'completed', 'output_text' => json_encode( $data ) ) ) );
}
function reset_rate() {
	global $cache;
	foreach ( array_keys( $cache ) as $key ) {
		if ( strpos( $key, 'mcf_ai_search_rate_' ) === 0 ) { unset( $cache[ $key ] ); }
	}
}
$empty = array( 'normalised_terms' => array( 'carrot' ), 'unmatched_terms' => array(), 'recipe_ids' => array(), 'other_recipe_ids' => array() );
$validation_candidates = array( array( 'id' => 1, 'match_band' => 'primary' ) );
$validated_empty = invoke( 'validate_search_decision', $empty, $validation_candidates );
expect( $validated_empty['recipe_ids'] === array() && $validated_empty['other_recipe_ids'] === array() && $validated_empty['normalised_terms'] === array( 'carrot' ), 'Valid empty selection is success' );
foreach ( array(
	array( 'normalised_terms' => array( 'carrot' ), 'unmatched_terms' => array(), 'recipe_ids' => array( 999 ), 'other_recipe_ids' => array() ),
	array( 'normalised_terms' => array( 'carrot' ), 'unmatched_terms' => array(), 'recipe_ids' => array( '1' ), 'other_recipe_ids' => array() ),
	array( 'normalised_terms' => array( 'carrot' ), 'unmatched_terms' => array(), 'recipe_ids' => array( 1 ), 'other_recipe_ids' => array( 1 ) ),
	array( 'normalised_terms' => array( 'carrot' ), 'unmatched_terms' => array(), 'recipe_ids' => array( -1 ), 'other_recipe_ids' => array() ),
	array( 'recipe_ids' => array() ),
) as $invalid ) {
	expect( is_wp_error( invoke( 'validate_search_decision', $invalid, $validation_candidates ) ), 'Reject malformed, unknown or duplicate IDs' );
}
$candidates = array( array( 'id' => 1, 'title' => 'Carrot soup', 'description' => 'Uses plenty of carrots', 'main_search_terms' => array( 'carrot' ), 'ingredients' => array( '500 g carrots' ) ) );
$banded = invoke( 'validate_search_decision', array( 'normalised_terms' => array( 'potato' ), 'unmatched_terms' => array(), 'recipe_ids' => array( 2, 3 ), 'other_recipe_ids' => array( 1 ) ), array( array( 'id' => 1, 'match_band' => 'primary' ), array( 'id' => 2, 'match_band' => 'secondary' ), array( 'id' => 3, 'match_band' => 'incidental' ) ) );
expect( $banded['recipe_ids'] === array() && $banded['other_recipe_ids'] === array( 1, 2 ), 'Deterministic match bands prevent weak primary results' );
$carrot_soup = invoke_mealdb( 'score_candidate', array( 'title' => 'Moroccan Carrot Soup', 'ingredients' => array( '500 g carrots', '1 onion', '500 ml vegetable stock' ), 'method' => array( 'Cook the carrots until soft.', 'Blend the carrots with the stock.' ) ), array( 'carrot' ) );
$carrot_side = invoke_mealdb( 'score_candidate', array( 'title' => 'Corned Beef and Cabbage', 'ingredients' => array( '500 g corned beef', '1 carrot', '1 small cabbage', '2 potatoes' ), 'method' => array( 'Simmer the vegetables with the beef.' ) ), array( 'carrot' ) );
$carrot_beef = invoke_mealdb( 'score_candidate', array( 'title' => 'Beef and Carrot Stew', 'ingredients' => array( '500 g beef', '500 g carrots', '1 onion', '500 ml stock' ), 'method' => array( 'Brown the beef and carrots.', 'Simmer the beef and carrots in stock.' ) ), array( 'carrot', 'beef' ) );
expect( 'primary' === $carrot_soup['match_band'], 'Carrot-led title is a primary candidate' );
expect( 'primary' !== $carrot_side['match_band'], 'A side carrot ingredient is not a primary carrot match' );
expect( 'primary' === $carrot_beef['match_band'], 'Carrot and beef title can satisfy both search terms' );
$fallback = invoke( 'deterministic_result_ids', array( array( 'id' => 1, 'match_band' => 'primary' ), array( 'id' => 2, 'match_band' => 'secondary' ), array( 'id' => 3, 'match_band' => 'incidental' ) ) );
expect( $fallback['recipe_ids'] === array( 1 ) && $fallback['other_recipe_ids'] === array( 2 ), 'Local fallback preserves strong and secondary matches' );
$carrot_interpretation = array( 'ingredients' => array( 'Carrot' ), 'residual' => array(), 'terms' => array( 'carrot' ) );
$beef_interpretation = array( 'ingredients' => array( 'Beef' ), 'residual' => array(), 'terms' => array( 'beef' ) );
$response = decision_response( $empty );
$result = invoke( 'ai_rank_recipe_search', 'carrot', $carrot_interpretation, $candidates );
expect( $result['source'] === 'ai' && $result['recipe_ids'] === array(), 'Fresh zero-match AI decision' );
expect( isset( $result['ai_duration_ms'] ), 'AI decision includes request timing' );
$result = invoke( 'ai_rank_recipe_search', 'beef', $beef_interpretation, $candidates );
expect( is_wp_error( $result ) && $result->get_error_code() === 'rate_limited', 'Uncached rapid request is explicit failure' );
reset_rate();
MCF_Recipe_Plugin::$description = 'Edited recipe';
invoke( 'ai_rank_recipe_search', 'carrot', $carrot_interpretation, $candidates );
expect( $calls === 2, 'Fresh calls are made when no learned decision is supplied to this unit' );
reset_rate();
$response = array( 'status' => 401, 'body' => 'Sensitive response not logged' );
$result = invoke( 'ai_rank_recipe_search', 'different search', $carrot_interpretation, $candidates );
expect( is_wp_error( $result ) && $result->get_error_data()['http_status'] === 401, 'HTTP failures retain safe status only' );
reset_rate();
$response = new WP_Error( 'timeout', 'Sensitive transport details' );
$result = invoke( 'ai_rank_recipe_search', 'different search', $carrot_interpretation, $candidates );
expect( is_wp_error( $result ) && $result->get_error_code() === 'transport_error', 'Transport failures remain distinct from empty success' );
reset_rate();
$response = decision_response( array( 'normalised_terms' => array( 'carrot', 'beef' ), 'unmatched_terms' => array(), 'recipe_ids' => array( 1 ), 'other_recipe_ids' => array( 2 ) ) );
$pair_candidates = array( $candidates[0], array( 'id' => 2, 'title' => 'Beef and carrot stew', 'description' => '', 'main_search_terms' => array( 'beef', 'carrot' ), 'ingredients' => array( 'beef', 'carrots' ) ) );
$pair_interpretation = array( 'ingredients' => array( 'Carrot', 'Beef' ), 'residual' => array(), 'terms' => array( 'carrot', 'beef' ) );
$result = invoke( 'ai_rank_recipe_search', 'carrots and beef', $pair_interpretation, $pair_candidates );
expect( $result['recipe_ids'] === array( 1 ) && $result['other_recipe_ids'] === array( 2 ), 'Strong and secondary results separated' );
MCF_Recipe_Debug::record( 'carrot', array() );
expect( MCF_Recipe_Debug::entries() === array(), 'Logging disabled by default' );
MCF_Recipe_Admin::$debug = true;
MCF_Recipe_Debug::record( 'carrot ' . 'sk-' . 'example-not-a-real-key test-only-placeholder', array( 'source' => 'ai', 'body' => 'Never store this' ) );
$log = MCF_Recipe_Debug::entries();
expect( strpos( json_encode( $log ), 'test-only-placeholder' ) === false && strpos( json_encode( $log ), 'sk-' ) === false, 'Search text key redaction' );
expect( ! isset( $log[0]['body'] ), 'Log accepts only allowlisted fields' );
for ( $i = 0; $i < 55; $i++ ) { MCF_Recipe_Debug::record( 'carrot', array() ); }
expect( count( MCF_Recipe_Debug::entries() ) === 50, 'Log capped at 50 entries' );
$cache[ MCF_Recipe_Debug::LOG_KEY ] = array( array( 'time' => time() - DAY_IN_SECONDS - 1 ) );
expect( MCF_Recipe_Debug::entries() === array(), 'Expired entries excluded' );
