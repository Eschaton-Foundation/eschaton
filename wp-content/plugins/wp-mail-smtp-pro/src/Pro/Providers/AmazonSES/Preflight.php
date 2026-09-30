<?php

namespace WPMailSMTP\Pro\Providers\AmazonSES;

use Exception;
use WPMailSMTP\Providers\Preflight\Code;
use WPMailSMTP\Providers\Preflight\Findings;
use WPMailSMTP\Providers\Preflight\PreflightAbstract;
use WPMailSMTP\Vendor\Aws\Exception\AwsException;
use WPMailSMTP\Vendor\Aws\SesV2\SesV2Client;

/**
 * Issues one SES identity-list request to establish whether the credentials authenticate in the
 * configured region.
 *
 * @since 4.10.0
 */
class Preflight extends PreflightAbstract {

	/**
	 * Identities per page. One, because the check reads no identity and the list carries the account's
	 * own addresses and domains.
	 *
	 * @since 4.10.0
	 *
	 * @var int
	 */
	private const PAGE_SIZE = 1;

	/**
	 * The region is absent, unparseable, or not one the plugin offers.
	 *
	 * @since 4.10.0
	 *
	 * @var string
	 */
	private const REGION_INVALID = 'amazonses_region_invalid';

	/**
	 * The configured region belongs to an AWS partition the credentials do not.
	 *
	 * @since 4.10.0
	 *
	 * @var string
	 */
	private const REGION_PARTITION_MISMATCH = 'amazonses_region_partition_mismatch';

	/**
	 * Region prefix of the GovCloud partition.
	 *
	 * @since 4.10.0
	 *
	 * @var string
	 */
	private const GOVCLOUD_PREFIX = 'us-gov-';

	/**
	 * The AWS error code raised when the Access Key ID was not recognised.
	 *
	 * @since 4.10.0
	 *
	 * @var string
	 */
	private const CODE_KEY_REJECTED = 'UnrecognizedClientException';

	/**
	 * The AWS error code raised when the signature did not verify, which is the secret.
	 *
	 * @since 4.10.0
	 *
	 * @var string
	 */
	private const CODE_SIGNATURE_REJECTED = 'InvalidSignatureException';

	/**
	 * AWS error codes that mean throttling. SESv2 was only observed signalling it at 429, but AWS
	 * services also raise `ThrottlingException` at 400, where the status alone reads as a
	 * credential refusal.
	 *
	 * @since 4.10.0
	 *
	 * @var string[]
	 */
	private const CODES_THROTTLED = [ 'TooManyRequestsException', 'ThrottlingException' ];

	/**
	 * Read the fields this check sends.
	 *
	 * The region is checked against the known set in `run()` rather than for presence here.
	 *
	 * @since 4.10.0
	 *
	 * @param array $config Mailer options as submitted.
	 *
	 * @return array|null
	 */
	protected function sanitize_config( $config ) {

		$sanitized_config = [
			'client_id'     => sanitize_text_field( (string) ( $config['client_id'] ?? '' ) ),
			'client_secret' => sanitize_text_field( (string) ( $config['client_secret'] ?? '' ) ),
			'region'        => sanitize_text_field( (string) ( $config['region'] ?? '' ) ),
		];

		if ( $sanitized_config['client_id'] === '' || $sanitized_config['client_secret'] === '' ) {
			return null;
		}

		return $sanitized_config;
	}

	/**
	 * Run the check.
	 *
	 * The identity list is issued only so that a refusal can be attributed; its payload is not read.
	 *
	 * @since 4.10.0
	 *
	 * @param array $config Mailer options.
	 *
	 * @return Findings
	 */
	public function run( $config ) {

		$sanitized_config = $this->sanitize_config( $config );

		if ( $sanitized_config === null ) {
			return $this->incomplete_config();
		}

		$findings = Findings::none();

		$config = $sanitized_config;
		$region = $this->region( $config );

		if ( ! array_key_exists( $region, Auth::get_regions_names() ) ) {
			$findings->add( $this->error( self::REGION_INVALID, 'region' ) );

			return $findings;
		}

		try {
			$this->identities( $this->client( $config, $region ) );
		} catch ( Exception $e ) {
			return $this->failure_findings( $e, $region );
		}

		return $findings;
	}

	/**
	 * Normalise the configured region.
	 *
	 * Lower-casing is load-bearing: DNS resolves a mis-cased host while the SigV4 credential
	 * scope carries the region verbatim, so SES answers with the wrong-secret error.
	 *
	 * @since 4.10.0
	 *
	 * @param array $config Mailer options.
	 *
	 * @return string
	 */
	private function region( $config ) {

		return strtolower( Auth::prepare_region( $config['region'] ) );
	}

	/**
	 * Build the SES client.
	 *
	 * No credential is passed as a scalar argument anywhere on this path: an uncaught throw
	 * renders string frame arguments into the log, and array arguments as `Array`.
	 *
	 * @since 4.10.0
	 *
	 * @param array  $config Mailer options.
	 * @param string $region Normalised region.
	 *
	 * @return SesV2Client
	 */
	private function client( $config, $region ) {

		return Auth::make_client(
			[
				'key'    => $config['client_id'],
				'secret' => $config['client_secret'],
			],
			$region,
			'v2',
			[
				'retries' => 0,
				'http'    => [
					'connect_timeout' => self::TIMEOUT,
					'timeout'         => self::TIMEOUT,
				],
			]
		);
	}

	/**
	 * Issue the identity-list request, whose payload is discarded.
	 *
	 * @since 4.10.0
	 *
	 * @param SesV2Client $client SES client.
	 */
	private function identities( $client ) {

		$client->listEmailIdentities( [ 'PageSize' => self::PAGE_SIZE ] );
	}

	/**
	 * Attribute a thrown SES failure.
	 *
	 * The AWS code is the only trustworthy discriminator: the message is prose, and for a bad Access
	 * Key ID it claims a token the user never supplied. A denied action names nothing at all, since
	 * SigV4 verified before IAM refused, which leaves both values the right ones.
	 *
	 * @since 4.10.0
	 *
	 * @param Exception $error  Thrown failure.
	 * @param string    $region Normalised region.
	 *
	 * @return Findings
	 */
	private function failure_findings( $error, $region ) {

		$findings = Findings::none();

		if ( ! $error instanceof AwsException ) {
			return $findings;
		}

		$transport = $this->transport_finding( $error );

		if ( $transport !== null ) {
			$findings->add( $transport );

			return $findings;
		}

		$code = (string) $error->getAwsErrorCode();

		if ( $code === self::CODE_KEY_REJECTED ) {
			$findings->add( $this->key_rejection_finding( $region ) );
		} elseif ( $code === self::CODE_SIGNATURE_REJECTED ) {
			$findings->add( $this->error( Code::AUTH_FAILED, 'client_secret' ) );
		}

		return $findings;
	}

	/**
	 * Attribute a refused Access Key ID.
	 *
	 * A key valid in the commercial partition is refused by a GovCloud endpoint with the response an
	 * unknown key gets, so on a GovCloud region the key cannot be named.
	 *
	 * @since 4.10.0
	 *
	 * @param string $region Normalised region.
	 *
	 * @return Finding
	 */
	private function key_rejection_finding( $region ) {

		if ( strpos( $region, self::GOVCLOUD_PREFIX ) === 0 ) {
			return $this->error( self::REGION_PARTITION_MISMATCH, 'region' );
		}

		return $this->error( Code::AUTH_FAILED, 'client_id' );
	}

	/**
	 * Classify a throw that carries no verdict about the configuration.
	 *
	 * The SDK surfaces these as exceptions rather than status codes, so a connection failure is
	 * only distinguishable through `isConnectionError()`: it carries neither a status nor an
	 * AWS code.
	 *
	 * @since 4.10.0
	 *
	 * @param AwsException $error Thrown failure.
	 *
	 * @return Finding|null Null when the throw says something about the configuration.
	 */
	private function transport_finding( $error ) {

		if ( $error->isConnectionError() ) {
			return $this->inconclusive( Code::NETWORK_ERROR );
		}

		$status = (int) $error->getStatusCode();

		if ( $status === 429 || in_array( (string) $error->getAwsErrorCode(), self::CODES_THROTTLED, true ) ) {
			return $this->inconclusive( Code::RATE_LIMITED );
		}

		return $status >= 500 ? $this->inconclusive( Code::PROVIDER_UNAVAILABLE ) : null;
	}
}
