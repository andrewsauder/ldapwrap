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


	/**
	 * @param $userArray an associative array that matches the structure of \andrewsauder\ldapwrap\models\user. It can also include "newpassword" when changepassword=true
	 *
	 * @return mixed
	 */
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



	/**
	 * @param $userArray an associative array that matches the structure of \andrewsauder\ldapwrap\models\user. It can also include "newpassword" when changepassword=true
	 *
	 * @return mixed
	 */
	public function createUser($userArray) {

		$ldaprecord = [];

		//if middle name is posted, we use it
		$middleInitial = '';
		$cn            = $userArray[ 'givenname' ] . ' ' . $userArray[ 'sn' ];
		if( isset( $userArray[ 'mn' ] ) && trim( $userArray[ 'mn' ] ) != '' ) {
			$middleInitial = strtoupper( substr($userArray[ 'mn' ],0,1) );
			$cn            = $userArray[ 'givenname' ] . ' ' . strtoupper( $middleInitial ) . '. ' . $userArray[ 'sn' ];
		}

		//generate account name
		$sAMAccount = strtolower( substr( $userArray[ 'givenname' ], 0, 1 ) . $userArray[ 'sn' ] );

		//check if another user already exists with this account name
		if( $this->userExists( $sAMAccount . "@" . $userArray[ 'domain' ] ) ) {

			if( $middleInitial != '' ) {
				$sAMAccount = strtolower( substr( $userArray[ 'givenname' ], 0, 1 ) . $middleInitial . $userArray[ 'sn' ] );

				if( $this->userExists( $sAMAccount . "@" . $userArray[ 'domain' ] )  ) {
					return [
						'error'   => true,
						'message' => 'A user with this account name already exists. Have IT manually create account.'
					];
				}
			}
			else {
				return [
					'error'   => true,
					'message' => 'A user with this account name already exists. Please enter middle name.'
				];
			}
		}

		$ldaprecord[ 'cn' ]                 = $cn;
		$ldaprecord[ 'givenname' ]          = $userArray[ 'givenname' ];
		$ldaprecord[ 'sn' ]                 = $userArray[ 'sn' ];

		$ldaprecord[ 'objectclass' ][ 0 ]   = 'top';
		$ldaprecord[ 'objectclass' ][ 1 ]   = 'person';
		$ldaprecord[ 'objectclass' ][ 2 ]   = 'organizationalPerson';
		$ldaprecord[ 'objectclass' ][ 3 ]   = 'user';

		$ldaprecord[ 'samaccountname' ]     = $sAMAccount;
		$ldaprecord[ 'userprincipalname' ]  = $sAMAccount . "@" . $userArray[ 'domain' ];
		$ldaprecord[ 'displayname' ]        = $cn;
		$ldaprecord[ 'useraccountcontrol' ] = "512";
		$ldaprecord[ 'pwdlastset' ] = -1;

		if(isset($userArray[ 'telephonenumber' ]) && !empty($userArray[ 'telephonenumber' ])) {
			$ldaprecord[ 'telephonenumber' ]    = $userArray[ 'telephonenumber' ];
		}
		if(isset($userArray[ 'department' ]) && !empty($userArray[ 'department' ])) {
			$ldaprecord[ 'department' ]    = $userArray[ 'department' ];
		}
		if(isset($userArray[ 'employeenumber' ]) && !empty($userArray[ 'employeenumber' ])) {
			$ldaprecord[ 'employeenumber' ]    = $userArray[ 'employeenumber' ];
		}

		if( $userArray[ 'setup_email' ] == 1 ) {
			//$ldaprecord[ 'mail' ] = $sAMAccount . "@" . $userArray[ 'domain' ];
			mail('asauder@garrettcounty.org', 'New User needs email', 'New user created that needs email - '.$sAMAccount . "@" . $userArray[ 'domain' ]);
		}

		$dn = 'CN=' . $ldaprecord[ 'cn' ] . ',' . $userArray[ 'ou' ];

		$status = $this->ldap->add( $dn, $ldaprecord );


		if( $status ) {
			$passwordStatus = $this->ldap->changePassword( $dn, $userArray[ 'newpassword' ] );
			$ldaprecord['changepassword'] = true;
			$ldaprecord['newpassword'] = $userArray[ 'newpassword' ];
			return [
				'error'   => false,
				'message' => 'Success',
				'data'   => $ldaprecord
			];
		}
		else {
			return [
				'error'   => true,
				'message' => 'Failed to create user',
				'data'   => $ldaprecord
			];
		}
	}

	public function userExists( $userprincipalname ) {

		$q      = '(&(objectClass=User)(userPrincipalName=' . $userprincipalname . '))';
		$fields = [
			"userPrincipalName",
			"sAMAccountName",
		];

		$users = $this->ldap->search( $q, $fields );

		if( isset( $users[ 0 ] ) ) {
			return true;
		}

		return false;

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