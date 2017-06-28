<?php

/**
 * Currency rate import model (From google.com)
 */
class Cyberhouse_GoogleCurrency_Model_Google extends Mage_Directory_Model_Currency_Import_Abstract
{

    protected $_url = 'https://www.google.com/finance/converter?a=1&from={{CURRENCY_FROM}}&to={{CURRENCY_TO}}';
    protected $_messages = array();

    CONST CONFIG_PREFIX = 'currency/import/';
    CONST REGEX_NUMBER_PATTERN = "/[0-9]*\.?[0-9]+/";

    protected function _convert ( $currencyFrom, $currencyTo, $retry = 0 )
    {
        $url = str_replace( '{{CURRENCY_FROM}}', $currencyFrom, $this->_url );
        $url = str_replace( '{{CURRENCY_TO}}', $currencyTo, $url );

        try {
            $ch = curl_init();

            // set URL and other appropriate options
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_HEADER, 0 );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );

            $timeout = Mage::getStoreConfig( 'currency/webservicex/timeout' );
            if ($timeout) {
                curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
            }

            if ($this->getConfigSetting( 'use_proxy' )) {
                if ($host = $this->getConfigSetting( 'proxy_host' )) {
                    curl_setopt( $ch, CURLOPT_PROXY, $host );
                }
                if ($port = $this->getConfigSetting( 'proxy_port' )) {
                    curl_setopt( $ch, CURLOPT_PROXYPORT, $port );
                }
                if ($username = $this->getConfigSetting( 'proxy_username' )) {
                    curl_setopt( $ch, CURLOPT_PROXYUSERNAME, $username );
                }
                if ($password = $this->getConfigSetting( 'proxy_password' )) {
                    $decrypted = Mage::helper( 'core' )->decrypt( $password );
                    curl_setopt( $ch, CURLOPT_PROXYPASSWORD, $decrypted );
                }
            }

            $res = curl_exec( $ch );
            curl_close( $ch );
            sleep( 1 ); //Be nice to Google

            $doc = new DOMDocument();
            $doc->loadHTML( $res, LIBXML_NOERROR );

            $rate = false;
            if ($result = $doc->getElementById( 'currency_converter_result' )->nodeValue) {
                $result = str_replace( "1 " . $currencyFrom . " = ", '', $result );
                $result = str_replace( " " . $currencyTo, '', $result );
                $rate = floatval( $result );
            }

            if (!$rate) {
                $this->_messages[] = Mage::helper( 'directory' )->__( 'Cannot retrieve rate from %s', $url );
                return null;
            }

            return (float)$rate * 1.0; // change 1.0 to influence rate;
        } catch (Exception $e) {
            if ($retry == 0) {
                $this->_convert( $currencyFrom, $currencyTo, 1 );
            } else {
                $this->_messages[] = Mage::helper( 'directory' )->__( 'Cannot retrieve rate from %s', $url );
            }
        }
    }

    private function getConfigSetting ( $setting )
    {
        $path = self::CONFIG_PREFIX . $setting;
        $value = Mage::getStoreConfig( $path );
        return $value;
    }
}