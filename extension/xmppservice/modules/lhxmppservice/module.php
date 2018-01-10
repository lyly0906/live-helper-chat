<?php

$Module = array( "name" => "LHC XMPP module",
				 'variable_params' => true );

$ViewList = array();

/**
 * Callback handlers
 * */
$ViewList['operatorstatus'] = array(
    'params' => array(),
    'uparams' => array()
);

$ViewList['processmessage'] = array(
    'params' => array(),
    'uparams' => array()
);

/**
 * General user cases
 * */
$ViewList['operators'] = array(
    'params' => array(),
    'uparams' => array('username','type','timefrom','timeto'),
    'functions' => array('use_admin'),
);

$ViewList['options'] = array(
    'params' => array(),
    'uparams' => array(),
    'functions' => array('use_admin'),
);

$ViewList['adduser'] = array(
	'params' => array(),
	'uparams' => array()
);

$ViewList['edituser'] = array(
	'params' => array(),
	'uparams' => array()
);

$ViewList['deleteuser'] = array(
	'params' => array(),
	'uparams' => array()
);

$ViewList['editoperator'] = array(
    'params' => array('id'),
    'uparams' => array(),
    'functions' => array('use_admin'),
);

$ViewList['addgroup'] = array(
	'params' => array(),
	'uparams' => array()
);

$ViewList['editgroup'] = array(
	'params' => array(),
	'uparams' => array()
);

$ViewList['addothergroup'] = array(
	'params' => array(),
	'uparams' => array()
);

$ViewList['setusercard'] = array(
	'params' => array(),
	'uparams' => array()
);

$ViewList['addroster'] = array(
	'params' => array(),
	'uparams' => array()
);

$ViewList['iqsend'] = array(
	'params' => array(),
	'uparams' => array()
);

$ViewList['offline_post'] = array(
	'params' => array(),
	'uparams' => array()
);

$ViewList['sendofflinemessage'] = array(
	'params' => array(),
	'uparams' => array()
);

$ViewList['roomoperate'] = array(
	'params' => array(),
	'uparams' => array()
);

$ViewList['sendmuctouser'] = array(
	'params' => array(),
	'uparams' => array()
);

$ViewList['setroomaffiliation'] = array(
	'params' => array(),
	'uparams' => array()
);

$ViewList['getrooms'] = array(
	'params' => array(),
	'uparams' => array()
);

$ViewList['setusertogroup'] = array(
	'params' => array(),
	'uparams' => array()
);

$ViewList['deleteoperator'] = array(
    'params' => array('id'),
    'uparams' => array('csfr'),
    'functions' => array('use_admin'),
);

$ViewList['index'] = array(
    'params' => array(),
    'uparams' => array(),
    'functions' => array('use_admin'),
);

$ViewList['newxmppaccount'] = array(
    'params' => array(),
    'uparams' => array(),
    'functions' => array('use_admin'),
);

$ViewList['test'] = array(
    'params' => array(),
    'uparams' => array(),
    'functions' => array('use_admin'),
);

$FunctionList['use_admin'] = array('explain' => 'Allow operator to use XMPP module');