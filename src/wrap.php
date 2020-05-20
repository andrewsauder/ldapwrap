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
	 * @return \andrewsauder\ldapwrap\models\ou[]
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


	/**
	 * @param $dn
	 *
	 * @return \andrewsauder\ldapwrap\models\user[]
	 */
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
			$user->displayname        = isset( $rawUser[ 'displayname' ] ) ? $rawUser[ 'displayname' ] : '';
			$user->givenname          = isset( $rawUser[ 'givenname' ] ) ? $rawUser[ 'givenname' ] : '';
			$user->sn                 = isset( $rawUser[ 'sn' ] ) ? $rawUser[ 'sn' ] : '';
			$user->mail               = isset( $rawUser[ 'mail' ] ) ? $rawUser[ 'mail' ] : '';
			$user->samaccountname     = isset( $rawUser[ 'samaccountname' ] ) ? $rawUser[ 'samaccountname' ] : '';
			$user->userprincipalname  = isset( $rawUser[ 'userprincipalname' ] ) ? $rawUser[ 'userprincipalname' ] : '';
			$user->telephonenumber    = isset( $rawUser[ 'telephonenumber' ] ) ? $rawUser[ 'telephonenumber' ] : '';
			$user->useraccountcontrol = isset( $rawUser[ 'useraccountcontrol' ] ) ? $rawUser[ 'useraccountcontrol' ] : '';
			$user->department         = isset( $rawUser[ 'department' ] ) ? $rawUser[ 'department' ] : '';
			$user->employeenumber     = isset( $rawUser[ 'employeenumber' ] ) ? $rawUser[ 'employeenumber' ] : '';
			$user->pwdlastset         = isset( $rawUser[ 'pwdlastset' ] ) ? $rawUser[ 'pwdlastset' ] : '';
			$user->dn                 = isset( $rawUser[ 'dn' ] ) ? $rawUser[ 'dn' ] : '';
			$user->active             = ( $user->useraccountcontrol & 2 ) == 2 ? false : true;
			$user->changepassword     = false;

			$users[] = $user;
		}

		return $users;

	}


	public function updateUser( $userArray ) {

		$modify = null;
		$delete = null;

		$params = [
			'modify' => [],
			'delete' => [],
		];

		$params = $this->modVDel( $userArray, 'telephonenumber', $params );
		$params = $this->modVDel( $userArray, 'mail', $params );
		$params = $this->modVDel( $userArray, 'givenname', $params );
		$params = $this->modVDel( $userArray, 'sn', $params );
		$params = $this->modVDel( $userArray, 'department', $params );
		$params = $this->modVDel( $userArray, 'employeenumber', $params );

		//user account control parameters (http://www.selfadsi.org/ads-attributes/user-userAccountControl.htm)
		if( $userArray[ 'active' ] == 1 ) {
			$enable                                     = 512; // UF_NORMAL_ACCOUNT
			$params[ 'modify' ][ 'useraccountcontrol' ] = [
				$enable
			];
		}
		else {
			$disable                                    = 514; // UF_NORMAL_ACCOUNT + UF_ACCOUNT_DISABLE
			$params[ 'modify' ][ 'useraccountcontrol' ] = [
				$disable
			];
		}

		if( count( $params[ 'modify' ] ) > 0 ) {
			$modify = $this->ldap->modify( $userArray[ 'dn' ], $params[ 'modify' ] );
		}
		if( count( $params[ 'delete' ] ) > 0 ) {
			$delete = $this->ldap->delete( $userArray[ 'dn' ], $params[ 'delete' ] );
		}

		if( $userArray[ 'changepassword' ] ) {
			$passwordStatus = $this->ldap->changePassword( $userArray[ 'dn' ], $userArray[ 'newpassword' ] );
		}

		return $userArray;
	}


	private function modVDel( $post, $key, $params ) {

		if( isset( $post[ $key ] ) && trim( $post[ $key ] ) != '' ) {
			$params[ 'modify' ][ $key ] = $post[ $key ];
		}
		else {
			$params[ 'delete' ][ $key ] = [];
		}

		return $params;
	}

}