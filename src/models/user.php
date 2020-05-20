<?php
namespace andrewsauder\ldapwrap\models;

class user {

	/** @var string surname */
	public $sn = '';

	/** @var string given name */
	public $givenname = '';

	/** @var string display name */
	public $displayname = '';

	/** @var string display name */
	public $useraccountcontrol = '';

	/** @var string Timestamp of last password change */
	public $pwdlastset = '';

	/** @var string account name */
	public $samaccountname = '';

	/** @var string userPrincipalName */
	public $userprincipalname = '';

	/** @var string distinguished name */
	public $dn = '';

	/** @var string distinguished name */
	public $mail = '';

	/** @var string distinguished name */
	public $telephonenumber = '';

	/** @var string distinguished name */
	public $department = '';

	/** @var string distinguished name */
	public $employeenumber = '';

	/** @var bool */
	public $active = false;

	/** @var bool */
	public $changepassword = false;

}