<?php
namespace andrewsauder\ldapwrap\models;

class config {

	/** @var string Required */
	public $server = '';

	/** @var string Required */
	public $user = '';

	/** @var string Required */
	public $password = '';

	/** @var string Optional. Default=3268 */
	public $port = 3268;

	/** @var string Optional. Default=ldap */
	public $protocol = 'ldap';

	/** @var string Optional. If provided, nothing searches can go above this level */
	public $baseDn = '';


}