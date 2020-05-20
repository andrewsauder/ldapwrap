<?php
namespace andrewsauder\ldapwrap\models;

class ou {

	/** @var string Name of organizational unit */
	public $name = '';
	/** @var string Name of organizational unit */
	public $dn = '';

	/** @var \model\ou[] Array of sub organizational units */
	public $children = [];

	/** @var bool */
	public $childrenFetched = false;

	/** @var \model\user[] List of users in organization unit (not always populated) */
	public $users = [];

	/** @var bool */
	public $usersFetched = false;

}