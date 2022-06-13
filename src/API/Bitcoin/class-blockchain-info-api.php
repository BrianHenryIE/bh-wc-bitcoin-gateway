<?php
/**
 * "Please limit your queries to a maximum of 1 every 10 seconds"
 *
 * @see https://www.blockchain.com/api/blockchain_api
 * @see https://www.blockchain.com/api/q
 *
 * @package    nullcorps/woocommerce-gateway-bitcoin
 */

namespace Nullcorps\WC_Gateway_Bitcoin\API\Bitcoin;

use DateTimeInterface;
use Nullcorps\WC_Gateway_Bitcoin\API\API_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * @phpstan-import-type TransactionArray from API_Interface as TransactionArray
 */
class Blockchain_Info_API implements Blockchain_API_Interface {
	use LoggerAwareTrait;

	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}


	/**
	 *
	 * @see Blockchain_API_Interface::get_received_by_address()
	 *
	 * @param string $btc_address
	 * @param bool   $confirmed
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function get_received_by_address( string $btc_address, bool $confirmed ): string {

		$minimum_confirmations = $confirmed ? 1 : 0;

		$url = "https://blockchain.info/q/getreceivedbyaddress/{$btc_address}?confirmations={$minimum_confirmations}";

		$request_response = wp_remote_get( $url );

		if ( is_wp_error( $request_response ) || 200 !== $request_response['response']['code'] ) {
			throw new \Exception();
		}

		return $request_response['body'];
	}


	/**
	 * @param string $btc_address
	 * @param int    $number_of_confirmations
	 *
	 * @return array{confirmed_balance:string, unconfirmed_balance:string, number_of_confirmations:int}
	 * @throws \Exception
	 */
	public function get_address_balance( string $btc_address, int $number_of_confirmations ): array {

		$result                            = array();
		$result['number_of_confirmations'] = $number_of_confirmations;

		$query = array(
			'unconfirmed_balance' => 0,
			'confirmed_balance'   => $number_of_confirmations,
		);

		foreach ( $query as $key => $number_of_confirmations ) {

			$url = "https://blockchain.info/q/addressbalance/{$btc_address}?confirmations={$number_of_confirmations}";

			$request_response = wp_remote_get( $url );

			// TODO: Does "Item not found" mean address-unused?
			if ( is_wp_error( $request_response ) || 200 !== $request_response['response']['code'] ) {
				// {"message":"Item not found or argument invalid","error":"not-found-or-invalid-arg"}
				// 429
				throw new \Exception();
			}

			$balance = $request_response['body'];

			if ( is_numeric( $balance ) && $balance > 0 ) {
				$balance = floatval( $balance ) / 100000000;
			}

			$result[ $key ] = "{$balance}";
		}

		return $result;
	}

	/**
	 * @param string $btc_address
	 *
	 * @return array<string, TransactionArray>
	 */
	public function get_transactions_received( string $btc_address ): array {

		$blockchain_height_url = 'https://blockchain.info/q/getblockcount';
		$request_response      = wp_remote_get( $blockchain_height_url );
		if ( is_wp_error( $request_response ) || 200 !== $request_response['response']['code'] ) {
			throw new \Exception();
		}

		$blockchain_height = (int) $request_response['body'];

		$url = "https://blockchain.info/rawaddr/$btc_address";

		$this->logger->debug( 'Querying: ' . $url );

		$request_response = wp_remote_get( $url );

		if ( is_wp_error( $request_response ) || 200 !== $request_response['response']['code'] ) {
			throw new \Exception();
		}

		$address_data = json_decode( $request_response['body'], true );

		$blockchain_transactions = isset( $address_data['txs'] ) ? $address_data['txs'] : array();

		/**
		 * @param array $blockchain_transaction
		 *
		 * @return array{txid:string, time:DateTimeInterface, value:string, confirmations:int}
		 */
		$blockchain_mapper = function( array $blockchain_transaction ) use ( $blockchain_height ): array {

			$txid = $blockchain_transaction['hash'];

			$value_including_fee = array_reduce(
				$blockchain_transaction['inputs'],
				function( $carry, $v_in ) {
					return $carry + $v_in['prev_out']['value'];
				},
				0
			);

			$confirmations = $blockchain_height - $blockchain_transaction['block_height'];

			$value = ( $value_including_fee - $blockchain_transaction['fee'] ) / 100000000;

			return array(
				'txid'          => $txid,
				'time'          => \DateTime::createFromFormat( 'U', $blockchain_transaction['time'], new \DateTimeZone( 'UTC' ) ),
				'value'         => "{$value}",
				'confirmations' => $confirmations,
			);
		};

		$transactions_received = array_filter(
			$blockchain_transactions,
			function( array $transaction ): bool {
				return $transaction['result'] > 0;
			}
		);

		$transactions = array_map( $blockchain_mapper, $transactions_received );

		$keyed_transactions = array();
		foreach ( $transactions as $transaction ) {
			$keyed_transactions[ $transaction['txid'] ] = $transaction;
		}

		return $keyed_transactions;
	}

	public function get_transaction( string $tx_hash ): array {

		$url = "https://blockchain.info/rawtx/$tx_hash";

		$request_response = wp_remote_get( $url );

		if ( is_wp_error( $request_response ) || 200 !== $request_response['response']['code'] ) {
			throw new \Exception();
		}

		$transaction_data = json_decode( $request_response['body'], true );

		return $transaction_data;

	}

}
