<?php
defined( 'ABSPATH' ) || exit;

/** Matching is deliberately independent of WordPress storage and submission state. */
class CGSG_Engine {
    public static function field_types() {
        return array( 'text', 'textarea', 'email', 'website', 'name', 'phone', 'address', 'select', 'multiselect', 'radio', 'checkbox', 'post_title', 'post_content', 'post_excerpt', 'post_tags', 'post_custom_field' );
    }
    public static function types() {
        return array( 'email' => 'Email address', 'email_domain' => 'Email domain', 'contains' => 'Text contains', 'word' => 'Whole word / phrase', 'exact' => 'Text equals', 'any_link' => 'Any link', 'link_domain' => 'Link domain', 'link_count' => 'Link count exceeds', 'ip' => 'IP address / CIDR range' );
    }

    public static function domain( $value ) {
        return strtolower( rtrim( trim( $value ), '.' ) );
    }

    public static function domain_matches( $host, $domain ) {
        $host = self::domain( $host );
        $domain = self::domain( $domain );
        return $domain !== '' && ( $host === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain );
    }

    public static function ip_matches( $ip, $range ) {
        $parts = explode( '/', trim( $range ) );
        $address = @inet_pton( trim( $ip ) );
        $network = @inet_pton( $parts[0] );
        if ( false === $address || false === $network || strlen( $address ) !== strlen( $network ) || count( $parts ) > 2 ) {
            return false;
        }
        $max = strlen( $network ) * 8;
        if ( isset( $parts[1] ) && ( ! ctype_digit( $parts[1] ) || (int) $parts[1] > $max ) ) {
            return false;
        }
        $bits = isset( $parts[1] ) ? (int) $parts[1] : $max;
        $bytes = intdiv( $bits, 8 );
        $remaining = $bits % 8;
        return substr( $address, 0, $bytes ) === substr( $network, 0, $bytes ) &&
            ( ! $remaining || ( ord( $address[$bytes] ) & ( 255 << ( 8 - $remaining ) ) ) === ( ord( $network[$bytes] ) & ( 255 << ( 8 - $remaining ) ) ) );
    }

    public static function links( $text ) {
        // Recognizes HTTP(S), www and bare public-looking domains, including HTML href values.
        preg_match_all( '~(?<![\pL\pN_@.-])(?:(?:https?://|www\.)[^\s<>"\x27]+|(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}(?:[/:?#][^\s<>"\x27]*)?)~iu', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ), $matches );
        $hosts = array();
        foreach ( $matches[0] as $url ) {
            $url = rtrim( $url, '.,;!)]}' );
            $host = parse_url( preg_match( '~^https?://~i', $url ) ? $url : 'https://' . $url, PHP_URL_HOST );
            if ( $host ) {
                $hosts[] = self::domain( $host );
            }
        }
        return $hosts;
    }

    private static function values( $rule, $form, $entry ) {
        $values = array();
        $selected = isset( $rule['fields'] ) ? $rule['fields'] : array();
        foreach ( $form['fields'] as $field ) {
            $id = (string) $field->id;
            if ( $selected && ! in_array( $id, $selected, true ) ) {
                continue;
            }
            $type = $field->get_input_type();
            // Never inspect passwords, upload paths, administrative HTML or hidden tracking values by default.
            if ( ! in_array( $type, self::field_types(), true ) ) {
                continue;
            }
            if ( in_array( $rule['type'], array( 'email', 'email_domain' ), true ) && $type !== 'email' && ! $selected ) {
                continue;
            }
            foreach ( $entry as $key => $value ) {
                if ( ( (string) $key === $id || strpos( (string) $key, $id . '.' ) === 0 ) && is_scalar( $value ) ) {
                    $values[] = (string) $value;
                }
            }
        }
        return $values;
    }

    public static function evaluate( $rules, $form, $entry, $ip = '' ) {
        $hits = array();
        foreach ( $rules as $rule ) {
            if ( empty( $rule['enabled'] ) || ( ! empty( $rule['form_id'] ) && (int) $rule['form_id'] !== (int) $form['id'] ) ) {
                continue;
            }
            $match = false;
            if ( $rule['type'] === 'ip' ) {
                foreach ( $rule['values'] as $range ) {
                    $match = $match || self::ip_matches( $ip, $range );
                }
            } else {
                $values = self::values( $rule, $form, $entry );
                if ( in_array( $rule['type'], array( 'any_link', 'link_domain', 'link_count' ), true ) ) {
                    $hosts = self::links( implode( "\n", $values ) );
                    $hosts = array_values( array_filter( $hosts, function( $host ) use ( $rule ) {
                        foreach ( $rule['allow_domains'] ?? array() as $allowed ) {
                            if ( self::domain_matches( $host, $allowed ) ) { return false; }
                        }
                        return true;
                    } ) );
                    if ( $rule['type'] === 'any_link' ) { $match = count( $hosts ) > 0; }
                    if ( $rule['type'] === 'link_count' ) { $match = count( $hosts ) > (int) ( $rule['values'][0] ?? 0 ); }
                    if ( $rule['type'] === 'link_domain' ) {
                        foreach ( $hosts as $host ) {
                            foreach ( $rule['values'] as $domain ) { $match = $match || self::domain_matches( $host, $domain ); }
                        }
                    }
                } else {
                    foreach ( $values as $value ) {
                        $value = trim( html_entity_decode( strip_tags( $value ), ENT_QUOTES, 'UTF-8' ) );
                        foreach ( $rule['values'] as $needle ) {
                            if ( $needle === '' ) { continue; }
                            switch ( $rule['type'] ) {
                                case 'email': case 'exact': $match = $match || preg_match( '~^' . preg_quote( $needle, '~' ) . '$~iu', $value ) === 1; break;
                                case 'email_domain':
                                    $at = strrpos( $value, '@' );
                                    $match = $match || ( $at !== false && self::domain_matches( substr( $value, $at + 1 ), $needle ) ); break;
                                case 'contains': $match = $match || preg_match( '~' . preg_quote( $needle, '~' ) . '~iu', $value ) === 1; break;
                                case 'word': $match = $match || preg_match( '~(?<![\pL\pN_])' . preg_quote( $needle, '~' ) . '(?![\pL\pN_])~iu', $value ) === 1; break;
                            }
                        }
                    }
                }
            }
            if ( $match ) { $hits[] = array( 'id' => $rule['id'], 'label' => $rule['label'] ); }
        }
        return $hits;
    }
}
