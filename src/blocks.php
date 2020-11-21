<?php
/**
 * Add extra fields into rest api to format blocks as json data.
 *
 * @package WP_REST_Blocks.
 */

namespace WP_REST_Blocks\Data;

use WP_Block_Type_Registry;

/**
 * Bootstrap filters and actions.
 */
function bootstrap() {
	add_action( 'rest_api_init', __NAMESPACE__ . '\\wp_rest_blocks_init' );
}

/**
 * Add rest api fields.
 */
function wp_rest_blocks_init() {
	$types = get_post_types(
		[
			'show_in_rest' => true,
		],
		'names'
	);

	register_rest_field(
		$types,
		'has_blocks',
		array(
			'get_callback'    => __NAMESPACE__ . '\\has_blocks_get_callback',
			'update_callback' => null,
			'schema'          => array(
				'description' => __( 'Has blocks.', 'wp-rest-blocks' ),
				'type'        => 'boolean',
			),
		)
	);

	register_rest_field(
		$types,
		'blocks',
		array(
			'get_callback'    => __NAMESPACE__ . '\\blocks_get_callback',
			'update_callback' => null,
			'schema'          => array(
				'description' => __( 'Blocks.', 'wp-rest-blocks' ),
				'type'        => 'object',
			),
		)
	);
}


/**
 * Callback to get if post content has block data.
 *
 * @param array $object Array of data rest api request.
 *
 * @return bool
 */
function has_blocks_get_callback( $object ) {
	return has_blocks( $object['id'] );
}

/**
 * Loop around all blocks and get block data.
 *
 * @param array $object Array of data rest api request.
 *
 * @return array
 */
function blocks_get_callback( $object ) {
	if ( !isset($object['content'])) return [];
	$blocks = parse_blocks( $object['content']['raw'] );
	$output = [];
	foreach ( $blocks as $block ) {
		$block_data = handle_do_block( $block );
		if ( $block_data ) {
			$output[] = $block_data;
		}
	}

	return $output;
}

/**
 * Process a block, getting all extra fields.
 *
 * @param array $block Block data.
 *
 * @return array
 */
function handle_do_block( $block ) {
	if ( ! $block['blockName'] ) {
		return false;
	}

	$block['rendered'] = render_block( $block );
	$block['rendered'] = do_shortcode( $block['rendered'] );
	$block['attrs']    = wp_parse_args( $block['attrs'], get_block_defaults( $block['blockName'] ) );
	if ( ! empty( $block['innerBlocks'] ) ) {
		$output = [];
		foreach ( $block['innerBlocks'] as $_block ) {
			$output[] = handle_do_block( $_block );
		}
		$block['innerBlocks'] = $output;
	}
	$name = str_replace( '/', '_', $block['blockName'] );

	return apply_filters( 'block_data_' . $name, $block );
}

/**
 * Get default values for register blocks.
 *
 * @param string $name Name of block.
 *
 * @return array
 */
function get_block_defaults( $name ) {
	$defaults   = [];
	$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $name );
	if ( ! $block_type ) {
		return $defaults;
	}

	if ( ! $block_type->attributes ) {
		return $defaults;
	}

	foreach ( $block_type->attributes as $key => $attributes ) {
		if ( isset( $attributes['default'] ) ) {
			$defaults[ $key ] = $attributes['default'];
		} else {
			switch ( $attributes['type'] ) {
				case 'string':
					$defaults[ $key ] = '';
					break;
				case 'array':
					$defaults[ $key ] = [];
					break;
				case 'object':
					$defaults[ $key ] = (object) [];
					break;
			}
		}
	}

	return $defaults;
}

/**
 * Process block data with attributes.
 *
 * @param array $block Block data.
 * @param array $data  Attribute data.
 *
 * @return mixed
 */
function exact_attrs( $block, $data ) {
	$attrs = [];
	$html  = $block['rendered'];

	foreach ( $data as $key => $datum ) {
		$array = array();

		if ( 'attribute' === $datum['source'] ) {
			preg_match( '/<' . $datum['selector'] . '.*?' . $datum['attribute'] . '="([^"]*)"/i', $html, $array );
		}
		if ( 'html' === $datum['source'] ) {
			preg_match( '/<' . $datum['selector'] . '.*?>(.*)<\/' . $datum['selector'] . '>/i', $html, $array );
		}
		if ( $array ) {
			$attrs[ $key ] = array_pop( $array );
		}
	}

	$block['attrs'] = wp_parse_args( $attrs, $block['attrs'] );

	return $block;
}
