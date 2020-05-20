<?php
namespace andrewsauder\ldapwrap\models;

class user {

	/** @var string surname */
	public $sn = '';

	/** @var string given name */
	public $givenName = '';

	/** @var string display name */
	public $displayName = '';

	/** @var string display name */
	public $userAccountControl = '';

	/** @var string Timestamp of last password change */
	public $pwdLastSet = '';

	/** @var string account name */
	public $samAccountName = '';

	/** @var string userPrincipalName */
	public $userPrincipalName = '';

	/** @var string distinguished name */
	public $dn = '';

	/** @var string distinguished name */
	public $mail = '';

	/** @var string distinguished name */
	public $telephoneNumber = '';

	/** @var string distinguished name */
	public $department = '';

	/** @var string distinguished name */
	public $employeeNumber = '';

	/** @var bool */
	public $active = false;

	/** @var bool */
	public $changePassword = false;

}