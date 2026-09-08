<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Creates a self-contained, branded A4 recipe PDF without invoking the browser print UI. */
class MCF_Recipe_PDF {
	const PAGE_WIDTH = 595.28;
	const PAGE_HEIGHT = 841.89;

	public static function render( $recipe ) {
		$logo = self::logo_image();
		$pages = array();
		$page = self::new_page( $logo );
		$y = 704;

		self::write( $page, 44, $y, 'F2', 25, '0.14 0.42 0.25', $recipe['title'] ?? 'Recipe' );
		$y -= 34;
		$meta = array_filter( array( implode( ' / ', (array) ( $recipe['cuisine'] ?? array() ) ), $recipe['cook_time'] ?? '', ! empty( $recipe['servings'] ) ? 'Serves ' . $recipe['servings'] : '' ) );
		if ( $meta ) {
			self::write( $page, 44, $y, 'F1', 10, '0.31 0.39 0.34', implode( '  |  ', $meta ) );
			$y -= 22;
		}
		if ( ! empty( $recipe['dietary'] ) ) {
			self::write( $page, 44, $y, 'F2', 9, '0.53 0.61 0.22', implode( '  |  ', (array) $recipe['dietary'] ) );
			$y -= 21;
		}
		$page .= "0.965 0.56 0.22 rg\n44 " . self::number( $y ) . " 507 3 re f\n";
		$y -= 22;

		self::paragraph( $pages, $page, $y, $logo, $recipe['description'] ?? '', 11, 507, 16 );
		self::section( $pages, $page, $y, $logo, 'Ingredients' );
		self::bullet_list( $pages, $page, $y, $logo, (array) ( $recipe['ingredients'] ?? array() ) );
		self::section( $pages, $page, $y, $logo, 'Method' );
		self::numbered_list( $pages, $page, $y, $logo, (array) ( $recipe['method'] ?? array() ) );

		if ( ! empty( $recipe['allergens'] ) ) {
			self::section( $pages, $page, $y, $logo, 'Allergen information' );
			self::paragraph( $pages, $page, $y, $logo, $recipe['allergens'], 10, 507, 14 );
		}
		if ( ! empty( $recipe['storage'] ) ) {
			self::section( $pages, $page, $y, $logo, 'Storage and reheating' );
			self::paragraph( $pages, $page, $y, $logo, $recipe['storage'], 10, 507, 14 );
		}
		if ( ! empty( $recipe['source_url'] ) ) {
			self::space( $pages, $page, $y, $logo, 32 );
			self::write( $page, 44, $y, 'F2', 9, '0.14 0.42 0.25', 'Recipe source' );
			$y -= 13;
			self::paragraph( $pages, $page, $y, $logo, $recipe['source_url'], 8, 507, 11 );
		}

		$pages[] = $page;
		return self::compile( $pages, $logo );
	}

	private static function new_page( $logo ) {
		$page = "q\n0.14 0.42 0.25 rg\n0 824 595.28 17.89 re f\nQ\n";
		if ( $logo ) {
			$page .= "q\n164 0 0 82 44 734 cm\n/Logo Do\nQ\n";
		} else {
			self::write( $page, 44, 786, 'F2', 18, '0.965 0.56 0.22', 'MARCHAM' );
			self::write( $page, 44, 766, 'F2', 9, '0.14 0.42 0.25', 'COMMUNITY FRIDGE' );
		}
		self::write( $page, 390, 782, 'F2', 9, '0.53 0.61 0.22', 'WASTE LESS. SHARE MORE.');
		return $page;
	}

	private static function section( &$pages, &$page, &$y, $logo, $title ) {
		self::space( $pages, $page, $y, $logo, 31 );
		self::write( $page, 44, $y, 'F2', 15, '0.14 0.42 0.25', $title );
		$y -= 8;
		$page .= "0.53 0.61 0.22 rg\n44 " . self::number( $y ) . " 507 1 re f\n";
		$y -= 15;
	}

	private static function paragraph( &$pages, &$page, &$y, $logo, $text, $size, $width, $leading ) {
		foreach ( self::wrap( $text, $size, $width ) as $line ) {
			self::space( $pages, $page, $y, $logo, $leading );
			self::write( $page, 44, $y, 'F1', $size, '0.16 0.24 0.19', $line );
			$y -= $leading;
		}
		$y -= 5;
	}

	private static function bullet_list( &$pages, &$page, &$y, $logo, $items ) {
		foreach ( $items as $item ) {
			$lines = self::wrap( $item, 10, 474 );
			foreach ( $lines as $index => $line ) {
				self::space( $pages, $page, $y, $logo, 14 );
				if ( 0 === $index ) {
					self::write( $page, 48, $y, 'F2', 10, '0.965 0.56 0.22', '-' );
				}
				self::write( $page, 62, $y, 'F1', 10, '0.16 0.24 0.19', $line );
				$y -= 14;
			}
			$y -= 2;
		}
		$y -= 4;
	}

	private static function numbered_list( &$pages, &$page, &$y, $logo, $items ) {
		foreach ( array_values( $items ) as $number => $item ) {
			$lines = self::wrap( $item, 10, 462 );
			foreach ( $lines as $index => $line ) {
				self::space( $pages, $page, $y, $logo, 14 );
				if ( 0 === $index ) {
					self::write( $page, 44, $y, 'F2', 10, '0.14 0.42 0.25', ( $number + 1 ) . '.' );
				}
				self::write( $page, 69, $y, 'F1', 10, '0.16 0.24 0.19', $line );
				$y -= 14;
			}
			$y -= 4;
		}
	}

	private static function space( &$pages, &$page, &$y, $logo, $height ) {
		if ( $y - $height >= 58 ) {
			return;
		}
		$pages[] = $page;
		$page = self::new_page( $logo );
		$y = 704;
		self::write( $page, 44, $y, 'F2', 12, '0.14 0.42 0.25', 'Recipe continued' );
		$y -= 25;
	}

	private static function write( &$page, $x, $y, $font, $size, $colour, $text ) {
		$text = self::escape( $text );
		if ( '' === $text ) {
			return;
		}
		$page .= "BT\n/" . $font . ' ' . self::number( $size ) . " Tf\n" . $colour . " rg\n1 0 0 1 " . self::number( $x ) . ' ' . self::number( $y ) . " Tm\n(" . $text . ") Tj\nET\n";
	}

	private static function wrap( $text, $size, $width ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
		if ( '' === $text ) {
			return array();
		}
		$limit = max( 16, (int) floor( $width / ( $size * 0.51 ) ) );
		$lines = array();
		$line = '';
		foreach ( preg_split( '/\s+/', $text ) as $word ) {
			$candidate = '' === $line ? $word : $line . ' ' . $word;
			if ( strlen( $candidate ) > $limit && '' !== $line ) {
				$lines[] = $line;
				$line = $word;
			} else {
				$line = $candidate;
			}
		}
		if ( '' !== $line ) {
			$lines[] = $line;
		}
		return $lines;
	}

	private static function escape( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		if ( function_exists( 'iconv' ) ) {
			$text = iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text );
		}
		return str_replace( array( '\\', '(', ')', "\r", "\n" ), array( '\\\\', '\\(', '\\)', ' ', ' ' ), (string) $text );
	}

	private static function logo_image() {
		$path = MCF_RECIPE_PATH . 'assets/images/marcham-community-fridge-logo.png';
		if ( ! is_readable( $path ) || ! function_exists( 'wp_get_image_editor' ) ) {
			return null;
		}
		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return null;
		}
		$temp = wp_tempnam( 'mcf-recipe-logo.jpg' );
		if ( ! $temp ) {
			return null;
		}
		$editor->set_quality( 92 );
		$saved = $editor->save( $temp, 'image/jpeg' );
		$info = ! is_wp_error( $saved ) ? @getimagesize( $temp ) : false;
		$data = $info ? @file_get_contents( $temp ) : false;
		@unlink( $temp );
		if ( ! $info || ! $data || empty( $info[0] ) || empty( $info[1] ) ) {
			return null;
		}
		return array( 'width' => (int) $info[0], 'height' => (int) $info[1], 'data' => $data );
	}

	private static function compile( $pages, $logo ) {
		$objects = array(
			1 => '<< /Type /Catalog /Pages 2 0 R >>',
			2 => '',
			3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
			4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
		);
		$image_number = 0;
		$next = 5;
		if ( $logo ) {
			$image_number = $next++;
			$objects[ $image_number ] = '<< /Type /XObject /Subtype /Image /Width ' . $logo['width'] . ' /Height ' . $logo['height'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen( $logo['data'] ) . " >>\nstream\n" . $logo['data'] . "\nendstream";
		}
		$page_numbers = array();
		foreach ( $pages as $index => $content ) {
			$footer = "0.14 0.42 0.25 rg\n44 42 507 1 re f\n";
			self::write( $footer, 44, 27, 'F1', 8, '0.31 0.39 0.34', 'Marcham Community Fridge CIC  |  marchamfridge.co.uk' );
			self::write( $footer, 492, 27, 'F1', 8, '0.31 0.39 0.34', 'Page ' . ( $index + 1 ) . ' of ' . count( $pages ) );
			$content .= $footer;
			$content_number = $next++;
			$page_number = $next++;
			$objects[ $content_number ] = '<< /Length ' . strlen( $content ) . " >>\nstream\n" . $content . "endstream";
			$resources = '<< /Font << /F1 3 0 R /F2 4 0 R >>';
			if ( $image_number ) {
				$resources .= ' /XObject << /Logo ' . $image_number . ' 0 R >>';
			}
			$resources .= ' >>';
			$objects[ $page_number ] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . '] /Resources ' . $resources . ' /Contents ' . $content_number . ' 0 R >>';
			$page_numbers[] = $page_number;
		}
		$kids = implode( ' ', array_map( function ( $number ) { return $number . ' 0 R'; }, $page_numbers ) );
		$objects[2] = '<< /Type /Pages /Count ' . count( $page_numbers ) . ' /Kids [ ' . $kids . ' ] >>';
		ksort( $objects );
		$pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array( 0 );
		foreach ( $objects as $number => $object ) {
			$offsets[ $number ] = strlen( $pdf );
			$pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
		}
		$startxref = strlen( $pdf );
		$pdf .= 'xref' . "\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
		foreach ( array_keys( $objects ) as $number ) {
			$pdf .= sprintf( '%010d 00000 n ', $offsets[ $number ] ) . "\n";
		}
		$pdf .= 'trailer' . "\n<< /Size " . ( count( $objects ) + 1 ) . ' /Root 1 0 R >>' . "\nstartxref\n" . $startxref . "\n%%EOF";
		return $pdf;
	}

	private static function number( $number ) {
		return rtrim( rtrim( number_format( (float) $number, 2, '.', '' ), '0' ), '.' );
	}
}
