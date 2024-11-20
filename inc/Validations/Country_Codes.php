<?php

namespace MeuMouse\Joinotify\Validations;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Country codes class
 * 
 * @since 1.0.0
 * @package MeuMouse.com
 */
class Country_Codes {

    /**
     * Get country codes with their respective country names
     * 
     * @since 1.0.0
     * @return array
     */
    public static function get_country_codes_with_names() {
        $countries = apply_filters( 'Joinotify/Validations/Get_Country_Codes_With_Names', array(
            '1' => [ 'US' => __('United States', 'joinotify'), 'CA' => __('Canada', 'joinotify') ],
            '7' => [ 'RU' => __('Russia', 'joinotify'), 'KZ' => __('Kazakhstan', 'joinotify') ],
            '20' => [ 'EG' => __('Egypt', 'joinotify') ],
            '27' => [ 'ZA' => __('South Africa', 'joinotify') ],
            '30' => [ 'GR' => __('Greece', 'joinotify') ],
            '31' => [ 'NL' => __('Netherlands', 'joinotify') ],
            '32' => [ 'BE' => __('Belgium', 'joinotify') ],
            '33' => [ 'FR' => __('France', 'joinotify') ],
            '34' => [ 'ES' => __('Spain', 'joinotify') ],
            '36' => [ 'HU' => __('Hungary', 'joinotify') ],
            '39' => [ 'IT' => __('Italy', 'joinotify') ],
            '40' => [ 'RO' => __('Romania', 'joinotify') ],
            '41' => [ 'CH' => __('Switzerland', 'joinotify') ],
            '43' => [ 'AT' => __('Austria', 'joinotify') ],
            '44' => [ 'GB' => __('United Kingdom', 'joinotify') ],
            '45' => [ 'DK' => __('Denmark', 'joinotify') ],
            '46' => [ 'SE' => __('Sweden', 'joinotify') ],
            '47' => [ 'NO' => __('Norway', 'joinotify') ],
            '48' => [ 'PL' => __('Poland', 'joinotify') ],
            '49' => [ 'DE' => __('Germany', 'joinotify') ],
            '51' => [ 'PE' => __('Peru', 'joinotify') ],
            '52' => [ 'MX' => __('Mexico', 'joinotify') ],
            '53' => [ 'CU' => __('Cuba', 'joinotify') ],
            '54' => [ 'AR' => __('Argentina', 'joinotify') ],
            '55' => [ 'BR' => __('Brasil', 'joinotify') ],
            '56' => [ 'CL' => __('Chile', 'joinotify') ],
            '57' => [ 'CO' => __('Colombia', 'joinotify') ],
            '58' => [ 'VE' => __('Venezuela', 'joinotify') ],
            '60' => [ 'MY' => __('Malaysia', 'joinotify') ],
            '61' => [ 'AU' => __('Australia', 'joinotify') ],
            '62' => [ 'ID' => __('Indonesia', 'joinotify') ],
            '63' => [ 'PH' => __('Philippines', 'joinotify') ],
            '64' => [ 'NZ' => __('New Zealand', 'joinotify') ],
            '65' => [ 'SG' => __('Singapore', 'joinotify') ],
            '66' => [ 'TH' => __('Thailand', 'joinotify') ],
            '81' => [ 'JP' => __('Japan', 'joinotify') ],
            '82' => [ 'KR' => __('South Korea', 'joinotify') ],
            '84' => [ 'VN' => __('Vietnam', 'joinotify') ],
            '86' => [ 'CN' => __('China', 'joinotify') ],
            '90' => [ 'TR' => __('Turkey', 'joinotify') ],
            '91' => [ 'IN' => __('India', 'joinotify') ],
            '92' => [ 'PK' => __('Pakistan', 'joinotify') ],
            '93' => [ 'AF' => __('Afghanistan', 'joinotify') ],
            '94' => [ 'LK' => __('Sri Lanka', 'joinotify') ],
            '95' => [ 'MM' => __('Myanmar', 'joinotify') ],
            '98' => [ 'IR' => __('Iran', 'joinotify') ],
            '211' => [ 'SS' => __('South Sudan', 'joinotify') ],
            '212' => [ 'MA' => __('Morocco', 'joinotify'), 'EH' => __('Western Sahara', 'joinotify') ],
            '213' => [ 'DZ' => __('Algeria', 'joinotify') ],
            '216' => [ 'TN' => __('Tunisia', 'joinotify') ],
            '218' => [ 'LY' => __('Libya', 'joinotify') ],
            '220' => [ 'GM' => __('Gambia', 'joinotify') ],
            '221' => [ 'SN' => __('Senegal', 'joinotify') ],
            '222' => [ 'MR' => __('Mauritania', 'joinotify') ],
            '223' => [ 'ML' => __('Mali', 'joinotify') ],
            '224' => [ 'GN' => __('Guinea', 'joinotify') ],
            '225' => [ 'CI' => __('Ivory Coast', 'joinotify') ],
            '226' => [ 'BF' => __('Burkina Faso', 'joinotify') ],
            '227' => [ 'NE' => __('Niger', 'joinotify') ],
            '228' => [ 'TG' => __('Togo', 'joinotify') ],
            '229' => [ 'BJ' => __('Benin', 'joinotify') ],
            '230' => [ 'MU' => __('Mauritius', 'joinotify') ],
            '231' => [ 'LR' => __('Liberia', 'joinotify') ],
            '232' => [ 'SL' => __('Sierra Leone', 'joinotify') ],
            '233' => [ 'GH' => __('Ghana', 'joinotify') ],
            '234' => [ 'NG' => __('Nigeria', 'joinotify') ],
            '235' => [ 'TD' => __('Chad', 'joinotify') ],
            '236' => [ 'CF' => __('Central African Republic', 'joinotify') ],
            '237' => [ 'CM' => __('Cameroon', 'joinotify') ],
            '238' => [ 'CV' => __('Cape Verde', 'joinotify') ],
            '239' => [ 'ST' => __('São Tomé and Príncipe', 'joinotify') ],
            '240' => [ 'GQ' => __('Equatorial Guinea', 'joinotify') ],
            '241' => [ 'GA' => __('Gabon', 'joinotify') ],
            '242' => [ 'CG' => __('Republic of the Congo', 'joinotify') ],
            '243' => [ 'CD' => __('Democratic Republic of the Congo', 'joinotify') ],
            '244' => [ 'AO' => __('Angola', 'joinotify') ],
            '245' => [ 'GW' => __('Guinea-Bissau', 'joinotify') ],
            '246' => [ 'IO' => __('British Indian Ocean Territory', 'joinotify') ],
            '248' => [ 'SC' => __('Seychelles', 'joinotify') ],
            '249' => [ 'SD' => __('Sudan', 'joinotify') ],
            '250' => [ 'RW' => __('Rwanda', 'joinotify') ],
            '251' => [ 'ET' => __('Ethiopia', 'joinotify') ],
            '252' => [ 'SO' => __('Somalia', 'joinotify') ],
            '253' => [ 'DJ' => __('Djibouti', 'joinotify') ],
            '254' => [ 'KE' => __('Kenya', 'joinotify') ],
            '255' => [ 'TZ' => __('Tanzania', 'joinotify') ],
            '256' => [ 'UG' => __('Uganda', 'joinotify') ],
            '257' => [ 'BI' => __('Burundi', 'joinotify') ],
            '258' => [ 'MZ' => __('Mozambique', 'joinotify') ],
            '260' => [ 'ZM' => __('Zambia', 'joinotify') ],
            '261' => [ 'MG' => __('Madagascar', 'joinotify') ],
            '262' => [ 'RE' => __('Réunion', 'joinotify'), 'YT' => __('Mayotte', 'joinotify') ],
            '263' => [ 'ZW' => __('Zimbabwe', 'joinotify') ],
            '264' => [ 'NA' => __('Namibia', 'joinotify') ],
            '265' => [ 'MW' => __('Malawi', 'joinotify') ],
            '266' => [ 'LS' => __('Lesotho', 'joinotify') ],
            '267' => [ 'BW' => __('Botswana', 'joinotify') ],
            '268' => [ 'SZ' => __('Eswatini (Swaziland)', 'joinotify') ],
            '269' => [ 'KM' => __('Comoros', 'joinotify') ],
            '290' => [ 'SH' => __('Saint Helena', 'joinotify') ],
            '297' => [ 'AW' => __('Aruba', 'joinotify') ],
            '298' => [ 'FO' => __('Faroe Islands', 'joinotify') ],
            '299' => [ 'GL' => __('Greenland', 'joinotify') ],
            '350' => [ 'GI' => __('Gibraltar', 'joinotify') ],
            '351' => [ 'PT' => __('Portugal', 'joinotify') ],
            '352' => [ 'LU' => __('Luxembourg', 'joinotify') ],
            '353' => [ 'IE' => __('Ireland', 'joinotify') ],
            '354' => [ 'IS' => __('Iceland', 'joinotify') ],
            '355' => [ 'AL' => __('Albania', 'joinotify') ],
            '356' => [ 'MT' => __('Malta', 'joinotify') ],
            '357' => [ 'CY' => __('Cyprus', 'joinotify') ],
            '358' => [ 'FI' => __('Finland', 'joinotify'), 'AX' => __('Åland Islands', 'joinotify') ],
            '359' => [ 'BG' => __('Bulgaria', 'joinotify') ],
            '370' => [ 'LT' => __('Lithuania', 'joinotify') ],
            '371' => [ 'LV' => __('Latvia', 'joinotify') ],
            '372' => [ 'EE' => __('Estonia', 'joinotify') ],
            '373' => [ 'MD' => __('Moldova', 'joinotify') ],
            '374' => [ 'AM' => __('Armenia', 'joinotify') ],
            '375' => [ 'BY' => __('Belarus', 'joinotify') ],
            '376' => [ 'AD' => __('Andorra', 'joinotify') ],
            '377' => [ 'MC' => __('Monaco', 'joinotify') ],
            '378' => [ 'SM' => __('San Marino', 'joinotify') ],
            '379' => [ 'VA' => __('Vatican City', 'joinotify') ],
            '380' => [ 'UA' => __('Ukraine', 'joinotify') ],
            '381' => [ 'RS' => __('Serbia', 'joinotify') ],
            '382' => [ 'ME' => __('Montenegro', 'joinotify') ],
            '385' => [ 'HR' => __('Croatia', 'joinotify') ],
            '386' => [ 'SI' => __('Slovenia', 'joinotify') ],
            '387' => [ 'BA' => __('Bosnia and Herzegovina', 'joinotify') ],
            '389' => [ 'MK' => __('North Macedonia', 'joinotify') ],
            '420' => [ 'CZ' => __('Czech Republic', 'joinotify') ],
            '421' => [ 'SK' => __('Slovakia', 'joinotify') ],
            '423' => [ 'LI' => __('Liechtenstein', 'joinotify') ],
            '500' => [ 'FK' => __('Falkland Islands (Malvinas)', 'joinotify') ],
            '501' => [ 'BZ' => __('Belize', 'joinotify') ],
            '502' => [ 'GT' => __('Guatemala', 'joinotify') ],
            '503' => [ 'SV' => __('El Salvador', 'joinotify') ],
            '504' => [ 'HN' => __('Honduras', 'joinotify') ],
            '505' => [ 'NI' => __('Nicaragua', 'joinotify') ],
            '506' => [ 'CR' => __('Costa Rica', 'joinotify') ],
            '507' => [ 'PA' => __('Panama', 'joinotify') ],
            '508' => [ 'PM' => __('Saint Pierre and Miquelon', 'joinotify') ],
            '509' => [ 'HT' => __('Haiti', 'joinotify') ],
            '590' => [ 'GP' => __('Guadeloupe', 'joinotify'), 'BL' => __('Saint Barthelemy', 'joinotify'), 'MF' => __('Saint Martin', 'joinotify') ],
            '591' => [ 'BO' => __('Bolivia', 'joinotify') ],
            '592' => [ 'GY' => __('Guyana', 'joinotify') ],
            '593' => [ 'EC' => __('Ecuador', 'joinotify') ],
            '594' => [ 'GF' => __('French Guiana', 'joinotify') ],
            '595' => [ 'PY' => __('Paraguay', 'joinotify') ],
            '596' => [ 'MQ' => __('Martinique', 'joinotify') ],
            '597' => [ 'SR' => __('Suriname', 'joinotify') ],
            '598' => [ 'UY' => __('Uruguay', 'joinotify') ],
            '599' => [ 'CW' => __('Curaçao', 'joinotify'), 'BQ' => __('Dutch Caribbean', 'joinotify') ],
            '670' => [ 'TL' => __('East Timor', 'joinotify') ],
            '672' => [ 'NF' => __('Norfolk Island', 'joinotify') ],
            '673' => [ 'BN' => __('Brunei', 'joinotify') ],
            '674' => [ 'NR' => __('Nauru', 'joinotify') ],
            '675' => [ 'PG' => __('Papua New Guinea', 'joinotify') ],
            '676' => [ 'TO' => __('Tonga', 'joinotify') ],
            '677' => [ 'SB' => __('Solomon Islands', 'joinotify') ],
            '678' => [ 'VU' => __('Vanuatu', 'joinotify') ],
            '679' => [ 'FJ' => __('Fiji', 'joinotify') ],
            '680' => [ 'PW' => __('Palau', 'joinotify') ],
            '681' => [ 'WF' => __('Wallis and Futuna', 'joinotify') ],
            '682' => [ 'CK' => __('Cook Islands', 'joinotify') ],
            '683' => [ 'NU' => __('Niue', 'joinotify') ],
            '685' => [ 'WS' => __('Samoa', 'joinotify') ],
            '686' => [ 'KI' => __('Kiribati', 'joinotify') ],
            '687' => [ 'NC' => __('New Caledonia', 'joinotify') ],
            '688' => [ 'TV' => __('Tuvalu', 'joinotify') ],
            '689' => [ 'PF' => __('French Polynesia', 'joinotify') ],
            '690' => [ 'TK' => __('Tokelau', 'joinotify') ],
            '691' => [ 'FM' => __('Federated States of Micronesia', 'joinotify') ],
            '692' => [ 'MH' => __('Marshall Islands', 'joinotify') ],
            '850' => [ 'KP' => __('North Korea', 'joinotify') ],
            '852' => [ 'HK' => __('Hong Kong', 'joinotify') ],
            '853' => [ 'MO' => __('Macau', 'joinotify') ],
            '855' => [ 'KH' => __('Cambodia', 'joinotify') ],
            '856' => [ 'LA' => __('Lao', 'joinotify') ],
            '880' => [ 'BD' => __('Bangladesh', 'joinotify') ],
            '886' => [ 'TW' => __('Taiwan', 'joinotify') ],
            '960' => [ 'MV' => __('Maldives', 'joinotify') ],
            '961' => [ 'LB' => __('Lebanon', 'joinotify') ],
            '962' => [ 'JO' => __('Jordan', 'joinotify') ],
            '963' => [ 'SY' => __('Syria', 'joinotify') ],
            '964' => [ 'IQ' => __('Iraq', 'joinotify') ],
            '965' => [ 'KW' => __('Kuwait', 'joinotify') ],
            '966' => [ 'SA' => __('Saudi Arabia', 'joinotify') ],
            '967' => [ 'YE' => __('Yemen', 'joinotify') ],
            '968' => [ 'OM' => __('Oman', 'joinotify') ],
            '970' => [ 'PS' => __('Palestine', 'joinotify') ],
            '971' => [ 'AE' => __('United Arab Emirates', 'joinotify') ],
            '972' => [ 'IL' => __('Israel', 'joinotify') ],
            '973' => [ 'BH' => __('Bahrain', 'joinotify') ],
            '974' => [ 'QA' => __('Qatar', 'joinotify') ],
            '975' => [ 'BT' => __('Bhutan', 'joinotify') ],
            '976' => [ 'MN' => __('Mongolia', 'joinotify') ],
            '977' => [ 'NP' => __('Nepal', 'joinotify') ],
            '992' => [ 'TJ' => __('Tajikistan', 'joinotify') ],
            '993' => [ 'TM' => __('Turkmenistan', 'joinotify') ],
            '994' => [ 'AZ' => __('Azerbaijan', 'joinotify') ],
            '995' => [ 'GE' => __('Georgia', 'joinotify') ],
            '996' => [ 'KG' => __('Kyrgyzstan', 'joinotify') ],
            '998' => [ 'UZ' => __('Uzbekistan', 'joinotify') ]
        ));

        // Sort the array of countries based on the country code (numeric key)
        ksort( $countries, SORT_NUMERIC );

        return $countries;
    }


    /**
     * Get the list of country codes for validation purposes.
     * 
     * @since 1.0.0
     * @return array
     */
    public static function get_country_codes_for_validation() {
        $countries = self::get_country_codes_with_names();
        
        // Extract just the numeric country codes for validation
        $country_codes = array_keys( $countries );
        
        // Allow filtering of country codes for validation
        return apply_filters( 'Joinotify/Validations/Valid_Country_Codes', $country_codes );
    }


    /**
     * Build country code select options
     * 
     * @since 1.0.0
     * @return array
     */
    public static function build_country_code_select() {
        $countries = self::get_country_codes_with_names();
        $options = array();

        foreach ( $countries as $code => $country_data ) {
            foreach ( $country_data as $country_abbr => $country_name ) {
                $options[] = array(
                    'code' => $code,
                    'country' => $country_name
                );
            }
        }

        // Sort countries by numeric code
        usort( $options, function( $a, $b ) {
            return $a['code'] - $b['code'];
        });

        return $options;
    }


    /**
     * Detects the country code from a phone number or IP address.
     *
     * @since 1.0.0
     * @param string $phone_number | Phone number in E.164 format or with explicit country codes.
     * @param string $ip_address | User's IP address (optional, for geolocation fallback).
     * @return string Detected country code (e.g. 'US', 'BR').
     */
    public function detect_country_code( $phone_number = '', $ip_address = '' ) {
        $country_code = self::detect_from_phone_number( $phone_number );

        if ( ! $country_code && $ip_address ) {
            $country_code = self::detect_from_ip( $ip_address );
        }

        return $country_code;
    }


    /**
     * Detects the country code based on the phone number.
     *
     * @since 1.0.0
     * @param string $phone_number | Phone number in E.164 format or with explicit country codes.
     * @return string|null Country code or null if unable to determine.
     */
    public static function detect_from_phone_number( $phone_number ) {
        // Removes all non-numeric characters
        $phone_number = preg_replace('/\D/', '', $phone_number);

        // Checks if the phone number starts with a known country code
        $country_codes = self::get_country_codes_for_validation();

        foreach ( $country_codes as $code => $countries ) {
            if ( strpos( $phone_number, $code ) === 0 ) {
                return $countries[0]; // Returns the first country associated with the code
            }
        }

        return null;
    }


    /**
     * Detects the country code based on the IP address
     *
     * @since 1.0.0
     * @param string $ip_address | IP address of the user
     * @return string|null Country code, or null if unable to determine
     */
    public static function detect_from_ip( $ip_address ) {
        // Use an external IP-based geolocation API
        $response = wp_remote_get('http://ipinfo.io/{$ip_address}/json');

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        return isset( $data['country'] ) ? $data['country'] : null;
    }
}