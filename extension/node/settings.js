var settings = {}

/**
 * To what address listen
 * */
//settings.listen_address = '127.0.0.1';
settings.listen_address = '192.168.1.20';

/**
 * Secret key it has to match php extension secret key
 * */
settings.secret_key = '397126845';


/**
 * To what port to listen
 * */
settings.listen_port = 4567;

/**
 * Path to ejabberdctl
 * */
settings.ejabberdctl = '/usr/local/sbin/ejabberdctl'

//************************** CHAT SETTINGS

/**
 * How long online visitor should be considered as online. Miliseconds
 * 
 * It's 5 minutes at the moment 5 * 60 * 1000
 * */
settings.online_timeout = 300000;


settings.debug = {};

/**
 * Enable debug output
 * */
settings.debug.output = true;

module.exports = settings;