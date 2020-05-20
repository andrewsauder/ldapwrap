<?php


namespace andrewsauder\ldapwrap;


class wrap {

	private $ldap;


	public function __construct( $config ) {

		$this->ldap = new core( $config );

	}


	/**
	 * @param  string  $distinguishedName
	 * @param  bool    $recursiveOu
	 * @param  bool    $fetchUsers
	 *
	 * @return array
	 */
	public function getOUs( $distinguishedName = '', $recursiveOu = false, $fetchUsers = false ) {

		$q      = 'ou=*';
		$fields = [
			'ou',
			'dn'
		];

		//get OUs from AD
		$rawOus = $this->ldap->flatSearch( $q, $fields, $distinguishedName );

		//if no results, return blank
		if( count( $rawOus ) == 0 ) {
			return [];
		}

		//sort by name
		usort( $rawOus, function( $a, $b ) {
			return strcmp( $a[ "ou" ], $b[ "ou" ] );
		} );

		//final objects
		$ous = [];

		//get additional data for each
		foreach( $rawOus as $i => $rawOu ) {
			$ou                    = new \ldap\models\ou();
			$ou->name              = $rawOu[ 'ou' ];
			$ou->distinguishedName = $rawOu[ 'dn' ];

			if( $recursiveOu ) {
				$searchChildrenDistinguishedName = 'ou=' . $rawOu[ 'ou' ] . ',' . $distinguishedName;
				$subOus                          = $this->getOUs( $searchChildrenDistinguishedName );
				if( count( $subOus ) > 0 ) {
					$ou->children[] = $subOus;
				}
				$ou->childrenFetched = true;
			}

			//$users = $this->getOUUsers( $subDistinguishedName );

			//$fu = [];
			//foreach( $users as $user ) {
			//	$fu[] = $this->getUser( $user );
			//}


			//if( $users !== false && count( $users ) > 0 ) {
			//	$ous[ $i ][ 'users' ] = $fu;
			//}

			$ous[] = $ou;

		}


		return $ous;

	}

}