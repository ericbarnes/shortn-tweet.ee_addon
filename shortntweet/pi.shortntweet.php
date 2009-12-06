<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

	$plugin_info = array(
		'pi_name'        => "Short 'n Tweet",
		'pi_version'     => '2.0',
		'pi_author'      => 'Eric Barnes',
		'pi_author_url'  => 'http://ericlbarnes.com/',
		'pi_description' => 'Allows users to tweet blog entries',
		'pi_usage'       => Shortntweet::usage()
	);


/**
 * Shortn_tweet Class
 *
 * @package			ExpressionEngine
 * @category		Plugin
 * @author			Eric Barnes
 * @link			http://ericlbarnes.com/ee_tweet_this
 * 
 * Significantly modified by Ryan Masuga, masugadesign.com
 * Friday September 18, 2009
 * added bit.ly support
 * added variables for more flexible output (tagdata)
 * 
 * 
 */
class Shortntweet {
	
	var $bitly_login = 'YOURBITLYLOGIN'; // enter your bit.ly login here
	var $bitly_apikey = 'R_01234567890abcdefghijklmnopqrstu'; // enter your key here
	var $bitly_version = '2.0.1'; 
	var $bitly_url = 'http://api.bit.ly/shorten?';
	var $return_data = "";
	
	// --------------------------------------------------------------------
	
	/**
	* Constructor
	* Get the tweet text
	*/
	function Shortntweet()
	{
		$this->EE =& get_instance(); 
		
		$tagdata = $this->EE->TMPL->tagdata;
		
		// Format URL //
		$title_url = $this->EE->TMPL->fetch_param('url');
		$title_url = str_replace(SLASH, '/', $title_url); // fix any encoded slashes
		
		$short_url = ($this->EE->TMPL->fetch_param('short_url') !== FALSE) ? $short_url = $this->EE->TMPL->fetch_param('short_url') : '';

		if ($short_url != '') 
		{
			if ($short_url == 'bit.ly') 
			{
				$title_url = $this->_do_shorten_url_bitly($title_url);
			}
			else
			{
				$title_url = $this->_do_shorten_url_isgd($title_url);
			}
		}
		
		// Format Title //
		$title = $this->EE->TMPL->fetch_param('title');
		$title = $this->_dot($title, 70, '...');
		$title = $this->_convert_chars($title);
		
		$link_title = $this->EE->TMPL->fetch_param('link_title');
		
		$twitter_url = 'http://twitter.com/home?status=';
		$twitter_full_url = 'http://twitter.com/home?status='.$title.' '.$title_url;
		
		// Now return it //
		if ($tagdata != '')
		{
			$f = array(LD.'twt:title'.RD, LD.'twt:title_url'.RD, LD.'twt:twitter_url'.RD, LD.'twt:twitter_full_url'.RD);
			$r = array($title, $title_url, $twitter_url, $twitter_full_url);
			$c = array('twt:title' => $title, 'twt:title_url' => $title_url, 'twt:twitter_url' => $twitter_url, 'twt:twitter_full_url' => $twitter_full_url);
			$this->return_data = str_replace($f, $r, $this->EE->functions->prep_conditionals($tagdata, $c));
		}
		else 
		{
			$this->return_data = "<li><a href=\"http://twitter.com/home?status=$title $title_url\" title=\"Click to send this page to Twitter!\" target=\"_blank\"><img src=\"/images/site/icon_share_twitter.png\" alt=\"Twitter\" />Tweet This</a>";		  
		}
		

	}
	
	// --------------------------------------------------------------------
	
	/**
	* Take any previously encoded quotes, dashes or slashes and simplify them.
	* 
	* @param	string $string The string
	* @return	string
	*/
	function _convert_chars($string) 
	{
		$search = array("&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;");
		$replace = array("'", "'", '&quot;', '&quot;','-','--');
		return str_replace($search, $replace, $string);
	}

	// ------------------------------------------------------------------------
	
	/**
	* Trim a string and add dots to the end.
	* 
	* @param	string $str The string
	* @param	int $len The length you want returned
	* @param	string $dots the suffix
	* @return	string
	*/
	function _dot($str, $len, $dots = "...") 
	{
		if (strlen($str) > $len) 
		{
			$dotlen = strlen($dots);
			$str = substr_replace($str, $dots, $len - $dotlen);
		}
		return $str;
	}
	
	// --------------------------------------------------------------------
	
	/**
	* Do a curl request 
	*
	* @param string
	* @param string
	* @param $string
	* @return string
	*/
	function _do_curl_request($url, $variable, $value) 
	{
		$api = $url."?".$variable."=".$value;
		$session = curl_init();
		curl_setopt($session, CURLOPT_URL, $api);
		curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);
		$data = curl_exec($session);
		curl_close($session);
		return $data;
	}
	
	// --------------------------------------------------------------------
	
	/**
	* Shorten the url using is.gd
	*
	* @param string
	* @return string 
	* @uses do_curl_request
	*/
	function _do_shorten_url_isgd($longurl) 
	{
		$url = "http://is.gd/api.php";
		$variable = "longurl";
		$shorturl = $this->_do_curl_request($url, $variable, $longurl);
		return $shorturl;
	}
	
	// ------------------------------------------------------------------------ 
	
	/**
	* Shorten the url using bit.ly
	*
	* @param string
	* @return string 
	* http://code.google.com/p/bitly-api/wiki/ApiDocumentation
	* http://davidwalsh.name/bitly-php
	* Sample format of the bit.ly URL:
	* http://api.bit.ly/shorten?version=2.0.1&longUrl=http://site.com&login=bitlyapidemo&apiKey=123apikey123&format=json&history=1
	*   
	*  
	*  sample bit.ly JSON result
	*  
	*  {
	*      "errorCode": 0, 
	*      "errorMessage": "", 
	*      "results": {
	*          "http://www.site.com/weblog/item/title/": {
	*              "hash": "3SdGZD", 
	*              "shortKeywordUrl": "", 
	*              "shortUrl": "http://bit.ly/4hfg8", 
	*              "userHash": "4hfg8"
	*          }
	*      }, 
	*      "statusCode": "OK"
	*  }
	*
	*/
	function _do_shorten_url_bitly($orig_url) 
	{
		$longurl = rawurlencode($orig_url);
		$shorturl = $this->bitly_url.'version='.$this->bitly_version.'&longUrl='.$longurl.'&login='.$this->bitly_login.'&apiKey='.$this->bitly_apikey.'&format=json&history=1';
		
		//using curl
		$ccc = curl_init();
		curl_setopt($ccc,CURLOPT_URL,$shorturl);
		curl_setopt($ccc,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ccc,CURLOPT_HEADER,false);
		$response = curl_exec($ccc);
		curl_close($ccc);
		
		//decode JSON
		$json = @json_decode($response,true);
		return $json['results'][$orig_url]['shortUrl'];
	}

	// --------------------------------------------------------------------
	
	/**
	 * Usage
	 *
	 * Plugin Usage
	 *
	 * @access	public
	 * @return	string
	 */
	function usage()
	{
		ob_start(); 
		?>
		------------------
		EXAMPLE USAGE:
		http://expressionengine.com/forums/viewthread/115527/
		------------------
		
		{exp:shortntweet title="{title}" url="{title_permalink=blog/entry}"}
		
		or 
		
    {exp:shortntweet title="{title}" url="{url_title_path=weblog/item}" short_url="bit.ly"}
      <li><a href="{twt:twitter_url}{twt:title} {twt:title_url}">Tweet this thang</a></li>
    {/exp:shortntweet}
		
		------------------
		PARAMETERS:
		------------------
		
		title="{title}"
		- The title of the entry.
		
		url="{title_permalink=blog/entry}"
		- The url or permalink to the entry.
		
		no_short_url="TRUE"
		- Do you want to use the is.gd url shortening server? Default is Yes. 
		
		link_title="Tweet This!"
		- This is the link title. Could be text or image.
		
		<?php
		$buffer = ob_get_contents();

		ob_end_clean(); 

		return $buffer;
	}
	
}

/* End of file pi.shortntweet.php */
/* Location: ./shortntweet/pi.shortntweet.php */