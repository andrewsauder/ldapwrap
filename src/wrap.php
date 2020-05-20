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
				$subOus                          = $this->getOUs( $searchChildrenDistinguishedName, $recursiveOu, $fetchUsers );
				if( count( $subOus ) > 0 ) {
					$ou->children = $subOus;
				}
				$ou->childrenFetched = true;
			}


			if( $fetchUsers ) {
				$ou->users        = $this->getUsers( $ou->dn );
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
			"userprincipalname",
			"samaccountname",
			"telephonenumber",
			"useraccountcontrol",
			"department",
			"employeenumber",
			"pwdlastset",
			"dn"
		];

		$rawUsers = $this->ldap->flatSearch( $q, $fields, $dn );

		$users = [];

		foreach( $rawUsers as $rawUser ) {
			$user                     = new \andrewsauder\ldapwrap\models\user();
			$user->displayname        = isset($rawUser[ 'displayname' ]) ? $rawUser[ 'displayname' ] : '';
			$user->givenname          = isset($rawUser[ 'givenname' ]) ? $rawUser[ 'givenname' ] : '';
			$user->sn                 = isset($rawUser[ 'sn' ]) ? $rawUser[ 'sn' ] : '';
			$user->mail               = isset($rawUser[ 'mail' ]) ? $rawUser[ 'mail' ] : '';
			$user->samaccountname  = isset($rawUser[ 'samaccountname' ]) ? $rawUser[ 'samaccountname' ] : '';
			$user->userprincipalname  = isset($rawUser[ 'userprincipalname' ]) ? $rawUser[ 'userprincipalname' ] : '';
			$user->telephonenumber  = isset($rawUser[ 'telephonenumber' ]) ? $rawUser[ 'telephonenumber' ] : '';
			$user->useraccountcontrol = isset($rawUser[ 'useraccountcontrol' ]) ? $rawUser[ 'useraccountcontrol' ] : '';
			$user->department         = isset($rawUser[ 'department' ]) ? $rawUser[ 'department' ] : '';
			$user->employeenumber     = isset($rawUser[ 'employeenumber' ]) ? $rawUser[ 'employeenumber' ] : '';
			$user->pwdlastset         = isset($rawUser[ 'pwdlastset' ]) ? $rawUser[ 'pwdlastset' ] : '';
			$user->dn                 = isset($rawUser[ 'dn' ]) ? $rawUser[ 'dn' ] : '';
			$user->active             = ( $user->userAccountControl & 2 ) == 2 ? false : true;
			$user->changepassword     = false;

			$users[] = $user;
		}

		return $users;

	}

}