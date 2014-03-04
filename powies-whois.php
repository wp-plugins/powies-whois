<?php
/*
Plugin Name: Powie's WHOIS
Plugin URI: http://www.powie.de/wordpress
Description: Domain WHOIS Shortcode Plugin
Version: 0.9.14
License: GPLv2
Author: Thomas Ehrhardt
Author URI: http://www.powie.de
*/

//Define some stuff
define( 'PWHOIS_VERSION', '0.9.14');
define( 'PWHOIS_PLUGIN_DIR', dirname( plugin_basename( __FILE__ ) ) );
//define( 'PL_PAGEPEEKER_URL', 'http://free.pagepeeker.com/v2/thumbs.php?size=%s&url=%s');
load_plugin_textdomain( 'pwhois', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

//create custom plugin settings menu
add_action('admin_menu', 'pwhois_create_menu');
add_action('admin_init', 'pwhois_register_settings' );

//Shortcode
add_shortcode('pwhois', 'pwhois_show');

//Hook for Activation
register_activation_hook( __FILE__, 'pwhois_activate' );
//Hook for Deactivation
register_deactivation_hook( __FILE__, 'pwhois_deactivate' );

function pwhois_create_menu() {
	// or create options menu page
	add_options_page(__('Powies WHOIS Setup'),__('Powies WHOIS Setup'), 9, PWHOIS_PLUGIN_DIR.'/pwhois_settings.php');
}

function pwhois_register_settings() {
	//register settings
	register_setting( 'pwhois-settings', 'display-on-free' );
	register_setting( 'pwhois-settings', 'display-on-connect' );
	register_setting( 'pwhois-settings', 'display-on-invalid' );
	register_setting( 'pwhois-settings', 'show-www' );
	register_setting( 'pwhois-settings', 'show-whois-output' );
}

function pwhois_show( $atts ) {
	global $PWHOIS_SERVERS;
	$sc = '<!-- pWHOIS Plugin Output by www.powie.de -->';
	$sc.= '<div id="pwhois_form"><form method="post" id="whois" action="">
			<input type="hidden" name="action" value="pwhois_post" />';
	$sc.= wp_nonce_field( 'pwhoisnonce', 'post_nonce', true, false );
	$sc.= '<legend>'.__('Domain', 'pwhois').'</legend>';
	if (get_option('show-www') == 1) {
		$sc.= 'www.';
	}
    $sc.= '<input type="text" size="30" name="domain" id="domain" />
		   <select id="tld" name="tld">';
	//$sc.='<option '.$selected.' value=".de">.de</option>';
		foreach($PWHOIS_SERVERS as $ws => $v)	{
			$selected = ( $atts['default'] == $ws ) ? 'selected' : '';
			$sc.='<option '.$selected.' value=".'.$ws.'">.'.$ws.'</option>';
		}
	$sc.='</select>';
	$sc.=' <input type="submit" id="whoissubmit" name="whoissubmit" value="'.__("Check", 'pwhois').'" /></form>
		   </div>';
	$sc.= '<div id="pwhois_work" style="display:none;"><img src="'.includes_url().'images/spinner.gif" alt="wait" /> '.__("Waiting for domain lookup!", 'pwhois').'</div>';
	$sc.= '<div id="pwhois_result"></div>';
	$sc.='<!-- /pWHOIS Plugin Output -->';
	return $sc;
}

//Activate
function pwhois_activate() {
	// do not generate any output here
}

//Deactivate
function pwhois_deactivate() {
	// do not generate any output here
}

//Nonces!!! nont nonsens :)
add_action('init','pwhoisnonce_create');
function pwhoisnonce_create() {
	$pwhoisnonce = wp_create_nonce('pwhoisnonce');
}

// JS Frontend
// thanks to http://www.garyc40.com/2010/03/5-tips-for-using-ajax-in-wordpress/
// embed the javascript file that makes the AJAX request

wp_enqueue_script( 'pwhois-ajax-request', plugin_dir_url( __FILE__ ) . '/pwhois.js', array( 'jquery' ) );
// declare the URL to the file that handles the AJAX request (wp-admin/admin-ajax.php)
wp_localize_script( 'pwhois-ajax-request', 'pWhoisAjax',
	array(  'ajaxurl' => admin_url( 'admin-ajax.php' ), 'enter_domain' => __('Please enter Domain', 'pwhois') )
);


//Ajax Function Frontend
add_action('wp_ajax_pwhois_post', 'pwhois_post');
add_action('wp_ajax_nopriv_pwhois_post', 'pwhois_post' );

function pwhois_post(){
	global $PWHOIS_SERVERS;
	//global WP_CONTENT_DIR;
	//ccheck nonce
	if (! wp_verify_nonce($_POST['post_nonce'], 'pwhoisnonce') ) die('Security check');
		//Whois ausführen
		$whois=new psWhois;
		$result=$whois->lookup(trim($_POST['domain']).$_POST['tld'], $PWHOIS_SERVERS);
		//file_put_contents(WP_CONTENT_DIR."/logs/pwhois.log",$_POST['domain']."\n",FILE_APPEND );
		//testen
		if (stristr($result,'Status: free')) {
			$msg=get_option('display-on-free');
		} elseif (stristr($result,'available')) {
			$msg=get_option('display-on-free');
		} elseif (stristr($result,'no match')) {
			$msg=get_option('display-on-free');
		} elseif (stristr($result,'not found')) {
			$msg=get_option('display-on-free');
		} elseif (stristr($result,'nothing found')) {
			$msg=get_option('display-on-free');
		} elseif (stristr($result,'Status: invalid')) {
			$msg=get_option('display-on-invalid');
		} else {
			$msg=get_option('display-on-connect');
		}
		//Ergebnis liefern
		$msg = '<p>'.$msg.'</p>';
		if (get_option('show-whois-output') == 1) {
			$msg.='<code>'.nl2br($result).'</code>';
		}
		$response = json_encode( array( 'success' => true , 'msg' => $msg ) );
		header( "Content-Type: application/json" );
		echo $response;
		die();
}
class psWhois{
	public function lookup($domain,$whoisservers) {
		$domain = trim($domain); //remove space from start and end of domain
		if(substr(strtolower($domain), 0, 7) == "http://") $domain = substr($domain, 7); // remove http:// if included
		if(substr(strtolower($domain), 0, 4) == "www.") $domain = substr($domain, 4);//remove www from domain
		if(preg_match("/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/",$domain))
			return $this->queryWhois("whois.lacnic.net",$domain);
		elseif(preg_match("/^([-a-z0-9]{2,100})\.([a-z\.]{2,8})$/i",$domain))
		{
			$domain_parts = explode(".", $domain);
			$tld = strtolower(array_pop($domain_parts));
			$server = $whoisservers[$tld][0];
			if(!$server) {
				return "Error: No appropriate Whois server found for $domain domain!";
			}
			$res=$this->queryWhois($server,$domain);
			if ( preg_match("/Whois Server: (.*)/", $res, $matches) == 1 ) {
				//echo "Suche mit ".$matches[1];
				$res=$this->queryWhois($matches[1],$domain);
			}
			return $res;
		}
		else
			return "Invalid Input";
	}
	private function queryWhois($server,$domain) {
		$fp = @fsockopen($server, 43, $errno, $errstr, 5) or pWhoisTimeout();
		if($server=="whois.verisign-grs.com")
			$domain="=".$domain;
				fputs($fp, $domain . "\r\n");
				$out = "";
		while(!feof($fp)){
			$out .= fgets($fp);
		}
		fclose($fp);
		return $out;
	}
}

function pWhoisTimeout() {
	$msg = __('Whois Server timeout', 'pwhois');
	$response = json_encode( array( 'success' => true , 'msg' => $msg ) );
	header( "Content-Type: application/json" );
	echo $response;
	die();
}

//Server Array, kann erweitert werden
$PWHOIS_SERVERS = array(
"com"               =>  array("whois.verisign-grs.com","whois.crsnic.net"),
"net"               =>  array("whois.verisign-grs.com","whois.crsnic.net"),
"org"               =>  array("whois.pir.org","whois.publicinterestregistry.net"),
"info"              =>  array("whois.afilias.info","whois.afilias.net"),
"biz"               =>  array("whois.neulevel.biz"),
"us"                =>  array("whois.nic.us"),
"uk"                =>  array("whois.nic.uk"),
"ca"                =>  array("whois.cira.ca"),
"tel"               =>  array("whois.nic.tel"),
"ie"                =>  array("whois.iedr.ie","whois.domainregistry.ie"),
"it"                =>  array("whois.nic.it"),
"li"                =>  array("whois.nic.li"),
"no"                =>  array("whois.norid.no"),
"cc"                =>  array("whois.nic.cc"),
"eu"                =>  array("whois.eu"),
"nu"                =>  array("whois.nic.nu"),
"au"                =>  array("whois.aunic.net","whois.ausregistry.net.au"),
"de"                =>  array("whois.denic.de"),
"ws"                =>  array("whois.worldsite.ws","whois.nic.ws","www.nic.ws"),
"sc"                =>  array("whois2.afilias-grs.net"),
"mobi"              =>  array("whois.dotmobiregistry.net"),
"pro"               =>  array("whois.registrypro.pro","whois.registry.pro"),
"edu"               =>  array("whois.educause.net","whois.crsnic.net"),
"tv"                =>  array("whois.nic.tv","tvwhois.verisign-grs.com"),
"travel"            =>  array("whois.nic.travel"),
"name"              =>  array("whois.nic.name"),
"in"                =>  array("whois.inregistry.net","whois.registry.in"),
"me"                =>  array("whois.nic.me","whois.meregistry.net"),
"at"                =>  array("whois.nic.at"),
"be"                =>  array("whois.dns.be"),
"cn"                =>  array("whois.cnnic.cn","whois.cnnic.net.cn"),
"asia"              =>  array("whois.nic.asia"),
"ru"                =>  array("whois.ripn.ru","whois.ripn.net"),
"ro"                =>  array("whois.rotld.ro"),
"aero"              =>  array("whois.aero"),
"fr"                =>  array("whois.nic.fr"),
"se"                =>  array("whois.iis.se","whois.nic-se.se","whois.nic.se"),
"nl"                =>  array("whois.sidn.nl","whois.domain-registry.nl"),
"nz"                =>  array("whois.srs.net.nz","whois.domainz.net.nz"),
"mx"                =>  array("whois.nic.mx"),
"tw"                =>  array("whois.apnic.net","whois.twnic.net.tw"),
"ch"                =>  array("whois.nic.ch"),
"hk"                =>  array("whois.hknic.net.hk"),
"ac"                =>  array("whois.nic.ac"),
"ae"                =>  array("whois.nic.ae"),
"af"                =>  array("whois.nic.af"),
"ag"                =>  array("whois.nic.ag"),
"al"                =>  array("whois.ripe.net"),
"am"                =>  array("whois.amnic.net"),
"as"                =>  array("whois.nic.as"),
"az"                =>  array("whois.ripe.net"),
"ba"                =>  array("whois.ripe.net"),
"bg"                =>  array("whois.register.bg"),
"bi"                =>  array("whois.nic.bi"),
"bj"                =>  array("www.nic.bj"),
"br"                =>  array("whois.nic.br"),
"bt"                =>  array("whois.netnames.net"),
"by"                =>  array("whois.ripe.net"),
"bz"                =>  array("whois.belizenic.bz"),
"cd"                =>  array("whois.nic.cd"),
"ck"                =>  array("whois.nic.ck"),
"cl"                =>  array("nic.cl"),
"coop"              =>  array("whois.nic.coop"),
"cx"                =>  array("whois.nic.cx"),
"cy"                =>  array("whois.ripe.net"),
"cz"                =>  array("whois.nic.cz"),
"dk"                =>  array("whois.dk-hostmaster.dk"),
"dm"                =>  array("whois.nic.cx"),
"dz"                =>  array("whois.ripe.net"),
"ee"                =>  array("whois.eenet.ee"),
"eg"                =>  array("whois.ripe.net"),
"es"                =>  array("whois.ripe.net"),
"fi"                =>  array("whois.ficora.fi"),
"fo"                =>  array("whois.ripe.net"),
"gb"                =>  array("whois.ripe.net"),
"ge"                =>  array("whois.ripe.net"),
"gl"                =>  array("whois.ripe.net"),
"gm"                =>  array("whois.ripe.net"),
"gov"               =>  array("whois.nic.gov"),
"gr"                =>  array("whois.ripe.net"),
"gs"                =>  array("whois.adamsnames.tc"),
"hm"                =>  array("whois.registry.hm"),
"hn"                =>  array("whois2.afilias-grs.net"),
"hr"                =>  array("whois.ripe.net"),
"hu"                =>  array("whois.ripe.net"),
"il"                =>  array("whois.isoc.org.il"),
"int"               =>  array("whois.isi.edu"),
"iq"                =>  array("vrx.net"),
"ir"                =>  array("whois.nic.ir"),
"is"                =>  array("whois.isnic.is"),
"je"                =>  array("whois.je"),
"jp"                =>  array("whois.jprs.jp"),
"kg"                =>  array("whois.domain.kg"),
"kr"                =>  array("whois.nic.or.kr"),
"la"                =>  array("whois2.afilias-grs.net"),
"lt"                =>  array("whois.domreg.lt"),
"lu"                =>  array("whois.restena.lu"),
"lv"                =>  array("whois.nic.lv"),
"ly"                =>  array("whois.lydomains.com"),
"ma"                =>  array("whois.iam.net.ma"),
"mc"                =>  array("whois.ripe.net"),
"md"                =>  array("whois.nic.md"),
"mil"               =>  array("whois.nic.mil"),
"mk"                =>  array("whois.ripe.net"),
"ms"                =>  array("whois.nic.ms"),
"mt"                =>  array("whois.ripe.net"),
"mu"                =>  array("whois.nic.mu"),
"my"                =>  array("whois.mynic.net.my"),
"nf"                =>  array("whois.nic.cx"),
"pl"                =>  array("whois.dns.pl"),
"pr"                =>  array("whois.nic.pr"),
"pt"                =>  array("whois.dns.pt"),
"sa"                =>  array("saudinic.net.sa"),
"sb"                =>  array("whois.nic.net.sb"),
"sg"                =>  array("whois.nic.net.sg"),
"sh"                =>  array("whois.nic.sh"),
"si"                =>  array("whois.arnes.si"),
"sk"                =>  array("whois.sk-nic.sk"),
"sm"                =>  array("whois.ripe.net"),
"st"                =>  array("whois.nic.st"),
"su"                =>  array("whois.ripn.net"),
"tc"                =>  array("whois.adamsnames.tc"),
"tf"                =>  array("whois.nic.tf"),
"th"                =>  array("whois.thnic.net"),
"tj"                =>  array("whois.nic.tj"),
"tk"                =>  array("whois.nic.tk"),
"tl"                =>  array("whois.domains.tl"),
"tm"                =>  array("whois.nic.tm"),
"tn"                =>  array("whois.ripe.net"),
"to"                =>  array("whois.tonic.to"),
"tp"                =>  array("whois.domains.tl"),
"tr"                =>  array("whois.nic.tr"),
"ua"                =>  array("whois.ripe.net"),
"uy"                =>  array("nic.uy"),
"uz"                =>  array("whois.cctld.uz"),
"va"                =>  array("whois.ripe.net"),
"vc"                =>  array("whois2.afilias-grs.net"),
"ve"                =>  array("whois.nic.ve"),
"vg"                =>  array("whois.adamsnames.tc"),
"yu"                =>  array("whois.ripe.net")
);

?>