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
			$ou       = new \andrewsauder\ldapwrap\models\ou();
			$ou->name = $rawOu[ 'ou' ];
			$ou->dn   = $rawOu[ 'dn' ];

			if( $recursiveOu ) {
				$searchChildrenDistinguishedName = 'ou=' . $rawOu[ 'ou' ] . ',' . $distinguishedName;
				$subOus                          = $this->getOUs( $searchChildrenDistinguishedName );
				if( count( $subOus ) > 0 ) {
					$ou->children[] = $subOus;
				}
				$ou->childrenFetched = true;
			}


			if( $fetchUsers ) {
				$ou->users = $this->getUsers( $ou->dn );
				$ou->usersFetched = true;
			}

			$ous[] = $ou;

		}


		return $ous;

	}


	public function getUsers( $dn ) {

		$q = '(objectClass=User)';

		$fields = [
			"displayname",
			"givenname",
			"sn",
			"mail",
			"userPrincipalName",
			"sAMAccountName",
			"telephoneNumber",
			"useraccountcontrol",
			"department",
			"employeeNumber",
			"pwdlastset",
			"dn"
		];

		$rawUsers = $this->ldap->search( $q, $fields, $dn );

		$users = [];

		foreach( $rawUsers as $rawUser ) {
			$user                     = new \andrewsauder\ldapwrap\models\user();
			$user->displayName        = $rawUser[ 'displayname' ];
			$user->givenName          = $rawUser[ 'givenname' ];
			$user->sn                 = $rawUser[ 'sn' ];
			$user->mail               = $rawUser[ 'mail' ];
			$user->userPrincipalName  = $rawUser[ 'userprincipalname' ];
			$user->telephoneNumber    = $rawUser[ 'telephonenumber' ];
			$user->userAccountControl = $rawUser[ 'useraccountcontrol' ];
			$user->department         = $rawUser[ 'department' ];
			$user->employeeNumber     = $rawUser[ 'employeeNumber' ];
			$user->pwdLastSet         = $rawUser[ 'pwdlastset' ];
			$user->active             = ( $user->userAccountControl & 2 ) == 2 ? false : true;
			$user->changePassword     = false;

			$users[] = $user;
		}

		return $users;

	}

}