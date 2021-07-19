<?php


namespace andrewsauder\ldapwrap;


class core {

	/** @var \andrewsauder\ldapwrap\models\config */
	private $config;

	private $connection = null;


	/**
	 * core constructor.
	 *
	 * @param $config array with config options (see config model for available settings)
	 */
	public function __construct( $config ) {

		$this->config = new \andrewsauder\ldapwrap\models\config();

		$this->config->server = $config['server'];
		$this->config->user = $config['user'];
		$this->config->password = $config['password'];

		if( isset( $config[ 'port' ] ) ) {
			$this->config->port = (int) $config[ 'port' ];
		}

		if( isset( $config[ 'protocol' ] ) ) {
			$this->config->protocol = $config[ 'protocol' ];
		}

		if( isset( $config[ 'baseDn' ] ) ) {
			$this->config->baseDn = $config[ 'baseDn' ];
		}
		elseif( isset( $config[ 'base_dn' ] ) ) {
			$this->config->baseDn = $config[ 'base_dn' ];
		}


	}


	/**
	 * @param  string    $q   LDAP Query
	 * @param  string[]  $f   Fields to return
	 * @param  string    $dn  Distinguished name to run query inside of
	 *
	 * @return array
	 */
	public function search( $q = '', $f = [], $dn = '' ) {

		$connected = $this->connect();

		if( !$connected ) {
			error_log( 'LdapWrap: Connection error' );

			return [];
		}

		if( !is_array( $f ) ) {
			$f = json_decode( $f, true );
		}

		$result = ldap_search( $this->connection, $this->getfullDn($dn), $q, $f );

		if( $result === false ) {
			error_log( "LdapWrap: Error in search query: " . ldap_error( $this->connection ) );
		}
		$data = ldap_get_entries( $this->connection, $result );

		$fin = [];

		if( $data[ 'count' ] > 0 ) {
			foreach( $data as $index => $item ) {

				if( !is_numeric( $index ) ) {
					continue;
				}

				$finIndex         = count( $fin );
				$fin[ $finIndex ] = [];

				foreach( $item as $key => $value ) {
					if( is_numeric( $key ) || $key == 'count' ) {
						continue;
					}

					if( !isset( $value[ 'count' ] ) ) {
						$fin[ $finIndex ][ $key ] = $value;
					}
					elseif( $value[ 'count' ] == 0 ) {
						$fin[ $finIndex ][ $key ] = null;
					}
					elseif( $value[ 'count' ] == 1 ) {
						$fin[ $finIndex ][ $key ] = $value[ 0 ];
					}
					else {
						$fin[ $finIndex ][ $key ] = [];
						foreach( $value as $vi => $vv ) {
							if( !is_numeric( $vi ) ) {
								continue;
							}
							$fin[ $finIndex ][ $key ][] = $vv;
						}
					}

				}

			}
		}

		return $fin;

	}



	/**
	 * @param  string  $q   LDAP Query
	 * @param  string  $f   Field to return
	 * @param  string  $dn  Distinguished name to run query inside of
	 *
	 * @return array|bool
	 */
	public function singleSearch( $q = '', $f = '', $dn = '' ) {

		$this->connect();

		$sr = ldap_list( $this->connection, $this->getfullDn($dn), $q, [ $f ] ) or error_log( 'LdapWrap: '.$this->getfullDn($dn) );

		if( $sr === false ) {
			return false;
		}

		$info = ldap_get_entries( $this->connection, $sr );

		$fin = [];

		for( $i = 0; $i < $info[ "count" ]; $i++ ) {
			$fin[] = $info[ $i ][ $f ][ 0 ];
		}

		return $fin;
	}


	/**
	 * @param  string    $q   LDAP Query
	 * @param  string[]  $f   Field to return
	 * @param  string    $dn  Distinguished name to run query inside of
	 *
	 * @return array|bool
	 */
	public function flatSearch( $q = '', $f = [], $dn = '' ) {

		$this->connect();

		$sr = ldap_list( $this->connection, $this->getfullDn($dn), $q, $f ) or error_log( 'LdapWrap: '.$this->getfullDn($dn) );

		if( $sr === false ) {
			return [];
		}

		$info = ldap_get_entries( $this->connection, $sr );

		$fin = [];

		for( $i = 0; $i < $info[ "count" ]; $i++ ) {
			$ffin = [];
			foreach( $f as $k ) {
				if(!isset($info[ $i ][ $k ])) {
					$ffin[ $k ] = null;
				}
				elseif( !isset( $info[ $i ][ $k ][ 'count' ] ) ) {
					$ffin[ $k ] = $info[ $i ][ $k ];
				}
				elseif( $info[ $i ][ $k ][ 'count' ] == 0 ) {
					$ffin[ $k ] = null;
				}
				elseif( $info[ $i ][ $k ][ 'count' ] == 1 ) {
					$ffin[ $k ] = $info[ $i ][ $k ][ 0 ];
				}
				else {
					$ffin[ $k ] = [];
					foreach( $info[ $i ][ $k ] as $vi => $vv ) {
						if( !is_numeric( $vi ) ) {
							continue;
						}
						$ffin[ $k ][] = $vv;
					}
				}
			}
			$fin[] = $ffin;
		}

		return $fin;
	}


	public function modify( $dn, $new ) {

		$this->connect();

		$modified = ldap_modify( $this->connection, $this->getfullDn($dn), $new );

		return $modified;

	}


	public function replace( $dn, $new ) {

		$this->connect();

		$modified = ldap_mod_replace( $this->connection, $this->getfullDn($dn), $new );

		return $modified;

	}


	public function delete( $dn, $new ) {

		$this->connect();
		
		foreach($new as $key=>$value) {
			$modified = @ldap_mod_del( $this->connection, $this->getfullDn($dn), [ $key=>$value ] );
		}
		
		return $modified;

	}


	public function add( $dn, $record ) {

		$add = ldap_add( $this->connection, $this->getfullDn($dn), $record );

		return $add;

	}


	public function changePassword( $dn, $newPassword ) {

		$this->connect();

		$encoded_newPassword = $this->encodePassword( $newPassword );

		$ldapData = [
			'unicodePwd' => $encoded_newPassword
		];

		$modified = ldap_mod_replace( $this->connection, $this->getfullDn($dn), $ldapData );

		return $modified;

	}


	private function encodePassword( $newPassword ) {

		$newPassword = "\"" . $newPassword . "\"";
		$len         = strlen( $newPassword );
		$newPassw    = '';
		for( $i = 0; $i < $len; $i++ ) {
			$newPassw .= "{$newPassword[$i]}\000";
		}
		$newPassword = $newPassw;

		return $newPassword;

		return $newPassw;
	}

	private function getfullDn( $dn ) {

		$fullDnPieces = [];

		if($dn!='') {
			$fullDnPieces[] = $dn;
		}

		if (strpos($dn, $this->config->baseDn) === false) {
			$fullDnPieces[] = $this->config->baseDn;
		}

		return implode(',', $fullDnPieces);

	}

	private function connect() {

		if( $this->connection !== null ) {
			return true;
		}

		$connectionString = $this->config->protocol . '://' . $this->config->server . ':' . $this->config->port;
		$this->connection = ldap_connect( $connectionString );

		if( $this->connection === false ) {
			error_log( 'LdapWrap: failed to connect using connection string: ' . $this->config->server );

			throw new \Exception('LdapWrap: Connection error');
			return false;
		}

		ldap_set_option( $this->connection, LDAP_OPT_PROTOCOL_VERSION, 3 );
		ldap_set_option( $this->connection, LDAP_OPT_REFERRALS, 0 );

		//define(LDAP_OPT_DIAGNOSTIC_MESSAGE, 0x0032);
		$successful = ldap_bind( $this->connection, $this->config->user, $this->config->password );

		if( !$successful ) {
			$errorNo  = ldap_errno( $this->connection );
			$errorMsg = ldap_error( $this->connection );
			error_log( 'LdapWrap: failed to bind. Error ' . $errorNo . ': "' . $errorMsg . '". Using username: ' . $this->config->user . ' on connection string: ' . $connectionString );

			if( ldap_get_option( $this->connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $extended_error ) ) {
				error_log( "LdapWrap: fError Binding to LDAP: $extended_error" );
			}
			else {
				error_log( "LdapWrap: Error Binding to LDAP: No additional information is available." );
			}

			throw new \Exception('LdapWrap: Failed to bind');

			$this->connection = null;

			return false;
		}

		return true;

	}

}